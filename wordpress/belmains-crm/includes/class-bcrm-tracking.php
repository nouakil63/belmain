<?php
/** Shipment evidence and the free Iziship/WooCommerce tracking metadata bridge. */
defined( 'ABSPATH' ) || exit;

final class BCRM_Tracking {

	/** Orders verified by WooCommerce's tracking form, for this request only. */
	private static $tracked_order_ids = array();

	private const STATUSES = array(
		'pending'    => 'En attente de suivi',
		'shipped'    => 'Expédié',
		'in_transit' => 'En transit',
		'relay'      => 'Disponible en point relais',
		'delivered'  => 'Livré',
		'exception'  => 'Incident',
		'returned'   => 'Retourné',
	);

	public static function register() {
		add_action( 'woocommerce_rest_insert_shop_order_object', array( __CLASS__, 'observe_rest_update' ), 20, 3 );
		add_action( 'woocommerce_track_order', array( __CLASS__, 'authorize_tracked_order' ) );
		add_action( 'woocommerce_order_details_after_order_table', array( __CLASS__, 'customer_tracking' ) );
		add_action( 'woocommerce_email_after_order_table', array( __CLASS__, 'email_tracking' ), 20, 4 );
	}

	/** Do not mistake WooCommerce's completed status for a carrier delivery event. */
	public static function get( $order ) {
		if ( ! $order instanceof WC_Order ) {
			return self::empty_tracking();
		}

		$source = (string) $order->get_meta( '_bcrm_tracking_source', true );
		$source = in_array( $source, array( 'manual', 'iziship', 'imported' ), true ) ? $source : '';
		$number = self::text( $order->get_meta( '_bcrm_tracking_number', true ), 120 );
		$carrier = self::text( $order->get_meta( '_bcrm_tracking_carrier', true ), 120 );
		$url = self::safe_url( $order->get_meta( '_bcrm_tracking_url', true ) );
		$shipped_at = self::stored_date( $order->get_meta( '_bcrm_tracking_shipped_at', true ) );
		$delivered_at = self::stored_date( $order->get_meta( '_bcrm_tracking_delivered_at', true ) );

		if ( '' === $source ) {
			$number = self::text( $order->get_meta( 'tracking_number', true ), 120 );
			$imported = self::plugin_tracking( $order );
			if ( '' === $number && $imported ) {
				$number = $imported['number'];
			}
			// Carrier metadata belongs to the same tracking number, never another parcel.
			if ( $imported && $number === $imported['number'] ) {
				$carrier = $carrier ?: $imported['carrier'];
				$url = $url ?: $imported['url'];
				$shipped_at = $shipped_at ?: $imported['shipped_at'];
			}
			$source = '' !== $number ? 'imported' : '';
		}

		$status = (string) $order->get_meta( '_bcrm_tracking_status', true );
		if ( ! isset( self::STATUSES[ $status ] ) ) {
			$status = '' !== $number ? 'shipped' : 'pending';
		}

		return array(
			'number' => $number,
			'carrier' => $carrier,
			'url' => $url,
			'status' => $status,
			'status_label' => self::STATUSES[ $status ],
			'shipped_at' => $shipped_at,
			'delivered_at' => $delivered_at,
			'source' => $source,
			'status_is_manual' => 'yes' === $order->get_meta( '_bcrm_tracking_status_manual', true ),
			'observed_at' => self::text( $order->get_meta( '_bcrm_tracking_external_observed_at', true ), 60 ),
		);
	}

