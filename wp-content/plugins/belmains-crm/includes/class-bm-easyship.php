<?php
defined( 'ABSPATH' ) || exit;

/**
 * Client API Easyship (version d'API 2023-01) + synchronisation du suivi.
 *
 * Métadonnées de commande utilisées :
 *  _bm_easyship_shipment_id, _bm_tracking_number, _bm_carrier, _bm_tracking_url,
 *  _bm_tracking_status (cf. bm_tracking_statuses), _bm_label_url, _bm_shipped_at, _bm_delivered_at
 *
 * NOTE : les chemins/champs de l'API sont ceux de la doc publique Easyship 2023-01.
 * Vérifiez-les sur https://developers.easyship.com avant la mise en production.
 */
class BM_Easyship {

	const API_BASE = 'https://public-api.easyship.com/2023-01';

	public static function init(): void {
		add_action( 'bm_crm_sync_tracking', array( __CLASS__, 'cron_sync' ) );
		add_action( 'woocommerce_order_status_processing', array( __CLASS__, 'maybe_auto_create' ), 20 );
	}

	public static function is_configured(): bool {
		return '' !== (string) bm_setting( 'easyship_token' );
	}

	/* ------------------------------------------------------------------ HTTP */

	public static function request( string $method, string $path, ?array $body = null ) {
		if ( ! self::is_configured() ) {
			return new WP_Error( 'bm_easyship_token', 'Jeton API Easyship manquant (Belmains → Réglages).' );
		}
		$args = array(
			'method'  => $method,
			'timeout' => 30,
			'headers' => array(
				'Authorization' => 'Bearer ' . bm_setting( 'easyship_token' ),
				'Content-Type'  => 'application/json',
				'Accept'        => 'application/json',
			),
		);
		if ( null !== $body ) {
			$args['body'] = wp_json_encode( $body );
		}
		$res = wp_remote_request( self::API_BASE . $path, $args );
		if ( is_wp_error( $res ) ) {
			bm_log( 'Easyship HTTP error: ' . $res->get_error_message(), array( 'path' => $path ), 'error' );
			return $res;
		}
		$code = wp_remote_retrieve_response_code( $res );
		$data = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( $code < 200 || $code >= 300 ) {
			$msg = $data['error']['message'] ?? ( $data['message'] ?? 'Réponse HTTP ' . $code );
			if ( ! empty( $data['error']['details'] ) ) {
				$msg .= ' — ' . implode( ' ; ', (array) $data['error']['details'] );
			}
			bm_log( 'Easyship API error: ' . $msg, array( 'path' => $path, 'code' => $code, 'body' => $data ), 'error' );
			return new WP_Error( 'bm_easyship_api', $msg, $data );
		}
		return is_array( $data ) ? $data : array();
	}

	/* --------------------------------------------------------------- Payload */

