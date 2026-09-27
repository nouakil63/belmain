<?php
defined( 'ABSPATH' ) || exit;

/**
 * Stocks : journal des mouvements (table bm_stock_movements), ajustements manuels,
 * page « Stocks », alerte de stock bas sur le tableau de bord.
 */
class BM_Stock {

	/** @var array<int,int> stock avant modification, par produit */
	private static array $before = array();
	private static array $from_product_save = array();
	private static string $reason = '';
	private static string $note = '';
	private static ?int $order_id = null;

	public static function init(): void {
		// wc_update_product_stock() (commandes, ajustements) …
		add_action( 'woocommerce_product_before_set_stock', array( __CLASS__, 'capture_before' ) );
		add_action( 'woocommerce_variation_before_set_stock', array( __CLASS__, 'capture_before' ) );
		// … et enregistrement direct de la fiche produit (admin, import, API).
		add_action( 'woocommerce_before_product_object_save', array( __CLASS__, 'capture_before_save' ) );
		add_action( 'woocommerce_product_set_stock', array( __CLASS__, 'log_after' ) );
		add_action( 'woocommerce_variation_set_stock', array( __CLASS__, 'log_after' ) );
		// Rattachement du mouvement à la commande qui l'a provoqué.
		add_action( 'woocommerce_reduce_order_item_stock', array( __CLASS__, 'attach_order' ), 10, 3 );
		add_action( 'woocommerce_restore_order_item_stock', array( __CLASS__, 'attach_order' ), 10, 3 );
		add_action( 'woocommerce_restock_refunded_item', array( __CLASS__, 'attach_order_refund' ), 10, 4 );
		add_action( 'admin_post_bm_stock_adjust', array( __CLASS__, 'handle_adjust' ) );
	}

	public static function reasons(): array {
		return array(
			'commande'    => 'Commande client',
			'annulation'  => 'Annulation / remboursement',
			'reception'   => 'Réception fournisseur',
			'inventaire'  => 'Correction d\'inventaire',
			'retour'      => 'Retour client remis en stock',
			'casse'       => 'Casse / perte / défectueux',
			'sav'         => 'Envoi SAV / remplacement',
			'autre'       => 'Autre',
		);
	}

	/* ------------------------------------------------------------ Journal */

	public static function capture_before( $product ): void {
		if ( $product instanceof WC_Product ) {
			self::$before[ $product->get_id() ] = (int) $product->get_stock_quantity();
		}
	}

	public static function capture_before_save( $product ): void {
		if ( ! $product instanceof WC_Product || ! $product->get_id() ) {
			return;
		}
		$changes = $product->get_changes();
		if ( array_key_exists( 'stock_quantity', $changes ) && ! isset( self::$before[ $product->get_id() ] ) ) {
			self::$before[ $product->get_id() ]            = (int) get_post_meta( $product->get_id(), '_stock', true );
			self::$from_product_save[ $product->get_id() ] = true;
		}
	}

	public static function attach_order_refund( $product_id, $old_stock, $new_stock, $order ): void {
		global $wpdb;
		if ( ! $order instanceof WC_Order ) {
			return;
		}
		$wpdb->query( $wpdb->prepare(
			"UPDATE {$wpdb->prefix}bm_stock_movements SET order_id = %d WHERE product_id = %d AND order_id IS NULL ORDER BY id DESC LIMIT 1",
			$order->get_id(), (int) $product_id
		) );
	}

	public static function attach_order( $item, $change, $order ): void {
		global $wpdb;
		$product = $item instanceof WC_Order_Item_Product ? $item->get_product() : null;
		if ( ! $product || ! $order instanceof WC_Order ) {
			return;
		}
		$wpdb->query( $wpdb->prepare(
			"UPDATE {$wpdb->prefix}bm_stock_movements SET order_id = %d WHERE product_id = %d AND order_id IS NULL ORDER BY id DESC LIMIT 1",
			$order->get_id(), $product->get_id()
		) );
	}

	public static function log_after( $product ): void {
		if ( ! $product instanceof WC_Product ) {
			return;
		}
		$id     = $product->get_id();
		$after  = (int) $product->get_stock_quantity();
		$before = self::$before[ $id ] ?? null;
		unset( self::$before[ $id ] );
		if ( null === $before || $before === $after ) {
			return;
		}
		$reason = self::$reason;
		$note   = self::$note;
		$order  = self::$order_id;
		if ( '' === $reason ) {
			if ( ! empty( self::$from_product_save[ $id ] ) ) {
				$reason = 'inventaire';
				$note   = 'Modification directe de la fiche produit';
			} else {
				// wc_update_product_stock() hors ajustement manuel = commande (réduction) ou annulation/remboursement (restauration).
				$reason = $after < $before ? 'commande' : 'annulation';
			}
		}
		unset( self::$from_product_save[ $id ] );
		self::insert( $id, $before, $after - $before, $after, $reason, $note, $order );
	}

