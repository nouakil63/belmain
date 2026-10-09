<?php
/** Run with wp eval-file on the isolated local validation installation only. */
global $wpdb;
if ( 'bcrm_validation_' !== $wpdb->prefix || 'local' !== wp_get_environment_type() ) {
    throw new RuntimeException( 'Requires isolated local validation tables.' );
}
require dirname( __DIR__ ) . '/tools/public-catalog-guard.php';
$check = static function ( $ok, $name ) {
    if ( ! $ok ) { throw new RuntimeException( 'FAIL ' . $name ); }
    echo 'PASS ' . $name . "\n";
};
$product = new WC_Product_Simple();
$product->set_status( 'publish' );
$product->set_regular_price( '89.99' );
$product->set_price( '89.99' );
$check( ! $product->is_purchasable(), 'WooCommerce disallows purchase independently of the button' );
$check( ! apply_filters( 'woocommerce_add_to_cart_validation', true, 0, 1 ), 'Classic add-to-cart is rejected' );
$errors = new WP_Error();
do_action( 'woocommerce_after_checkout_validation', array(), $errors );
$check( $errors->has_errors(), 'Classic checkout is rejected before order creation' );
$check( array() === WC()->payment_gateways()->get_available_payment_gateways(), 'No payment gateway is available' );
wp_set_current_user( 0 );
$check( is_wp_error( apply_filters( 'rest_authentication_errors', null ) ), 'Anonymous REST access stays closed' );
$check( is_wp_error( apply_filters( 'rest_authentication_errors', true ) ), 'Earlier authentication success cannot expose REST to guests' );
$original_error = new WP_Error( 'bad_nonce' );
$check( $original_error === apply_filters( 'rest_authentication_errors', $original_error ), 'Existing authentication failures are preserved' );
$administrators = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
if ( ! $administrators ) { throw new RuntimeException( 'Administrator fixture missing' ); }
wp_set_current_user( $administrators[0] );
$check( null === apply_filters( 'rest_authentication_errors', null ), 'Authorized administration remains available' );
foreach ( array( '/wc/store/v1/checkout', '/wc/store/v1/cart/add-item' ) as $route ) {
    $request = new WP_REST_Request( 'POST', $route );
    $check( is_wp_error( apply_filters( 'rest_pre_dispatch', null, rest_get_server(), $request ) ), 'Store API write rejected even for admin: ' . $route );
}
$check( false === apply_filters( 'belmains_storefront_value', true, 'reviews_enabled' ), 'Demonstration reviews are absent from public rendering' );
$check( false === wp_mail( 'nobody@example.invalid', 'catalog guard check', 'Must never be sent' ), 'Mail is suppressed' );
$check( 0 === (int) get_option( 'blog_public' ), 'Indexing stays disabled' );
$check( 0 === (int) get_option( 'users_can_register' ), 'Public account registration stays disabled' );
echo "Catalog checks complete; no order or product persisted.\n";
