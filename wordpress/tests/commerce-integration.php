<?php
/**
 * Real WooCommerce integration checks for Belmains Commerce.
 *
 * Run with wp eval-file ONLY against the disposable bcrm_validation_ database.
 * No gateway is contacted and all mail is suppressed. Dedicated fixtures and
 * changed options are restored, including on failure; existing fixtures remain.
 */

global $wpdb;
if ( 'bcrm_validation_' !== $wpdb->prefix || 'local' !== wp_get_environment_type() ) {
	throw new RuntimeException( 'Commerce tests require the isolated local validation tables.' );
}
if ( ! class_exists( 'Belmains_Commerce' ) || ! class_exists( 'BCRM_Commerce' ) || ! function_exists( 'WC' ) ) {
	throw new RuntimeException( 'Activate WooCommerce, Belmains Commerce and Belmains CRM in the isolated copy first.' );
}

add_filter( 'pre_wp_mail', '__return_false' );

final class Belmains_Commerce_Test_Json_End extends RuntimeException {}

final class Belmains_Commerce_Integration {
	private static $checks = array();
	private static $options = array();
	private static $products = array();
	private static $orders = array();
	private static $product_id;

	private static function check( $condition, $name ) {
		if ( ! $condition ) {
			throw new RuntimeException( 'FAIL: ' . $name );
		}
		self::$checks[] = $name;
		echo 'PASS ' . $name . "\n";
	}

	private static function close( $actual, $expected ) {
		return abs( (float) $actual - (float) $expected ) < 0.005;
	}

	private static function option( $key, $value ) {
		if ( ! array_key_exists( $key, self::$options ) ) {
			$missing = '__bcommerce_option_absent__';
			self::$options[ $key ] = get_option( $key, $missing );
		}
		update_option( $key, $value );
	}

	private static function reset_cart() {
		WC()->cart->empty_cart( false );
		WC()->session->set( 'order_awaiting_payment', null );
		wc_clear_notices();
	}

	private static function add( $quantity, $extra = array() ) {
		$key = WC()->cart->add_to_cart( self::$product_id, $quantity, 0, array(), $extra );
		WC()->cart->calculate_totals();
		return $key;
	}

	private static function store_request( $route, $data, $nonce = true ) {
		$request = new WP_REST_Request( 'POST', '/wc/store/v1/' . $route );
		$request->set_header( 'Content-Type', 'application/json' );
		if ( $nonce ) {
			$request->set_header( 'Nonce', wp_create_nonce( 'wc_store_api' ) );
		}
		$request->set_body( wp_json_encode( $data ) );
		$response = rest_do_request( $request );
		// Store API may use nested stdClass values; inspect its actual JSON shape.
		$response->set_data( json_decode( wp_json_encode( $response->get_data() ), true ) );
		return $response;
	}

	private static function ajax_request( $data, $method = 'POST' ) {
		$original_post = $_POST;
		$original_method = $_SERVER['REQUEST_METHOD'];
		$_POST = $data;
		$_SERVER['REQUEST_METHOD'] = $method;
		$handler = static function() {
			return static function() { throw new Belmains_Commerce_Test_Json_End(); };
		};
		add_filter( 'wp_doing_ajax', '__return_true' );
		add_filter( 'wp_die_ajax_handler', $handler );
		http_response_code( 200 );
		ob_start();
		try {
			do_action( 'wc_ajax_belmains_add_to_cart' );
		} catch ( Belmains_Commerce_Test_Json_End $expected ) {
			// Capture WordPress' actual JSON response without terminating wp eval-file.
		} finally {
			$json = ob_get_clean();
			$status = http_response_code();
			remove_filter( 'wp_doing_ajax', '__return_true' );
			remove_filter( 'wp_die_ajax_handler', $handler );
			$_POST = $original_post;
			$_SERVER['REQUEST_METHOD'] = $original_method;
		}
		return array( 'status' => $status, 'body' => json_decode( $json, true ) );
	}