	public static function insert( int $product_id, ?int $before, int $delta, ?int $after, string $reason, string $note = '', ?int $order_id = null ): void {
		global $wpdb;
		$wpdb->insert( $wpdb->prefix . 'bm_stock_movements', array(
			'product_id' => $product_id,
			'qty_before' => $before,
			'qty_delta'  => $delta,
			'qty_after'  => $after,
			'reason'     => $reason,
			'note'       => $note,
			'order_id'   => $order_id,
			'user_id'    => get_current_user_id() ?: null,
			'created_at' => current_time( 'mysql' ),
		) );
	}

	/**
	 * Ajustement manuel (positif ou négatif) avec motif, journalisé.
	 */
	public static function adjust( int $product_id, int $delta, string $reason, string $note = '', ?int $order_id = null ) {
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return new WP_Error( 'bm_product', 'Produit introuvable.' );
		}
		if ( ! $product->managing_stock() ) {
			$product->set_manage_stock( true );
			$product->set_stock_quantity( 0 );
			$product->save();
		}
		self::$reason   = $reason;
		self::$note     = $note;
		self::$order_id = $order_id;
		$new = wc_update_product_stock( $product, abs( $delta ), $delta >= 0 ? 'increase' : 'decrease' );
		self::$reason   = '';
		self::$note     = '';
		self::$order_id = null;
		return $new;
	}

	public static function movements( int $limit = 100, ?int $product_id = null ): array {
		global $wpdb;
		$sql = "SELECT * FROM {$wpdb->prefix}bm_stock_movements";
		if ( $product_id ) {
			$sql .= $wpdb->prepare( ' WHERE product_id = %d', $product_id );
		}
		$sql .= $wpdb->prepare( ' ORDER BY id DESC LIMIT %d', $limit );
		return $wpdb->get_results( $sql ) ?: array();
	}

	/* ----------------------------------------------------------- Inventaire */

	/**
	 * Tous les produits/variations avec gestion de stock.
	 */
	public static function inventory(): array {
		$rows = array();
		$ids  = wc_get_products( array( 'limit' => -1, 'status' => 'publish', 'return' => 'ids', 'orderby' => 'title', 'order' => 'ASC' ) );
		foreach ( $ids as $id ) {
			$p = wc_get_product( $id );
			if ( ! $p ) {
				continue;
			}
			if ( $p->is_type( 'variable' ) ) {
				foreach ( $p->get_children() as $vid ) {
					$v = wc_get_product( $vid );
					if ( $v ) {
						$rows[] = $v;
					}
				}
			} else {
				$rows[] = $p;
			}
		}
		return $rows;
	}

	public static function low_stock( ?int $threshold = null ): array {
		$threshold = $threshold ?? (int) bm_setting( 'low_stock', 10 );
		$out       = array();
		foreach ( self::inventory() as $p ) {
			if ( $p->managing_stock() && (int) $p->get_stock_quantity() <= $threshold ) {
				$out[] = $p;
			}
		}
		return $out;
	}

	/* ------------------------------------------------------------ Page admin */

	public static function render_page(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( 'Accès refusé.' );
		}
		$threshold = (int) bm_setting( 'low_stock', 10 );
		$filter_id = absint( $_GET['product'] ?? 0 );
		$rows      = self::inventory();
		$moves     = self::movements( 100, $filter_id ?: null );
		?>
		<div class="wrap bm-wrap">
			<h1>Stocks</h1>
			<?php if ( isset( $_GET['bm_msg'] ) ) : ?><div class="notice notice-success is-dismissible"><p><?php echo esc_html( sanitize_text_field( wp_unslash( $_GET['bm_msg'] ) ) ); ?></p></div><?php endif; ?>
			<?php if ( isset( $_GET['bm_err'] ) ) : ?><div class="notice notice-error is-dismissible"><p><?php echo esc_html( sanitize_text_field( wp_unslash( $_GET['bm_err'] ) ) ); ?></p></div><?php endif; ?>

			<div class="bm-grid-2">
				<div class="bm-card">
					<h2>Inventaire</h2>
					<table class="wp-list-table widefat fixed striped">
						<thead><tr><th>Produit</th><th>SKU</th><th style="width:90px">Stock</th><th>État</th><th style="width:120px"></th></tr></thead>
						<tbody>
						<?php foreach ( $rows as $p ) :
							$qty  = $p->managing_stock() ? (int) $p->get_stock_quantity() : null;
							$low  = null !== $qty && $qty <= $threshold; ?>
							<tr>
								<td><a href="<?php echo esc_url( get_edit_post_link( $p->get_parent_id() ?: $p->get_id() ) ); ?>"><?php echo esc_html( $p->get_name() ); ?></a></td>
								<td><code><?php echo esc_html( $p->get_sku() ?: '—' ); ?></code></td>
								<td><strong><?php echo null === $qty ? '∞' : esc_html( $qty ); ?></strong></td>
								<td><?php
									if ( null === $qty ) { echo bm_badge( 'none', 'Non géré' ); }
									elseif ( $qty <= 0 ) { echo bm_badge( 'exception', 'Rupture' ); }
									elseif ( $low ) { echo bm_badge( 'pending', 'Stock bas' ); }
									else { echo bm_badge( 'delivered', 'OK' ); }
								?></td>
								<td><a class="button button-small" href="<?php echo esc_url( bm_admin_url( 'belmains-stock', array( 'product' => $p->get_id() ) ) ); ?>#bm-adjust">Ajuster</a></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</div>

				<div class="bm-card" id="bm-adjust">
					<h2>Ajuster le stock</h2>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="bm_stock_adjust">
						<?php wp_nonce_field( 'bm_stock_adjust' ); ?>
						<table class="form-table" role="presentation">
							<tr><th><label for="bm-p">Produit</label></th><td>
								<select name="product_id" id="bm-p" class="regular-text" required>
									<?php foreach ( $rows as $p ) : ?>
										<option value="<?php echo esc_attr( $p->get_id() ); ?>" <?php selected( $filter_id, $p->get_id() ); ?>><?php echo esc_html( $p->get_name() . ( $p->get_sku() ? ' (' . $p->get_sku() . ')' : '' ) ); ?></option>
									<?php endforeach; ?>
								</select></td></tr>
							<tr><th><label for="bm-d">Quantité (+/−)</label></th><td><input type="number" name="delta" id="bm-d" value="0" step="1" required class="small-text"> <span class="description">Ex. <code>+50</code> réception, <code>-2</code> casse.</span></td></tr>
							<tr><th><label for="bm-r">Motif</label></th><td>
								<select name="reason" id="bm-r">
									<?php foreach ( self::reasons() as $k => $l ) : if ( in_array( $k, array( 'commande', 'annulation' ), true ) ) { continue; } ?>
										<option value="<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $l ); ?></option>
									<?php endforeach; ?>
								</select></td></tr>
							<tr><th><label for="bm-n">Note</label></th><td><input type="text" name="note" id="bm-n" class="regular-text" placeholder="N° de bon de livraison, lot…"></td></tr>
						</table>
						<?php submit_button( 'Enregistrer le mouvement' ); ?>
					</form>
				</div>
			</div>

			<div class="bm-card">
				<h2>Historique des mouvements <?php if ( $filter_id ) : ?><a class="button button-small" href="<?php echo esc_url( bm_admin_url( 'belmains-stock' ) ); ?>">Tout afficher</a><?php endif; ?></h2>
				<table class="wp-list-table widefat fixed striped">
					<thead><tr><th>Date</th><th>Produit</th><th>Avant</th><th>Δ</th><th>Après</th><th>Motif</th><th>Commande</th><th>Par</th></tr></thead>
					<tbody>
					<?php if ( ! $moves ) : ?><tr><td colspan="8">Aucun mouvement enregistré.</td></tr><?php endif; ?>
					<?php foreach ( $moves as $m ) :
						$p = wc_get_product( (int) $m->product_id );
						$u = $m->user_id ? get_userdata( (int) $m->user_id ) : null; ?>
						<tr>
							<td><?php echo esc_html( bm_format_date( $m->created_at ) ); ?></td>
							<td><?php echo $p ? esc_html( $p->get_name() ) : '#' . (int) $m->product_id; ?></td>
							<td><?php echo esc_html( $m->qty_before ?? '—' ); ?></td>
							<td><strong style="color:<?php echo $m->qty_delta < 0 ? '#b91c1c' : '#15803d'; ?>"><?php echo esc_html( ( $m->qty_delta > 0 ? '+' : '' ) . $m->qty_delta ); ?></strong></td>
							<td><?php echo esc_html( $m->qty_after ?? '—' ); ?></td>
							<td><?php echo esc_html( self::reasons()[ $m->reason ] ?? $m->reason ); ?><?php echo $m->note ? '<br><small>' . esc_html( $m->note ) . '</small>' : ''; ?></td>
							<td><?php echo $m->order_id ? '<a href="' . esc_url( bm_order_edit_link( (int) $m->order_id ) ) . '">#' . (int) $m->order_id . '</a>' : '—'; ?></td>
							<td><?php echo $u ? esc_html( $u->display_name ) : 'Système'; ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>
		<?php
	}

	public static function handle_adjust(): void {
		check_admin_referer( 'bm_stock_adjust' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( 'Accès refusé.' );
		}
		$pid    = absint( $_POST['product_id'] ?? 0 );
		$delta  = (int) ( $_POST['delta'] ?? 0 );
		$reason = sanitize_key( $_POST['reason'] ?? 'autre' );
		$note   = sanitize_text_field( wp_unslash( $_POST['note'] ?? '' ) );
		if ( 0 === $delta ) {
			wp_safe_redirect( bm_admin_url( 'belmains-stock', array( 'bm_err' => 'Quantité nulle : aucun mouvement.' ) ) );
			exit;
		}
		$res = self::adjust( $pid, $delta, array_key_exists( $reason, self::reasons() ) ? $reason : 'autre', $note );
		if ( is_wp_error( $res ) ) {
			wp_safe_redirect( bm_admin_url( 'belmains-stock', array( 'bm_err' => $res->get_error_message() ) ) );
			exit;
		}
		wp_safe_redirect( bm_admin_url( 'belmains-stock', array( 'product' => $pid, 'bm_msg' => sprintf( 'Stock mis à jour : %d.', (int) $res ) ) ) );
		exit;
	}
}
