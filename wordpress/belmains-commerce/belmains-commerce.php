<?php
/**
 * Plugin Name: Belmains Commerce
 * Description: Relie la fiche Belmains au panier WooCommerce et applique les offres par quantité réelle de gants.
 * Version: 0.1.0
 * Requires at least: 6.3
 * Requires PHP: 8.0
 * Requires Plugins: woocommerce
 * Author: Belmains
 * License: GPL-2.0-or-later
 * Text Domain: belmains-commerce
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Belmains_Commerce {
    public const VERSION = '0.1.0';
    public const SINGLE_CENTS = 8999;
    public const DUO_CENTS = 14999;
    public const REGULAR_CENTS = 10999;
    public const MAX_QUANTITY = 999;

    public static function register() {
        if ( ! class_exists( 'WooCommerce' ) ) { return; }
        add_action( 'wc_ajax_belmains_add_to_cart', array( __CLASS__, 'ajax_add_to_cart' ) );
        add_action( 'woocommerce_before_calculate_totals', array( __CLASS__, 'apply_offer' ), 1000 );
        add_action( 'woocommerce_cart_loaded_from_session', array( __CLASS__, 'apply_offer' ), 1000 );
        add_filter( 'woocommerce_add_to_cart_validation', array( __CLASS__, 'validate_add' ), 10, 6 );
        add_filter( 'woocommerce_update_cart_validation', array( __CLASS__, 'validate_update' ), 10, 4 );
        add_action( 'woocommerce_check_cart_items', array( __CLASS__, 'check_cart' ) );
        add_action( 'woocommerce_store_api_cart_errors', array( __CLASS__, 'store_api_cart_errors' ), 10, 2 );
        add_filter( 'woocommerce_quantity_input_args', array( __CLASS__, 'quantity_args' ), 10, 2 );
        add_filter( 'woocommerce_cart_item_price', array( __CLASS__, 'cart_item_price' ), 10, 3 );
        add_filter( 'woocommerce_widget_cart_item_quantity', array( __CLASS__, 'mini_cart_quantity' ), 10, 3 );
        add_filter( 'woocommerce_get_item_data', array( __CLASS__, 'item_data' ), 10, 2 );
        add_action( 'woocommerce_checkout_create_order_line_item', array( __CLASS__, 'record_offer' ), 10, 4 );
    }

    public static function product_id() {
        return absint( get_option( 'belmains_product_id', 0 ) );
    }

    /** No product is created by activation or by a storefront request. */
    public static function product() {
        if ( ! function_exists( 'wc_get_product' ) || 'EUR' !== get_woocommerce_currency() ) { return false; }
        $product = wc_get_product( self::product_id() );
        return $product && $product->is_type( 'simple' ) && 'publish' === $product->get_status() ? $product : false;
    }

    public static function price_cents( $quantity ) {
        $quantity = max( 0, (int) $quantity );
        return intdiv( $quantity, 2 ) * self::DUO_CENTS + ( $quantity % 2 ) * self::SINGLE_CENTS;
    }

    private static function matches( $item ) {
        return self::product_id() > 0 && (int) ( $item['product_id'] ?? 0 ) === self::product_id()
            && empty( $item['variation_id'] );
    }

    private static function valid_quantity( $quantity, $allow_zero = false ) {
        return is_scalar( $quantity ) && is_numeric( $quantity ) && is_finite( (float) $quantity )
            && (float) $quantity === floor( (float) $quantity )
            && (float) $quantity >= ( $allow_zero ? 0 : 1 ) && (float) $quantity <= self::MAX_QUANTITY;
    }

    public static function cart_quantity( $cart, $except_key = null ) {
        $quantity = 0;
        foreach ( $cart->get_cart() as $key => $item ) {
            if ( $key !== $except_key && self::matches( $item ) ) { $quantity += (float) $item['quantity']; }
        }
        return $quantity;
    }

    /**
     * Price the same physical SKU, not a virtual pack: stock is deducted per glove.
     * Integer cents are apportioned across any duplicate cart lines before deriving
     * their unit price. Woo multiplies this unrounded unit price by the quantity.
     * This makes recalculation idempotent and avoids a 150.00 total for two gloves.
     */
    public static function apply_offer( $cart ) {
        if ( ! self::product() ) { return; }
        $quantity = self::cart_quantity( $cart );
        if ( ! self::valid_quantity( $quantity ) ) { return; }
        $quantity = (int) $quantity;
        $total_cents = self::price_cents( $quantity );
        $allocated = 0;
        $processed = 0;
        foreach ( $cart->get_cart() as $key => $item ) {
            if ( ! self::matches( $item ) || ! self::valid_quantity( $item['quantity'] ) ) { continue; }
            $line_quantity = (int) $item['quantity'];
            $processed += $line_quantity;
            $next = intdiv( $total_cents * $processed, $quantity );
            $line_cents = $next - $allocated;
            $allocated = $next;
            // Clone to avoid sharing one mutable product object between split lines.
            $cart->cart_contents[ $key ]['data'] = clone $item['data'];
            $cart->cart_contents[ $key ]['data']->set_price( $line_cents / 100 / $line_quantity );
            $cart->cart_contents[ $key ]['_belmains_offer_quantity'] = $quantity;
            $cart->cart_contents[ $key ]['_belmains_offer_line_cents'] = $line_cents;
        }
    }

    private static function unavailable_message() {
        return 'Le gant Belmains est temporairement indisponible à la commande.';
    }

    private static function quantity_message() {
        return 'Choisissez un nombre entier de gants, entre 1 et ' . self::MAX_QUANTITY . '.';
    }

    public static function validate_add( $passed, $product_id, $quantity, $variation_id = 0, $variation = array(), $cart_item_data = array() ) {
        if ( (int) $product_id !== self::product_id() || ! self::product_id() ) { return $passed; }
        if ( ! self::product() || $variation_id ) {
            wc_add_notice( self::unavailable_message(), 'error' );
            return false;
        }
        $in_cart = WC()->cart ? self::cart_quantity( WC()->cart ) : 0;
        if ( ! self::valid_quantity( $quantity ) || ! self::valid_quantity( $in_cart + (float) $quantity ) ) {
            wc_add_notice( self::quantity_message(), 'error' );
            return false;
        }
        return $passed;
    }

    public static function validate_update( $passed, $key, $item, $quantity ) {
        if ( ! self::matches( $item ) ) { return $passed; }
        $others = WC()->cart ? self::cart_quantity( WC()->cart, $key ) : 0;
        if ( ! self::valid_quantity( $quantity, true ) || ! self::valid_quantity( $others + (float) $quantity, true ) ) {
            wc_add_notice( self::quantity_message(), 'error' );
            return false;
        }
        return $passed;
    }

    private static function cart_error( $cart ) {
        $quantity = self::cart_quantity( $cart );
        if ( ! $quantity ) { return ''; }
        if ( ! self::product() ) { return self::unavailable_message(); }
        if ( ! self::valid_quantity( $quantity ) ) { return self::quantity_message(); }
        foreach ( $cart->get_cart() as $item ) {
            if ( self::matches( $item ) && ! self::valid_quantity( $item['quantity'] ) ) { return self::quantity_message(); }
        }
        return '';
    }

    public static function check_cart() {
        if ( ! WC()->cart ) { return; }
        $message = self::cart_error( WC()->cart );
        if ( $message && ! wc_has_notice( $message, 'error' ) ) { wc_add_notice( $message, 'error' ); }
    }

    public static function store_api_cart_errors( $errors, $cart ) {
        $message = self::cart_error( $cart );
        if ( $message ) { $errors->add( 'belmains_invalid_cart', $message ); }
    }

    public static function quantity_args( $args, $product ) {
        if ( $product && $product->get_id() === self::product_id() ) {
            $args['step'] = 1;
            $current_max = isset( $args['max_value'] ) ? (float) $args['max_value'] : -1;
            $args['max_value'] = $current_max >= 0 ? min( $current_max, self::MAX_QUANTITY ) : self::MAX_QUANTITY;
        }
        return $args;
    }

    public static function cart_item_price( $html, $item, $key ) {
        if ( self::matches( $item ) && (int) ( $item['_belmains_offer_quantity'] ?? 0 ) >= 2 ) {
            return '<span class="belmains-offer-price">Offre duo appliquée</span>';
        }
        return $html;
    }

    public static function mini_cart_quantity( $html, $item, $key ) {
        if ( ! self::matches( $item ) || ! isset( $item['_belmains_offer_line_cents'] ) ) { return $html; }
        $quantity = (int) $item['quantity'];
        return '<span class="quantity">' . esc_html( $quantity . ( 1 === $quantity ? ' gant' : ' gants' ) )
            . ' — ' . WC()->cart->get_product_subtotal( $item['data'], $quantity ) . '</span>';
    }

    public static function item_data( $data, $item ) {
        if ( self::matches( $item ) && (int) ( $item['_belmains_offer_quantity'] ?? 0 ) >= 2 ) {
            $data[] = array( 'key' => 'Offre', 'value' => 'Tarif duo appliqué par paire de gants dans le panier.' );
        }
        return $data;
    }

    public static function record_offer( $order_item, $cart_item_key, $values, $order ) {
        if ( self::matches( $values ) && isset( $values['_belmains_offer_line_cents'] ) ) {
            $order_item->add_meta_data( '_belmains_offer_line_cents', (int) $values['_belmains_offer_line_cents'], true );
            $order_item->add_meta_data( '_belmains_offer_cart_quantity', (int) $values['_belmains_offer_quantity'], true );
        }
    }

    /** Configuration for the theme; do not cache personalized cart pages. */
    public static function frontend_config() {
        $product = self::product();
        if ( ! $product ) { return array( 'available' => false ); }
        return array(
            'available' => $product->is_purchasable() && $product->is_in_stock(),
            'product_id' => $product->get_id(),
            'ajax_url' => WC_AJAX::get_endpoint( 'belmains_add_to_cart' ),
            'nonce' => wp_create_nonce( 'belmains_add_to_cart' ),
            'cart_url' => wc_get_cart_url(),
            'checkout_url' => wc_get_checkout_url(),
            'cart_count' => WC()->cart ? WC()->cart->get_cart_contents_count() : 0,
            'currency' => 'EUR',
            'single_cents' => self::SINGLE_CENTS,
            'duo_cents' => self::DUO_CENTS,
            'regular_cents' => self::REGULAR_CENTS,
            'max_quantity' => self::MAX_QUANTITY,
        );
    }

    private static function ajax_error( $message, $status = 400 ) {
        $notices = function_exists( 'wc_print_notices' ) && WC()->session ? wc_print_notices( true ) : '';
        wp_send_json_error( array( 'message' => $message, 'notices_html' => $notices ), $status );
    }

    public static function ajax_add_to_cart() {
        nocache_headers();
        if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
            self::ajax_error( 'Cette action nécessite un envoi du formulaire.', 405 );
        }
        $nonce = isset( $_POST['nonce'] ) && is_string( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'belmains_add_to_cart' ) ) {
            self::ajax_error( 'Votre page a expiré. Actualisez-la puis réessayez.', 403 );
        }
        $product_id = isset( $_POST['product_id'] ) && is_scalar( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
        $quantity = isset( $_POST['quantity'] ) && is_scalar( $_POST['quantity'] ) ? wp_unslash( (string) $_POST['quantity'] ) : '';
        if ( ! self::valid_quantity( $quantity ) || $product_id !== self::product_id() ) {
            self::ajax_error( 'Le produit ou la quantité demandé est invalide.' );
        }
        $product = self::product();
        if ( ! $product || ! $product->is_purchasable() ) {
            self::ajax_error( self::unavailable_message(), 409 );
        }
        if ( ! WC()->cart || ! WC()->session ) { wc_load_cart(); }
        $quantity = (int) $quantity;
        // Let Woo and all installed extensions validate purchasability and stock.
        $valid = apply_filters( 'woocommerce_add_to_cart_validation', true, $product_id, $quantity );
        $key = $valid ? WC()->cart->add_to_cart( $product_id, $quantity ) : false;
        if ( ! $key ) {
            self::ajax_error( 'Le gant n’a pas pu être ajouté. Vérifiez la quantité et le stock disponible.', 409 );
        }
        WC()->cart->calculate_totals();
        WC()->session->set_customer_session_cookie( true );
        do_action( 'woocommerce_ajax_added_to_cart', $product_id );
        do_action( 'internal_woocommerce_cart_item_added_from_user_request', $product_id, $quantity );
        ob_start();
        woocommerce_mini_cart();
        $mini_cart = ob_get_clean();
        $count = WC()->cart->get_cart_contents_count();
        $fragments = apply_filters( 'woocommerce_add_to_cart_fragments', array(
            'div.widget_shopping_cart_content' => '<div class="widget_shopping_cart_content">' . $mini_cart . '</div>',
            'span.belmains-cart-count' => '<span class="belmains-cart-count">' . esc_html( $count ) . '</span>',
        ) );
        wp_send_json_success( array(
            'cart_count' => $count,
            'cart_url' => wc_get_cart_url(),
            'checkout_url' => wc_get_checkout_url(),
            'cart_total' => WC()->cart->get_cart_total(),
            'cart_hash' => WC()->cart->get_cart_hash(),
            'notices_html' => wc_print_notices( true ),
            'fragments' => $fragments,
        ) );
    }
}

function belmains_commerce_product() { return Belmains_Commerce::product(); }

add_action( 'plugins_loaded', array( 'Belmains_Commerce', 'register' ), 20 );
add_action( 'before_woocommerce_init', static function() {
    if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
    }
} );