	private static function setup() {
		wp_set_current_user( 0 );
		$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
		$_SERVER['HTTP_HOST'] = '127.0.0.1:9494';
		$_SERVER['REQUEST_METHOD'] = 'POST';
		unset( $_COOKIE['bcrm_consent'], $_COOKIE['bcrm_v'], $_COOKIE['bcrm_s'] );
		self::option( 'woocommerce_currency', 'EUR' );
		self::option( 'woocommerce_calc_taxes', 'no' );
		self::option( 'woocommerce_manage_stock', 'yes' );
		WC()->initialize_session();
		WC()->initialize_cart();
		WC()->session->set_customer_session_cookie( true );
		WC()->customer->set_billing_country( 'FR' );
		WC()->customer->set_shipping_country( 'FR' );
		WC()->customer->set_billing_postcode( '75001' );
		WC()->customer->set_shipping_postcode( '75001' );
		WC()->customer->set_calculated_shipping( true );
		self::reset_cart();

		$product = new WC_Product_Simple();
		$product->set_name( 'Gant gris · commerce validation isolée' );
		$product->set_sku( 'BELMAINS-COMMERCE-TEST-' . wp_generate_uuid4() );
		$product->set_regular_price( '109.99' );
		$product->set_sale_price( '89.99' );
		$product->set_manage_stock( true );
		$product->set_stock_quantity( 20 );
		$product->set_backorders( 'no' );
		$product->set_virtual( false );
		$product->set_status( 'publish' );
		$product->save();
		self::$product_id = $product->get_id();
		self::$products[] = self::$product_id;
		self::option( 'belmains_product_id', self::$product_id );
		self::check( Belmains_Commerce::product()->get_id() === self::$product_id, 'Configured product is resolved through WooCommerce' );
	}

	private static function prices_and_session() {
		foreach ( array( 1 => 89.99, 2 => 149.99, 3 => 239.98, 4 => 299.98, 10 => 749.95 ) as $quantity => $expected ) {
			self::reset_cart();
			$key = self::add( $quantity );
			self::check( (bool) $key && self::close( WC()->cart->get_cart_contents_total(), $expected ), "Cart price for {$quantity} physical gloves is {$expected} EUR" );
			WC()->cart->calculate_totals();
			WC()->cart->calculate_totals();
			self::check( self::close( WC()->cart->get_cart_contents_total(), $expected ), "Repeated totals preserve the price for quantity {$quantity}" );
		}

		self::reset_cart();
		$key = self::add( 2, array( 'price' => 0.01, 'line_total' => 0.02, 'belmains_price' => 0.01 ) );
		WC()->cart->cart_contents[ $key ]['data']->set_price( 0.01 );
		WC()->cart->calculate_totals();
		self::check( self::close( WC()->cart->get_cart_contents_total(), 149.99 ), 'Server offer overrides forged cart data and a modified cached product price' );
		WC()->cart->set_quantity( $key, 3 );
		self::check( self::close( WC()->cart->get_cart_contents_total(), 239.98 ), 'Changing duo quantity to three recalculates the actual order amount' );
		WC()->cart->set_quantity( $key, 1 );
		self::check( self::close( WC()->cart->get_cart_contents_total(), 89.99 ), 'Changing quantity back to one removes the duo price' );
		WC()->cart->set_quantity( $key, 0 );
		self::check( WC()->cart->is_empty(), 'Setting quantity to zero removes the product' );

		self::reset_cart();
		self::add( 1, array( 'integration_line' => 'one' ) );
		self::add( 1, array( 'integration_line' => 'two' ) );
		self::check( 2 === count( WC()->cart->get_cart() ) && self::close( WC()->cart->get_cart_contents_total(), 149.99 ), 'Separate lines of the same SKU share exactly one duo offer without a lost cent' );
		self::add( 1, array( 'integration_line' => 'three' ) );
		self::check( self::close( WC()->cart->get_cart_contents_total(), 239.98 ), 'Three separate lines retain the exact duo plus single price' );

		$session = new WC_Cart_Session( WC()->cart );
		$session->set_session();
		WC()->session->save_data();
		$stored = WC()->session->get_session( WC()->session->get_customer_id(), array() );
		$stored_cart = maybe_unserialize( $stored['cart'] ?? array() );
		self::check( 3 === count( $stored_cart ), 'WooCommerce persists physical quantities in the guest cart session' );
		WC()->cart->set_cart_contents( array() );
		WC()->session->set( 'cart', $stored_cart );
		$session->get_cart_from_session();
		WC()->cart->calculate_totals();
		self::check( 3 === (int) WC()->cart->get_cart_contents_count() && self::close( WC()->cart->get_cart_contents_total(), 239.98 ), 'Database session rehydration keeps quantities and recomputes the offer' );

		self::reset_cart();
		$other = new WC_Product_Simple();
		$other->set_name( 'Produit hors offre · validation isolée' );
		$other->set_regular_price( '12.34' );
		$other->set_status( 'publish' );
		$other->save();
		self::$products[] = $other->get_id();
		WC()->cart->add_to_cart( $other->get_id(), 2, 0, array(), array( 'belmains_offer' => 'duo' ) );
		self::add( 2 );
		self::check( self::close( WC()->cart->get_cart_contents_total(), 174.67 ), 'A product outside the configured SKU keeps its normal price in a mixed cart' );
	}