	/** The order creation date defines this cohort, not a claimed shipment event date. */
	public static function shipments( $from, $to, $page = 1, $search = '', $status = '' ) {
		$metrics = array_fill_keys( array( 'awaiting', 'shipped', 'in_transit', 'relay', 'delivered', 'exception', 'returned' ), 0 );
		$result = array( 'items' => array(), 'metrics' => $metrics, 'total' => 0, 'pages' => 0, 'page' => max( 1, (int) $page ), 'warnings' => array() );
		if ( ! function_exists( 'wc_get_orders' ) || ! class_exists( 'BCRM_Commerce' ) ) {
			$result['warnings'][] = 'Activez WooCommerce pour consulter les expéditions.';
			return $result;
		}
		$scan = BCRM_Commerce::scan_orders( $from, $to );
		if ( is_wp_error( $scan ) ) {
			return $scan;
		}
		$result['warnings'] = isset( $scan['warnings'] ) ? $scan['warnings'] : array();
		$result['warnings'][] = 'Cohorte : commandes contenant un produit physique, créées sur la période, hors commandes annulées, échouées ou remboursées. Les compteurs portent sur toute cette cohorte ; la recherche et le filtre de suivi ne modifient que la liste.';
		$result['warnings'][] = 'Les statuts de livraison sont renseignés manuellement. Une réception du numéro de suivi indique une expédition, jamais une livraison confirmée. Aucun suivi transporteur en temps réel ne fonctionne sans connexion supplémentaire.';
		if ( ! empty( $scan['capped'] ) && ! preg_grep( '/5[ .]?000|limite|plafond/i', $result['warnings'] ) ) {
			$result['warnings'][] = 'Analyse limitée à 5 000 commandes. Réduisez la période pour obtenir une cohorte complète.';
		}
		$search = self::text( $search, 200 );
		$status = 'awaiting' === $status ? 'pending' : (string) $status;
		if ( '' !== $status && ! isset( self::STATUSES[ $status ] ) ) {
			return new WP_Error( 'bcrm_tracking_status', 'Le statut de suivi est inconnu.', array( 'status' => 400 ) );
		}
		$matching = array();
		foreach ( $scan['orders'] as $order ) {
			if ( ! $order instanceof WC_Order || in_array( $order->get_status(), array( 'cancelled', 'failed', 'refunded' ), true ) || ! self::has_physical_items( $order ) ) {
				continue;
			}
			$tracking = self::get( $order );
			$key = 'pending' === $tracking['status'] ? 'awaiting' : $tracking['status'];
			++$result['metrics'][ $key ];
			if ( '' !== $status && $status !== $tracking['status'] ) {
				continue;
			}
			if ( '' !== $search ) {
				$haystack = implode( ' ', array( $order->get_id(), $order->get_order_number(), $order->get_formatted_billing_full_name(), $order->get_billing_email(), $tracking['number'], $tracking['carrier'] ) );
				if ( false === stripos( remove_accents( $haystack ), remove_accents( $search ) ) ) {
					continue;
				}
			}
			$matching[] = $order;
		}
		$result['total'] = count( $matching );
		$result['pages'] = (int) ceil( $result['total'] / 20 );
		$result['page'] = min( $result['page'], max( 1, $result['pages'] ) );
		foreach ( array_slice( $matching, ( $result['page'] - 1 ) * 20, 20 ) as $order ) {
			$result['items'][] = BCRM_Commerce::serialize_order( $order );
		}
		return $result;
	}

