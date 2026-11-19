<?php
/**
 * Real WooCommerce VAT checks on a disposable installation only.
 * Set BELMAINS_TAX_RATES_FILE to the reviewed WooCommerce CSV, then wp eval-file.
 */
global $wpdb;
if ( 'bcrm_validation_' !== $wpdb->prefix || 'local' !== wp_get_environment_type()
    || ! defined( 'WP_HTTP_BLOCK_EXTERNAL' ) || ! WP_HTTP_BLOCK_EXTERNAL
    || ! defined( 'DISABLE_WP_CRON' ) || ! DISABLE_WP_CRON ) {
    throw new RuntimeException( 'VAT tests require isolated local tables, external HTTP blocked and cron disabled.' );
}
if ( ! class_exists( 'Belmains_Commerce' ) || ! class_exists( 'BCRM_Commerce' ) ) {
    throw new RuntimeException( 'Activate WooCommerce and the two Belmains plugins in the isolated copy.' );
}
add_filter( 'pre_wp_mail', '__return_false', PHP_INT_MAX );
add_filter( 'pre_http_request', static function () { return new WP_Error( 'isolated_test', 'External HTTP blocked.' ); }, PHP_INT_MAX );

final class Belmains_Tax_Integration {
    private static $checks = array();
    private static $options = array();
    private static $rates = array();
    private static $rate_ids = array();
    private static $products = array();
    private static $orders = array();
    private static $coupon;
    private static $tax_class;
    private static $product;

    private static function check( $condition, $name ) {
        if ( ! $condition ) { throw new RuntimeException( 'FAIL: ' . $name ); }
        self::$checks[] = $name;
    }

    private static function same( $actual, $expected ) {
        return abs( (float) $actual - (float) $expected ) < 0.005;
    }

    private static function option( $key, $value ) {
        if ( ! array_key_exists( $key, self::$options ) ) {
            self::$options[ $key ] = get_option( $key, '__belmains_missing__' );
        }
        update_option( $key, $value );
    }

    private static function address( $country ) {
        WC()->customer->set_billing_country( 'DE' );
        WC()->customer->set_shipping_country( $country );
        WC()->customer->set_shipping_state( '' );
        WC()->customer->set_shipping_postcode( '10000' );
        WC()->customer->set_shipping_city( 'Validation' );
        WC()->customer->set_is_vat_exempt( false );
        WC()->customer->set_calculated_shipping( true );
    }

    private static function cart( $qty, $split = false ) {
        WC()->cart->empty_cart( false );
        wc_clear_notices();
        if ( $split ) {
            for ( $i = 0; $i < $qty; $i++ ) {
                WC()->cart->add_to_cart( self::$product->get_id(), 1, 0, array(), array( 'vat_test_line' => $i ) );
            }
        } else { WC()->cart->add_to_cart( self::$product->get_id(), $qty ); }
        WC()->cart->calculate_totals();
    }

    private static function setup() {
        $path = getenv( 'BELMAINS_TAX_RATES_FILE' );
        if ( ! $path || ! is_readable( $path ) ) { throw new RuntimeException( 'Set BELMAINS_TAX_RATES_FILE.' ); }
        $file = fopen( $path, 'r' );
        $headers = fgetcsv( $file );
        while ( false !== ( $row = fgetcsv( $file ) ) ) {
            $rate = array_combine( $headers, $row );
            if ( ! preg_match( '/^[A-Z]{2}$/', $rate['Country code'] ) || ! is_numeric( $rate['Rate %'] ) ) {
                throw new RuntimeException( 'Unexpected CSV rate.' );
            }
            self::$rates[ $rate['Country code'] ] = (float) $rate['Rate %'];
        }
        fclose( $file );
        self::check( 27 === count( self::$rates ) && isset( self::$rates['GR'] ), '27 unique country codes including GR loaded' );
        foreach ( array(
            'woocommerce_calc_taxes' => 'yes', 'woocommerce_prices_include_tax' => 'yes',
            'woocommerce_tax_based_on' => 'shipping', 'woocommerce_default_country' => 'FR',
            'woocommerce_currency' => 'EUR', 'woocommerce_price_num_decimals' => '2',
            'woocommerce_tax_display_shop' => 'incl', 'woocommerce_tax_display_cart' => 'incl',
            'woocommerce_tax_round_at_subtotal' => 'no',
        ) as $key => $value ) { self::option( $key, $value ); }
        $class = WC_Tax::create_tax_class( 'Belmains isolated VAT ' . wp_generate_uuid4() );
        if ( is_wp_error( $class ) ) { throw new RuntimeException( $class->get_error_message() ); }
        self::$tax_class = $class['slug'];
        foreach ( self::$rates as $country => $rate ) {
            self::$rate_ids[ $country ] = WC_Tax::_insert_tax_rate( array(
                'tax_rate_country' => $country, 'tax_rate_state' => '', 'tax_rate' => $rate,
                'tax_rate_name' => 'Validation VAT ' . $country, 'tax_rate_priority' => 1,
                'tax_rate_compound' => 0, 'tax_rate_shipping' => 1, 'tax_rate_class' => self::$tax_class,
            ) );
        }
        WC_Cache_Helper::invalidate_cache_group( 'taxes' );
        WC()->initialize_session();
        WC()->initialize_cart();
        wp_set_current_user( 0 );
        self::$product = new WC_Product_Simple();
        self::$product->set_name( 'Gant — validation TVA isolée' );
        self::$product->set_regular_price( '109.99' );
        self::$product->set_sale_price( '89.99' );
        self::$product->set_tax_status( 'taxable' );
        self::$product->set_tax_class( self::$tax_class );
        self::$product->set_status( 'publish' );
        self::$product->set_manage_stock( true );
        self::$product->set_stock_quantity( 100 );
        self::$product->save();
        self::$products[] = self::$product->get_id();
        self::option( 'belmains_product_id', self::$product->get_id() );
    }

