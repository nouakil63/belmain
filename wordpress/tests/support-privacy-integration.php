<?php
/**
 * Privacy checks for CRM support tickets in the isolated local validation site.
 * Only this run's tickets and one pending order are created and removed in
 * finally. No archive is generated and no email or external request is sent.
 */
global $wpdb;
if ( 'bcrm_validation_' !== $wpdb->prefix || 'local' !== wp_get_environment_type() ) {
    throw new RuntimeException( 'Privacy tests require the isolated local validation tables.' );
}
if ( ! class_exists( 'BCRM_Support' ) || ! class_exists( 'WC_Order' ) ) {
    throw new RuntimeException( 'WooCommerce and Belmains CRM must be active.' );
}
if ( ! class_exists( 'BCRM_Privacy' ) ) {
    require_once BCRM_PATH . 'includes/class-bcrm-privacy.php';
}
require_once ABSPATH . 'wp-admin/includes/privacy-tools.php';

$table = $wpdb->prefix . 'bcrm_tickets';
$fixture_ids = array();
$own_ids = array();
$order = null;
$run = strtolower( wp_generate_uuid4() );
$email = 'privacy-' . $run . '@example.invalid';
$other_email = 'privacy-' . $run . '+other@example.invalid';
$checks = 0;
$mail_attempts = 0;
$block_mail = static function() use ( &$mail_attempts ) { ++$mail_attempts; return false; };
add_filter( 'pre_wp_mail', $block_mail );
$registered_here = false === has_filter( 'wp_privacy_personal_data_exporters', array( 'BCRM_Privacy', 'exporters' ) );
if ( $registered_here ) { BCRM_Privacy::register(); }
$check = static function( $condition, $label ) use ( &$checks ) {
    if ( ! $condition ) { throw new RuntimeException( 'FAIL: ' . $label ); }
    ++$checks;
    echo 'PASS ' . $label . "\n";
};
$count_email = static function( $address ) use ( $wpdb, $table ) {
    return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE LOWER(customer_email) = %s", strtolower( $address ) ) );
};
$ticket = static function( $address, $index, $order_id = 0 ) use ( $wpdb, $table, &$fixture_ids ) {
    $row = array(
        'subject' => 'Question privée ' . $index,
        'customer_email' => $address,
        'order_id' => $order_id,
        'status' => $index % 2 ? 'pending' : 'closed',
        'priority' => 'normal',
        'message' => 'Message de validation ' . $index,
        'created_at' => '2026-09-30 11:12:13',
        'updated_at' => '2026-10-01 14:15:16',
    );
    if ( false === $wpdb->insert( $table, $row, array( '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s' ) ) ) {
        throw new RuntimeException( 'Cannot create privacy fixture.' );
    }
    $id = (int) $wpdb->insert_id;
    $fixture_ids[] = $id;
    return $id;
};

