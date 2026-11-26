<?php
/** Isolated validation of the one-page order (the cart edited inside the checkout). Run through WP-CLI eval-file only. */
global $wpdb;
if ( 'bcrm_validation_' !== $wpdb->prefix || 'local' !== wp_get_environment_type() ) {
    throw new RuntimeException( 'Order page tests require the isolated local validation tables.' );
}
if ( ! function_exists( 'belmains_checkout_set_quantity' ) || ! class_exists( 'Belmains_Commerce' ) || ! class_exists( 'WooCommerce' ) ) {
    throw new RuntimeException( 'The Belmains theme, Belmains Commerce and WooCommerce must be active.' );
}
if ( ! class_exists( 'Belmains_Order_Page_Test_Json_End' ) ) { class Belmains_Order_Page_Test_Json_End extends Exception {} }

// Nothing is printed before the end: the handler's HTTP status can only be read while no output has started.
$checks = 0; $log = array();
$check = static function ( $condition, $label ) use ( &$checks, &$log ) {
    if ( ! $condition ) { throw new RuntimeException( 'FAIL: ' . $label ); }
    ++$checks; $log[] = 'PASS ' . $label;
};
$saved = array( 'product' => get_option( 'belmains_product_id', null ), 'post' => $_POST, 'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET' );
$closed = false;
$catalog = static function () use ( &$closed ) { return $closed; };
add_filter( 'belmains_catalog_only', $catalog, PHP_INT_MAX );
$checkout = false;
$as_checkout = static function ( $is ) use ( &$checkout ) { return $checkout ? true : $is; };
add_filter( 'woocommerce_is_checkout', $as_checkout, PHP_INT_MAX );
$call = static function ( array $data, $method = 'POST' ) {
    $_POST = $data; $_SERVER['REQUEST_METHOD'] = $method;
    $handler = static function () { return static function () { throw new Belmains_Order_Page_Test_Json_End(); }; };
    add_filter( 'wp_doing_ajax', '__return_true' ); add_filter( 'wp_die_ajax_handler', $handler );
    http_response_code( 200 ); ob_start();
    try { do_action( 'wc_ajax_belmains_set_quantity' ); } catch ( Belmains_Order_Page_Test_Json_End $end ) {
        // WordPress's real JSON answer, captured without ending wp eval-file.
    } finally {
        $json = ob_get_clean(); $status = http_response_code();
        remove_filter( 'wp_doing_ajax', '__return_true' ); remove_filter( 'wp_die_ajax_handler', $handler );
    }
    return array( 'status' => $status, 'body' => json_decode( $json, true ) );
};
$product = null;