	private static function stock_and_store_api() {
		self::reset_cart();
		self::check( false === self::add( 21 ) && WC()->cart->is_empty(), 'Adding more than the available physical stock is refused' );
		wc_clear_notices();
		self::add( 19 );
		self::check( false === self::add( 2 ) && 19 === (int) WC()->cart->get_cart_contents_count(), 'Adding a duo also accounts for stock already in the cart' );
		self::reset_cart();

		$response = self::store_request( 'cart/add-item', array( 'id' => self::$product_id, 'quantity' => 2 ) );
		self::check( 201 === $response->get_status(), 'WooCommerce Store API accepts the product with its own cart nonce' );
		$data = $response->get_data();
		self::check( '14999' === (string) ( $data['totals']['total_items'] ?? '' ), 'Store API uses the same exact duo price in cents' );
		$key = $data['items'][0]['key'] ?? '';
		$response = self::store_request( 'cart/update-item', array( 'key' => $key, 'quantity' => 3 ) );
		self::check( 200 === $response->get_status() && '23998' === (string) ( $response->get_data()['totals']['total_items'] ?? '' ), 'Store API quantity updates preserve server pricing' );
		$response = self::store_request( 'cart/update-item', array( 'key' => $key, 'quantity' => 21 ) );
		self::check( $response->get_status() >= 400 && 3 === (int) WC()->cart->get_cart_contents_count(), 'Store API refuses an unavailable quantity without changing the cart' );
		$response = self::store_request( 'cart/add-item', array( 'id' => self::$product_id, 'quantity' => 1 ), false );
		self::check( in_array( $response->get_status(), array( 401, 403 ), true ), 'Store API mutations require a cart nonce or token' );
	}