	/** Callers enforce the CRM capability and nonce before entering this method. */
	public static function save( $id, $data ) {
		$order = function_exists( 'wc_get_order' ) ? wc_get_order( absint( $id ) ) : false;
		if ( ! $order instanceof WC_Order ) {
			return new WP_Error( 'bcrm_order_not_found', 'Commande introuvable.', array( 'status' => 404 ) );
		}
		if ( ! is_array( $data ) ) {
			return new WP_Error( 'bcrm_tracking_payload', 'Les informations de suivi sont invalides.', array( 'status' => 400 ) );
		}
		$current = self::get( $order );
		$values = array();
		foreach ( array( 'number', 'carrier', 'url', 'status', 'shipped_at', 'delivered_at' ) as $key ) {
			$value = array_key_exists( $key, $data ) ? $data[ $key ] : $current[ $key ];
			if ( ! is_scalar( $value ) && null !== $value ) {
				return new WP_Error( 'bcrm_tracking_payload', 'Un champ de suivi est invalide.', array( 'status' => 400 ) );
			}
			$values[ $key ] = trim( (string) $value );
		}
		if ( self::text( $values['number'], 120 ) !== $current['number'] ) {
			if ( ! array_key_exists( 'status', $data ) ) {
				$values['status'] = '' !== $values['number'] ? 'shipped' : 'pending';
			}
			foreach ( array( 'url', 'shipped_at', 'delivered_at' ) as $key ) {
				if ( ! array_key_exists( $key, $data ) ) {
					$values[ $key ] = '';
				}
			}
		}
		if ( ! isset( self::STATUSES[ $values['status'] ] ) ) {
			return new WP_Error( 'bcrm_tracking_status', 'Sélectionnez un statut de suivi valide.', array( 'status' => 400 ) );
		}
		$url = self::safe_url( $values['url'] );
		if ( '' !== $values['url'] && '' === $url ) {
			return new WP_Error( 'bcrm_tracking_url', 'Le lien de suivi doit être une adresse HTTPS valide, sans identifiants dans son URL.', array( 'status' => 400 ) );
		}
		$values['url'] = $url;
		$values['number'] = self::text( $values['number'], 120 );
		$values['carrier'] = self::text( $values['carrier'], 120 );
		foreach ( array( 'shipped_at', 'delivered_at' ) as $key ) {
			$date = self::parse_date( $values[ $key ] );
			if ( false === $date ) {
				return new WP_Error( 'bcrm_tracking_date', 'Indiquez une date valide au format AAAA-MM-JJ.', array( 'status' => 400 ) );
			}
			$values[ $key ] = $date;
		}
		if ( $values['shipped_at'] && $values['delivered_at'] && $values['delivered_at'] < $values['shipped_at'] ) {
			return new WP_Error( 'bcrm_tracking_date_order', 'La livraison ne peut pas précéder la date d’expédition.', array( 'status' => 400 ) );
		}
		foreach ( $values as $key => $value ) {
			$order->update_meta_data( '_bcrm_tracking_' . $key, $value );
		}
		$order->update_meta_data( '_bcrm_tracking_source', 'manual' );
		$order->update_meta_data( '_bcrm_tracking_status_manual', 'yes' );
		$order->update_meta_data( '_bcrm_tracking_manual_updated_at', current_datetime()->format( DATE_ATOM ) );
		$order->save();
		return array( 'success' => true );
	}

	/** Observe only evidence in an authenticated Woo REST write, never guess an API. */
	public static function observe_rest_update( $order, $request, $creating = false ) {
		if ( ! $order instanceof WC_Order || ! $request instanceof WP_REST_Request ) {
			return;
		}
		$metadata = $request->get_param( 'meta_data' );
		if ( ! is_array( $metadata ) ) {
			return;
		}
		$found = false;
		foreach ( $metadata as $entry ) {
			if ( is_array( $entry ) && isset( $entry['key'] ) && 'tracking_number' === $entry['key'] && array_key_exists( 'value', $entry ) && is_scalar( $entry['value'] ) ) {
				$found = true;
			}
		}
		if ( ! $found ) {
			return;
		}
		$number = self::text( $order->get_meta( 'tracking_number', true ), 120 );
		$previous = self::text( $order->get_meta( '_bcrm_tracking_number', true ), 120 );
		$changed = $previous !== $number;
		$now = current_datetime()->format( DATE_ATOM );
		$order->update_meta_data( '_bcrm_tracking_number', $number );
		$order->update_meta_data( '_bcrm_tracking_source', 'iziship' );
		$order->update_meta_data( '_bcrm_tracking_external_observed_at', $now );
		$order->update_meta_data( '_bcrm_tracking_evidence', 'woocommerce_rest:meta_data.tracking_number' );
		if ( $changed || ! $order->get_meta( '_bcrm_tracking_status', true ) ) {
			$order->update_meta_data( '_bcrm_tracking_status', '' !== $number ? 'shipped' : 'pending' );
			$order->update_meta_data( '_bcrm_tracking_status_manual', 'no' );
			if ( $changed ) {
				// A new parcel must not inherit another parcel's delivery event or URL.
				foreach ( array( 'carrier', 'url', 'shipped_at', 'delivered_at' ) as $key ) {
					$order->delete_meta_data( '_bcrm_tracking_' . $key );
				}
			}
		}
		$order->save();
		update_option( 'bcrm_iziship_last_sync', $now, false );
		update_option( 'bcrm_iziship_external_updates', (int) get_option( 'bcrm_iziship_external_updates', 0 ) + 1, false );
	}

