<?php
/** Run with wp eval-file ONLY on the isolated CRM validation database prefix. */
global $wpdb, $checks;
if ( 'bcrm_validation_' !== $wpdb->prefix || 'local' !== wp_get_environment_type() ) { throw new RuntimeException( 'Tests require the isolated local validation tables.' ); }
add_filter( 'pre_wp_mail', '__return_false' );
// These are disposable, isolated validation tables, never the user's live records.
foreach ( wc_get_orders( array( 'limit' => -1, 'type' => 'shop_order' ) ) as $old ) { foreach ( $old->get_refunds() as $old_refund ) { $old_refund->delete( true ); } $old->delete( true ); }
foreach ( wc_get_products( array( 'limit' => -1, 'status' => array( 'publish', 'draft', 'private' ), 'type' => array( 'simple', 'variable', 'variation' ) ) ) as $old ) { $old->delete( true ); }
foreach ( array( 'bcrm_events', 'bcrm_tickets', 'bcrm_marketing_costs' ) as $table ) { $wpdb->query( "DELETE FROM {$wpdb->prefix}$table" ); }
delete_option( 'bcrm_fixture_order' );
$checks = array();
function bcrm_check( $condition, $name ) { global $checks; if ( ! $condition ) { throw new RuntimeException( 'FAIL: ' . $name ); } $checks[] = $name; echo 'PASS ' . $name . "\n"; }
function bcrm_req( $route, $method = 'GET', $data = null, $query = array() ) {
    $r = new WP_REST_Request( $method, '/belmains-crm/v1/' . $route );
    $r->set_query_params( $query );
    if ( null !== $data ) { $r->set_header( 'Content-Type', 'application/json' ); $r->set_body( wp_json_encode( $data ) ); }
    return rest_do_request( $r );
}
BCRM_App::activate();
wp_set_current_user( 0 );
bcrm_check( 401 === bcrm_req( 'dashboard' )->get_status(), 'anonymous cannot read CRM' );
bcrm_check( 401 === bcrm_req( 'export', 'GET', null, array( 'type' => 'orders' ) )->get_status(), 'anonymous cannot export clients' );
$reader = username_exists( 'crm_validation_reader' ) ?: wp_insert_user( array( 'user_login' => 'crm_validation_reader', 'user_pass' => wp_generate_password( 32 ), 'user_email' => 'reader@example.invalid', 'role' => 'subscriber' ) );
wp_set_current_user( $reader );
bcrm_check( 403 === bcrm_req( 'orders' )->get_status(), 'subscriber cannot read orders' );
bcrm_check( 403 === bcrm_req( 'settings', 'POST', array( 'analytics_enabled' => false ) )->get_status(), 'subscriber cannot change settings' );
wp_set_current_user( 1 );
bcrm_check( 200 === bcrm_req( 'dashboard' )->get_status(), 'administrator can read CRM' );
bcrm_check( 400 === bcrm_req( 'dashboard', 'GET', null, array( 'from' => '2026-02-31', 'to' => '2026-09-30' ) )->get_status(), 'invalid dates rejected' );
bcrm_check( 400 === bcrm_req( 'dashboard', 'GET', null, array( 'from' => '2020-01-01', 'to' => '2026-09-30' ) )->get_status(), 'oversized range rejected' );
$from = wp_date( 'Y-m-d', time() - 29 * DAY_IN_SECONDS ); $to = wp_date( 'Y-m-d' );
$product = new WC_Product_Simple();
$product->set_name( 'Gant gris · validation isolée' ); $product->set_sku( 'TEST-BELMAINS-GRIS' ); $product->set_regular_price( '109.99' ); $product->set_sale_price( '89.99' );
$product->set_manage_stock( true ); $product->set_stock_quantity( 4 ); $product->set_status( 'publish' ); $product->save();
$order = wc_create_order();
$order->set_billing_first_name( 'Camille' ); $order->set_billing_last_name( 'Validation' ); $order->set_billing_email( 'camille@example.invalid' ); $order->set_currency( 'EUR' );
$order->set_billing_address_1( 'Adresse de validation' ); $order->set_billing_city( 'Paris' ); $order->set_billing_country( 'FR' );
$order->add_product( $product, 2 );
$item = current( $order->get_items() );
$item->set_subtotal( 100 ); $item->set_total( 100 ); $item->set_taxes( array( 'total' => array( 1 => 20 ), 'subtotal' => array( 1 => 20 ) ) ); $item->save();
$order->set_cart_tax( 20 ); $order->set_total( 120 ); $order->set_status( 'processing' ); $order->save();
$serialized_order = BCRM_Commerce::serialize_order( $order );
bcrm_check( strtotime( $serialized_order['date'] ) === $order->get_date_created()->getTimestamp(), 'serialized ISO date preserves the exact order timestamp' );
update_option( 'bcrm_fixture_order', $order->get_id() );
$refund = wc_create_refund( array( 'order_id' => $order->get_id(), 'amount' => 30, 'reason' => 'Remboursement partiel de validation', 'refund_payment' => false, 'restock_items' => false, 'line_items' => array( $item->get_id() => array( 'qty' => 0, 'refund_total' => 25, 'refund_tax' => array( 1 => 5 ) ) ) ) );
bcrm_check( ! is_wp_error( $refund ), 'real Woo refund created without payment' );
$summary = BCRM_Commerce::summary( $from, $to );
bcrm_check( abs( $summary['metrics']['net_revenue'] - 75 ) < 0.01, '120 incl20 tax minus30 incl5 refund equals75 net' );
bcrm_check( null === $summary['metrics']['profit'], 'unknown costs never report fake profit' );
$costs = bcrm_req( 'orders/' . $order->get_id() . '/costs', 'POST', array( 'goods' => 40, 'shipping' => 5, 'packaging' => 2, 'fees' => 3 ) );
bcrm_check( 200 === $costs->get_status(), 'save four historical costs' );
$summary = BCRM_Commerce::summary( $from, $to );
bcrm_check( abs( $summary['metrics']['profit'] - 25 ) < 0.01, 'contribution excludes known costs and tax correctly' );
bcrm_check( 400 === bcrm_req( 'orders/' . $order->get_id() . '/costs', 'POST', array( 'goods' => -1 ) )->get_status(), 'negative costs rejected' );
bcrm_check( 200 === bcrm_req( 'orders/' . $order->get_id() . '/note', 'POST', array( 'note' => '<b>Note interne de validation</b>' ) )->get_status(), 'internal note saved' );
$detail = BCRM_Commerce::order( $order->get_id() );
bcrm_check( ! str_contains( wp_json_encode( $detail['notes'] ), '<b>' ), 'notes sanitized' );
$order = wc_get_order( $order->get_id() ); $order->set_status( 'completed' ); $order->save();
bcrm_check( 'pending' === BCRM_Tracking::get( $order )['status'], 'completed Woo order is not a delivered parcel' );
$external = new WP_REST_Request( 'PUT', '/wc/v3/orders/' . $order->get_id() );
$external->set_header( 'Content-Type', 'application/json' ); $external->set_body( wp_json_encode( array( 'meta_data' => array( array( 'key' => 'tracking_number', 'value' => 'TEST-COLIS-0001' ) ) ) ) );
$external_response = rest_do_request( $external );
bcrm_check( 200 === $external_response->get_status(), 'real Woo REST metadata update accepted' );
$tracking = BCRM_Tracking::get( wc_get_order( $order->get_id() ) );
bcrm_check( 'TEST-COLIS-0001' === $tracking['number'] && 'shipped' === $tracking['status'], 'incoming tracking number observed as shipment only' );
bcrm_check( ! empty( BCRM_Tracking::integration()['last_sync'] ), 'external receipt timestamp recorded' );
bcrm_check( 400 === bcrm_req( 'orders/' . $order->get_id() . '/tracking', 'POST', array( 'url' => 'javascript:alert(1)' ) )->get_status(), 'unsafe tracking URL rejected' );
bcrm_check( 200 === bcrm_req( 'orders/' . $order->get_id() . '/tracking', 'POST', array( 'number' => 'TEST-COLIS-0001', 'carrier' => 'Transporteur de validation', 'url' => 'https://example.com/tracking/TEST-COLIS-0001', 'status' => 'delivered', 'shipped_at' => $from, 'delivered_at' => $to ) )->get_status(), 'manual delivery recorded distinctly' );
$shipment = BCRM_Tracking::shipments( $from, $to );
bcrm_check( 1 === $shipment['metrics']['delivered'], 'shipment count follows explicit delivery status' );
$foreign = wc_create_order(); $foreign->set_currency( 'USD' ); $foreign->add_product( $product, 1 ); $foreign->calculate_totals(); $foreign->set_status( 'processing' ); $foreign->save();
$summary = BCRM_Commerce::summary( $from, $to );
bcrm_check( abs( $summary['metrics']['net_revenue'] - 75 ) < 0.01 && count( $summary['warnings'] ) > 0, 'foreign currencies never mixed into EUR totals' );
$clients = BCRM_Commerce::customers( $from, $to );
bcrm_check( 1 === $clients['total'] && 'camille@example.invalid' === $clients['items'][0]['email'], 'guest customer included' );
$stock = BCRM_Commerce::stock();
bcrm_check( count( $stock['items'] ) >= 1 && 1 === (int) $stock['items'][0]['quantity'], 'stock reflects three units deducted by paid Woo orders' );
$ticket = bcrm_req( 'tickets', 'POST', array( 'subject' => 'Question de validation', 'customer_email' => 'camille@example.invalid', 'order_id' => $order->get_id(), 'message' => 'Suivi du colis de test', 'status' => 'open', 'priority' => 'normal' ) );
bcrm_check( 200 === $ticket->get_status(), 'private SAV ticket created' );
$ticket_id = $ticket->get_data()['id'];
bcrm_check( 200 === bcrm_req( 'tickets/' . $ticket_id, 'POST', array( 'status' => 'closed', 'note' => 'Résolu dans la copie de validation' ) )->get_status(), 'ticket updated without email' );
$order = wc_get_order( $order->get_id() ); $order->update_meta_data( '_bcrm_source', 'google' ); $order->update_meta_data( '_bcrm_campaign', 'test-gant' ); $order->save();
bcrm_check( 200 === bcrm_req( 'marketing/costs', 'POST', array( 'date' => $to, 'source' => 'google', 'campaign' => 'test-gant', 'amount' => 10, 'note' => 'Dépense de validation' ) )->get_status(), 'marketing cost saved' );
$marketing = BCRM_Marketing::report( $from, $to );
bcrm_check( abs( $marketing['totals']['roas'] - 7.5 ) < 0.01, 'observed ad ROAS uses net attributed revenue' );
$order->set_billing_first_name( '=2+2' ); $order->save();
$export = bcrm_req( 'export', 'GET', null, array( 'type' => 'orders', 'from' => $from, 'to' => $to ) );
bcrm_check( 200 === $export->get_status() && str_contains( $export->get_data()['csv'], "'=2+2" ), 'CSV formula injection protected' );
$order->set_billing_first_name( 'Camille' ); $order->save();