	public static function build_shipment_payload( WC_Order $order ): array {
		$items       = array();
		$total_kg    = 0.0;
		$hs          = bm_setting( 'hs_code' );
		$def_weight  = (float) bm_setting( 'parcel_weight', 0.45 );

		foreach ( $order->get_items() as $item ) {
			/** @var WC_Order_Item_Product $item */
			$product = $item->get_product();
			$qty     = max( 1, (int) $item->get_quantity() );
			$weight  = $product && $product->get_weight() ? wc_get_weight( (float) $product->get_weight(), 'kg' ) : $def_weight;
			$total_kg += $weight * $qty;
			$unit_value = $qty ? (float) $item->get_total() / $qty : 0.0;

			$line = array(
				'description'            => $item->get_name(),
				'sku'                    => $product ? (string) $product->get_sku() : '',
				'quantity'               => $qty,
				'actual_weight'          => round( $weight, 3 ),
				'declared_currency'      => $order->get_currency(),
				'declared_customs_value' => round( $unit_value, 2 ),
				'category'               => 'health_beauty',
				'origin_country_alpha2'  => 'CN',
			);
			if ( $hs ) {
				$line['hs_code'] = $hs;
			}
			$items[] = $line;
		}

		$dest = array(
			'contact_name'   => trim( $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name() ) ?: $order->get_formatted_billing_full_name(),
			'company_name'   => $order->get_shipping_company(),
			'line_1'         => $order->get_shipping_address_1(),
			'line_2'         => $order->get_shipping_address_2(),
			'city'           => $order->get_shipping_city(),
			'state'          => $order->get_shipping_state(),
			'postal_code'    => $order->get_shipping_postcode(),
			'country_alpha2' => $order->get_shipping_country() ?: $order->get_billing_country(),
			'contact_phone'  => $order->get_billing_phone(),
			'contact_email'  => $order->get_billing_email(),
		);

		$origin = array(
			'company_name'   => bm_setting( 'origin_company', 'Belmains' ),
			'contact_name'   => bm_setting( 'origin_name', bm_setting( 'origin_company', 'Belmains' ) ),
			'line_1'         => bm_setting( 'origin_line1' ),
			'line_2'         => bm_setting( 'origin_line2' ),
			'city'           => bm_setting( 'origin_city' ),
			'postal_code'    => bm_setting( 'origin_postcode' ),
			'country_alpha2' => bm_setting( 'origin_country', 'FR' ),
			'contact_phone'  => bm_setting( 'origin_phone' ),
			'contact_email'  => bm_setting( 'origin_email' ),
		);

		$payload = array(
			'platform_name'         => 'WooCommerce',
			'platform_order_number' => $order->get_order_number(),
			'origin_address'        => $origin,
			'destination_address'   => $dest,
			'incoterms'             => 'DDU',
			'insurance'             => array( 'is_insured' => false ),
			'courier_selection'     => array(
				'allow_courier_fallback' => true,
				'apply_shipping_rules'   => true,
			),
			'shipping_settings'     => array(
				'units'                 => array( 'weight' => 'kg', 'dimensions' => 'cm' ),
				'buy_label'             => '1' === bm_setting( 'easyship_buy_label', '0' ),
				'buy_label_synchronous' => false,
				'printing_options'      => array( 'format' => 'pdf', 'label' => '4x6' ),
			),
			'parcels'               => array(
				array(
					'box'                 => array(
						'length' => (float) bm_setting( 'parcel_length', 22 ),
						'width'  => (float) bm_setting( 'parcel_width', 16 ),
						'height' => (float) bm_setting( 'parcel_height', 8 ),
					),
					'total_actual_weight' => round( max( $total_kg, 0.05 ), 3 ),
					'items'               => $items,
				),
			),
		);

		return apply_filters( 'bm_easyship_shipment_payload', $payload, $order );
	}

	/* --------------------------------------------------------------- Actions */

	/**
	 * Crée l'expédition Easyship pour une commande (POST /shipments).
	 */
	public static function create_shipment( WC_Order $order ) {
		if ( $order->get_meta( '_bm_easyship_shipment_id' ) ) {
			return new WP_Error( 'bm_exists', 'Une expédition Easyship existe déjà pour cette commande.' );
		}
		$res = self::request( 'POST', '/shipments', self::build_shipment_payload( $order ) );
		if ( is_wp_error( $res ) ) {
			$order->add_order_note( 'Easyship : échec de création — ' . $res->get_error_message() );
			return $res;
		}
		$shipment = $res['shipment'] ?? $res;
		self::apply_shipment_data( $order, $shipment, 'api' );
		$order->add_order_note( 'Easyship : expédition créée (' . ( $shipment['easyship_shipment_id'] ?? '?' ) . ').' );
		return $shipment;
	}

	/**
	 * Achète l'étiquette (POST /labels).
	 */
	public static function buy_label( WC_Order $order ) {
		$id = $order->get_meta( '_bm_easyship_shipment_id' );
		if ( ! $id ) {
			return new WP_Error( 'bm_no_shipment', 'Aucune expédition Easyship liée.' );
		}
		$res = self::request( 'POST', '/labels', array( 'shipments' => array( array( 'easyship_shipment_id' => $id ) ) ) );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$shipment = $res['shipments'][0] ?? ( $res['shipment'] ?? null );
		if ( $shipment ) {
			self::apply_shipment_data( $order, $shipment, 'api' );
		}
		$order->add_order_note( 'Easyship : étiquette demandée.' );
		return $res;
	}

	/**
	 * Récupère l'expédition (GET /shipments/{id}) et met à jour le suivi.
	 */
	public static function sync_tracking( WC_Order $order ) {
		$id = $order->get_meta( '_bm_easyship_shipment_id' );
		if ( ! $id ) {
			return new WP_Error( 'bm_no_shipment', 'Aucune expédition Easyship liée.' );
		}
		$res = self::request( 'GET', '/shipments/' . rawurlencode( $id ) );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$shipment = $res['shipment'] ?? $res;
		self::apply_shipment_data( $order, $shipment, 'api' );
		return $shipment;
	}

	/**
	 * Applique les données d'une expédition Easyship (réponse API ou webhook) sur la commande.
	 */
	public static function apply_shipment_data( WC_Order $order, array $s, string $source = 'api' ): void {
		if ( ! empty( $s['easyship_shipment_id'] ) ) {
			$order->update_meta_data( '_bm_easyship_shipment_id', sanitize_text_field( $s['easyship_shipment_id'] ) );
		}
		if ( ! empty( $s['tracking_number'] ) ) {
			$order->update_meta_data( '_bm_tracking_number', sanitize_text_field( $s['tracking_number'] ) );
		}
		if ( ! empty( $s['tracking_page_url'] ) ) {
			$order->update_meta_data( '_bm_tracking_url', esc_url_raw( $s['tracking_page_url'] ) );
		}
		$carrier = $s['courier_service']['name'] ?? ( $s['courier']['name'] ?? ( $s['courier_service_name'] ?? '' ) );
		if ( $carrier ) {
			$order->update_meta_data( '_bm_carrier', sanitize_text_field( $carrier ) );
		}
		$label = $s['shipping_documents'][0]['url'] ?? ( $s['label_url'] ?? '' );
		if ( $label ) {
			$order->update_meta_data( '_bm_label_url', esc_url_raw( $label ) );
		}

		$raw_status = $s['tracking_status'] ?? ( $s['shipment_state'] ?? ( $s['label_state'] ?? '' ) );
		$status     = self::normalize_status( (string) $raw_status );
		if ( 'none' !== $status ) {
			BM_Shipments::set_tracking_status( $order, $status, $source, 'Easyship : ' . $raw_status );
		}
		$order->save();
	}

	/**
	 * Normalise un statut Easyship vers bm_tracking_statuses().
	 */
	public static function normalize_status( string $raw ): string {
		$raw = strtolower( trim( $raw ) );
		$map = array(
			'created'            => 'pending',
			'not_created'        => 'pending',
			'pending'            => 'pending',
			'label_pending'      => 'pending',
			'generated'          => 'label_created',
			'label_created'      => 'label_created',
			'printed'            => 'label_created',
			'label_printed'      => 'label_created',
			'info_received'      => 'label_created',
			'in_transit'         => 'in_transit',
			'in_transit_to_customer' => 'in_transit',
			'out_for_delivery'   => 'out_for_delivery',
			'delivered'          => 'delivered',
			'exception'          => 'exception',
			'failed_attempt'     => 'exception',
			'failed'             => 'exception',
			'returned_to_sender' => 'returned',
			'return_to_sender'   => 'returned',
			'cancelled'          => 'cancelled',
			'canceled'           => 'cancelled',
		);
		return $map[ $raw ] ?? 'none';
	}

	/* ------------------------------------------------------------- Automates */

	public static function maybe_auto_create( $order_id ): void {
		if ( '1' !== bm_setting( 'easyship_auto', '0' ) || ! self::is_configured() ) {
			return;
		}
		$order = wc_get_order( $order_id );
		if ( ! $order || $order->get_meta( '_bm_easyship_shipment_id' ) || ! $order->needs_shipping_address() ) {
			return;
		}
		self::create_shipment( $order );
	}

	/**
	 * Cron : re-synchronise les expéditions non livrées (filet de sécurité si un webhook est perdu).
	 */
	public static function cron_sync(): void {
		if ( ! self::is_configured() ) {
			return;
		}
		$orders = wc_get_orders( array(
			'type'       => 'shop_order',
			'limit'      => 50,
			'status'     => array( 'processing', 'bm-expediee' ),
			'meta_query' => array(
				array( 'key' => '_bm_easyship_shipment_id', 'compare' => 'EXISTS' ),
				array( 'key' => '_bm_tracking_status', 'value' => array( 'delivered', 'cancelled', 'returned' ), 'compare' => 'NOT IN' ),
			),
		) );
		foreach ( $orders as $order ) {
			self::sync_tracking( $order );
		}
	}
}