try {
    $order = new WC_Order();
    $order->set_created_via( 'belmains_privacy_validation' );
    $order->set_status( 'pending' );
    $order->set_billing_email( $email );
    $order->update_meta_data( '_belmains_validation_fixture', 'support-privacy-' . $run );
    $order->save();
    for ( $i = 1; $i <= 101; ++$i ) {
        $own_ids[] = $ticket( 1 === $i ? strtoupper( $email ) : $email, $i, 1 === $i ? $order->get_id() : 0 );
    }
    $other_ids = array( $ticket( $other_email, 102 ), $ticket( $other_email, 103 ) );
    $anonymous_id = $ticket( '', 104 );
    $other_before = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table WHERE customer_email = %s ORDER BY id ASC", $other_email ), ARRAY_A );
    $anonymous_before = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $anonymous_id ), ARRAY_A );
    $raw_message = "<img src=x onerror=alert(1)> & <strong>Texte littéral</strong>\nUne note de démonstration.";
    $wpdb->update( $table, array( 'message' => $raw_message ), array( 'id' => $own_ids[0] ), array( '%s' ), array( '%d' ) );

    $exporters = apply_filters( 'wp_privacy_personal_data_exporters', array() );
    $erasers = apply_filters( 'wp_privacy_personal_data_erasers', array() );
    $check( isset( $exporters['belmains-support-tickets'] ) && is_callable( $exporters['belmains-support-tickets']['callback'] ), 'Exporter is registered with WordPress privacy tools' );
    $check( isset( $erasers['belmains-support-tickets'] ) && is_callable( $erasers['belmains-support-tickets']['callback'] ), 'Eraser is registered with WordPress privacy tools' );
    $export = $exporters['belmains-support-tickets']['callback'];
    $erase = $erasers['belmains-support-tickets']['callback'];

    foreach ( array( '', 'not-an-email', array( $email ), str_replace( '@', '<>@', $email ) ) as $invalid ) {
        $check( is_wp_error( call_user_func( $export, $invalid ) ), 'Invalid email cannot export ticket data' );
        $check( is_wp_error( call_user_func( $erase, $invalid ) ), 'Invalid email cannot erase ticket data' );
    }
    foreach ( array( 0, -1, '1.5', PHP_INT_MAX, array( 1 ) ) as $invalid_page ) {
        $check( is_wp_error( call_user_func( $export, $email, $invalid_page ) ) && is_wp_error( call_user_func( $erase, $email, $invalid_page ) ), 'Invalid page cannot query or mutate ticket data' );
    }

    $first = call_user_func( $export, '  ' . strtoupper( $email ) . '  ', 1 );
    $second = call_user_func( $export, $email, 2 );
    $third = call_user_func( $export, $email, 3 );
    $after = call_user_func( $export, $email, 4 );
    $check( 50 === count( $first['data'] ) && false === $first['done'], 'First export page contains exactly 50 tickets, regardless of email case' );
    $check( 50 === count( $second['data'] ) && false === $second['done'], 'Second export page contains the next 50 tickets' );
    $check( 1 === count( $third['data'] ) && true === $third['done'], 'Third export page contains the final ticket and completes' );
    $check( array() === $after['data'] && true === $after['done'], 'Export beyond the end is empty and complete' );
    $items = array_merge( $first['data'], $second['data'], $third['data'] );
    $expected_ids = array_map( static function( $id ) { return 'belmains-support-ticket-' . $id; }, $own_ids );
    $check( array_column( $items, 'item_id' ) === $expected_ids, 'Stable ticket IDs preserve ordering with no duplicates or other customers' );
    $repeat = call_user_func( $export, $email, 1 );
    $check( $first === $repeat, 'Repeated export page is stable' );
    $fields = array_column( $first['data'][0]['data'], 'value', 'name' );
    $check( 9 === count( $fields ) && in_array( strtoupper( $email ), $fields, true ) && in_array( (string) $order->get_id(), $fields, true ), 'Export includes email, order reference and all support fields' );
    $check( in_array( esc_html( $raw_message ), $fields, true ), 'Customer text is preserved as literal text rather than HTML' );
    $group = array( 'group_label' => $first['data'][0]['group_label'], 'items' => array( $first['data'][0]['item_id'] => $first['data'][0]['data'] ) );
    $html = wp_privacy_generate_personal_data_export_group_html( $group, 'belmains-support-tickets' );
    $check( false === strpos( $html, '<img' ) && false === strpos( $html, '<strong>Texte' ) && false !== strpos( $html, '&lt;img' ), 'Native WordPress export renders ticket markup inertly' );
    $other_export = call_user_func( $export, $other_email );
    $check( 2 === count( $other_export['data'] ) && true === $other_export['done'], 'Similar plus-address belongs to a separate customer' );
    $empty = call_user_func( $export, 'no-such-' . $run . '@example.invalid' );
    $check( array() === $empty['data'] && true === $empty['done'], 'Unknown email exports no records' );

    // A database failure must not silently produce a successful empty export.
    $break_read = static function( $query ) use ( $table ) {
        return str_contains( $query, 'SELECT id, subject, customer_email' ) && str_contains( $query, $table ) ? "SELECT missing_privacy_validation_column FROM $table" : $query;
    };
    $old_suppression = $wpdb->suppress_errors();
    add_filter( 'query', $break_read );
    try { $failed = call_user_func( $export, $email ); }
    finally { remove_filter( 'query', $break_read ); $wpdb->suppress_errors( $old_suppression ); }
    $check( is_wp_error( $failed ), 'Export database failure is reported instead of completing silently' );

    $break_update = static function( $query ) use ( $table ) {
        return str_contains( $query, "UPDATE $table SET customer_email" ) ? "UPDATE $table SET missing_privacy_validation_column = 1 WHERE id = 0" : $query;
    };
    $old_suppression = $wpdb->suppress_errors();
    add_filter( 'query', $break_update );
    try { $failed = call_user_func( $erase, $email ); }
    finally { remove_filter( 'query', $break_update ); $wpdb->suppress_errors( $old_suppression ); }
    $check( is_wp_error( $failed ) && 101 === $count_email( $email ), 'Erase database failure reports an error and can be retried safely' );

    $one = call_user_func( $erase, strtoupper( $email ), 1 );
    $check( true === $one['items_removed'] && false === $one['items_retained'] && false === $one['done'] && 51 === $count_email( $email ), 'First erasure batch anonymizes 50 matching tickets' );
    $two = call_user_func( $erase, $email, 2 );
    $check( true === $two['items_removed'] && false === $two['done'] && 1 === $count_email( $email ), 'Second erasure batch does not skip rows after email removal' );
    $three = call_user_func( $erase, $email, 3 );
    $check( true === $three['items_removed'] && true === $three['done'] && 0 === $count_email( $email ), 'Third erasure batch finishes all 101 records' );
    $id_list = implode( ',', array_map( 'absint', $own_ids ) );
    $anonymized = $wpdb->get_results( "SELECT * FROM $table WHERE id IN ($id_list) ORDER BY id ASC", ARRAY_A );
    $check( 101 === count( $anonymized ), 'Anonymization preserves operational ticket records' );
    $all_anonymized = true;
    $metadata_kept = true;
    foreach ( $anonymized as $index => $row ) {
        $all_anonymized = $all_anonymized && '' === $row['customer_email'] && 0 === (int) $row['order_id'] && 'Demande anonymisée' === $row['subject'] && 'Données personnelles effacées à la demande du client.' === $row['message'];
        $metadata_kept = $metadata_kept && ( ( $index + 1 ) % 2 ? 'pending' : 'closed' ) === $row['status'] && 'normal' === $row['priority'] && '2026-09-30 11:12:13' === $row['created_at'] && '2026-10-01 14:15:16' === $row['updated_at'];
    }
    $check( $all_anonymized, 'Every matching ticket loses email, free text and order association' );
    $check( $metadata_kept, 'Technical statuses, priorities and dates are preserved' );
    $check( $other_before === $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table WHERE customer_email = %s ORDER BY id ASC", $other_email ), ARRAY_A ), 'Other customer tickets remain byte-for-byte unchanged' );
    $check( $anonymous_before === $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $anonymous_id ), ARRAY_A ), 'Existing ticket with empty email remains unchanged' );
    $empty = call_user_func( $export, $email );
    $check( array() === $empty['data'] && true === $empty['done'], 'Anonymized tickets no longer appear in personal exports' );
    $again = call_user_func( $erase, $email, 1 );
    $check( false === $again['items_removed'] && false === $again['items_retained'] && true === $again['done'], 'Repeated erasure is an idempotent completed operation' );
    $empty = call_user_func( $erase, 'no-such-' . $run . '@example.invalid' );
    $check( false === $empty['items_removed'] && true === $empty['done'], 'Unknown email erases no records' );
    $unchanged_order = wc_get_order( $order->get_id() );
    $check( $email === $unchanged_order->get_billing_email() && 'pending' === $unchanged_order->get_status(), 'WooCommerce order data remains under its own privacy workflow' );
    $check( 0 === $mail_attempts, 'Privacy callbacks send no email' );
    echo $checks . " support privacy integration checks passed\n";
} finally {
    foreach ( $fixture_ids as $fixture_id ) { $wpdb->delete( $table, array( 'id' => $fixture_id ), array( '%d' ) ); }
    if ( $order && $order->get_id() ) { $order->delete( true ); }
    remove_filter( 'pre_wp_mail', $block_mail );
    if ( $registered_here ) {
        remove_filter( 'wp_privacy_personal_data_exporters', array( 'BCRM_Privacy', 'exporters' ) );
        remove_filter( 'wp_privacy_personal_data_erasers', array( 'BCRM_Privacy', 'erasers' ) );
    }
}