	public static function integration() {
		$public = self::site_public_https();
		$last_sync = get_option( 'bcrm_iziship_last_sync', '' );
		$warnings = array(
			'Configuration Iziship : choisissez la remontée du numéro dans la métadonnée tracking_number. Les notes de commande ne sont pas analysées automatiquement.',
			'Les mises à jour comptées sont des écritures WooCommerce REST reçues sur tracking_number. Ce signal ne permet pas, à lui seul, de certifier l’identité du service émetteur.',
			'Iziship marque la commande Terminée lors de l’expédition. Les événements Livré, Incident, Relais et Retourné doivent être renseignés manuellement tant qu’une source transporteur documentée n’est pas raccordée.',
			'Le numéro de suivi est reconnu gratuitement. Si Iziship ne fournit ni transporteur ni URL, complétez ces champs dans la commande ; aucun lien n’est inventé.',
			'Cette version présente un suivi principal par commande. Si une extension existante fournit plusieurs colis, le dernier est affiché ; consultez alors la commande WooCommerce pour le détail complet.',
		);
		if ( ! function_exists( 'wc_get_order' ) ) {
			$warnings[] = 'WooCommerce doit être activé avant de configurer la connexion.';
		}
		if ( ! $public ) {
			$warnings[] = 'Connexion en attente : cette boutique ne dispose pas encore d’une adresse HTTPS publique. Iziship ne peut pas joindre belmains.local depuis ses serveurs.';
		} else {
			$warnings[] = 'Une adresse HTTPS publique est configurée ; son accessibilité effective depuis Iziship reste à vérifier lors du raccordement.';
		}
		if ( ! $last_sync ) {
			$warnings[] = 'Aucune écriture de suivi externe observée. La connexion n’est pas encore confirmée.';
		}
		return array(
			'method' => 'tracking_number',
			'last_sync' => $last_sync ?: null,
			'external_updates' => (int) get_option( 'bcrm_iziship_external_updates', 0 ),
			'site_public_https' => $public,
			'guide_url' => 'https://wiki.iziship.co/article/01-Votre-boutique-en-ligne/02-WooCommerce/01-guide-connexion',
			'rest_keys_url' => admin_url( 'admin.php?page=wc-settings&tab=advanced&section=keys' ),
			'fields' => array(
				array( 'name' => 'tracking_number', 'description' => 'Métadonnée WooCommerce : numéro de suivi écrit par Iziship via une clé REST dédiée en lecture/écriture.' ),
				array( 'name' => '_wc_shipment_tracking_items', 'description' => 'Lecture facultative si une extension de suivi existante renseigne déjà ce tableau ; aucune extension payante requise.' ),
				array( 'name' => '_bcrm_tracking_*', 'description' => 'Transporteur, URL HTTPS, dates et statut de livraison renseignés dans ce CRM ; distincts du statut commercial WooCommerce.' ),
			),
			'warnings' => $warnings,
		);
	}