wp_set_current_user( 0 );
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_COOKIE['bcrm_consent'] = 'yes'; $_COOKIE['bcrm_v'] = str_repeat( 'a', 32 ); $_COOKIE['bcrm_s'] = str_repeat( 'b', 32 );
$event = new WP_REST_Request( 'POST', '/belmains-crm/v1/collect' ); $event->set_header( 'Origin', 'https://attacker.invalid' ); $event->set_header( 'Content-Type', 'application/json' );
$event->set_body( wp_json_encode( array( 'id' => str_repeat( 'c', 32 ), 'event' => 'pageview', 'path' => '/?email=private@example.invalid', 'source' => 'google', 'device' => 'desktop' ) ) );
bcrm_check( 403 === rest_do_request( $event )->get_status(), 'cross-origin analytics event rejected' );
$event->set_header( 'Origin', 'http://127.0.0.1:9494' ); $_COOKIE['bcrm_consent'] = 'no';
bcrm_check( 403 === rest_do_request( $event )->get_status(), 'no audience collection without consent' );
$_COOKIE['bcrm_consent'] = 'yes';
foreach ( array( 'id', 'event', 'path', 'source', 'device' ) as $malformed_field ) {
    $malformed_event = new WP_REST_Request( 'POST', '/belmains-crm/v1/collect' );
    $malformed_event->set_header( 'Origin', 'http://127.0.0.1:9494' ); $malformed_event->set_header( 'Content-Type', 'application/json' );
    $malformed_payload = array( 'id' => str_repeat( 'd', 32 ), 'event' => 'pageview', 'path' => '/', 'source' => 'direct', 'device' => 'desktop' );
    $malformed_payload[ $malformed_field ] = array( 'invalid' );
    $malformed_event->set_body( wp_json_encode( $malformed_payload ) );
    bcrm_check( 400 === rest_do_request( $malformed_event )->get_status(), 'array-valued analytics ' . $malformed_field . ' rejected' );
}
bcrm_check( 204 === rest_do_request( $event )->get_status(), 'consented view collected' );
rest_do_request( $event );
$audience = BCRM_Audience::summary( $from, $to );
bcrm_check( 1 === $audience['pageviews'] && 1 === $audience['visitors'], 'event retries deduplicated' );
$row = $wpdb->get_row( "SELECT * FROM {$wpdb->prefix}bcrm_events LIMIT 1", ARRAY_A );
bcrm_check( ! str_contains( $row['page_path'], '@' ) && ! str_contains( wp_json_encode( $row ), '127.0.0.1' ), 'no raw IP or query PII in events' );
$tracked_order = wc_create_order(); $tracked_order->set_billing_email( 'tracked@example.invalid' ); $tracked_order->add_product( $product, 1 ); $tracked_order->calculate_totals(); $tracked_order->save();
BCRM_Audience::attach_consent( $tracked_order ); $tracked_order->set_status( 'processing' ); $tracked_order->save(); BCRM_Audience::purchase( $tracked_order->get_id() );
$audience = BCRM_Audience::summary( $from, $to );
bcrm_check( 1 === $audience['tracked_orders'] && abs( $audience['conversion'] - 100 ) < 0.01, 'server-confirmed paid order tracked once' );
bcrm_check( false === get_transient( 'bcrm_order_consent_' . $tracked_order->get_id() ), 'temporary order audience link removed after payment' );
bcrm_check( 0 === (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}bcrm_events WHERE event_key LIKE 'order:%'" ), 'no plain order id stored in analytics' );
wp_set_current_user( 1 );
$full = wc_create_order(); $full->set_billing_email( 'full@example.invalid' ); $full->set_currency( 'EUR' ); $full->add_product( $product, 1 ); $full->calculate_totals(); $full->set_status( 'processing' ); $full->save();
wc_create_refund( array( 'order_id' => $full->get_id(), 'amount' => $full->get_total(), 'refund_payment' => false ) );
$full_range = BCRM_Commerce::orders( $from, $to, 1, 'full@example.invalid' );
bcrm_check( 1 === $full_range['total'], 'fully refunded order remains queryable' );
$large = new WC_Product_Variable(); $large->set_name( 'Variations · validation' ); $large->set_manage_stock( true ); $large->set_stock_quantity( 12 ); $large->set_status( 'publish' ); $large->save();
$variation = new WC_Product_Variation(); $variation->set_parent_id( $large->get_id() ); $variation->set_regular_price( 89.99 ); $variation->set_manage_stock( 'parent' ); $variation->set_status( 'publish' ); $variation->save();
$stock = BCRM_Commerce::stock();
$variation_row = array_values( array_filter( $stock['items'], fn( $r ) => $r['id'] === $variation->get_id() ) );
bcrm_check( count( $variation_row ) === 1 && $variation_row[0]['shared_stock'] && 12 === (int) $variation_row[0]['quantity'], 'variation inherits parent stock without silent zero' );
foreach ( array( 'dashboard', 'orders', 'customers', 'shipments', 'stock', 'tickets', 'settings', 'integration', 'marketing' ) as $route ) {
    bcrm_check( 200 === bcrm_req( $route )->get_status(), 'REST route ' . $route );
}
echo count( $checks ) . " integration checks passed.\n";
file_put_contents( dirname( ABSPATH ) . '/crm-integration-result.json', wp_json_encode( array( 'checks' => $checks, 'count' => count( $checks ), 'hpos' => get_option( 'woocommerce_custom_orders_table_enabled' ), 'order_id' => $order->get_id() ), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );
