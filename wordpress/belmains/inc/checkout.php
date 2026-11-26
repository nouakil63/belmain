<?php
/**
 * One-page order: a filled cart has no page of its own, it is edited inside the checkout.
 * WooCommerce's own checkout markup is kept as it is (payment extensions rely on it); the layout is
 * done in woocommerce.css and the cart controls are added through WooCommerce's filters.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Largest quantity a line may hold: the limit already enforced when adding to the cart. */
function belmains_checkout_max_quantity() {
    return class_exists( 'Belmains_Commerce' ) ? (int) Belmains_Commerce::MAX_QUANTITY : 99;
}

/** Where a request for the cart page must go instead, or '' to show the cart page (empty cart). */
function belmains_checkout_cart_redirect() {
    if ( belmains_catalog_only() || ! function_exists( 'is_cart' ) || ! is_cart() ) { return ''; }
    if ( ! in_array( $_SERVER['REQUEST_METHOD'] ?? 'GET', array( 'GET', 'HEAD' ), true ) ) { return ''; }
    return WC()->cart && ! WC()->cart->is_empty() ? wc_get_checkout_url() : '';
}
add_action( 'template_redirect', function () {
    $target = belmains_checkout_cart_redirect();
    if ( $target ) { wp_safe_redirect( $target, 302 ); exit; }
}, 20 );

// "View cart" would only lead back to the page the customer is already on.
add_filter( 'wc_add_to_cart_message_html', function ( $message ) {
    return trim( preg_replace( '#<a\b[^>]*\bwc-forward\b[^>]*>.*?</a>#s', '', (string) $message ) );
} );

/** True while the checkout page or its order summary refresh is being rendered. */
function belmains_is_order_page() {
    return function_exists( 'is_checkout' ) && is_checkout() && ! is_wc_endpoint_url( 'order-received' ) && ! is_wc_endpoint_url( 'order-pay' );
}

// Order summary line: picture, name, quantity controls and removal.
add_filter( 'woocommerce_cart_item_name', function ( $name, $item, $key ) {
    if ( ! belmains_is_order_page() || empty( $item['data'] ) || ! $item['data'] instanceof WC_Product ) { return $name; }
    return '<span class="belmains-line-thumb">' . $item['data']->get_image( 'woocommerce_thumbnail', array( 'alt' => '' ) ) . '</span><span class="belmains-line-name">' . $name . '</span>';
}, 20, 3 );

add_filter( 'woocommerce_checkout_cart_item_quantity', function ( $html, $item, $key ) {
    if ( empty( $item['data'] ) || ! $item['data'] instanceof WC_Product ) { return $html; }
    $product = $item['data'];
    $quantity = (int) $item['quantity'];
    $max = $product->is_sold_individually() ? 1 : belmains_checkout_max_quantity();
    if ( $product->managing_stock() && ! $product->backorders_allowed() ) { $max = max( 1, min( $max, (int) $product->get_stock_quantity() ) ); }
    $label = wp_strip_all_tags( $product->get_name() );
    return '<span class="belmains-line-controls" data-cart-key="' . esc_attr( $key ) . '">'
        . '<span class="belmains-line-qty">'
        . '<button type="button" class="belmains-qty-step" data-step="-1" aria-label="' . esc_attr( 'Diminuer la quantité de ' . $label ) . '"' . disabled( $quantity <= 1, true, false ) . '>−</button>'
        . '<input type="number" class="belmains-qty-input" value="' . esc_attr( $quantity ) . '" min="1" max="' . esc_attr( $max ) . '" step="1" inputmode="numeric" autocomplete="off" aria-label="' . esc_attr( 'Quantité de ' . $label ) . '">'
        . '<button type="button" class="belmains-qty-step" data-step="1" aria-label="' . esc_attr( 'Augmenter la quantité de ' . $label ) . '"' . disabled( $quantity >= $max, true, false ) . '>+</button>'
        . '</span>'
        . '<button type="button" class="belmains-line-remove" aria-label="' . esc_attr( 'Retirer ' . $label . ' de la commande' ) . '">Retirer</button>'
        . '</span>';
}, 20, 3 );

// Announces cart changes and their refusals; outside the summary, which is replaced on every refresh.
add_action( 'woocommerce_checkout_before_order_review', function () {
    echo '<p class="belmains-cart-feedback" role="status" aria-live="polite"></p>';
} );

// "Paiement" heading before the payment methods, printed once with the page.
add_action( 'woocommerce_review_order_before_payment', function () {
    echo '<h3 class="belmains-payment-heading" id="belmains-payment-heading">Paiement</h3>';
} );

// What the customer can count on, stated once under the order button.
add_action( 'woocommerce_checkout_after_order_review', function () {
    $returns = belmains_legal_url( 'returns' );
    echo '<ul class="belmains-order-assurances">'
        . '<li>Paiement sécurisé, traité par Revolut</li>'
        . '<li>Livraison offerte à domicile</li>'
        . '<li>14 jours pour changer d’avis' . ( $returns ? ' — <a href="' . esc_url( $returns ) . '">livraison et retours</a>' : '' ) . '</li>'
        . '</ul>';
} );

// The order button must say that it commits to pay.
add_filter( 'woocommerce_order_button_text', function () { return 'Commander et payer'; } );

// Payment extensions ship some titles in English only.
add_filter( 'woocommerce_gateway_title', function ( $title, $id = '' ) {
    $french = array( 'Pay with card' => 'Carte bancaire', 'Pay by Bank' => 'Virement bancaire instantané' );
    return is_admin() && ! wp_doing_ajax() ? $title : ( $french[ $title ] ?? $title );
}, 20, 2 );

add_filter( 'gettext', function ( $translation, $text, $domain ) {
    return 'woocommerce' === $domain && 'Billing details' === $text && belmains_is_order_page() ? 'Vos coordonnées' : $translation;
}, 20, 3 );

// Empty cart: a sentence and a way forward instead of a notice box.
add_action( 'wp', function () {
    remove_action( 'woocommerce_cart_is_empty', 'wc_empty_cart_message', 10 );
    add_action( 'woocommerce_cart_is_empty', function () {
        echo '<p class="belmains-empty-cart">Votre panier est vide pour le moment.</p>';
    }, 10 );
} );
add_filter( 'woocommerce_return_to_shop_text', function () { return 'Découvrir le gant Belmains'; } );

add_action( 'wp_enqueue_scripts', function () {
    if ( ! belmains_is_order_page() ) { return; }
    wp_enqueue_script( 'belmains-checkout', get_theme_file_uri( 'assets/checkout.js' ), array( 'jquery', 'wc-checkout' ), wp_get_theme()->get( 'Version' ), array( 'in_footer' => true ) );
    wp_localize_script( 'belmains-checkout', 'BelmainsCheckout', array(
        'url' => WC_AJAX::get_endpoint( 'belmains_set_quantity' ),
        'nonce' => wp_create_nonce( 'belmains_checkout_cart' ),
        'updated' => 'Votre commande a été mise à jour.',
        'failed' => 'La modification n’a pas pu être enregistrée. Actualisez la page puis réessayez.',
    ) );
}, 30 );

/** Sets the quantity of one cart line from the order page; 0 removes the line. */
function belmains_checkout_set_quantity() {
    nocache_headers();
    $refuse = static function ( $message, $status = 400 ) { wp_send_json_error( array( 'message' => $message ), $status ); };
    if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) { $refuse( 'Cette action nécessite un envoi depuis la page de commande.', 405 ); }
    if ( belmains_catalog_only() ) { $refuse( 'La boutique n’est pas encore ouverte.', 503 ); }
    $nonce = isset( $_POST['nonce'] ) && is_string( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
    if ( ! wp_verify_nonce( $nonce, 'belmains_checkout_cart' ) ) { $refuse( 'Votre page a expiré. Actualisez-la puis réessayez.', 403 ); }
    $key = isset( $_POST['key'] ) && is_string( $_POST['key'] ) ? sanitize_text_field( wp_unslash( $_POST['key'] ) ) : '';
    $raw = isset( $_POST['quantity'] ) && is_scalar( $_POST['quantity'] ) ? (string) wp_unslash( $_POST['quantity'] ) : '';
    if ( ! preg_match( '/^\d{1,4}$/D', $raw ) ) { $refuse( 'Indiquez une quantité en chiffres.' ); }
    $quantity = (int) $raw;
    if ( ! WC()->cart || ! WC()->session ) { wc_load_cart(); }
    $cart = WC()->cart;
    $item = $cart->get_cart_item( $key );
    if ( ! $item || empty( $item['data'] ) ) { $refuse( 'Cet article n’est plus dans votre commande. Actualisez la page.', 409 ); }
    $product = $item['data'];
    if ( 0 === $quantity ) {
        $cart->remove_cart_item( $key );
    } else {
        if ( $quantity > belmains_checkout_max_quantity() || ( $product->is_sold_individually() && $quantity > 1 ) ) { $refuse( 'Cette quantité dépasse le maximum autorisé par commande.' ); }
        // The same checks as WooCommerce's own cart update, extensions included.
        $before = wc_notice_count( 'error' );
        if ( ! apply_filters( 'woocommerce_update_cart_validation', true, $key, $item, $quantity ) ) {
            $notices = wc_get_notices( 'error' );
            $last = wc_notice_count( 'error' ) > $before ? end( $notices ) : null;
            if ( $last ) { array_pop( $notices ); $all = wc_get_notices(); $all['error'] = $notices; wc_set_notices( $all ); }
            $refuse( $last ? wp_strip_all_tags( $last['notice'] ?? '' ) : 'Cette quantité ne peut pas être commandée.', 409 );
        }
        if ( ! $product->has_enough_stock( $quantity ) ) {
            $refuse( sprintf( 'Stock insuffisant : %d exemplaire(s) disponible(s).', (int) $product->get_stock_quantity() ), 409 );
        }
        $cart->set_quantity( $key, $quantity, true );
    }
    $cart->calculate_totals();
    wp_send_json_success( array( 'empty' => $cart->is_empty(), 'redirect' => wc_get_cart_url(), 'count' => $cart->get_cart_contents_count() ) );
}
add_action( 'wc_ajax_belmains_set_quantity', 'belmains_checkout_set_quantity' );