	private static function ajax_security() {
		self::reset_cart();
		$valid = array( 'nonce' => wp_create_nonce( 'belmains_add_to_cart' ), 'product_id' => self::$product_id, 'quantity' => '2' );
		$response = self::ajax_request( $valid, 'GET' );
		self::check( 405 === $response['status'] && WC()->cart->is_empty(), 'Storefront add-to-cart rejects a GET mutation' );
		$response = self::ajax_request( array_merge( $valid, array( 'nonce' => 'invalid' ) ) );
		self::check( 403 === $response['status'] && WC()->cart->is_empty(), 'Storefront add-to-cart rejects an invalid nonce' );
		$response = self::ajax_request( array_merge( $valid, array( 'product_id' => 0 ) ) );
		self::check( 400 === $response['status'] && WC()->cart->is_empty(), 'Storefront add-to-cart accepts only the configured product' );
		foreach ( array( 'zero' => 0, 'negative' => -1, 'fraction' => 1.5, 'too large' => 1000, 'array' => array( 2 ), 'malformed text' => '2gants' ) as $label => $quantity ) {
			$response = self::ajax_request( array_merge( $valid, array( 'quantity' => $quantity ) ) );
			self::check( 400 === $response['status'] && WC()->cart->is_empty(), 'Storefront add-to-cart rejects quantity: ' . $label );
		}
		$response = self::ajax_request( array_merge( $valid, array( 'quantity' => 21 ) ) );
		self::check( 409 === $response['status'] && WC()->cart->is_empty(), 'Storefront add-to-cart returns a stock error without modifying the cart' );
		$response = self::ajax_request( array_merge( $valid, array( 'price' => 0.01, 'total' => 0.02, 'offerPrice' => 1 ) ) );
		self::check( 200 === $response['status'] && ! empty( $response['body']['success'] ) && self::close( WC()->cart->get_cart_contents_total(), 149.99 ), 'Forged posted prices cannot change the duo amount through the actual storefront action' );
		$data = $response['body']['data'] ?? array();
		self::check( 2 === (int) ( $data['cart_count'] ?? 0 ) && ! empty( $data['cart_hash'] ) && isset( $data['fragments']['span.belmains-cart-count'] ), 'Storefront success supplies the real cart quantity, hash and badge fragment' );
		self::check( wc_get_cart_url() === ( $data['cart_url'] ?? '' ) && wc_get_checkout_url() === ( $data['checkout_url'] ?? '' ), 'Storefront success links to the native WooCommerce cart and checkout' );
	}

	private static function checkout_and_crm() {
		self::reset_cart();
		self::add( 2 );
		$from = wp_date( 'Y-m-d' );
		$baseline = BCRM_Commerce::summary( $from, $from )['metrics'];
		$checkout = WC()->checkout();
		$checkout_data = array(
			'billing_first_name' => 'Camille', 'billing_last_name' => 'Validation commerce',
			'billing_email' => 'commerce-validation@example.invalid', 'billing_phone' => '0100000000',
			'billing_address_1' => 'Adresse de validation', 'billing_city' => 'Paris',
			'billing_postcode' => '75001', 'billing_country' => 'FR',
			'shipping_first_name' => 'Camille', 'shipping_last_name' => 'Validation commerce',
			'shipping_address_1' => 'Adresse de validation', 'shipping_city' => 'Paris',
			'shipping_postcode' => '75001', 'shipping_country' => 'FR',
			'payment_method' => '', 'order_comments' => 'Test isolé, aucun paiement ni envoi réel.',
		);
		foreach ( $checkout->get_checkout_fields() as $fieldset ) {
			foreach ( $fieldset as $key => $field ) {
				$checkout_data[ $key ] = $checkout_data[ $key ] ?? '';
			}
		}
		$errors = new WP_Error();
		$validate = new ReflectionMethod( $checkout, 'validate_checkout' );
		$validate->setAccessible( true );
		$validate->invokeArgs( $checkout, array( &$checkout_data, &$errors ) );
		self::check( in_array( 'payment', $errors->get_error_codes(), true ), 'Checkout validation refuses an order without a configured payment method' );
		wc_clear_notices();
		// Exercise native order persistence separately from the deliberately absent gateway.
		$order_id = $checkout->create_order( $checkout_data );
		self::check( ! is_wp_error( $order_id ) && $order_id > 0, 'Native WooCommerce checkout creates a persistent order from the real cart' );
		self::$orders[] = $order_id;
		$order = wc_get_order( $order_id );
		self::check( 'checkout' === $order->get_created_via() && 0 === $order->get_customer_id(), 'Guest checkout preserves the native order origin and customer model' );
		self::check( self::close( $order->get_total(), 149.99 ) && 2 === (int) $order->get_item_count(), 'Persisted duo order contains two physical gloves and the correct total' );
		self::check( $order->needs_payment() && ! $order->is_paid(), 'Creating a checkout order does not mark an unpaid purchase as paid' );
		$pending = BCRM_Commerce::summary( $from, $from )['metrics'];
		self::check( $pending['orders_total'] === $baseline['orders_total'] + 1 && self::close( $pending['net_revenue'], $baseline['net_revenue'] ), 'CRM immediately receives the pending order without counting unpaid revenue' );
		self::check( 20 === (int) wc_get_product( self::$product_id )->get_stock_quantity(), 'Order creation alone has not reduced physical stock' );

		$order->payment_complete( 'ISOLATED-SIMULATION-NO-GATEWAY' );
		$order = wc_get_order( $order_id );
		self::check( $order->is_paid() && ! $order->needs_payment(), 'Simulated payment confirmation uses WooCommerce paid order lifecycle' );
		self::check( 18 === (int) wc_get_product( self::$product_id )->get_stock_quantity(), 'A paid duo removes exactly two physical gloves from inventory' );
		$order->payment_complete( 'ISOLATED-SIMULATION-NO-GATEWAY' );
		self::check( 18 === (int) wc_get_product( self::$product_id )->get_stock_quantity(), 'Repeated payment confirmation never reduces stock twice' );
		$paid = BCRM_Commerce::summary( $from, $from )['metrics'];
		self::check( $paid['orders_paid'] === $baseline['orders_paid'] + 1 && self::close( $paid['net_revenue'] - $baseline['net_revenue'], 149.99 ), 'CRM recognizes the paid order and exact revenue once' );
		$clients = BCRM_Commerce::customers( $from, $from, 1, 'commerce-validation@example.invalid' );
		self::check( 1 === $clients['total'], 'The guest buyer appears in CRM customer search' );
		self::check( 'pending' === BCRM_Tracking::get( $order )['status'], 'A paid order is still awaiting shipment and is never marked delivered automatically' );

		$item = current( $order->get_items() );
		$refund = wc_create_refund( array(
			'order_id' => $order_id, 'amount' => 149.99, 'reason' => 'Validation isolée remboursement complet',
			'refund_payment' => false, 'restock_items' => true,
			'line_items' => array( $item->get_id() => array( 'qty' => 2, 'refund_total' => 149.99, 'refund_tax' => array() ) ),
		) );
		self::check( ! is_wp_error( $refund ), 'WooCommerce records a full refund without contacting a payment provider' );
		self::check( 20 === (int) wc_get_product( self::$product_id )->get_stock_quantity(), 'Restocking the returned duo restores exactly two gloves' );
		$refunded = BCRM_Commerce::summary( $from, $from )['metrics'];
		self::check( self::close( $refunded['net_revenue'], $baseline['net_revenue'] ) && self::close( $refunded['refund_total'] - $baseline['refund_total'], 149.99 ), 'CRM removes refunded revenue and records the actual refund amount' );
	}