	/**
	 * Woo fires this after checking the tracking nonce, order number and billing
	 * email. Keep the grant in memory, scoped to the exact verified order. Repeat
	 * the nonce/email checks defensively; posted values alone never grant access.
	 */
	public static function authorize_tracked_order( $order_id ) {
		$nonce = $_REQUEST['woocommerce-order-tracking-nonce'] ?? ( $_REQUEST['_wpnonce'] ?? '' );
		$email = $_REQUEST['order_email'] ?? '';
		if ( ! is_string( $nonce ) || ! is_string( $email ) || ! isset( $_REQUEST['orderid'] ) || ! is_scalar( $_REQUEST['orderid'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( $nonce, 'woocommerce-order_tracking' ) ) {
			return;
		}
		$email = sanitize_email( wp_unslash( $email ) );
		$order = wc_get_order( absint( $order_id ) );
		if ( $order instanceof WC_Order && '' !== $email && strtolower( $order->get_billing_email() ) === strtolower( $email ) ) {
			self::$tracked_order_ids[ $order->get_id() ] = true;
		}
	}

	public static function customer_tracking( $order ) {
		if ( ! $order instanceof WC_Order || ! self::can_view_order( $order ) ) {
			return;
		}
		self::render_tracking( $order, false );
	}

	public static function email_tracking( $order, $sent_to_admin, $plain_text, $email ) {
		if ( $sent_to_admin || ! $order instanceof WC_Order ) {
			return;
		}
		self::render_tracking( $order, (bool) $plain_text );
	}

	private static function render_tracking( $order, $plain_text ) {
		$tracking = self::get( $order );
		if ( '' === $tracking['number'] && 'pending' === $tracking['status'] ) {
			return;
		}
		$lines = array( 'Statut : ' . $tracking['status_label'] );
		if ( $tracking['number'] ) {
			$lines[] = 'Numéro de suivi : ' . $tracking['number'];
		}
		if ( $tracking['carrier'] ) {
			$lines[] = 'Transporteur : ' . $tracking['carrier'];
		}
		if ( $tracking['shipped_at'] ) {
			$lines[] = 'Date d’expédition : ' . self::display_date( $tracking['shipped_at'] );
		}
		if ( $tracking['delivered_at'] ) {
			$lines[] = 'Date de livraison renseignée : ' . self::display_date( $tracking['delivered_at'] );
		}
		if ( $plain_text ) {
			echo "\n" . esc_html( 'Suivi de votre colis' ) . "\n";
			foreach ( $lines as $line ) {
				echo wp_strip_all_tags( $line ) . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- plain-text email, no HTML context.
			}
			if ( $tracking['url'] ) {
				echo 'Suivre le colis : ' . esc_url_raw( $tracking['url'], array( 'https' ) ) . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- plain-text email.
			}
			return;
		}
		echo '<section class="bcrm-order-tracking"><h2>' . esc_html( 'Suivi de votre colis' ) . '</h2>';
		foreach ( $lines as $line ) {
			echo '<p>' . esc_html( $line ) . '</p>';
		}
		if ( $tracking['url'] ) {
			echo '<p><a href="' . esc_url( $tracking['url'], array( 'https' ) ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( 'Suivre mon colis' ) . '</a></p>';
		}
		echo '</section>';
	}

	private static function can_view_order( $order ) {
		if ( isset( self::$tracked_order_ids[ $order->get_id() ] ) ) {
			return true;
		}
		if ( current_user_can( 'manage_woocommerce' ) || current_user_can( 'manage_options' ) ) {
			return true;
		}
		if ( get_current_user_id() && (int) $order->get_user_id() === get_current_user_id() ) {
			return true;
		}
		$key = isset( $_GET['key'] ) && is_string( $_GET['key'] ) ? wc_clean( wp_unslash( $_GET['key'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- the order key is the WooCommerce guest-view credential.
		return '' !== $key && '' !== $order->get_order_key() && hash_equals( $order->get_order_key(), $key );
	}

	private static function empty_tracking() {
		return array( 'number' => '', 'carrier' => '', 'url' => '', 'status' => 'pending', 'status_label' => self::STATUSES['pending'], 'shipped_at' => '', 'delivered_at' => '', 'source' => '', 'status_is_manual' => false, 'observed_at' => '' );
	}

	private static function plugin_tracking( $order ) {
		$items = $order->get_meta( '_wc_shipment_tracking_items', true );
		if ( ! is_array( $items ) ) {
			return null;
		}
		foreach ( array_reverse( $items ) as $item ) {
			if ( ! is_array( $item ) || empty( $item['tracking_number'] ) ) {
				continue;
			}
			$shipped_at = '';
			if ( isset( $item['date_shipped'] ) && is_numeric( $item['date_shipped'] ) && (int) $item['date_shipped'] > 0 ) {
				$shipped_at = wp_date( 'Y-m-d', (int) $item['date_shipped'], wp_timezone() );
			}
			return array(
				'number' => self::text( $item['tracking_number'], 120 ),
				'carrier' => self::text( ! empty( $item['custom_tracking_provider'] ) ? $item['custom_tracking_provider'] : ( $item['tracking_provider'] ?? '' ), 120 ),
				'url' => self::safe_url( $item['custom_tracking_link'] ?? '' ),
				'shipped_at' => $shipped_at,
			);
		}
		return null;
	}

	private static function has_physical_items( $order ) {
		foreach ( $order->get_items() as $item ) {
			$product = $item->get_product();
			if ( ! $product || ! $product->is_virtual() ) {
				return true;
			}
		}
		return false;
	}

	private static function safe_url( $value ) {
		if ( ! is_string( $value ) || '' === trim( $value ) || strlen( $value ) > 2048 || preg_match( '/[\x00-\x20\x7f]/', $value ) ) {
			return '';
		}
		$parts = wp_parse_url( $value );
		if ( ! is_array( $parts ) || 'https' !== strtolower( $parts['scheme'] ?? '' ) || empty( $parts['host'] ) || isset( $parts['user'] ) || isset( $parts['pass'] ) ) {
			return '';
		}
		if ( ! filter_var( $value, FILTER_VALIDATE_URL ) ) {
			return '';
		}
		return esc_url_raw( $value, array( 'https' ) );
	}

	private static function text( $value, $limit ) {
		if ( ! is_scalar( $value ) ) {
			return '';
		}
		$value = sanitize_text_field( (string) $value );
		return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $limit ) : substr( $value, 0, $limit );
	}

	private static function parse_date( $value ) {
		if ( '' === $value ) {
			return '';
		}
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}(?:T\d{2}:\d{2}(?::\d{2})?(?:Z|[+-]\d{2}:\d{2})?)?$/', $value ) ) {
			return false;
		}
		try {
			$date = new DateTimeImmutable( $value, wp_timezone() );
			$errors = DateTimeImmutable::getLastErrors();
			if ( $errors && ( $errors['warning_count'] || $errors['error_count'] ) ) {
				return false;
			}
			return $date->setTimezone( wp_timezone() )->format( 'Y-m-d' );
		} catch ( Exception $e ) {
			return false;
		}
	}

	private static function stored_date( $value ) {
		return is_string( $value ) ? ( self::parse_date( $value ) ?: '' ) : '';
	}

	private static function display_date( $value ) {
		$date = DateTimeImmutable::createFromFormat( '!Y-m-d', $value, wp_timezone() );
		return $date ? $date->format( 'd/m/Y' ) : $value;
	}

	private static function site_public_https() {
		$parts = wp_parse_url( home_url( '/' ) );
		if ( ! is_array( $parts ) || 'https' !== strtolower( $parts['scheme'] ?? '' ) ) {
			return false;
		}
		$host = strtolower( rtrim( $parts['host'] ?? '', '.' ) );
		if ( '' === $host || 'localhost' === $host || false === strpos( $host, '.' ) || preg_match( '/\.(local|localhost|test|invalid|example|internal)$/', $host ) ) {
			return false;
		}
		$ip = trim( $host, '[]' );
		if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			return false !== filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE );
		}
		return true;
	}
}
