<?php
/**
 * Autonomous guest tracking checks in the disposable local validation database.
 * Creates only two dedicated pending orders, blocks mail and deletes the orders
 * in finally. Never run against the commercial tables.
 */
global $wpdb;
if ( 'bcrm_validation_' !== $wpdb->prefix || 'local' !== wp_get_environment_type() ) {
    throw new RuntimeException( 'Tracking tests require the isolated local validation tables.' );
}
if ( ! class_exists( 'BCRM_Tracking' ) || ! class_exists( 'WC_Shortcode_Order_Tracking' ) ) {
    throw new RuntimeException( 'WooCommerce and Belmains CRM must be active.' );
}

$saved_request = $_REQUEST;
$saved_get = $_GET;
$saved_user = get_current_user_id();
$fixture_ids = array();
$tracking_number = 'QA-TRACKING-FORM-' . wp_generate_uuid4();
$block_mail = static function() { return false; };
add_filter( 'pre_wp_mail', $block_mail );
$grant = new ReflectionProperty( BCRM_Tracking::class, 'tracked_order_ids' );
$grant->setAccessible( true );
$saved_grants = $grant->getValue();
$checks = 0;
$check = static function( $condition, $label ) use ( &$checks ) {
    if ( ! $condition ) { throw new RuntimeException( 'FAIL: ' . $label ); }
    ++$checks;
    echo 'PASS ' . $label . "\n";
};
$render = static function( $candidate ) {
    ob_start();
    try {
        BCRM_Tracking::customer_tracking( $candidate );
        return ob_get_contents();
    } finally {
        ob_end_clean();
    }
};
$fixture = static function( $number, $status ) use ( &$fixture_ids ) {
    $candidate = new WC_Order();
    $candidate->set_created_via( 'belmains_tracking_validation' );
    $candidate->set_customer_id( 0 );
    $candidate->set_status( 'pending' );
    $candidate->set_billing_first_name( 'Validation' );
    $candidate->set_billing_last_name( 'Suivi isolé' );
    $candidate->set_billing_email( 'tracking-form-validation@example.invalid' );
    $candidate->update_meta_data( '_belmains_validation_fixture', 'tracking-form' );
    $candidate->update_meta_data( '_bcrm_tracking_source', 'manual' );
    $candidate->update_meta_data( '_bcrm_tracking_number', $number );
    $candidate->update_meta_data( '_bcrm_tracking_status', $status );
    $fixture_ids[] = $candidate->save();
    return $candidate;
};

try {
    wp_set_current_user( 0 );
    if ( ! WC()->cart ) { wc_load_cart(); }
    $order = $fixture( $tracking_number, 'in_transit' );
    // Same billing email deliberately tests that the grant is scoped to one order.
    $other = $fixture( 'QA-OTHER-ORDER-PRIVATE', 'shipped' );
    $grant->setValue( null, array() );
    $_GET = $_REQUEST = array();
    $check( '' === $render( $order ), 'Anonymous order without credential hides parcel data' );
    $_REQUEST = array( 'orderid' => $order->get_id(), 'order_email' => $order->get_billing_email(), 'woocommerce-order-tracking-nonce' => wp_create_nonce( 'woocommerce-order_tracking' ) );
    $check( '' === $render( $order ), 'Posted matching details alone do not authorize a view' );
    $_REQUEST['woocommerce-order-tracking-nonce'] = 'invalid';
    do_action( 'woocommerce_track_order', $order->get_id() );
    $check( '' === $render( $order ), 'Tracking hook with invalid nonce does not authorize a view' );
    ob_start();
    WC_Shortcode_Order_Tracking::output( array() );
    ob_end_clean();
    $check( '' === $render( $order ), 'Tracking form with invalid nonce hides parcel data' );
    $_REQUEST['woocommerce-order-tracking-nonce'] = wp_create_nonce( 'woocommerce-order_tracking' );
    $_REQUEST['order_email'] = 'wrong-email@example.invalid';
    ob_start();
    WC_Shortcode_Order_Tracking::output( array() );
    ob_end_clean();
    $check( '' === $render( $order ), 'Tracking form with wrong billing email hides parcel data' );
    do_action( 'woocommerce_track_order', $order->get_id() );
    $check( '' === $render( $order ), 'Tracking hook with wrong billing email also stays closed' );
    $_REQUEST['order_email'] = strtoupper( $order->get_billing_email() );
    ob_start();
    WC_Shortcode_Order_Tracking::output( array() );
    ob_end_clean();
    $check( str_contains( $render( $order ), $tracking_number ), 'Verified Woo form displays parcel number to a guest' );
    $check( str_contains( $render( $order ), 'En transit' ), 'Verified Woo form displays the recorded parcel status' );
    $check( '' === $render( $other ), 'Verified form does not authorize a different order' );
    $grant->setValue( null, array() );
    $_GET = array( 'key' => $order->get_order_key() );
    $_REQUEST = array();
    $check( str_contains( $render( $order ), $tracking_number ), 'Existing valid guest order key remains supported' );
    $_GET = array( 'key' => 'wrong-key' );
    $check( '' === $render( $order ), 'Wrong guest order key still hides parcel data' );
    echo $checks . " tracking form integration checks passed\n";
} finally {
    foreach ( $fixture_ids as $fixture_id ) {
        $candidate = wc_get_order( $fixture_id );
        if ( $candidate ) { $candidate->delete( true ); }
    }
    $_REQUEST = $saved_request;
    $_GET = $saved_get;
    wp_set_current_user( $saved_user );
    $grant->setValue( null, $saved_grants );
    remove_filter( 'pre_wp_mail', $block_mail );
}
