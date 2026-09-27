<?php
defined( 'ABSPATH' ) || exit;

/**
 * Menu « Belmains » et tableau de bord.
 */
class BM_Admin {

	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 9 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_bar_menu', array( __CLASS__, 'admin_bar' ), 100 );
	}

	public static function menu(): void {
		add_menu_page( 'Belmains', 'Belmains', 'manage_woocommerce', 'belmains', array( __CLASS__, 'dashboard' ), 'dashicons-heart', 2 );
		add_submenu_page( 'belmains', 'Tableau de bord', 'Tableau de bord', 'manage_woocommerce', 'belmains', array( __CLASS__, 'dashboard' ) );
		add_submenu_page( 'belmains', 'Commandes', 'Commandes', 'manage_woocommerce', self::orders_url(), '' );
		add_submenu_page( 'belmains', 'Expéditions', 'Expéditions', 'manage_woocommerce', 'belmains-shipments', array( 'BM_Shipments', 'render_page' ) );
		add_submenu_page( 'belmains', 'Stocks', 'Stocks', 'manage_woocommerce', 'belmains-stock', array( 'BM_Stock', 'render_page' ) );
		// « Retours » est ajouté automatiquement par le CPT (show_in_menu => belmains).
		add_submenu_page( 'belmains', 'Clients', 'Clients', 'manage_woocommerce', 'admin.php?page=wc-admin&path=/customers', '' );
		add_submenu_page( 'belmains', 'Site & builder', 'Site & builder', 'edit_theme_options', 'customize.php?autofocus[panel]=bm_builder', '' );
		add_submenu_page( 'belmains', 'Réglages', 'Réglages', 'manage_woocommerce', 'belmains-settings', array( 'BM_Settings', 'render_page' ) );
	}

	public static function orders_url(): string {
		if ( function_exists( 'wc_get_container' ) && class_exists( '\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController' )
			&& wc_get_container()->get( \Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class )->custom_orders_table_usage_is_enabled() ) {
			return 'admin.php?page=wc-orders';
		}
		return 'edit.php?post_type=shop_order';
	}

	public static function admin_bar( WP_Admin_Bar $bar ): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$to_ship = BM_Shipments::query_orders( 'to_ship', 1, 1 )['total'];
		$bar->add_node( array(
			'id'    => 'bm-crm',
			'title' => '♥ Belmains' . ( $to_ship ? ' <span class="bm-ab-count">' . (int) $to_ship . '</span>' : '' ),
			'href'  => bm_admin_url( 'belmains' ),
		) );
	}

	public static function assets( string $hook ): void {
		$screen  = get_current_screen();
		$is_ours = str_contains( $hook, 'belmains' ) || ( $screen && in_array( $screen->id, array( 'shop_order', 'woocommerce_page_wc-orders', 'bm_return', 'edit-bm_return' ), true ) );
		wp_enqueue_style( 'bm-admin-bar', BM_CRM_URL . 'assets/admin.css', array(), BM_CRM_VERSION );
		if ( ! $is_ours ) {
			return;
		}
		wp_enqueue_script( 'bm-admin', BM_CRM_URL . 'assets/admin.js', array( 'jquery' ), BM_CRM_VERSION, true );
		wp_localize_script( 'bm-admin', 'BM_CRM', array(
			'ajax'  => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'bm_crm' ),
		) );
	}

	/* ------------------------------------------------------------ Dashboard */

	private static function stats( string $from ): array {
		$orders  = wc_get_orders( array(
			'limit'        => 5000, // borne haute explicite (évite un LIMIT illimité sur la table HPOS)
			'status'       => bm_paid_statuses(),
			'date_created' => '>=' . $from,
		) );
		$revenue = 0.0;
		foreach ( $orders as $o ) {
			$revenue += (float) $o->get_total() - (float) $o->get_total_refunded();
		}
		return array( 'count' => count( $orders ), 'revenue' => $revenue );
	}

	public static function dashboard(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( 'Accès refusé.' );
		}
		$today = self::stats( gmdate( 'Y-m-d', current_time( 'timestamp' ) ) );
		$week  = self::stats( gmdate( 'Y-m-d', current_time( 'timestamp' ) - 6 * DAY_IN_SECONDS ) );
		$month = self::stats( gmdate( 'Y-m-d', current_time( 'timestamp' ) - 29 * DAY_IN_SECONDS ) );

		$to_ship   = BM_Shipments::query_orders( 'to_ship', 1, 1 )['total'];
		$transit   = BM_Shipments::query_orders( 'in_transit', 1, 1 )['total'];
		$incidents = BM_Shipments::query_orders( 'exception', 1, 1 )['total'];
		$on_hold   = (int) wc_get_orders( array( 'limit' => 1, 'paginate' => true, 'status' => array( 'on-hold', 'pending' ), 'return' => 'ids' ) )->total;

		$open_returns = (int) ( new WP_Query( array(
			'post_type'      => BM_Returns::CPT,
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_query'     => array( array( 'key' => '_bm_status', 'value' => array( 'demandee', 'acceptee', 'en_transit', 'recue' ), 'compare' => 'IN' ) ),
		) ) )->found_posts;
		$new_returns = get_posts( array( 'post_type' => BM_Returns::CPT, 'posts_per_page' => 5, 'meta_key' => '_bm_status', 'meta_value' => 'demandee' ) );

		$low     = BM_Stock::low_stock();
		$recent  = wc_get_orders( array( 'limit' => 10, 'orderby' => 'date', 'order' => 'DESC' ) );
		$easy_ok = BM_Easyship::is_configured();
		?>
		<div class="wrap bm-wrap bm-dash">
			<h1>Belmains — Tableau de bord <small class="bm-muted"><?php echo esc_html( date_i18n( 'l j F Y' ) ); ?></small></h1>

			<?php if ( ! $easy_ok ) : ?>
				<div class="notice notice-warning"><p>Easyship n'est pas connecté. <a href="<?php echo esc_url( bm_admin_url( 'belmains-settings' ) ); ?>">Renseigner le jeton API</a> pour créer les expéditions et recevoir le suivi.</p></div>
			<?php endif; ?>

			<div class="bm-kpis">
				<div class="bm-kpi"><span>Aujourd'hui</span><b><?php echo wp_kses_post( wc_price( $today['revenue'] ) ); ?></b><small><?php echo (int) $today['count']; ?> commande(s)</small></div>
				<div class="bm-kpi"><span>7 jours</span><b><?php echo wp_kses_post( wc_price( $week['revenue'] ) ); ?></b><small><?php echo (int) $week['count']; ?> commande(s)</small></div>
				<div class="bm-kpi"><span>30 jours</span><b><?php echo wp_kses_post( wc_price( $month['revenue'] ) ); ?></b><small><?php echo (int) $month['count']; ?> commande(s) · panier moyen <?php echo $month['count'] ? wp_kses_post( wc_price( $month['revenue'] / $month['count'] ) ) : '—'; ?></small></div>
				<a class="bm-kpi bm-kpi-act <?php echo $to_ship ? 'is-hot' : ''; ?>" href="<?php echo esc_url( bm_admin_url( 'belmains-shipments', array( 'filter' => 'to_ship' ) ) ); ?>"><span>À expédier</span><b><?php echo (int) $to_ship; ?></b><small>commandes payées sans colis</small></a>
				<a class="bm-kpi bm-kpi-act" href="<?php echo esc_url( bm_admin_url( 'belmains-shipments', array( 'filter' => 'in_transit' ) ) ); ?>"><span>En transit</span><b><?php echo (int) $transit; ?></b><small>colis en route</small></a>
				<a class="bm-kpi bm-kpi-act <?php echo $incidents ? 'is-bad' : ''; ?>" href="<?php echo esc_url( bm_admin_url( 'belmains-shipments', array( 'filter' => 'exception' ) ) ); ?>"><span>Incidents</span><b><?php echo (int) $incidents; ?></b><small>livraison en échec / retour</small></a>
				<a class="bm-kpi bm-kpi-act <?php echo $open_returns ? 'is-hot' : ''; ?>" href="<?php echo esc_url( admin_url( 'edit.php?post_type=bm_return' ) ); ?>"><span>Retours ouverts</span><b><?php echo (int) $open_returns; ?></b><small>SAV à traiter</small></a>
				<a class="bm-kpi bm-kpi-act <?php echo $low ? 'is-bad' : ''; ?>" href="<?php echo esc_url( bm_admin_url( 'belmains-stock' ) ); ?>"><span>Stock bas</span><b><?php echo count( $low ); ?></b><small>réf. sous le seuil (<?php echo (int) bm_setting( 'low_stock', 10 ); ?>)</small></a>
				<a class="bm-kpi bm-kpi-act" href="<?php echo esc_url( admin_url( self::orders_url() . '&status=wc-on-hold' ) ); ?>"><span>En attente</span><b><?php echo (int) $on_hold; ?></b><small>paiement non confirmé</small></a>
			</div>

			<div class="bm-grid-2">
				<div class="bm-card">
					<h2>Dernières commandes <a class="button button-small" href="<?php echo esc_url( admin_url( self::orders_url() ) ); ?>">Toutes</a></h2>
					<table class="wp-list-table widefat fixed striped">
						<thead><tr><th>N°</th><th>Client</th><th>Statut</th><th>Suivi</th><th>Total</th><th>Date</th></tr></thead>
						<tbody>
						<?php foreach ( $recent as $o ) : /** @var WC_Order $o */ ?>
							<tr>
								<td><a href="<?php echo esc_url( $o->get_edit_order_url() ); ?>"><strong>#<?php echo esc_html( $o->get_order_number() ); ?></strong></a></td>
								<td><?php echo esc_html( $o->get_formatted_billing_full_name() ); ?></td>
								<td><span class="bm-badge" style="--bm-badge:#374151"><?php echo esc_html( wc_get_order_status_name( $o->get_status() ) ); ?></span></td>
								<td><?php echo BM_Orders::tracking_summary_html( $o ); ?></td>
								<td><?php echo wp_kses_post( $o->get_formatted_order_total() ); ?></td>
								<td><?php echo esc_html( bm_format_date( $o->get_date_created() ) ); ?></td>
							</tr>
						<?php endforeach; ?>
						<?php if ( ! $recent ) : ?><tr><td colspan="6">Aucune commande pour le moment.</td></tr><?php endif; ?>
						</tbody>
					</table>
				</div>

				<div>
					<div class="bm-card">
						<h2>Retours à traiter</h2>
						<?php if ( ! $new_returns ) : ?><p class="bm-muted">Aucune demande en attente.</p><?php endif; ?>
						<ul class="bm-list">
							<?php foreach ( $new_returns as $r ) : ?>
								<li><a href="<?php echo esc_url( get_edit_post_link( $r->ID ) ); ?>"><?php echo esc_html( $r->post_title ); ?></a><br><small><?php echo esc_html( bm_return_reasons()[ get_post_meta( $r->ID, '_bm_reason', true ) ] ?? '' ); ?> · <?php echo esc_html( date_i18n( 'd/m', strtotime( $r->post_date ) ) ); ?></small></li>
							<?php endforeach; ?>
						</ul>
					</div>
					<div class="bm-card">
						<h2>Stock bas</h2>
						<?php if ( ! $low ) : ?><p class="bm-muted">Tout est au-dessus du seuil.</p><?php endif; ?>
						<ul class="bm-list">
							<?php foreach ( $low as $p ) : ?>
								<li><a href="<?php echo esc_url( bm_admin_url( 'belmains-stock', array( 'product' => $p->get_id() ) ) ); ?>"><?php echo esc_html( $p->get_name() ); ?></a> <strong><?php echo (int) $p->get_stock_quantity(); ?></strong> restant(s)</li>
							<?php endforeach; ?>
						</ul>
					</div>
					<div class="bm-card">
						<h2>Raccourcis</h2>
						<p>
							<a class="button" href="<?php echo esc_url( admin_url( 'customize.php?autofocus[panel]=bm_builder' ) ); ?>">Modifier le site (builder)</a>
							<a class="button" href="<?php echo esc_url( admin_url( 'edit.php?post_type=product' ) ); ?>">Produits</a>
							<a class="button" href="<?php echo esc_url( admin_url( 'edit.php?post_type=shop_coupon' ) ); ?>">Codes promo</a>
							<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=wc-admin&path=/analytics/overview' ) ); ?>">Statistiques</a>
						</p>
					</div>
				</div>
			</div>
		</div>
		<?php
	}
}