    private static function countries() {
        foreach ( self::$rates as $country => $rate ) {
            self::address( $country );
            self::check( self::same( wc_get_price_including_tax( self::$product ), 89.99 ), "$country storefront stays at 89.99 TTC" );
            foreach ( array( 1 => 89.99, 2 => 149.99, 3 => 239.98, 4 => 299.98 ) as $qty => $gross ) {
                self::cart( $qty );
                $tax = round( $gross * $rate / ( 100 + $rate ), 2 );
                self::check( self::same( WC()->cart->get_total( 'edit' ), $gross ), "$country quantity $qty keeps total $gross TTC" );
                self::check( self::same( WC()->cart->get_cart_contents_tax(), $tax ), "$country quantity $qty uses shipping VAT $rate, not billing DE" );
                self::check( isset( WC()->cart->get_cart_contents_taxes()[ self::$rate_ids[ $country ] ] ), "$country quantity $qty selects its own tax row" );
                WC()->cart->calculate_totals();
                self::check( self::same( WC()->cart->get_total( 'edit' ), $gross ), "$country quantity $qty recalculates without drift" );
            }
        }
        foreach ( array( 'no', 'yes' ) as $rounding ) {
            self::option( 'woocommerce_tax_round_at_subtotal', $rounding );
            foreach ( array( 'FR', 'DE', 'FI' ) as $country ) {
                self::address( $country );
                foreach ( array( 2 => 149.99, 3 => 239.98 ) as $qty => $gross ) {
                    self::cart( $qty, true );
                    self::check( self::same( WC()->cart->get_total( 'edit' ), $gross ), "$country split $qty lines keeps TTC with rounding $rounding" );
                }
            }
        }
        self::option( 'woocommerce_tax_round_at_subtotal', 'no' );
        $session = new WC_Cart_Session( WC()->cart );
        $session->set_session();
        WC()->cart->set_cart_contents( array() );
        $session->get_cart_from_session();
        WC()->cart->calculate_totals();
        self::check( self::same( WC()->cart->get_total( 'edit' ), 239.98 ), 'Session rehydration retains Finland TTC and duo offer' );
        self::address( 'FR' );
        WC()->cart->calculate_totals();
        self::check( self::same( WC()->cart->get_total( 'edit' ), 239.98 ) && isset( WC()->cart->get_cart_contents_taxes()[ self::$rate_ids['FR'] ] ), 'Changing shipping address updates tax without changing TTC' );
    }

