<?php
defined( 'ABSPATH' ) || exit;

/**
 * Commandes : statuts Belmains, colonne « Suivi », metabox expédition,
 * informations de suivi dans les e-mails et l'espace client.
 */
class BM_Orders {

	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'register_statuses' ) );
		add_filter( 'wc_order_statuses', array( __CLASS__, 'add_statuses' ) );
		add_filter( 'woocommerce_order_is_paid_statuses', array( __CLASS__, 'paid_statuses' ) );
		add_filter( 'woocommerce_reports_order_statuses', array( __CLASS__, 'report_statuses' ) );
		add_filter( 'woocommerce_valid_order_statuses_for_payment_complete', array( __CLASS__, 'report_statuses' ) );
		add_filter( 'bulk_actions-edit-shop_order', array( __CLASS__, 'bulk_actions' ) );
		add_filter( 'bulk_actions-woocommerce_page_wc-orders', array( __CLASS__, 'bulk_actions' ) );

		// Colonne « Suivi » (liste classique + HPOS).
		add_filter( 'manage_edit-shop_order_columns', array( __CLASS__, 'columns' ), 20 );
		add_filter( 'manage_woocommerce_page_wc-orders_columns', array( __CLASS__, 'columns' ), 20 );
		add_action( 'manage_shop_order_posts_custom_column', array( __CLASS__, 'column_content' ), 10, 2 );
		add_action( 'manage_woocommerce_page_wc-orders_custom_column', array( __CLASS__, 'column_content' ), 10, 2 );

		add_action( 'add_meta_boxes', array( __CLASS__, 'meta_boxes' ) );
		add_action( 'woocommerce_process_shop_order_meta', array( __CLASS__, 'save_meta_box' ), 20 );

		// Client : e-mails et espace « Mon compte ».
		add_action( 'woocommerce_email_order_meta', array( __CLASS__, 'email_tracking' ), 10, 3 );
		add_action( 'woocommerce_order_details_after_order_table', array( __CLASS__, 'account_tracking' ) );
		add_filter( 'woocommerce_my_account_my_orders_columns', array( __CLASS__, 'account_columns' ) );
		add_action( 'woocommerce_my_account_my_orders_column_bm-suivi', array( __CLASS__, 'account_column' ) );
		add_action( 'woocommerce_order_status_bm-expediee', array( __CLASS__, 'email_shipped' ), 10, 2 );
		add_action( 'woocommerce_order_status_bm-livree', array( __CLASS__, 'email_delivered' ), 10, 2 );

		// AJAX (boutons de la metabox).
		add_action( 'wp_ajax_bm_shipment_action', array( __CLASS__, 'ajax_shipment_action' ) );
	}

	/* --------------------------------------------------------------- Statuts */

	public static function register_statuses(): void {
		$statuses = array(
			'wc-bm-expediee' => array( 'Expédiée', 'Expédiée <span class="count">(%s)</span>' ),
			'wc-bm-livree'   => array( 'Livrée', 'Livrée <span class="count">(%s)</span>' ),
			'wc-bm-retour'   => array( 'Retour en cours', 'Retour en cours <span class="count">(%s)</span>' ),
		);
		foreach ( $statuses as $slug => $labels ) {
			register_post_status( $slug, array(
				'label'                     => $labels[0],
				'public'                    => true,
				'exclude_from_search'       => false,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				'label_count'               => _n_noop( $labels[1], $labels[1], 'belmains-crm' ),
			) );
		}
	}

	public static function add_statuses( array $statuses ): array {
		$new = array();
		foreach ( $statuses as $k => $v ) {
			$new[ $k ] = $v;
			if ( 'wc-processing' === $k ) {
				$new['wc-bm-expediee'] = 'Expédiée';
				$new['wc-bm-livree']   = 'Livrée';
				$new['wc-bm-retour']   = 'Retour en cours';
			}
		}
		return $new;
	}

	public static function paid_statuses( array $s ): array {
		return array_unique( array_merge( $s, array( 'bm-expediee', 'bm-livree', 'bm-retour' ) ) );
	}

	public static function report_statuses( $s ): array {
		return array_unique( array_merge( (array) $s, array( 'bm-expediee', 'bm-livree' ) ) );
	}

	public static function bulk_actions( array $actions ): array {
		$actions['mark_bm-expediee'] = 'Passer en « Expédiée »';
		$actions['mark_bm-livree']   = 'Passer en « Livrée »';
		return $actions;
	}

	/* --------------------------------------------------------------- Colonne */

	public static function columns( array $cols ): array {
		$new = array();
		foreach ( $cols as $k => $v ) {
			$new[ $k ] = $v;
			if ( 'order_status' === $k ) {
				$new['bm_tracking'] = 'Suivi';
			}
		}
		return $new;
	}

	public static function column_content( $column, $order_or_id ): void {
		if ( 'bm_tracking' !== $column ) {
			return;
		}
		$order = $order_or_id instanceof WC_Order ? $order_or_id : wc_get_order( $order_or_id );
		if ( ! $order ) {
			return;
		}
		echo self::tracking_summary_html( $order );
	}

	public static function tracking_summary_html( WC_Order $order, bool $with_link = true ): string {
		$status  = $order->get_meta( '_bm_tracking_status' ) ?: 'none';
		$number  = $order->get_meta( '_bm_tracking_number' );
		$carrier = $order->get_meta( '_bm_carrier' );
		$url     = $order->get_meta( '_bm_tracking_url' );
		if ( 'none' === $status && ! $number ) {
			if ( in_array( $order->get_status(), array( 'processing' ), true ) ) {
				return bm_badge( 'pending' );
			}
			return '<span class="bm-muted">—</span>';
		}
		$html = bm_badge( $status );
		if ( $number ) {
			$num  = esc_html( $number );
			$html .= '<br><small>' . ( $carrier ? esc_html( $carrier ) . ' · ' : '' );
			$html .= ( $with_link && $url ) ? '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . $num . '</a>' : $num;
			$html .= '</small>';
		}
		return $html;
	}

	/* --------------------------------------------------------------- Metabox */

	public static function meta_boxes(): void {
		$screen = class_exists( '\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController' )
			&& wc_get_container()->get( \Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class )->custom_orders_table_usage_is_enabled()
			? wc_get_page_screen_id( 'shop-order' )
			: 'shop_order';
		add_meta_box( 'bm_shipment', 'Belmains — Expédition & suivi', array( __CLASS__, 'render_meta_box' ), $screen, 'side', 'high' );
		add_meta_box( 'bm_returns', 'Belmains — Retours SAV', array( 'BM_Returns', 'render_order_meta_box' ), $screen, 'normal', 'default' );
	}

	public static function render_meta_box( $post_or_order ): void {
		$order = $post_or_order instanceof WC_Order ? $post_or_order : wc_get_order( $post_or_order->ID );
		if ( ! $order ) {
			return;
		}
		wp_nonce_field( 'bm_shipment_save', 'bm_shipment_nonce' );
		$status  = $order->get_meta( '_bm_tracking_status' ) ?: 'none';
		$number  = $order->get_meta( '_bm_tracking_number' );
		$carrier = $order->get_meta( '_bm_carrier' );
		$url     = $order->get_meta( '_bm_tracking_url' );
		$es_id   = $order->get_meta( '_bm_easyship_shipment_id' );
		$label   = $order->get_meta( '_bm_label_url' );
		?>
		<div class="bm-mb" data-order="<?php echo esc_attr( $order->get_id() ); ?>">
			<p class="bm-mb-status"><?php echo bm_badge( $status ); ?></p>
			<p><label>Statut de suivi<br>
				<select name="bm_tracking_status" class="widefat">
					<?php foreach ( bm_tracking_statuses() as $k => $s ) : ?>
						<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $k, $status ); ?>><?php echo esc_html( $s['label'] ); ?></option>
					<?php endforeach; ?>
				</select></label></p>
			<p><label>Transporteur<br><input type="text" name="bm_carrier" class="widefat" value="<?php echo esc_attr( $carrier ); ?>" placeholder="Colissimo, Chronopost…"></label></p>
			<p><label>N° de suivi<br><input type="text" name="bm_tracking_number" class="widefat" value="<?php echo esc_attr( $number ); ?>"></label></p>
			<p><label>URL de suivi<br><input type="url" name="bm_tracking_url" class="widefat" value="<?php echo esc_attr( $url ); ?>"></label></p>
			<?php if ( $es_id ) : ?>
				<p class="bm-muted">Easyship : <code><?php echo esc_html( $es_id ); ?></code></p>
			<?php endif; ?>
			<?php if ( $label ) : ?>
				<p><a class="button" href="<?php echo esc_url( $label ); ?>" target="_blank" rel="noopener">Étiquette (PDF)</a></p>
			<?php endif; ?>
			<div class="bm-mb-actions">
				<?php if ( ! $es_id ) : ?>
					<button type="button" class="button button-primary bm-act" data-act="create" <?php disabled( ! BM_Easyship::is_configured() ); ?>>Créer sur Easyship</button>
				<?php else : ?>
					<?php if ( ! $label ) : ?><button type="button" class="button bm-act" data-act="label">Acheter l'étiquette</button><?php endif; ?>
					<button type="button" class="button bm-act" data-act="sync">Rafraîchir le suivi</button>
				<?php endif; ?>
				<span class="bm-act-msg"></span>
			</div>
			<p class="bm-muted" style="margin-top:8px">Enregistrez la commande pour valider une saisie manuelle. Les boutons Easyship agissent immédiatement.</p>
			<?php
			$events = BM_Shipments::get_events( $order->get_id(), 8 );
			if ( $events ) :
				?>
				<h4 style="margin:12px 0 4px">Historique</h4>
				<ul class="bm-timeline">
					<?php foreach ( $events as $e ) : ?>
						<li><time><?php echo esc_html( bm_format_date( $e->event_at ) ); ?></time> <?php echo bm_badge( $e->status ); ?> <?php echo $e->message ? '<span>' . esc_html( $e->message ) . '</span>' : ''; ?></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
		<?php
	}

	public static function save_meta_box( $order_id ): void {
		if ( ! isset( $_POST['bm_shipment_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['bm_shipment_nonce'] ), 'bm_shipment_save' ) ) {
			return;
		}
		$order = wc_get_order( $order_id );
		if ( ! $order || ! current_user_can( 'edit_shop_orders' ) ) {
			return;
		}
		$number  = sanitize_text_field( wp_unslash( $_POST['bm_tracking_number'] ?? '' ) );
		$carrier = sanitize_text_field( wp_unslash( $_POST['bm_carrier'] ?? '' ) );
		$url     = esc_url_raw( wp_unslash( $_POST['bm_tracking_url'] ?? '' ) );
		$status  = sanitize_key( $_POST['bm_tracking_status'] ?? 'none' );

		$order->update_meta_data( '_bm_tracking_number', $number );
		$order->update_meta_data( '_bm_carrier', $carrier );
		$order->update_meta_data( '_bm_tracking_url', $url ?: self::guess_tracking_url( $carrier, $number ) );

		if ( array_key_exists( $status, bm_tracking_statuses() ) && $status !== ( $order->get_meta( '_bm_tracking_status' ) ?: 'none' ) ) {
			BM_Shipments::set_tracking_status( $order, $status, 'manual', 'Saisie manuelle' );
		}
		$order->save();
	}

	/**
	 * URL de suivi publique pour les transporteurs courants (si non fournie).
	 */
	public static function guess_tracking_url( string $carrier, string $number ): string {
		if ( ! $number ) {
			return '';
		}
		$c = strtolower( $carrier );
		if ( str_contains( $c, 'colissimo' ) || str_contains( $c, 'la poste' ) ) {
			return 'https://www.laposte.fr/outils/suivre-vos-envois?code=' . rawurlencode( $number );
		}
		if ( str_contains( $c, 'chronopost' ) ) {
			return 'https://www.chronopost.fr/tracking-no-cms/suivi-page?listeNumerosLT=' . rawurlencode( $number );
		}
		if ( str_contains( $c, 'mondial' ) ) {
			return 'https://www.mondialrelay.fr/suivi-de-colis/?numeroExpedition=' . rawurlencode( $number );
		}
		if ( str_contains( $c, 'ups' ) ) {
			return 'https://www.ups.com/track?tracknum=' . rawurlencode( $number );
		}
		if ( str_contains( $c, 'dhl' ) ) {
			return 'https://www.dhl.com/fr-fr/home/tracking.html?tracking-id=' . rawurlencode( $number );
		}
		return '';
	}

	/* ------------------------------------------------------------------ AJAX */

	public static function ajax_shipment_action(): void {
		check_ajax_referer( 'bm_crm', 'nonce' );
		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			wp_send_json_error( array( 'message' => 'Accès refusé.' ) );
		}
		$order = wc_get_order( absint( $_POST['order_id'] ?? 0 ) );
		$act   = sanitize_key( $_POST['act'] ?? '' );
		if ( ! $order ) {
			wp_send_json_error( array( 'message' => 'Commande introuvable.' ) );
		}
		switch ( $act ) {
			case 'create':
				$res = BM_Easyship::create_shipment( $order );
				break;
			case 'label':
				$res = BM_Easyship::buy_label( $order );
				break;
			case 'sync':
				$res = BM_Easyship::sync_tracking( $order );
				break;
			default:
				$res = new WP_Error( 'bm_act', 'Action inconnue.' );
		}
		if ( is_wp_error( $res ) ) {
			wp_send_json_error( array( 'message' => $res->get_error_message() ) );
		}
		wp_send_json_success( array(
			'message' => 'OK — ' . bm_tracking_label( $order->get_meta( '_bm_tracking_status' ) ?: 'pending' ),
			'reload'  => true,
		) );
	}

	/* --------------------------------------------------------------- Client */

	public static function email_tracking( $order, $sent_to_admin, $plain_text ): void {
		$number = $order->get_meta( '_bm_tracking_number' );
		if ( ! $number ) {
			return;
		}
		$carrier = $order->get_meta( '_bm_carrier' );
		$url     = $order->get_meta( '_bm_tracking_url' );
		if ( $plain_text ) {
			echo "\nSuivi de colis : " . ( $carrier ? $carrier . ' ' : '' ) . $number . ( $url ? ' — ' . $url : '' ) . "\n";
			return;
		}
		echo '<h2>Suivi de votre colis</h2><p>';
		echo $carrier ? esc_html( $carrier ) . ' — ' : '';
		echo $url ? '<a href="' . esc_url( $url ) . '">' . esc_html( $number ) . '</a>' : esc_html( $number );
		echo '</p>';
	}

	public static function account_tracking( $order ): void {
		if ( ! $order instanceof WC_Order ) {
			return;
		}
		$status = $order->get_meta( '_bm_tracking_status' ) ?: 'none';
		$number = $order->get_meta( '_bm_tracking_number' );
		if ( 'none' === $status && ! $number ) {
			return;
		}
		$url = $order->get_meta( '_bm_tracking_url' );
		echo '<section class="bm-account-tracking"><h2>Suivi de livraison</h2>';
		echo '<p><strong>' . esc_html( bm_tracking_label( $status ) ) . '</strong>';
		if ( $number ) {
			echo ' — ' . esc_html( $order->get_meta( '_bm_carrier' ) ) . ' ';
			echo $url ? '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html( $number ) . '</a>' : esc_html( $number );
		}
		echo '</p>';
		$events = BM_Shipments::get_events( $order->get_id(), 10 );
		if ( $events ) {
			echo '<ul class="bm-account-timeline">';
			foreach ( $events as $e ) {
				echo '<li><time>' . esc_html( bm_format_date( $e->event_at ) ) . '</time> — ' . esc_html( bm_tracking_label( $e->status ) );
				echo $e->location ? ' (' . esc_html( $e->location ) . ')' : '';
				echo '</li>';
			}
			echo '</ul>';
		}
		echo '</section>';
	}

	public static function account_columns( array $cols ): array {
		$new = array();
		foreach ( $cols as $k => $v ) {
			$new[ $k ] = $v;
			if ( 'order-status' === $k ) {
				$new['bm-suivi'] = 'Suivi';
			}
		}
		return $new;
	}

	public static function account_column( $order ): void {
		$status = $order->get_meta( '_bm_tracking_status' ) ?: 'none';
		$url    = $order->get_meta( '_bm_tracking_url' );
		$label  = 'none' === $status ? '—' : bm_tracking_label( $status );
		echo $url ? '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html( $label ) . '</a>' : esc_html( $label );
	}

	/**
	 * E-mails transactionnels « Expédiée » / « Livrée », habillés avec le gabarit WooCommerce.
	 */
	public static function email_shipped( $order_id, $order = null ): void {
		$order = $order ?: wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}
		$number  = $order->get_meta( '_bm_tracking_number' );
		$url     = $order->get_meta( '_bm_tracking_url' );
		$carrier = $order->get_meta( '_bm_carrier' );
		$body    = '<p>Bonne nouvelle : votre commande n° ' . esc_html( $order->get_order_number() ) . ' vient d\'être expédiée.</p>';
		if ( $number ) {
			$body .= '<p>Suivi ' . ( $carrier ? esc_html( $carrier ) . ' : ' : ': ' );
			$body .= $url ? '<a href="' . esc_url( $url ) . '">' . esc_html( $number ) . '</a>' : esc_html( $number );
			$body .= '</p>';
		}
		$body .= '<p>Vous pouvez suivre votre colis à tout moment depuis votre espace client.</p>';
		self::send_customer_email( $order, 'Votre commande Belmains est en route', $body );
	}

	public static function email_delivered( $order_id, $order = null ): void {
		$order = $order ?: wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}
		$days = (int) bm_setting( 'return_window', 30 );
		$body = '<p>Votre commande n° ' . esc_html( $order->get_order_number() ) . ' a été livrée. Nous espérons que votre rituel Belmains vous plaira.</p>';
		$body .= '<p>Un souci ? Vous disposez de ' . $days . ' jours pour demander un retour gratuit depuis votre espace client, rubrique « Retours ».</p>';
		self::send_customer_email( $order, 'Votre commande Belmains a été livrée', $body );
	}

	public static function send_customer_email( WC_Order $order, string $subject, string $body ): void {
		$mailer  = WC()->mailer();
		$heading = $subject;
		$content = wc_get_template_html( 'emails/email-header.php', array( 'email_heading' => $heading ) );
		$content .= '<p>Bonjour ' . esc_html( $order->get_billing_first_name() ) . ',</p>' . $body;
		ob_start();
		do_action( 'woocommerce_email_order_details', $order, false, false, '' );
		$content .= ob_get_clean();
		$content .= wc_get_template_html( 'emails/email-footer.php' );
		$mailer->send( $order->get_billing_email(), $subject, $content, "Content-Type: text/html\r\n" );
	}
}
