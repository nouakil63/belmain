<?php
defined( 'ABSPATH' ) || exit;

/**
 * Expéditions : historique (table bm_shipment_events), page « Expéditions »,
 * webhook Easyship, mise à jour automatique des statuts de commande.
 */
class BM_Shipments {

	public static function init(): void {
		add_action( 'rest_api_init', array( __CLASS__, 'rest_routes' ) );
		add_action( 'admin_post_bm_bulk_shipments', array( __CLASS__, 'handle_bulk' ) );
	}

	/* ----------------------------------------------------------- Événements */

	public static function add_event( int $order_id, string $status, string $message = '', string $source = 'manual', string $location = '', ?string $event_at = null, ?string $shipment_id = null ): void {
		global $wpdb;
		$wpdb->insert( $wpdb->prefix . 'bm_shipment_events', array(
			'order_id'    => $order_id,
			'shipment_id' => $shipment_id,
			'status'      => $status,
			'message'     => $message,
			'location'    => $location,
			'source'      => $source,
			'event_at'    => $event_at ?: current_time( 'mysql' ),
			'created_at'  => current_time( 'mysql' ),
		) );
	}

	public static function get_events( int $order_id, int $limit = 20 ): array {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}bm_shipment_events WHERE order_id = %d ORDER BY event_at DESC, id DESC LIMIT %d",
			$order_id, $limit
		) ) ?: array();
	}

	/**
	 * Point d'entrée unique pour changer le statut de suivi : journalise, met à jour la commande.
	 */
	public static function set_tracking_status( WC_Order $order, string $status, string $source = 'manual', string $message = '', string $location = '', ?string $event_at = null ): void {
		$previous = $order->get_meta( '_bm_tracking_status' ) ?: 'none';
		$order->update_meta_data( '_bm_tracking_status', $status );

		if ( $previous !== $status ) {
			self::add_event( $order->get_id(), $status, $message, $source, $location, $event_at, $order->get_meta( '_bm_easyship_shipment_id' ) ?: null );
		}

		// Statut WooCommerce piloté par le suivi.
		$wc_status = $order->get_status();
		if ( in_array( $status, array( 'in_transit', 'out_for_delivery', 'label_created' ), true ) && 'processing' === $wc_status && 'label_created' !== $status ) {
			$order->update_meta_data( '_bm_shipped_at', current_time( 'mysql' ) );
			$order->set_status( 'bm-expediee', 'Suivi : colis pris en charge.' );
		} elseif ( 'delivered' === $status && in_array( $wc_status, array( 'processing', 'bm-expediee' ), true ) ) {
			$order->update_meta_data( '_bm_delivered_at', current_time( 'mysql' ) );
			$order->set_status( 'bm-livree', 'Suivi : colis livré.' );
		} elseif ( 'returned' === $status && in_array( $wc_status, array( 'bm-expediee', 'bm-livree' ), true ) ) {
			$order->set_status( 'bm-retour', 'Suivi : colis retourné à l\'expéditeur.' );
		} elseif ( 'exception' === $status ) {
			$order->add_order_note( 'Suivi : incident de livraison signalé' . ( $message ? ' — ' . $message : '' ) . '.' );
		}
	}

	/* --------------------------------------------------------------- Webhook */

	public static function rest_routes(): void {
		register_rest_route( 'belmains/v1', '/easyship/webhook', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'webhook' ),
			'permission_callback' => array( __CLASS__, 'webhook_auth' ),
		) );
	}

	/**
	 * Authentification du webhook : signature HMAC-SHA256 du corps brut avec la clé secrète,
	 * ou clé passée en paramètre `key` (fallback). À aligner avec la doc Easyship.
	 */
	public static function webhook_auth( WP_REST_Request $request ): bool {
		$secret = (string) bm_setting( 'easyship_webhook_key' );
		if ( '' === $secret ) {
			return false;
		}
		$sig = $request->get_header( 'x-easyship-signature' ) ?: $request->get_header( 'x-signature' );
		if ( $sig ) {
			$expected = hash_hmac( 'sha256', $request->get_body(), $secret );
			return hash_equals( $expected, (string) $sig ) || hash_equals( base64_encode( hex2bin( $expected ) ), (string) $sig );
		}
		$key = (string) $request->get_param( 'key' );
		return '' !== $key && hash_equals( $secret, $key );
	}

	public static function webhook( WP_REST_Request $request ) {
		$data  = $request->get_json_params() ?: array();
		$event = (string) ( $data['event_type'] ?? ( $data['event'] ?? '' ) );
		$ship  = $data['shipment'] ?? $data;
		bm_log( 'Webhook Easyship reçu', array( 'event' => $event ) );

		$order = self::find_order_for_shipment( $ship );
		if ( ! $order ) {
			return new WP_REST_Response( array( 'ok' => false, 'reason' => 'order_not_found' ), 202 );
		}

		// Checkpoints détaillés (si fournis) → historique.
		$checkpoints = $data['tracking']['checkpoints'] ?? ( $ship['tracking']['checkpoints'] ?? array() );
		foreach ( (array) $checkpoints as $cp ) {
			$status = BM_Easyship::normalize_status( (string) ( $cp['tag'] ?? ( $cp['status'] ?? '' ) ) );
			$at     = ! empty( $cp['checkpoint_time'] ) ? gmdate( 'Y-m-d H:i:s', strtotime( $cp['checkpoint_time'] ) ) : null;
			self::add_event( $order->get_id(), 'none' === $status ? 'in_transit' : $status, (string) ( $cp['message'] ?? '' ), 'webhook', (string) ( $cp['location'] ?? '' ), $at, $order->get_meta( '_bm_easyship_shipment_id' ) ?: null );
		}

		BM_Easyship::apply_shipment_data( $order, is_array( $ship ) ? $ship : array(), 'webhook' );
		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}

	public static function find_order_for_shipment( $ship ): ?WC_Order {
		if ( ! is_array( $ship ) ) {
			return null;
		}
		if ( ! empty( $ship['easyship_shipment_id'] ) ) {
			$found = wc_get_orders( array( 'limit' => 1, 'meta_key' => '_bm_easyship_shipment_id', 'meta_value' => sanitize_text_field( $ship['easyship_shipment_id'] ) ) );
			if ( $found ) {
				return $found[0];
			}
		}
		$num = $ship['platform_order_number'] ?? ( $ship['order_data']['platform_order_number'] ?? '' );
		if ( $num ) {
			$order = wc_get_order( (int) ltrim( (string) $num, '#' ) );
			if ( $order ) {
				return $order;
			}
		}
		return null;
	}

	/* ------------------------------------------------------------ Page admin */

	public static function query_orders( string $filter, int $paged = 1, int $per_page = 25 ): array {
		$args = array(
			'limit'    => $per_page,
			'page'     => $paged,
			'orderby'  => 'date',
			'order'    => 'DESC',
			'paginate' => true,
		);
		switch ( $filter ) {
			case 'to_ship':
				$args['status']     = array( 'processing' );
				$args['meta_query'] = array( 'relation' => 'OR',
					array( 'key' => '_bm_tracking_status', 'compare' => 'NOT EXISTS' ),
					array( 'key' => '_bm_tracking_status', 'value' => array( 'none', 'pending', 'label_created' ), 'compare' => 'IN' ),
				);
				break;
			case 'in_transit':
				$args['status']     = array( 'processing', 'bm-expediee' );
				$args['meta_query'] = array( array( 'key' => '_bm_tracking_status', 'value' => array( 'in_transit', 'out_for_delivery' ), 'compare' => 'IN' ) );
				break;
			case 'exception':
				$args['status']     = bm_paid_statuses();
				$args['meta_query'] = array( array( 'key' => '_bm_tracking_status', 'value' => array( 'exception', 'returned' ), 'compare' => 'IN' ) );
				break;
			case 'delivered':
				$args['status'] = array( 'bm-livree', 'completed' );
				break;
			default:
				$args['status'] = bm_paid_statuses();
		}
		$res = wc_get_orders( $args );
		return array( 'orders' => $res->orders, 'total' => $res->total, 'pages' => $res->max_num_pages );
	}

	public static function render_page(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( 'Accès refusé.' );
		}
		$filter = sanitize_key( $_GET['filter'] ?? 'to_ship' );
		$paged  = max( 1, absint( $_GET['paged'] ?? 1 ) );
		$q      = self::query_orders( $filter, $paged );
		$tabs   = array( 'to_ship' => 'À expédier', 'in_transit' => 'En transit', 'exception' => 'Incidents', 'delivered' => 'Livrées', 'all' => 'Toutes' );
		?>
		<div class="wrap bm-wrap">
			<h1>Expéditions</h1>
			<?php if ( isset( $_GET['bm_done'] ) ) : ?><div class="notice notice-success is-dismissible"><p><?php echo esc_html( sprintf( '%d expédition(s) traitée(s).', absint( $_GET['bm_done'] ) ) ); ?></p></div><?php endif; ?>
			<?php if ( isset( $_GET['bm_err'] ) ) : ?><div class="notice notice-error is-dismissible"><p><?php echo esc_html( sanitize_text_field( wp_unslash( $_GET['bm_err'] ) ) ); ?></p></div><?php endif; ?>
			<ul class="subsubsub">
				<?php foreach ( $tabs as $k => $label ) : ?>
					<li><a href="<?php echo esc_url( bm_admin_url( 'belmains-shipments', array( 'filter' => $k ) ) ); ?>" class="<?php echo $k === $filter ? 'current' : ''; ?>"><?php echo esc_html( $label ); ?></a> | </li>
				<?php endforeach; ?>
			</ul>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="bm_bulk_shipments">
				<input type="hidden" name="filter" value="<?php echo esc_attr( $filter ); ?>">
				<?php wp_nonce_field( 'bm_bulk_shipments' ); ?>
				<div class="tablenav top">
					<select name="bulk">
						<option value="">Actions groupées</option>
						<option value="create">Créer sur Easyship</option>
						<option value="sync">Rafraîchir le suivi</option>
						<option value="shipped">Marquer « Expédiée »</option>
						<option value="delivered">Marquer « Livrée »</option>
					</select>
					<button class="button">Appliquer</button>
					<span class="displaying-num" style="float:right"><?php echo esc_html( $q['total'] ); ?> commande(s)</span>
				</div>
				<table class="wp-list-table widefat fixed striped">
					<thead><tr>
						<td class="check-column"><input type="checkbox" id="bm-all"></td>
						<th>Commande</th><th>Client</th><th>Destination</th><th>Statut</th><th>Suivi</th><th>Montant</th><th>Date</th>
					</tr></thead>
					<tbody>
					<?php if ( ! $q['orders'] ) : ?>
						<tr><td colspan="8">Rien à afficher.</td></tr>
					<?php endif; ?>
					<?php foreach ( $q['orders'] as $order ) : /** @var WC_Order $order */ ?>
						<tr>
							<th class="check-column"><input type="checkbox" name="ids[]" value="<?php echo esc_attr( $order->get_id() ); ?>"></th>
							<td><a href="<?php echo esc_url( $order->get_edit_order_url() ); ?>"><strong>#<?php echo esc_html( $order->get_order_number() ); ?></strong></a></td>
							<td><?php echo esc_html( $order->get_formatted_billing_full_name() ); ?><br><small><?php echo esc_html( $order->get_billing_email() ); ?></small></td>
							<td><?php echo esc_html( $order->get_shipping_postcode() . ' ' . $order->get_shipping_city() . ' · ' . $order->get_shipping_country() ); ?></td>
							<td><?php echo esc_html( wc_get_order_status_name( $order->get_status() ) ); ?></td>
							<td><?php echo BM_Orders::tracking_summary_html( $order ); ?></td>
							<td><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></td>
							<td><?php echo esc_html( bm_format_date( $order->get_date_created() ) ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<?php if ( $q['pages'] > 1 ) : ?>
					<div class="tablenav bottom"><div class="tablenav-pages"><?php
						echo paginate_links( array(
							'base'    => add_query_arg( 'paged', '%#%' ),
							'total'   => $q['pages'],
							'current' => $paged,
						) );
					?></div></div>
				<?php endif; ?>
			</form>
		</div>
		<?php
	}

	public static function handle_bulk(): void {
		check_admin_referer( 'bm_bulk_shipments' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( 'Accès refusé.' );
		}
		$ids    = array_map( 'absint', (array) ( $_POST['ids'] ?? array() ) );
		$bulk   = sanitize_key( $_POST['bulk'] ?? '' );
		$filter = sanitize_key( $_POST['filter'] ?? 'to_ship' );
		$done   = 0;
		$err    = '';
		foreach ( $ids as $id ) {
			$order = wc_get_order( $id );
			if ( ! $order ) {
				continue;
			}
			switch ( $bulk ) {
				case 'create':
					$r = BM_Easyship::create_shipment( $order );
					if ( is_wp_error( $r ) ) { $err = $r->get_error_message(); } else { $done++; }
					break;
				case 'sync':
					$r = BM_Easyship::sync_tracking( $order );
					if ( is_wp_error( $r ) ) { $err = $r->get_error_message(); } else { $done++; }
					break;
				case 'shipped':
					self::set_tracking_status( $order, 'in_transit', 'manual', 'Marquée expédiée (action groupée)' );
					$order->save();
					$done++;
					break;
				case 'delivered':
					self::set_tracking_status( $order, 'delivered', 'manual', 'Marquée livrée (action groupée)' );
					$order->save();
					$done++;
					break;
			}
		}
		$args = array( 'filter' => $filter, 'bm_done' => $done );
		if ( $err ) {
			$args['bm_err'] = $err;
		}
		wp_safe_redirect( bm_admin_url( 'belmains-shipments', $args ) );
		exit;
	}
}