    private static function discounts_and_order() {
        self::address( 'ES' );
        self::cart( 2 );
        self::$coupon = new WC_Coupon();
        self::$coupon->set_code( 'vat-validation-' . wp_generate_uuid4() );
        self::$coupon->set_discount_type( 'percent' );
        self::$coupon->set_amount( 10 );
        self::$coupon->save();
        WC()->cart->apply_coupon( self::$coupon->get_code() );
        WC()->cart->calculate_totals();
        self::check( self::same( WC()->cart->get_total( 'edit' ), 134.99 ), '10 percent coupon applies to the 149.99 TTC duo' );
        self::check( self::same( WC()->cart->get_cart_contents_tax(), round( 134.99 * 21 / 121, 2 ) ), 'Coupon reduces the Spanish tax base' );
        self::cart( 2 );
        WC()->customer->set_is_vat_exempt( true );
        WC()->cart->calculate_totals();
        self::check( self::same( WC()->cart->get_cart_contents_tax(), 0 ), 'Native VAT exemption remains effective' );
        self::check( self::same( WC()->cart->get_total( 'edit' ), round( 149.99 / 1.21, 2 ) ), 'Exempt customer pays net amount without hidden tax' );
        self::address( 'US' );
        self::cart( 2 );
        self::check( self::same( WC()->cart->get_cart_contents_tax(), 0 ), 'Unconfigured destination does not inherit French VAT' );
        // This only verifies WooCommerce behavior, not entitlement to export/exempt.
        self::address( 'FR' );
        self::cart( 2 );
        $day = wp_date( 'Y-m-d' );
        $before = BCRM_Commerce::summary( $day, $day )['metrics'];
        $order_id = WC()->checkout()->create_order( array(
            'billing_first_name' => 'Validation', 'billing_last_name' => 'TVA',
            'billing_email' => 'vat-validation@example.invalid', 'billing_country' => 'DE',
            'shipping_country' => 'FR', 'shipping_postcode' => '75001', 'shipping_city' => 'Paris',
            'payment_method' => '', 'order_comments' => 'Test local uniquement.',
        ) );
        if ( is_wp_error( $order_id ) ) { throw new RuntimeException( $order_id->get_error_message() ); }
        self::$orders[] = $order_id;
        $order = wc_get_order( $order_id );
        self::check( self::same( $order->get_total(), 149.99 ) && self::same( $order->get_total_tax(), 25.00 ), 'Checkout persists 149.99 TTC including 25.00 French VAT' );
        $order->payment_complete( 'ISOLATED-VAT-TEST-NO-GATEWAY' );
        $paid = BCRM_Commerce::summary( $day, $day )['metrics'];
        self::check( self::same( $paid['net_revenue'] - $before['net_revenue'], 124.99 ), 'CRM counts 124.99 net revenue, not 149.99 TTC' );
        $item = current( $order->get_items() );
        $tax_id = self::$rate_ids['FR'];
        $refund = wc_create_refund( array(
            'order_id' => $order_id, 'amount' => 75.00, 'refund_payment' => false, 'restock_items' => true,
            'line_items' => array( $item->get_id() => array( 'qty' => 1, 'refund_total' => 62.50, 'refund_tax' => array( $tax_id => 12.50 ) ) ),
        ) );
        if ( is_wp_error( $refund ) ) { throw new RuntimeException( $refund->get_error_message() ); }
        $after = BCRM_Commerce::summary( $day, $day )['metrics'];
        self::check( self::same( $after['net_revenue'] - $before['net_revenue'], 62.49 ), 'CRM partial duo refund deducts refunded VAT from net revenue' );
        self::check( self::same( wc_get_order( $order_id )->get_total(), 149.99 ), 'Historical order total is preserved after partial refund' );
    }

    private static function cleanup() {
        if ( WC()->cart ) { WC()->cart->empty_cart( false ); }
        foreach ( self::$orders as $id ) {
            $order = wc_get_order( $id );
            if ( $order ) {
                foreach ( $order->get_refunds() as $refund ) { $refund->delete( true ); }
                $order->delete( true );
            }
        }
        foreach ( self::$products as $id ) { $product = wc_get_product( $id ); if ( $product ) { $product->delete( true ); } }
        if ( self::$coupon ) { self::$coupon->delete( true ); }
        if ( self::$tax_class ) { WC_Tax::delete_tax_class_by( 'slug', self::$tax_class ); }
        foreach ( self::$options as $key => $value ) {
            if ( '__belmains_missing__' === $value ) { delete_option( $key ); } else { update_option( $key, $value ); }
        }
        WC_Cache_Helper::invalidate_cache_group( 'taxes' );
        if ( WC()->session ) { WC()->session->destroy_session(); }
    }

    public static function run() {
        ob_start();
        try {
            self::setup();
            self::countries();
            self::discounts_and_order();
            $result = array( 'count' => count( self::$checks ), 'checks' => self::$checks,
                'wordpress' => get_bloginfo( 'version' ), 'woocommerce' => WC_VERSION,
                'commerce' => Belmains_Commerce::VERSION, 'prefix' => 'bcrm_validation_',
                'mail_sent' => false, 'external_payment' => false );
            echo wp_json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) . "\n";
        } finally { self::cleanup(); ob_end_flush(); }
    }
}
Belmains_Tax_Integration::run();