try {
    $product = new WC_Product_Simple();
    $product->set_name( 'Gant · validation page de commande' ); $product->set_sku( 'BELMAINS-COMMANDE-VALIDATION-' . wp_rand( 1000, 9999 ) );
    $product->set_regular_price( '89.99' ); $product->set_status( 'publish' ); $product->set_manage_stock( true ); $product->set_stock_quantity( 5 ); $product->set_stock_status( 'instock' ); $product->save();
    update_option( 'belmains_product_id', $product->get_id(), false );
    if ( ! WC()->cart || ! WC()->session ) { wc_load_cart(); }
    WC()->cart->empty_cart( false ); wc_clear_notices();
    $key = WC()->cart->add_to_cart( $product->get_id(), 1 ); WC()->cart->calculate_totals();
    $nonce = wp_create_nonce( 'belmains_checkout_cart' );
    $quantity = static function () use ( $key ) { $item = WC()->cart->get_cart_item( $key ); return $item ? (int) $item['quantity'] : 0; };
    $set = static function ( $value, array $changes = array() ) use ( $call, $key, $nonce ) { return $call( array_merge( array( 'nonce' => $nonce, 'key' => $key, 'quantity' => (string) $value ), $changes ) ); };

    // Refusals leave the cart as it was.
    $check( 405 === $call( array( 'nonce' => $nonce, 'key' => $key, 'quantity' => '2' ), 'GET' )['status'], 'a GET request is refused' );
    $check( 403 === $set( 2, array( 'nonce' => 'forged' ) )['status'], 'a forged nonce is refused' );
    $bad = array_map( static function ( $value ) use ( $set ) { return $set( $value )['status']; }, array( '-1', '1.5', 'deux', '', '12345' ) );
    $check( array( 400 ) === array_values( array_unique( $bad ) ), 'a quantity that is not a small whole number is refused' );
    $check( 409 === $set( 2, array( 'key' => str_repeat( 'a', 32 ) ) )['status'], 'an unknown cart line is refused' );
    $over = $set( 6 );
    $check( 409 === $over['status'] && false !== strpos( $over['body']['data']['message'], '5' ), 'a quantity above the stock is refused, with the stock stated' );
    $closed = true;
    $check( 503 === $set( 2 )['status'], 'nothing can be changed behind the pre-opening showcase' );
    $closed = false;
    $check( 1 === $quantity() && 0 === wc_notice_count( 'error' ), 'every refusal leaves the cart and the notices untouched' );

    // Changes.
    $two = $set( 2 );
    WC()->cart->calculate_totals();
    $offer = Belmains_Commerce::offer_for_product( $product );
    $check( 200 === $two['status'] && true === $two['body']['success'] && false === $two['body']['data']['empty'] && 2 === $two['body']['data']['count'] && 2 === $quantity(), 'two gloves are set from the order page' );
    $check( $offer['duo_enabled'] && abs( (float) WC()->cart->get_total( 'edit' ) - $offer['duo_cents'] / 100 ) < 0.011, 'the duo price applies to the pair: ' . WC()->cart->get_total( 'edit' ) );
    $set( 1 ); WC()->cart->calculate_totals();
    $check( 1 === $quantity() && abs( (float) WC()->cart->get_total( 'edit' ) - $offer['single_cents'] / 100 ) < 0.011, 'back to one glove at the single price' );

    // What the order page prints.
    $checkout = true;
    $item = WC()->cart->get_cart_item( $key );
    $controls = apply_filters( 'woocommerce_checkout_cart_item_quantity', ' <strong class="product-quantity">&times;&nbsp;1</strong>', $item, $key );
    $check( false !== strpos( $controls, 'data-cart-key="' . $key . '"' ) && false !== strpos( $controls, 'class="belmains-qty-input" value="1" min="1" max="5"' ) && false !== strpos( $controls, 'belmains-line-remove' ) && 1 === preg_match( '/data-step="-1"[^>]*disabled/', $controls ) && 0 === preg_match( '/data-step="1"[^>]*disabled/', $controls ) && false === strpos( $controls, ' name=' ), 'the line offers a quantity field bounded by the stock, both steps and a removal; none is sent with the order form' );
    $name = apply_filters( 'woocommerce_cart_item_name', $product->get_name(), $item, $key );
    $filtered = wp_kses_post( $name );
    $check( false !== strpos( $filtered, '<span class="belmains-line-thumb"><img' ) && false !== strpos( $filtered, '<span class="belmains-line-name">' . $product->get_name() . '</span>' ), 'the line carries the product picture and name, and both survive WooCommerce\'s own filtering' );
    $check( 'Commander et payer' === apply_filters( 'woocommerce_order_button_text', 'Place order' ), 'the order button says that it commits to pay' );
    $check( 'Carte bancaire' === apply_filters( 'woocommerce_gateway_title', 'Pay with card', 'revolut_cc' ) && 'Apple Pay' === apply_filters( 'woocommerce_gateway_title', 'Apple Pay', 'revolut_payment_request_apple_pay' ), 'English-only payment titles are shown in French, brand names untouched' );
    $check( 'Vos coordonnées' === __( 'Billing details', 'woocommerce' ), 'the billing block is titled « Vos coordonnées »' );
    $checkout = false;
    $check( $product->get_name() === apply_filters( 'woocommerce_cart_item_name', $product->get_name(), $item, $key ) && 'Vos coordonnées' !== __( 'Billing details', 'woocommerce' ), 'outside the order page nothing is rewritten' );
    $message = apply_filters( 'wc_add_to_cart_message_html', '<a href="' . esc_url( wc_get_cart_url() ) . '" tabindex="1" class="button wc-forward">Voir le panier</a> « Gant » a été ajouté à votre panier.', array( $product->get_id() => 1 ), false );
    $check( '« Gant » a été ajouté à votre panier.' === $message, 'the « view cart » button is dropped from the confirmation: the customer is already on the page' );

    // Removal of the last line empties the cart and sends back to the cart page.
    $gone = $set( 0 );
    $check( 200 === $gone['status'] && true === $gone['body']['data']['empty'] && wc_get_cart_url() === $gone['body']['data']['redirect'] && WC()->cart->is_empty(), 'removing the last glove empties the cart and points to the empty cart page' );
    ob_start(); do_action( 'wp' ); do_action( 'woocommerce_cart_is_empty' ); $empty = ob_get_clean();
    $check( false !== strpos( $empty, 'belmains-empty-cart' ) && false === strpos( $empty, 'woocommerce-info' ) && 'Découvrir le gant Belmains' === apply_filters( 'woocommerce_return_to_shop_text', 'Return to shop' ), 'the empty cart is a sentence and a way back to the glove, not a notice box' );
    $log[] = $checks . ' order page checks passed.';
} finally {
    echo implode( "\n", $log ) . "\n";
    remove_filter( 'belmains_catalog_only', $catalog, PHP_INT_MAX );
    remove_filter( 'woocommerce_is_checkout', $as_checkout, PHP_INT_MAX );
    $_POST = $saved['post']; $_SERVER['REQUEST_METHOD'] = $saved['method'];
    if ( WC()->cart ) { WC()->cart->empty_cart( false ); }
    wc_clear_notices();
    if ( $product && $product->get_id() ) { $product->delete( true ); }
    if ( null === $saved['product'] ) { delete_option( 'belmains_product_id' ); } else { update_option( 'belmains_product_id', $saved['product'], false ); }
}
