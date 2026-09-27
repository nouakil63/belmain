<?php
defined( 'ABSPATH' ) || exit;

/**
 * Retours SAV (CPT bm_return, statut en méta _bm_status).
 *  - Espace client : endpoint « retours » (liste + demande de retour).
 *  - Admin : liste sous le menu Belmains, metabox de traitement, remboursement WooCommerce.
 */
class BM_Returns {

	const CPT = 'bm_return';

	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'init', array( __CLASS__, 'maybe_flush' ), 99 );

		// Admin.
		add_filter( 'manage_' . self::CPT . '_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_' . self::CPT . '_posts_custom_column', array( __CLASS__, 'column' ), 10, 2 );
		add_filter( 'views_edit-' . self::CPT, array( __CLASS__, 'views' ) );
		add_action( 'pre_get_posts', array( __CLASS__, 'filter_list' ) );
		add_action( 'add_meta_boxes_' . self::CPT, array( __CLASS__, 'meta_boxes' ) );
		add_action( 'save_post_' . self::CPT, array( __CLASS__, 'save' ), 10, 2 );
		add_filter( 'enter_title_here', array( __CLASS__, 'title_placeholder' ), 10, 2 );
		add_action( 'wp_ajax_bm_return_refund', array( __CLASS__, 'ajax_refund' ) );

		// Client.
		add_action( 'init', array( __CLASS__, 'account_endpoint' ) );
		add_filter( 'woocommerce_account_menu_items', array( __CLASS__, 'account_menu' ) );
		add_action( 'woocommerce_account_retours_endpoint', array( __CLASS__, 'account_page' ) );
		add_action( 'template_redirect', array( __CLASS__, 'handle_customer_request' ) );
		add_filter( 'woocommerce_my_account_my_orders_actions', array( __CLASS__, 'order_action' ), 10, 2 );
	}

	/* --------------------------------------------------------------- CPT */

	public static function register(): void {
		register_post_type( self::CPT, array(
			'labels'          => array(
				'name'               => 'Retours',
				'singular_name'      => 'Retour',
				'add_new'            => 'Nouveau retour',
				'add_new_item'       => 'Nouveau retour',
				'edit_item'          => 'Retour',
				'search_items'       => 'Rechercher un retour',
				'not_found'          => 'Aucun retour.',
				'menu_name'          => 'Retours',
			),
			'public'          => false,
			'show_ui'         => true,
			'show_in_menu'    => 'belmains',
			'supports'        => array( 'title' ),
			'capability_type' => 'shop_order',
			'map_meta_cap'    => true,
			'has_archive'     => false,
			'rewrite'         => false,
		) );
	}

	public static function maybe_flush(): void {
		if ( '1' === get_option( 'bm_crm_flush_rewrite' ) ) {
			flush_rewrite_rules();
			delete_option( 'bm_crm_flush_rewrite' );
		}
	}

	/**
	 * Crée un retour. $items = [ order_item_id => qty ].
	 */
	public static function create( int $order_id, array $items, string $reason, string $note = '', string $source = 'admin' ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return new WP_Error( 'bm_order', 'Commande introuvable.' );
		}
		$post_id = wp_insert_post( array(
			'post_type'   => self::CPT,
			'post_status' => 'publish',
			'post_title'  => sprintf( 'Retour commande #%s — %s', $order->get_order_number(), $order->get_formatted_billing_full_name() ),
		), true );
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}
		$clean = array();
		foreach ( $items as $item_id => $qty ) {
			$qty = (int) $qty;
			if ( $qty > 0 && $order->get_item( (int) $item_id ) ) {
				$clean[ (int) $item_id ] = $qty;
			}
		}
		update_post_meta( $post_id, '_bm_order_id', $order_id );
		update_post_meta( $post_id, '_bm_customer_id', $order->get_customer_id() );
		update_post_meta( $post_id, '_bm_items', $clean );
		update_post_meta( $post_id, '_bm_reason', $reason );
		update_post_meta( $post_id, '_bm_customer_note', $note );
		update_post_meta( $post_id, '_bm_resolution', 'refund' );
		self::set_status( $post_id, 'demandee', 'Demande créée (' . $source . ').' );

		$order->add_order_note( sprintf( 'Retour SAV n°%d créé (%s).', $post_id, bm_return_reasons()[ $reason ] ?? $reason ) );
		self::notify( $post_id, 'created' );
		return $post_id;
	}

	public static function set_status( int $post_id, string $status, string $note = '' ): void {
		$prev = get_post_meta( $post_id, '_bm_status', true );
		update_post_meta( $post_id, '_bm_status', $status );
		$history   = (array) get_post_meta( $post_id, '_bm_history', true );
		$history[] = array(
			'date'   => current_time( 'mysql' ),
			'status' => $status,
			'user'   => get_current_user_id(),
			'note'   => $note,
		);
		update_post_meta( $post_id, '_bm_history', $history );

		if ( $prev !== $status ) {
			$order = wc_get_order( (int) get_post_meta( $post_id, '_bm_order_id', true ) );
			if ( $order ) {
				if ( 'acceptee' === $status && in_array( $order->get_status(), array( 'bm-livree', 'completed', 'bm-expediee' ), true ) ) {
					$order->set_status( 'bm-retour', 'Retour SAV accepté.' );
					$order->save();
				}
				if ( in_array( $status, array( 'remboursee', 'echangee', 'cloturee', 'refusee' ), true ) && 'bm-retour' === $order->get_status() ) {
					$order->set_status( 'refusee' === $status ? 'bm-livree' : 'completed', 'Retour SAV clôturé (' . $status . ').' );
					$order->save();
				}
			}
			if ( in_array( $status, array( 'acceptee', 'refusee', 'recue', 'remboursee', 'echangee' ), true ) ) {
				self::notify( $post_id, $status );
			}
		}
	}

	/* --------------------------------------------------------- Notifications */

	public static function notify( int $post_id, string $event ): void {
		$order = wc_get_order( (int) get_post_meta( $post_id, '_bm_order_id', true ) );
		if ( ! $order ) {
			return;
		}
		$num = $order->get_order_number();
		if ( 'created' === $event ) {
			$to = bm_setting( 'notify_email', get_option( 'admin_email' ) );
			wp_mail( $to, "[Belmains] Nouvelle demande de retour — commande #$num", "Une demande de retour a été créée pour la commande #$num.\nMotif : " . ( bm_return_reasons()[ get_post_meta( $post_id, '_bm_reason', true ) ] ?? '' ) . "\n\nTraiter : " . get_edit_post_link( $post_id, 'raw' ) );
			BM_Orders::send_customer_email( $order, 'Nous avons bien reçu votre demande de retour', '<p>Votre demande de retour pour la commande n° ' . esc_html( $num ) . ' a bien été enregistrée. Nous revenons vers vous sous 48 h ouvrées.</p>' );
			return;
		}
		$messages = array(
			'acceptee'   => '<p>Votre retour pour la commande n° ' . esc_html( $num ) . ' est accepté.</p><p>Merci de renvoyer le colis à l\'adresse suivante :</p><p>' . nl2br( esc_html( bm_setting( 'return_address' ) ) ) . '</p>',
			'refusee'    => '<p>Après examen, nous ne pouvons pas accepter votre demande de retour pour la commande n° ' . esc_html( $num ) . '. ' . nl2br( esc_html( get_post_meta( $post_id, '_bm_admin_note', true ) ) ) . '</p>',
			'recue'      => '<p>Nous avons bien reçu votre colis en retour (commande n° ' . esc_html( $num ) . '). Le traitement est en cours.</p>',
			'remboursee' => '<p>Votre remboursement pour la commande n° ' . esc_html( $num ) . ' a été effectué. Il apparaîtra sur votre compte sous 3 à 10 jours selon votre banque.</p>',
			'echangee'   => '<p>Votre produit de remplacement pour la commande n° ' . esc_html( $num ) . ' est en préparation.</p>',
		);
		$subjects = array(
			'acceptee'   => 'Votre retour est accepté',
			'refusee'    => 'Votre demande de retour',
			'recue'      => 'Colis en retour bien reçu',
			'remboursee' => 'Votre remboursement est effectué',
			'echangee'   => 'Votre échange est en préparation',
		);
		if ( isset( $messages[ $event ] ) ) {
			BM_Orders::send_customer_email( $order, $subjects[ $event ], $messages[ $event ] );
		}
	}

	/* ---------------------------------------------------------------- Admin */

	public static function title_placeholder( $text, $post ) {
		return self::CPT === $post->post_type ? 'Retour commande #…' : $text;
	}

	public static function columns( array $cols ): array {
		return array(
			'cb'         => $cols['cb'],
			'title'      => 'Retour',
			'bm_status'  => 'Statut',
			'bm_order'   => 'Commande',
			'bm_reason'  => 'Motif',
			'bm_refund'  => 'Remboursement',
			'date'       => 'Date',
		);
	}

	public static function column( string $col, int $post_id ): void {
		switch ( $col ) {
			case 'bm_status':
				echo bm_return_badge( get_post_meta( $post_id, '_bm_status', true ) ?: 'demandee' );
				break;
			case 'bm_order':
				$oid = (int) get_post_meta( $post_id, '_bm_order_id', true );
				echo $oid ? '<a href="' . esc_url( bm_order_edit_link( $oid ) ) . '">#' . $oid . '</a>' : '—';
				break;
			case 'bm_reason':
				echo esc_html( bm_return_reasons()[ get_post_meta( $post_id, '_bm_reason', true ) ] ?? '—' );
				break;
			case 'bm_refund':
				$amt = get_post_meta( $post_id, '_bm_refund_amount', true );
				echo $amt ? wp_kses_post( wc_price( (float) $amt ) ) : '—';
				break;
		}
	}

	public static function views( array $views ): array {
		$current = sanitize_key( $_GET['bm_status'] ?? '' );
		$base    = admin_url( 'edit.php?post_type=' . self::CPT );
		$views   = array( 'all' => '<a href="' . esc_url( $base ) . '" class="' . ( $current ? '' : 'current' ) . '">Tous</a>' );
		foreach ( bm_return_statuses() as $k => $s ) {
			$count = (int) ( new WP_Query( array( 'post_type' => self::CPT, 'meta_key' => '_bm_status', 'meta_value' => $k, 'fields' => 'ids', 'posts_per_page' => -1, 'no_found_rows' => false ) ) )->found_posts;
			$views[ $k ] = '<a href="' . esc_url( add_query_arg( 'bm_status', $k, $base ) ) . '" class="' . ( $current === $k ? 'current' : '' ) . '">' . esc_html( $s['label'] ) . ' <span class="count">(' . $count . ')</span></a>';
		}
		return $views;
	}

	public static function filter_list( WP_Query $q ): void {
		if ( is_admin() && $q->is_main_query() && self::CPT === $q->get( 'post_type' ) && ! empty( $_GET['bm_status'] ) ) {
			$q->set( 'meta_key', '_bm_status' );
			$q->set( 'meta_value', sanitize_key( $_GET['bm_status'] ) );
		}
	}

	public static function meta_boxes(): void {
		add_meta_box( 'bm_return_details', 'Commande & articles', array( __CLASS__, 'mb_details' ), self::CPT, 'normal', 'high' );
		add_meta_box( 'bm_return_process', 'Traitement', array( __CLASS__, 'mb_process' ), self::CPT, 'side', 'high' );
		add_meta_box( 'bm_return_history', 'Historique', array( __CLASS__, 'mb_history' ), self::CPT, 'normal', 'default' );
	}

	public static function mb_details( WP_Post $post ): void {
		wp_nonce_field( 'bm_return_save', 'bm_return_nonce' );
		$oid   = (int) get_post_meta( $post->ID, '_bm_order_id', true );
		$order = $oid ? wc_get_order( $oid ) : null;
		$items = (array) get_post_meta( $post->ID, '_bm_items', true );
		?>
		<p><label>Commande n° <input type="number" name="bm_order_id" value="<?php echo esc_attr( $oid ?: ( absint( $_GET['order_id'] ?? 0 ) ?: '' ) ); ?>" class="small-text"></label>
			<?php if ( $order ) : ?> — <a href="<?php echo esc_url( $order->get_edit_order_url() ); ?>">Ouvrir la commande</a> · <?php echo esc_html( $order->get_formatted_billing_full_name() ); ?> · <a href="mailto:<?php echo esc_attr( $order->get_billing_email() ); ?>"><?php echo esc_html( $order->get_billing_email() ); ?></a><?php endif; ?></p>
		<?php if ( ! $order && ! $oid && ! empty( $_GET['order_id'] ) ) {
			$order = wc_get_order( absint( $_GET['order_id'] ) );
		} ?>
		<?php if ( $order ) : ?>
			<table class="widefat striped">
				<thead><tr><th>Article</th><th>Commandé</th><th>Retourné</th></tr></thead>
				<tbody>
				<?php foreach ( $order->get_items() as $item_id => $item ) : ?>
					<tr>
						<td><?php echo esc_html( $item->get_name() ); ?></td>
						<td><?php echo esc_html( $item->get_quantity() ); ?></td>
						<td><input type="number" name="bm_items[<?php echo esc_attr( $item_id ); ?>]" min="0" max="<?php echo esc_attr( $item->get_quantity() ); ?>" value="<?php echo esc_attr( $items[ $item_id ] ?? 0 ); ?>" class="small-text"></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php else : ?>
			<p class="description">Saisissez le numéro de commande puis enregistrez pour charger les articles.</p>
		<?php endif; ?>
		<p><label>Motif<br>
			<select name="bm_reason">
				<?php foreach ( bm_return_reasons() as $k => $l ) : ?>
					<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $k, get_post_meta( $post->ID, '_bm_reason', true ) ); ?>><?php echo esc_html( $l ); ?></option>
				<?php endforeach; ?>
			</select></label></p>
		<p><label>Message du client<br><textarea name="bm_customer_note" class="large-text" rows="3"><?php echo esc_textarea( get_post_meta( $post->ID, '_bm_customer_note', true ) ); ?></textarea></label></p>
		<?php
	}

	public static function mb_process( WP_Post $post ): void {
		$status = get_post_meta( $post->ID, '_bm_status', true ) ?: 'demandee';
		$oid    = (int) get_post_meta( $post->ID, '_bm_order_id', true );
		$order  = $oid ? wc_get_order( $oid ) : null;
		$amount = get_post_meta( $post->ID, '_bm_refund_amount', true );
		$rid    = get_post_meta( $post->ID, '_bm_refund_id', true );
		?>
		<p><?php echo bm_return_badge( $status ); ?></p>
		<p><label>Statut<br><select name="bm_status" class="widefat">
			<?php foreach ( bm_return_statuses() as $k => $s ) : ?>
				<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $k, $status ); ?>><?php echo esc_html( $s['label'] ); ?></option>
			<?php endforeach; ?>
		</select></label></p>
		<p><label>Résolution<br><select name="bm_resolution" class="widefat">
			<option value="refund" <?php selected( 'refund', get_post_meta( $post->ID, '_bm_resolution', true ) ); ?>>Remboursement</option>
			<option value="exchange" <?php selected( 'exchange', get_post_meta( $post->ID, '_bm_resolution', true ) ); ?>>Échange</option>
			<option value="partial" <?php selected( 'partial', get_post_meta( $post->ID, '_bm_resolution', true ) ); ?>>Geste commercial partiel</option>
		</select></label></p>
		<p><label>N° de suivi du colis retour<br><input type="text" name="bm_return_tracking" class="widefat" value="<?php echo esc_attr( get_post_meta( $post->ID, '_bm_return_tracking', true ) ); ?>"></label></p>
		<p><label>Montant à rembourser (€)<br><input type="number" step="0.01" name="bm_refund_amount" class="widefat" value="<?php echo esc_attr( $amount ?: ( $order ? $order->get_total() : '' ) ); ?>"></label></p>
		<p><label><input type="checkbox" name="bm_restock" value="1" checked> Remettre en stock</label></p>
		<?php if ( $rid ) : ?>
			<p class="bm-muted">Remboursement WooCommerce n°<?php echo (int) $rid; ?> effectué.</p>
		<?php elseif ( $order ) : ?>
			<p><button type="button" class="button button-primary bm-refund" data-return="<?php echo esc_attr( $post->ID ); ?>">Rembourser via WooCommerce</button> <span class="bm-act-msg"></span></p>
			<p class="bm-muted">Crée un remboursement sur la commande (via la passerelle de paiement si elle le permet) et passe le retour en « Remboursée ».</p>
		<?php endif; ?>
		<p><label>Note interne<br><textarea name="bm_admin_note" class="widefat" rows="3"><?php echo esc_textarea( get_post_meta( $post->ID, '_bm_admin_note', true ) ); ?></textarea></label></p>
		<?php
	}

	public static function mb_history( WP_Post $post ): void {
		$history = array_reverse( (array) get_post_meta( $post->ID, '_bm_history', true ) );
		if ( ! $history ) {
			echo '<p class="bm-muted">Aucun événement.</p>';
			return;
		}
		echo '<ul class="bm-timeline">';
		foreach ( $history as $h ) {
			if ( empty( $h['status'] ) ) {
				continue;
			}
			$u = ! empty( $h['user'] ) ? get_userdata( (int) $h['user'] ) : null;
			echo '<li><time>' . esc_html( bm_format_date( $h['date'] ?? '' ) ) . '</time> ' . bm_return_badge( $h['status'] ) . ' <span>' . esc_html( $h['note'] ?? '' ) . '</span> <small>' . ( $u ? esc_html( $u->display_name ) : 'Client' ) . '</small></li>';
		}
		echo '</ul>';
	}

	public static function save( int $post_id, WP_Post $post ): void {
		if ( ! isset( $_POST['bm_return_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['bm_return_nonce'] ), 'bm_return_save' ) ) {
			return;
		}
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_shop_orders' ) ) {
			return;
		}
		$oid = absint( $_POST['bm_order_id'] ?? 0 );
		update_post_meta( $post_id, '_bm_order_id', $oid );
		$order = $oid ? wc_get_order( $oid ) : null;
		if ( $order ) {
			update_post_meta( $post_id, '_bm_customer_id', $order->get_customer_id() );
			if ( '' === trim( $post->post_title ) || str_starts_with( $post->post_title, 'Brouillon' ) ) {
				remove_action( 'save_post_' . self::CPT, array( __CLASS__, 'save' ), 10 );
				wp_update_post( array( 'ID' => $post_id, 'post_title' => sprintf( 'Retour commande #%s — %s', $order->get_order_number(), $order->get_formatted_billing_full_name() ) ) );
				add_action( 'save_post_' . self::CPT, array( __CLASS__, 'save' ), 10, 2 );
			}
		}
		$items = array();
		foreach ( (array) ( $_POST['bm_items'] ?? array() ) as $iid => $qty ) {
			if ( (int) $qty > 0 ) {
				$items[ (int) $iid ] = (int) $qty;
			}
		}
		update_post_meta( $post_id, '_bm_items', $items );
		update_post_meta( $post_id, '_bm_reason', sanitize_key( $_POST['bm_reason'] ?? 'autre' ) );
		update_post_meta( $post_id, '_bm_customer_note', sanitize_textarea_field( wp_unslash( $_POST['bm_customer_note'] ?? '' ) ) );
		update_post_meta( $post_id, '_bm_admin_note', sanitize_textarea_field( wp_unslash( $_POST['bm_admin_note'] ?? '' ) ) );
		update_post_meta( $post_id, '_bm_resolution', sanitize_key( $_POST['bm_resolution'] ?? 'refund' ) );
		update_post_meta( $post_id, '_bm_return_tracking', sanitize_text_field( wp_unslash( $_POST['bm_return_tracking'] ?? '' ) ) );
		update_post_meta( $post_id, '_bm_refund_amount', is_numeric( $_POST['bm_refund_amount'] ?? '' ) ? (float) $_POST['bm_refund_amount'] : '' );

		$status = sanitize_key( $_POST['bm_status'] ?? 'demandee' );
		if ( array_key_exists( $status, bm_return_statuses() ) && $status !== get_post_meta( $post_id, '_bm_status', true ) ) {
			self::set_status( $post_id, $status, 'Statut modifié.' );
		} elseif ( ! get_post_meta( $post_id, '_bm_status', true ) ) {
			self::set_status( $post_id, 'demandee', 'Retour créé manuellement.' );
		}
	}

	public static function render_order_meta_box( $post_or_order ): void {
		$order = $post_or_order instanceof WC_Order ? $post_or_order : wc_get_order( $post_or_order->ID );
		if ( ! $order ) {
			return;
		}
		$returns = get_posts( array( 'post_type' => self::CPT, 'meta_key' => '_bm_order_id', 'meta_value' => $order->get_id(), 'posts_per_page' => -1 ) );
		if ( $returns ) {
			echo '<ul>';
			foreach ( $returns as $r ) {
				echo '<li>' . bm_return_badge( get_post_meta( $r->ID, '_bm_status', true ) ?: 'demandee' ) . ' <a href="' . esc_url( get_edit_post_link( $r->ID ) ) . '">' . esc_html( $r->post_title ) . '</a> — ' . esc_html( bm_return_reasons()[ get_post_meta( $r->ID, '_bm_reason', true ) ] ?? '' ) . '</li>';
			}
			echo '</ul>';
		} else {
			echo '<p class="bm-muted">Aucun retour pour cette commande.</p>';
		}
		echo '<a class="button" href="' . esc_url( admin_url( 'post-new.php?post_type=' . self::CPT . '&order_id=' . $order->get_id() ) ) . '">Créer un retour</a>';
	}

	/**
	 * Remboursement WooCommerce depuis le retour.
	 */
	public static function ajax_refund(): void {
		check_ajax_referer( 'bm_crm', 'nonce' );
		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			wp_send_json_error( array( 'message' => 'Accès refusé.' ) );
		}
		$post_id = absint( $_POST['return_id'] ?? 0 );
		$amount  = (float) ( $_POST['amount'] ?? 0 );
		$restock = ! empty( $_POST['restock'] );
		$order   = wc_get_order( (int) get_post_meta( $post_id, '_bm_order_id', true ) );
		if ( ! $order || $amount <= 0 ) {
			wp_send_json_error( array( 'message' => 'Commande ou montant invalide.' ) );
		}
		if ( get_post_meta( $post_id, '_bm_refund_id', true ) ) {
			wp_send_json_error( array( 'message' => 'Déjà remboursé.' ) );
		}
		$line_items = array();
		foreach ( (array) get_post_meta( $post_id, '_bm_items', true ) as $iid => $qty ) {
			$item = $order->get_item( (int) $iid );
			if ( $item ) {
				$line_items[ (int) $iid ] = array( 'qty' => (int) $qty, 'refund_total' => 0, 'refund_tax' => array() );
			}
		}
		// Remboursement via la passerelle si elle le permet (Stripe, PayPal…), sinon remboursement « manuel »
		// enregistré dans WooCommerce : l'argent est alors à renvoyer depuis l'interface du prestataire de paiement.
		$gateway   = wc_get_payment_gateway_by_order( $order );
		$automatic = $gateway && $gateway->supports( 'refunds' );
		$refund    = wc_create_refund( array(
			'amount'         => $amount,
			'reason'         => 'Retour SAV n°' . $post_id,
			'order_id'       => $order->get_id(),
			'line_items'     => $line_items,
			'refund_payment' => $automatic,
			'restock_items'  => $restock,
		) );
		if ( is_wp_error( $refund ) ) {
			wp_send_json_error( array( 'message' => $refund->get_error_message() ) );
		}
		update_post_meta( $post_id, '_bm_refund_id', $refund->get_id() );
		update_post_meta( $post_id, '_bm_refund_amount', $amount );
		$note = 'Remboursement WooCommerce n°' . $refund->get_id() . ' (' . wp_strip_all_tags( wc_price( $amount ) ) . ')'
			. ( $automatic ? ' — envoyé via ' . $gateway->get_title() . '.' : ' — À VIRER MANUELLEMENT (' . ( $gateway ? $gateway->get_title() : $order->get_payment_method_title() ) . ' ne gère pas les remboursements automatiques).' );
		self::set_status( $post_id, 'remboursee', $note );
		wp_send_json_success( array( 'message' => $automatic ? 'Remboursé via la passerelle.' : 'Remboursement enregistré — à virer manuellement depuis votre prestataire de paiement.', 'reload' => true ) );
	}

	/* ---------------------------------------------------------------- Client */

	public static function account_endpoint(): void {
		add_rewrite_endpoint( 'retours', EP_ROOT | EP_PAGES );
	}

	public static function account_menu( array $items ): array {
		$new = array();
		foreach ( $items as $k => $v ) {
			$new[ $k ] = $v;
			if ( 'orders' === $k ) {
				$new['retours'] = 'Retours';
			}
		}
		return $new;
	}

	public static function order_action( array $actions, $order ): array {
		if ( self::order_eligible( $order ) ) {
			$actions['bm_return'] = array( 'url' => wc_get_account_endpoint_url( 'retours' ) . '?order=' . $order->get_id(), 'name' => 'Retourner' );
		}
		return $actions;
	}

	public static function order_eligible( $order ): bool {
		if ( ! $order instanceof WC_Order || ! in_array( $order->get_status(), array( 'bm-livree', 'completed', 'bm-expediee' ), true ) ) {
			return false;
		}
		$days = (int) bm_setting( 'return_window', 30 );
		$ref  = $order->get_meta( '_bm_delivered_at' ) ?: ( $order->get_date_completed() ? $order->get_date_completed()->date( 'Y-m-d H:i:s' ) : $order->get_date_created()->date( 'Y-m-d H:i:s' ) );
		return ( time() - strtotime( $ref ) ) <= $days * DAY_IN_SECONDS;
	}

	public static function account_page(): void {
		$user_id = get_current_user_id();
		$oid     = absint( $_GET['order'] ?? 0 );
		$order   = $oid ? wc_get_order( $oid ) : null;
		if ( isset( $_GET['bm_return_ok'] ) ) {
			wc_print_notice( 'Votre demande de retour a bien été enregistrée. Vous recevrez une réponse par e-mail.', 'success' );
		}
		if ( $order && (int) $order->get_customer_id() === $user_id && self::order_eligible( $order ) ) {
			?>
			<h3>Demander un retour — commande n°<?php echo esc_html( $order->get_order_number() ); ?></h3>
			<form method="post" class="bm-return-form">
				<?php wp_nonce_field( 'bm_customer_return', 'bm_customer_return_nonce' ); ?>
				<input type="hidden" name="bm_order_id" value="<?php echo esc_attr( $order->get_id() ); ?>">
				<table class="shop_table">
					<thead><tr><th>Article</th><th>Quantité à retourner</th></tr></thead>
					<tbody>
					<?php foreach ( $order->get_items() as $iid => $item ) : ?>
						<tr><td><?php echo esc_html( $item->get_name() ); ?></td>
							<td><input type="number" name="bm_items[<?php echo esc_attr( $iid ); ?>]" min="0" max="<?php echo esc_attr( $item->get_quantity() ); ?>" value="<?php echo esc_attr( $item->get_quantity() ); ?>"></td></tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<p class="form-row"><label for="bm_reason">Motif</label>
					<select name="bm_reason" id="bm_reason" required>
						<?php foreach ( bm_return_reasons() as $k => $l ) : ?><option value="<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $l ); ?></option><?php endforeach; ?>
					</select></p>
				<p class="form-row"><label for="bm_note">Précisions (facultatif)</label><textarea name="bm_note" id="bm_note" rows="4"></textarea></p>
				<p><button type="submit" class="button" name="bm_submit_return" value="1">Envoyer ma demande</button></p>
			</form>
			<?php
		}
		$returns = get_posts( array( 'post_type' => self::CPT, 'meta_key' => '_bm_customer_id', 'meta_value' => $user_id, 'posts_per_page' => -1 ) );
		echo '<h3>Mes retours</h3>';
		if ( ! $returns ) {
			echo '<p>Aucune demande de retour. Depuis « Commandes », cliquez sur « Retourner » pour une commande livrée depuis moins de ' . (int) bm_setting( 'return_window', 30 ) . ' jours.</p>';
			return;
		}
		echo '<table class="shop_table"><thead><tr><th>Retour</th><th>Commande</th><th>Statut</th><th>Date</th></tr></thead><tbody>';
		foreach ( $returns as $r ) {
			$st = get_post_meta( $r->ID, '_bm_status', true ) ?: 'demandee';
			echo '<tr><td>n°' . (int) $r->ID . '</td><td>#' . (int) get_post_meta( $r->ID, '_bm_order_id', true ) . '</td><td>' . esc_html( bm_return_statuses()[ $st ]['label'] ?? $st ) . '</td><td>' . esc_html( date_i18n( 'd/m/Y', strtotime( $r->post_date ) ) ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}

	public static function handle_customer_request(): void {
		if ( empty( $_POST['bm_submit_return'] ) || ! is_user_logged_in() ) {
			return;
		}
		if ( ! isset( $_POST['bm_customer_return_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['bm_customer_return_nonce'] ), 'bm_customer_return' ) ) {
			wc_add_notice( 'Session expirée, réessayez.', 'error' );
			return;
		}
		$order = wc_get_order( absint( $_POST['bm_order_id'] ?? 0 ) );
		if ( ! $order || (int) $order->get_customer_id() !== get_current_user_id() || ! self::order_eligible( $order ) ) {
			wc_add_notice( 'Cette commande n\'est pas éligible au retour.', 'error' );
			return;
		}
		$items = array_map( 'intval', (array) ( $_POST['bm_items'] ?? array() ) );
		if ( array_sum( $items ) <= 0 ) {
			wc_add_notice( 'Indiquez au moins un article à retourner.', 'error' );
			return;
		}
		$res = self::create( $order->get_id(), $items, sanitize_key( $_POST['bm_reason'] ?? 'autre' ), sanitize_textarea_field( wp_unslash( $_POST['bm_note'] ?? '' ) ), 'client' );
		if ( is_wp_error( $res ) ) {
			wc_add_notice( $res->get_error_message(), 'error' );
			return;
		}
		wp_safe_redirect( add_query_arg( 'bm_return_ok', '1', wc_get_account_endpoint_url( 'retours' ) ) );
		exit;
	}
}