	private static function cleanup() {
		if ( WC()->cart ) {
			self::reset_cart();
		}
		foreach ( self::$orders as $id ) {
			$order = wc_get_order( $id );
			if ( $order ) {
				foreach ( $order->get_refunds() as $refund ) {
					$refund->delete( true );
				}
				$order->delete( true );
			}
		}
		foreach ( self::$products as $id ) {
			$product = wc_get_product( $id );
			if ( $product ) {
				$product->delete( true );
			}
		}
		foreach ( self::$options as $key => $value ) {
			if ( '__bcommerce_option_absent__' === $value ) {
				delete_option( $key );
			} else {
				update_option( $key, $value );
			}
		}
		if ( WC()->session ) {
			WC()->session->destroy_session();
		}
	}

	public static function run() {
		ob_start();
		try {
			self::setup();
			self::prices_and_session();
			self::stock_and_store_api();
			self::ajax_security();
			self::checkout_and_crm();
			self::check( 'yes' === get_option( 'woocommerce_custom_orders_table_enabled' ), 'All order lifecycle tests ran with WooCommerce HPOS enabled' );
			echo count( self::$checks ) . " commerce integration checks passed.\n";
			file_put_contents( dirname( ABSPATH ) . '/commerce-integration-result.json', wp_json_encode( array(
				'count' => count( self::$checks ), 'checks' => self::$checks, 'hpos' => get_option( 'woocommerce_custom_orders_table_enabled' ),
				'isolated_prefix' => 'bcrm_validation_', 'external_payment' => false, 'mail_sent' => false,
			), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );
		} finally {
			self::cleanup();
			ob_end_flush();
		}
	}
}

Belmains_Commerce_Integration::run();
