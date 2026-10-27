<?php
/** Real Woo CRUD/REST logistics tests. Only the disposable local validation tables. */
global $wpdb;
if ( 'bcrm_validation_' !== $wpdb->prefix || 'local' !== wp_get_environment_type() ) { throw new RuntimeException( 'Isolated validation database required.' ); }
add_filter( 'pre_wp_mail', '__return_false', PHP_INT_MAX );
$shipping_checks = 0; $shipping_ids = array(); $shipping_product_id = 0; $shipping_saved_user = get_current_user_id();
$shipping_options = array();
foreach ( array( 'bcrm_iziship_last_sync', 'bcrm_iziship_external_updates', 'bcrm_shipping_events_last_received' ) as $key ) { $shipping_options[ $key ] = get_option( $key, null ); }
$check = static function( $ok, $label ) use ( &$shipping_checks ) { if ( ! $ok ) { throw new RuntimeException( 'FAIL: ' . $label ); } ++$shipping_checks; echo 'PASS ' . $label . "\n"; };
$request = static function( $route, $method = 'GET', $data = null, $query = array() ) {
    $r = new WP_REST_Request( $method, '/belmains-crm/v1/' . $route ); $r->set_query_params( $query );
    if ( null !== $data ) { $r->set_header( 'Content-Type', 'application/json' ); $r->set_body( wp_json_encode( $data ) ); }
    return rest_do_request( $r );
};
$read = static function( $id ) { $o = wc_get_order( $id ); $o->read_meta_data( true ); return $o; };
$save = static function( $id, $parcel ) use ( $read, $request ) {
    return $request( 'orders/' . $id . '/parcels', 'POST', array( 'revision' => BCRM_Shipping::document( $read( $id ) )['revision'], 'parcel' => $parcel ) );
};
$doc = static function( $id ) use ( $read ) { return BCRM_Shipping::document( $read( $id ) ); };
$today = wp_date( 'Y-m-d' ); $past = wp_date( 'Y-m-d', time() - 10 * DAY_IN_SECONDS );
try {
    $product = new WC_Product_Simple(); $product->set_name( 'Validation logistique privée' ); $product->set_regular_price( '1' ); $product->set_status( 'draft' ); $shipping_product_id = $product->save();
    $order = new WC_Order(); $order->set_status( 'processing' ); $order->set_billing_first_name( '=TEST SHIPPING' ); $order->set_billing_email( 'shipping-validation@example.invalid' ); $order->add_product( $product, 1 ); $order->calculate_totals(); $id = $order->save(); $shipping_ids[] = $id;
    wp_set_current_user( 0 );
    foreach ( array( 'parcels', 'shipping-export', 'orders/' . $id . '/shipping-history' ) as $route ) { $check( 401 === $request( $route )->get_status(), 'anonymous cannot read ' . $route ); }
    $check( 401 === $request( 'orders/' . $id . '/parcels', 'POST', array( 'revision' => 0, 'parcel' => array() ) )->get_status(), 'anonymous cannot write parcels' );
    wp_set_current_user( username_exists( 'crm_validation_reader' ) );
    $check( 403 === $request( 'orders/' . $id . '/parcels', 'POST', array( 'revision' => 0, 'parcel' => array() ) )->get_status(), 'subscriber cannot write parcels' );
    wp_set_current_user( 1 );
    $check( 0 === $doc( $id )['revision'] && ! $doc( $id )['parcels'], 'new order has no invented parcel' );
    $number = 'QA-CHRONO-' . $id;
    $response = $save( $id, array( 'number' => $number, 'carrier_code' => 'chronopost', 'status' => 'shipped', 'shipped_at' => $past, 'expected_at' => $past, 'label_url' => 'https://example.com/private-label.pdf', 'note' => 'PRIVATE-NOTE-DO-NOT-EXPOSE' ) );
    $check( 200 === $response->get_status(), 'create Chronopost parcel through authorized REST' );
    $a = $doc( $id )['parcels'][0]['id'];
    $shown = BCRM_Shipping::detail( $read( $id ) );
    $check( str_contains( $shown['parcels'][0]['url'], 'https://www.laposte.fr/outils/suivre-vos-envois?code=' ), 'official unified tracking URL generated' );
    $check( $shown['parcels'][0]['overdue'], 'calendar-day overdue flag derives from confirmed date' );
    $check( 'Chronopost' === $shown['parcels'][0]['carrier'], 'carrier label normalized' );
    $stale = $request( 'orders/' . $id . '/parcels', 'POST', array( 'revision' => 0, 'parcel' => array( 'id' => $a, 'status' => 'delivered' ) ) );
    $check( 409 === $stale->get_status() && 'shipped' === $doc( $id )['parcels'][0]['status'], 'stale editor cannot overwrite newer data' );
    foreach ( array(
        array( 'status' => 'nonsense' ), array( 'number' => array( 'bad' ) ), array( 'carrier_code' => 'unregistered' ),
        array( 'url' => 'javascript:alert(1)' ), array( 'url' => 'http://example.com' ), array( 'url' => 'https://user:pass@example.com' ), array( 'url' => 'https://127.0.0.1/' ),
        array( 'shipped_at' => '2026-02-31' ), array( 'delivered_at' => $past, 'shipped_at' => $today, 'status' => 'delivered' ),
        array( 'shipped_at' => '2999-01-01' ), array( 'archived' => 'false' ), array( 'number' => '', 'status' => 'in_transit' )
    ) as $i => $invalid ) {
        $before = $doc( $id ); $response = $save( $id, array_merge( array( 'id' => $a ), $invalid ) );
        $check( 400 === $response->get_status() && $doc( $id ) === $before, 'invalid parcel payload rejected atomically ' . $i );
    }
    $check( 200 === $save( $id, array( 'carrier_code' => 'colissimo', 'number' => 'QA-COLISSIMO-' . $id, 'status' => 'pending' ) )->get_status(), 'second parcel added without replacing first' );
    $b = $doc( $id )['parcels'][1]['id'];
    $check( 2 === count( $doc( $id )['parcels'] ), 'two parcels persisted independently' );
    $check( 400 === $save( $id, array( 'carrier_code' => 'chronopost', 'number' => $number, 'status' => 'pending' ) )->get_status(), 'duplicate manual parcel number rejected' );
    $check( 200 === $save( $id, array( 'id' => $a, 'status' => 'delivered', 'delivered_at' => $today ) )->get_status(), 'manual delivered event saved' );
    $check( 'partial' === BCRM_Tracking::get( $read( $id ) )['status'], 'one delivered parcel does not mark whole order delivered' );
    $check( 200 === $save( $id, array( 'id' => $b, 'status' => 'delivered', 'delivered_at' => $today ) )->get_status(), 'second parcel can be delivered independently' );
    $check( 'delivered' === BCRM_Tracking::get( $read( $id ) )['status'], 'all outbound parcels must be delivered for aggregate delivery' );
    $check( 200 === $save( $id, array( 'direction' => 'return', 'carrier_code' => 'colissimo', 'number' => 'QA-RETURN-' . $id, 'status' => 'pending' ) )->get_status(), 'return tracked as separate parcel' );
    $c = $doc( $id )['parcels'][2]['id'];
    $check( str_contains( BCRM_Shipping::detail( $read( $id ) )['parcels'][2]['return_address'], '10 rue des oliviers de serres' ), 'Colissimo return uses confirmed spelling and address' );
    $check( str_contains( BCRM_Shipping::returns_address( 'chronopost' ), '4 Rue Lucien Vidie' ) && '' === BCRM_Shipping::returns_address( 'dpd' ), 'carrier-specific returns never guess an unconfirmed address' );
    $check( 200 === $save( $id, array( 'id' => $c, 'archived' => true ) )->get_status(), 'parcel archive is reversible' );
    $check( 2 === count( BCRM_Shipping::customer_parcels( $read( $id ) ) ), 'archived parcel hidden from customers' );
    $check( 200 === $save( $id, array( 'id' => $c, 'archived' => false ) )->get_status(), 'archived parcel can be restored' );
    $projection = wp_json_encode( BCRM_Shipping::customer_parcels( $read( $id ) ) );
    $check( ! str_contains( $projection, 'PRIVATE-NOTE' ) && ! str_contains( $projection, 'private-label' ) && ! str_contains( $projection, 'actor' ), 'customer projection excludes private operational fields' );
    ob_start(); BCRM_Tracking::email_tracking( $read( $id ), false, true, null ); $email = ob_get_clean();
    $check( str_contains( $email, $number ) && str_contains( $email, 'QA-COLISSIMO-' ) && ! str_contains( $email, 'PRIVATE-NOTE' ), 'email rendering includes multiple parcels without private fields' );
    $event = array( 'event_id' => 'provider-create', 'parcel_id' => 'provider-parcel', 'occurred_at' => gmdate( 'Y-m-d\TH:i:s\Z', time() - 120 ), 'number' => 'QA-EXTERNAL-' . $id, 'carrier_code' => 'colissimo', 'status' => 'shipped' );
    $check( ! is_wp_error( BCRM_Shipping::import_events( $id, array( $event ) ) ), 'documented adapter accepts a dated shipment event' );
    $before = $doc( $id );
    $check( ! is_wp_error( BCRM_Shipping::import_events( $id, array( $event ) ) ) && $doc( $id ) === $before, 'event retry is idempotent' );
    $conflict = $event; $conflict['status'] = 'delivered';
    $check( is_wp_error( BCRM_Shipping::import_events( $id, array( $conflict ) ) ) && $doc( $id ) === $before, 'event ID reused with different data is rejected' );
    $delivered = array_merge( $event, array( 'event_id' => 'provider-delivery', 'status' => 'delivered', 'occurred_at' => gmdate( 'Y-m-d\TH:i:s\Z', time() - 30 ) ) );
    $check( ! is_wp_error( BCRM_Shipping::import_events( $id, array( $delivered ) ) ), 'provider delivery event accepted' );
    $old = array_merge( $event, array( 'event_id' => 'provider-late', 'status' => 'in_transit', 'occurred_at' => gmdate( 'Y-m-d\TH:i:s\Z', time() - 60 ) ) );
    $check( ! is_wp_error( BCRM_Shipping::import_events( $id, array( $old ) ) ), 'late event retained in history' );
    $external = array_values( array_filter( $doc( $id )['parcels'], static function( $p ) { return 'provider-parcel' === $p['id']; } ) )[0];
    $check( 'delivered' === $external['status'] && $today === $external['delivered_at'], 'late event does not roll back latest delivery' );
    $before = $doc( $id );
    $good = array_merge( $event, array( 'event_id' => 'batch-good', 'parcel_id' => 'batch-parcel', 'number' => 'QA-BATCH-' . $id ) );
    $bad = array_merge( $good, array( 'event_id' => 'batch-bad', 'status' => 'fake' ) );
    $check( is_wp_error( BCRM_Shipping::import_events( $id, array( $good, $bad ) ) ) && $doc( $id ) === $before, 'invalid batch cannot partially change shipments' );
    $duplicate = array_merge( $event, array( 'event_id' => 'duplicate-number', 'parcel_id' => 'duplicate-parcel' ) );
    $check( is_wp_error( BCRM_Shipping::import_events( $id, array( $duplicate ) ) ), 'adapter cannot duplicate a parcel number under another ID' );
    $r = new WP_REST_Request( 'PUT', '/wc/v3/orders/' . $id ); $r->set_header( 'Content-Type', 'application/json' );
    $r->set_body( wp_json_encode( array( 'meta_data' => array( array( 'key' => '_belmains_shipping_events', 'value' => array( $good ) ) ) ) ) );
    $check( 200 === rest_do_request( $r )->get_status(), 'standard Woo REST write reaches adapter hook' );
    $check( count( $doc( $id )['parcels'] ) === 5 && ! $read( $id )->get_meta( '_belmains_shipping_events', true ), 'adapter stores canonical parcel and removes raw inbox payload' );
    $r->set_body( wp_json_encode( array( 'meta_data' => array( array( 'key' => '_belmains_shipping_events', 'value' => array( $bad ) ) ) ) ) ); rest_do_request( $r );
    $check( ! empty( BCRM_Shipping::detail( $read( $id ) )['import_error'] ), 'invalid external payload produces visible connector diagnostic' );
    $r->set_body( wp_json_encode( array( 'meta_data' => array( array( 'key' => '_belmains_shipping_events', 'value' => array( $good ) ) ) ) ) ); rest_do_request( $r );
    $check( empty( BCRM_Shipping::detail( $read( $id ) )['import_error'] ), 'successful retry clears connector diagnostic' );
    $range = array( 'from' => $today, 'to' => $today, 'search' => (string) $id, 'direction' => 'return' );
    $listing = $request( 'parcels', 'GET', null, $range );
    $check( 200 === $listing->get_status() && 1 === $listing->get_data()['total'] && 'return' === $listing->get_data()['items'][0]['parcel']['direction'], 'return filter selects correct parcel' );
    $csv = $request( 'shipping-export', 'GET', null, $range );
    $check( 200 === $csv->get_status() && str_contains( $csv->get_data()['csv'], "'=TEST SHIPPING" ), 'shipping CSV neutralizes spreadsheet formulas' );
    $check( ! str_contains( $csv->get_data()['csv'], 'PRIVATE-NOTE' ) && ! str_contains( $csv->get_data()['csv'], 'private-label' ), 'list export excludes private notes and label URLs' );
    $check( count( BCRM_Shipping::history( $id )['shipping']['events'] ) >= 10, 'full event history remains exportable' );
    $check( 'processing' === $read( $id )->get_status() && '1.00' === $read( $id )->get_total(), 'shipping never changes commercial status or amount' );
    $r->set_body( wp_json_encode( array( 'meta_data' => array( array( 'key' => '_bcrm_shipping_v2', 'value' => array( 'invalid' ) ) ) ) ) );
    $before = $doc( $id );
    $check( 403 === rest_do_request( $r )->get_status() && $doc( $id ) === $before, 'Woo metadata cannot overwrite canonical shipping document' );
    $canonical_meta_id = 0;
    foreach ( $read( $id )->get_meta_data() as $meta ) { $m = $meta->get_data(); if ( '_bcrm_shipping_v2' === $m['key'] ) { $canonical_meta_id = $m['id']; } }
    $r->set_body( wp_json_encode( array( 'meta_data' => array( array( 'id' => $canonical_meta_id, 'key' => 'renamed_shipping', 'value' => 'changed' ) ) ) ) );
    $check( 403 === rest_do_request( $r )->get_status() && $doc( $id ) === $before, 'Woo metadata ID cannot rename protected shipping document' );
    $lock = 'bcrm_shipping_lock_' . $id; add_option( $lock, wp_json_encode( array( 'expires' => time() + 30, 'token' => 'test-lock' ) ), '', false );
    try { $check( 409 === $save( $id, array( 'id' => $a, 'note' => 'Blocked overlapping write' ) )->get_status(), 'overlapping writer receives conflict without mutation' ); }
    finally { delete_option( $lock ); }
    $check( $doc( $id ) === $before, 'conflicting write preserves complete document' );
    $future = array_merge( $good, array( 'event_id' => 'future-event', 'occurred_at' => '2999-01-01T12:00:00Z' ) );
    $check( is_wp_error( BCRM_Shipping::import_events( $id, array( $future ) ) ), 'future provider event rejected' );
    $offsetless = array_merge( $good, array( 'event_id' => 'no-zone', 'occurred_at' => '2026-10-01T12:00:00' ) );
    $check( is_wp_error( BCRM_Shipping::import_events( $id, array( $offsetless ) ) ), 'provider timestamp must identify time zone' );
    $legacy_order = new WC_Order(); $legacy_order->set_status( 'processing' ); $legacy_id = $legacy_order->save(); $shipping_ids[] = $legacy_id;
    $legacy_request = new WP_REST_Request( 'PUT', '/wc/v3/orders/' . $legacy_id ); $legacy_request->set_header( 'Content-Type', 'application/json' );
    $legacy_request->set_body( wp_json_encode( array( 'meta_data' => array( array( 'key' => 'tracking_number', 'value' => 'QA-LEGACY-1' ) ) ) ) ); rest_do_request( $legacy_request );
    $check( 'label_created' === BCRM_Tracking::get( $read( $legacy_id ) )['status'], 'tracking number alone never invents dispatch' );
    $legacy_request->set_body( wp_json_encode( array( 'meta_data' => array( array( 'key' => 'tracking_number', 'value' => 'QA-LEGACY-2' ) ) ) ) ); rest_do_request( $legacy_request );
    $check( 2 === count( $doc( $legacy_id )['parcels'] ), 'successive legacy tracking numbers retain both parcels' );
    $legacy_primary = $doc( $legacy_id )['parcels'][0]['id'];
    $save( $legacy_id, array( 'id' => $legacy_primary, 'status' => 'pending' ) );
    $check( 'label_created' === BCRM_Tracking::get( $read( $legacy_id ) )['status'], 'mixed preparation statuses never invent transit' );
    $before = $doc( $legacy_id );
    $legacy_request->set_body( wp_json_encode( array( 'meta_data' => array( array( 'key' => 'tracking_number', 'value' => 'QA-LEGACY-2' ) ) ) ) ); rest_do_request( $legacy_request );
    $check( $doc( $legacy_id ) === $before, 'legacy repeated number does not duplicate parcel or history' );
    $legacy_lock = 'bcrm_shipping_lock_' . $legacy_id;
    add_option( $legacy_lock, wp_json_encode( array( 'expires' => time() + 30, 'token' => 'legacy-test-lock' ) ), '', false );
    $legacy_request->set_body( wp_json_encode( array( 'meta_data' => array( array( 'key' => 'tracking_number', 'value' => 'QA-LEGACY-3' ) ) ) ) );
    try { rest_do_request( $legacy_request ); }
    finally { delete_option( $legacy_lock ); }
    $check( $doc( $legacy_id ) === $before && ! empty( BCRM_Shipping::detail( $read( $legacy_id ) )['import_error'] ), 'legacy concurrent import reports a retryable diagnostic' );
    rest_do_request( $legacy_request );
    $check( 3 === count( $doc( $legacy_id )['parcels'] ) && empty( BCRM_Shipping::detail( $read( $legacy_id ) )['import_error'] ), 'legacy retry records parcel and clears only its diagnostic' );
    foreach ( $doc( $legacy_id )['parcels'] as $parcel ) { $save( $legacy_id, array( 'id' => $parcel['id'], 'archived' => true ) ); }
    ob_start(); BCRM_Tracking::email_tracking( $read( $legacy_id ), false, true, null ); $archived_email = ob_get_clean();
    $check( '' === $archived_email, 'all archived parcels stay hidden instead of leaking legacy fallback' );
    $fixture = BCRM_Commerce::order( $id );
    file_put_contents( dirname( ABSPATH ) . '/shipping-ui-fixture.json', wp_json_encode( $fixture, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );
    echo $shipping_checks . " shipping integration checks passed\n";
} finally {
    foreach ( $shipping_ids as $id ) { $o = wc_get_order( $id ); if ( $o ) { $o->delete( true ); } }
    if ( $shipping_product_id ) { $p = wc_get_product( $shipping_product_id ); if ( $p ) { $p->delete( true ); } }
    foreach ( $shipping_options as $key => $value ) { if ( null === $value ) { delete_option( $key ); } else { update_option( $key, $value, false ); } }
    wp_set_current_user( $shipping_saved_user );
}
