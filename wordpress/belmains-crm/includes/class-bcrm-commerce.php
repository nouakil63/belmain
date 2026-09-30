<?php
/**
 * WooCommerce reporting and order tools. All order access uses WooCommerce CRUD.
 *
 * @package BelmainsCRM
 */

defined( 'ABSPATH' ) || exit;

class BCRM_Commerce {

	const PAGE_SIZE = 25;
	const SCAN_LIMIT = 5000;
	private static $scan_cache = array();

	/** Register cache invalidation without depending on the order storage engine. */
	public static function register() {
		foreach ( array( 'woocommerce_new_order', 'woocommerce_update_order', 'woocommerce_delete_order', 'woocommerce_trash_order', 'woocommerce_untrash_order', 'woocommerce_order_refunded', 'woocommerce_refund_deleted', 'woocommerce_new_order_refund', 'woocommerce_update_order_refund' ) as $hook ) {
			add_action( $hook, array( __CLASS__, 'invalidate' ) );
		}
	}

	public static function invalidate() {
		self::$scan_cache = array();
		update_option( 'bcrm_commerce_cache_version', wp_generate_uuid4(), false );
	}

	private static function available() {
		return function_exists( 'wc_get_orders' ) && function_exists( 'wc_get_order' );
	}

	private static function currency() {
		return function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'EUR';
	}

	private static function money( $amount ) {
		return round( (float) $amount, function_exists( 'wc_get_price_decimals' ) ? wc_get_price_decimals() : 2 );
	}

	private static function error( $code, $message, $status = 400 ) {
		return new WP_Error( $code, $message, array( 'status' => $status ) );
	}

	/** Inclusive calendar dates in the site's timezone, including DST transitions. */
	private static function query_args( $from, $to, $status = '' ) {
		$timezone = wp_timezone();
		$start    = DateTimeImmutable::createFromFormat( '!Y-m-d', (string) $from, $timezone );
		$end      = DateTimeImmutable::createFromFormat( '!Y-m-d', (string) $to, $timezone );
		if ( ! $start || ! $end || $start->format( 'Y-m-d' ) !== $from || $end->format( 'Y-m-d' ) !== $to || $end < $start || $start->diff( $end )->days > 365 ) {
			return self::error( 'bcrm_dates', 'Choisissez une période valide de 366 jours maximum.' );
		}
		$statuses = wc_get_order_statuses();
		if ( '' !== $status ) {
			$status = 'wc-' . preg_replace( '/^wc-/', '', sanitize_key( $status ) );
			if ( ! isset( $statuses[ $status ] ) ) {
				return self::error( 'bcrm_status', 'Statut de commande inconnu.' );
			}
		}
		return array(
			'type'         => 'shop_order',
			'status'       => '' === $status ? array_keys( $statuses ) : array( $status ),
			'date_created' => $start->getTimestamp() . '...' . $end->setTime( 23, 59, 59 )->getTimestamp(),
			'orderby'      => 'date',
			'order'        => 'DESC',
			'return'       => 'objects',
		);
	}

	/**
	 * Bounded source for aggregates, filtered searches, exports and shipment tools.
	 * Consumers must retain warnings and never describe capped values as complete.
	 */
	public static function scan_orders( $from, $to, $status = '' ) {
		if ( ! self::available() ) {
			return array( 'orders' => array(), 'total' => 0, 'capped' => false, 'warnings' => array( 'WooCommerce doit être activé pour afficher les commandes.' ) );
		}
		$args = self::query_args( $from, $to, $status );
		if ( is_wp_error( $args ) ) {
			return $args;
		}
		$cache_key = hash( 'sha256', wp_json_encode( $args ) );
		if ( isset( self::$scan_cache[ $cache_key ] ) ) {
			return self::$scan_cache[ $cache_key ];
		}
		$orders = array();
		$total  = 0;
		for ( $page = 1; $page <= (int) ceil( self::SCAN_LIMIT / 100 ); $page++ ) {
			$result = wc_get_orders( array_merge( $args, array( 'limit' => 100, 'paged' => $page, 'paginate' => true ) ) );
			$total  = (int) $result->total;
			foreach ( $result->orders as $order ) {
				$orders[] = $order;
			}
			if ( empty( $result->orders ) || count( $orders ) >= $total ) {
				break;
			}
		}
		$capped = $total > count( $orders );
		$result = array(
			'orders'   => $orders,
			'total'    => $total,
			'capped'   => $capped,
			'warnings' => $capped ? array( sprintf( 'Résultats partiels : seules les %1$d commandes les plus récentes sur %2$d sont analysées. Réduisez la période pour obtenir des chiffres complets.', count( $orders ), $total ) ) : array(),
		);
		// Keep memory bounded even if another component requests many periods at once.
		if ( count( self::$scan_cache ) >= 4 ) {
			array_shift( self::$scan_cache );
		}
		self::$scan_cache[ $cache_key ] = $result;
		return $result;
	}

	/** Paid historical orders remain in the revenue cohort after a refund. */
	private static function settled( $order ) {
		return $order->is_paid() || null !== $order->get_date_paid() || (float) $order->get_total_refunded() > 0;
	}

	private static function refund_ex_tax( $order ) {
		return (float) $order->get_total_refunded() - (float) $order->get_total_tax_refunded();
	}

	private static function net_revenue( $order ) {
		return (float) $order->get_total() - (float) $order->get_total_tax() - self::refund_ex_tax( $order );
	}

	private static function customer_key( $order ) {
		$email = strtolower( trim( (string) $order->get_billing_email() ) );
		if ( '' !== $email ) {
			return 'email_' . hash( 'sha256', $email );
		}
		return $order->get_customer_id() ? 'user_' . $order->get_customer_id() : 'order_' . $order->get_id();
	}

	private static function customer_name( $order ) {
		$name = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
		return '' !== $name ? $name : ( $order->get_billing_company() ?: 'Client sans nom' );
	}

	/** Costs are manually recorded historical totals; blank is unknown, not zero. */
	private static function costs( $order ) {
		$costs = array();
		foreach ( array( 'goods', 'shipping', 'packaging', 'fees' ) as $key ) {
			$value         = $order->get_meta( '_bcrm_cost_' . $key, true );
			$costs[ $key ] = '' !== $value && is_numeric( $value ) && is_finite( (float) $value ) && (float) $value >= 0 ? (float) $value : null;
		}
		return $costs;
	}

	public static function summary( $from, $to ) {
		$currency = self::currency();
		$empty    = array(
			'available' => self::available(),
			'currency'  => $currency,
			'metrics'   => array(
				'orders_total' => 0, 'orders_paid' => 0, 'orders_pending' => 0,
				'orders_failed' => 0, 'orders_cancelled' => 0, 'orders_refunded' => 0,
				'net_revenue' => 0, 'refund_total' => 0, 'average_order' => 0,
				'units_sold' => 0, 'customers' => 0, 'returning_customers' => 0,
				'profit' => null, 'profit_coverage' => 0,
			),
			'daily' => array(), 'statuses' => array(), 'products' => array(), 'warnings' => array(),
		);
		if ( ! $empty['available'] ) {
			$empty['warnings'][] = 'WooCommerce doit être activé pour afficher les ventes.';
			return $empty;
		}
		$args = self::query_args( $from, $to );
		if ( is_wp_error( $args ) ) {
			return $args;
		}
		$cache_key = 'bcrm_summary_' . md5( $from . '|' . $to . '|' . $currency . '|' . get_option( 'bcrm_commerce_cache_version', '1' ) );
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		$scan = self::scan_orders( $from, $to );
		if ( is_wp_error( $scan ) ) {
			return $scan;
		}
		$summary             = $empty;
		$summary['warnings'] = $scan['warnings'];
		$summary['partial']  = $scan['capped'];
		$summary['scanned_orders'] = count( $scan['orders'] );
		$summary['matching_orders'] = $scan['total'];
		$metrics             = &$summary['metrics'];
		$daily               = array();
		$statuses            = array();
		$products            = array();
		$buyers              = array();
		$foreign             = array();
		$known_cost_orders   = 0;
		$contribution        = 0;
		$unallocated_refunds = false;
		$missing_refunds     = false;
		$day = new DateTimeImmutable( $from, wp_timezone() );
		$end = new DateTimeImmutable( $to, wp_timezone() );
		while ( $day <= $end ) {
			$key           = $day->format( 'Y-m-d' );
			$daily[ $key ] = array( 'date' => $key, 'orders' => 0, 'revenue' => 0 );
			$day           = $day->modify( '+1 day' );
		}
		foreach ( $scan['orders'] as $order ) {
			if ( $order->get_currency() !== $currency ) {
				$foreign[ $order->get_currency() ] = ( $foreign[ $order->get_currency() ] ?? 0 ) + 1;
				continue;
			}
			$metrics['orders_total']++;
			$status              = $order->get_status();
			$statuses[ $status ] = ( $statuses[ $status ] ?? 0 ) + 1;
			foreach ( array( 'pending' => 'orders_pending', 'on-hold' => 'orders_pending', 'failed' => 'orders_failed', 'cancelled' => 'orders_cancelled' ) as $value => $metric ) {
				if ( $status === $value ) {
					$metrics[ $metric ]++;
				}
			}
			if ( (float) $order->get_total_refunded() > 0 || 'refunded' === $status ) {
				$metrics['orders_refunded']++;
			}
			if ( 'refunded' === $status && (float) $order->get_total_refunded() <= 0 ) {
				$missing_refunds = true;
			}
			if ( ! self::settled( $order ) ) {
				continue;
			}
			$metrics['orders_paid']++;
			$revenue = self::net_revenue( $order );
			$metrics['net_revenue']  += $revenue;
			$metrics['refund_total'] += self::refund_ex_tax( $order );
			$created = $order->get_date_created();
			$date    = $created ? wp_date( 'Y-m-d', $created->getTimestamp(), wp_timezone() ) : '';
			if ( isset( $daily[ $date ] ) ) {
				$daily[ $date ]['orders']++;
				$daily[ $date ]['revenue'] += $revenue;
			}
			$buyer = self::customer_key( $order );
			$buyers[ $buyer ] = ( $buyers[ $buyer ] ?? 0 ) + 1;
			$costs = self::costs( $order );
			if ( ! in_array( null, $costs, true ) ) {
				$known_cost_orders++;
				$contribution += $revenue - array_sum( $costs );
			}
			$allocated_refund = 0;
			foreach ( $order->get_items( 'line_item' ) as $item_id => $item ) {
				$quantity    = max( 0, (float) $item->get_quantity() - abs( (float) $order->get_qty_refunded_for_item( $item_id ) ) );
				$line_refund = abs( (float) $order->get_total_refunded_for_item( $item_id ) );
				$allocated_refund += $line_refund;
				$line_total  = (float) $item->get_total() - $line_refund;
				$product_id  = $item->get_variation_id() ?: $item->get_product_id();
				$product_key = $product_id ? (string) $product_id : 'deleted_' . hash( 'sha256', $item->get_name() );
				if ( ! isset( $products[ $product_key ] ) ) {
					$products[ $product_key ] = array( 'id' => $product_id, 'name' => $item->get_name(), 'quantity' => 0, 'revenue' => 0 );
				}
				$products[ $product_key ]['quantity'] += $quantity;
				$products[ $product_key ]['revenue']  += $line_total;
				$metrics['units_sold'] += $quantity;
			}
			// Global amount-only refunds cannot be attributed to a particular product.
			foreach ( $order->get_items( 'fee' ) as $item_id => $item ) {
				$allocated_refund += abs( (float) $order->get_total_refunded_for_item( $item_id, 'fee' ) );
			}
			$allocated_refund += abs( (float) $order->get_total_shipping_refunded() );
			if ( self::refund_ex_tax( $order ) - $allocated_refund > 0.01 ) {
				$unallocated_refunds = true;
			}
		}
		$metrics['net_revenue']         = self::money( $metrics['net_revenue'] );
		$metrics['refund_total']        = self::money( $metrics['refund_total'] );
		$metrics['average_order']       = $metrics['orders_paid'] ? self::money( $metrics['net_revenue'] / $metrics['orders_paid'] ) : 0;
		$metrics['customers']           = count( $buyers );
		$metrics['returning_customers'] = count( array_filter( $buyers, static function ( $count ) { return $count > 1; } ) );
		$metrics['profit_coverage']     = $metrics['orders_paid'] ? round( 100 * $known_cost_orders / $metrics['orders_paid'], 1 ) : 0;
		$metrics['profit']              = $metrics['orders_paid'] && $known_cost_orders === $metrics['orders_paid'] && ! $scan['capped'] ? self::money( $contribution ) : null;
		foreach ( $daily as &$day_row ) {
			$day_row['revenue'] = self::money( $day_row['revenue'] );
		}
		unset( $day_row );
		$summary['daily'] = array_values( $daily );
		foreach ( $statuses as $status => $count ) {
			$summary['statuses'][] = array( 'key' => $status, 'label' => wc_get_order_status_name( $status ), 'count' => $count );
		}
		foreach ( $products as &$product_row ) {
			$product_row['revenue'] = self::money( $product_row['revenue'] );
		}
		unset( $product_row );
		usort( $products, static function ( $a, $b ) { return $b['revenue'] <=> $a['revenue']; } );
		$summary['products'] = $products;
		if ( $foreign ) {
			$summary['warnings'][] = sprintf( '%1$d commande(s) en %2$s exclue(s) de ces indicateurs : seules les commandes en %3$s sont agrégées.', array_sum( $foreign ), implode( ', ', array_keys( $foreign ) ), $currency );
		}
		if ( $unallocated_refunds ) {
			$summary['warnings'][] = 'Des remboursements sans détail produit sont inclus dans le chiffre d’affaires net, mais ne peuvent pas être répartis entre les produits ou les quantités.';
		}
		if ( $missing_refunds ) {
			$summary['warnings'][] = 'Une commande porte le statut « Remboursée » sans remboursement enregistré. Son montant remboursé ne peut pas être déduit automatiquement ; vérifiez la commande dans WooCommerce.';
		}
		$summary['warnings'][] = 'Ventes hors taxes, nettes de tous les remboursements connus, rattachées à la date de création des commandes de la période. Les clients récurrents ont plusieurs commandes payées dans cette période.';
		if ( $metrics['orders_paid'] ) {
			$summary['warnings'][] = $known_cost_orders === $metrics['orders_paid']
				? 'La marge contributive déduit les coûts saisis par commande (produits, expédition, emballage, paiement), hors publicité et charges fixes. Après un retour, ajustez les coûts aux montants réellement supportés.'
				: 'Marge indisponible : renseignez les quatre coûts historiques de chaque commande payée, y compris zéro si un coût est nul. Les prix de vente ne sont jamais utilisés comme coûts.';
		}
		set_transient( $cache_key, $summary, 60 );
		return $summary;
	}

	public static function serialize_order( $order ) {
		$created  = $order->get_date_created();
		$tracking = class_exists( 'BCRM_Tracking' ) ? BCRM_Tracking::get( $order ) : array(
			'number' => '', 'carrier' => '', 'url' => '', 'status' => 'pending',
			'status_label' => 'À préparer', 'shipped_at' => null, 'delivered_at' => null, 'source' => '',
		);
		return array(
			'id' => $order->get_id(), 'number' => $order->get_order_number(),
			'date' => $created ? $created->format( DATE_ATOM ) : null,
			'customer' => self::customer_name( $order ), 'email' => $order->get_billing_email(),
			'status' => $order->get_status(), 'status_label' => wc_get_order_status_name( $order->get_status() ),
			'total' => self::money( $order->get_total() ), 'currency' => $order->get_currency(),
			'items_count' => $order->get_item_count(), 'edit_url' => $order->get_edit_order_url(),
			'tracking' => $tracking,
		);
	}

	private static function matches_order( $order, $search ) {
		$haystack = implode( ' ', array( $order->get_id(), $order->get_order_number(), self::customer_name( $order ), $order->get_billing_email(), $order->get_billing_phone() ) );
		return false !== stripos( remove_accents( $haystack ), remove_accents( $search ) );
	}

	public static function orders( $from, $to, $page = 1, $search = '', $status = '' ) {
		$page   = max( 1, (int) $page );
		$search = trim( sanitize_text_field( (string) $search ) );
		$empty  = array( 'items' => array(), 'total' => 0, 'pages' => 0, 'page' => $page, 'warnings' => array() );
		if ( ! self::available() ) {
			$empty['warnings'][] = 'WooCommerce doit être activé pour afficher les commandes.';
			return $empty;
		}
		$args = self::query_args( $from, $to, $status );
		if ( is_wp_error( $args ) ) {
			return $args;
		}
		if ( '' === $search ) {
			$result = wc_get_orders( array_merge( $args, array( 'limit' => self::PAGE_SIZE, 'paged' => $page, 'paginate' => true ) ) );
			return array( 'items' => array_map( array( __CLASS__, 'serialize_order' ), $result->orders ), 'total' => (int) $result->total, 'pages' => (int) $result->max_num_pages, 'page' => $page, 'warnings' => array() );
		}
		$scan = self::scan_orders( $from, $to, $status );
		if ( is_wp_error( $scan ) ) {
			return $scan;
		}
		$matched = array_values( array_filter( $scan['orders'], static function ( $order ) use ( $search ) { return self::matches_order( $order, $search ); } ) );
		$total   = count( $matched );
		return array(
			'items' => array_map( array( __CLASS__, 'serialize_order' ), array_slice( $matched, ( $page - 1 ) * self::PAGE_SIZE, self::PAGE_SIZE ) ),
			'total' => $total, 'pages' => (int) ceil( $total / self::PAGE_SIZE ), 'page' => $page, 'warnings' => $scan['warnings'], 'partial' => $scan['capped'],
		);
	}

	private static function find_order( $id ) {
		if ( ! self::available() ) {
			return self::error( 'bcrm_woocommerce', 'WooCommerce doit être activé.', 503 );
		}
		$order = wc_get_order( absint( $id ) );
		if ( ! $order || ! is_a( $order, 'WC_Order' ) || 'shop_order' !== $order->get_type() || 'trash' === $order->get_status() ) {
			return self::error( 'bcrm_order_missing', 'Commande introuvable.', 404 );
		}
		return $order;
	}

	public static function order( $id ) {
		$order = self::find_order( $id );
		if ( is_wp_error( $order ) ) {
			return $order;
		}
		$result = self::serialize_order( $order );
		$result['items'] = array();
		foreach ( $order->get_items() as $item ) {
			$result['items'][] = array( 'name' => $item->get_name(), 'quantity' => $item->get_quantity(), 'total' => self::money( $item->get_total() ) );
		}
		$address = $order->get_address( 'shipping' );
		if ( empty( $address['address_1'] ) ) {
			$address = $order->get_address( 'billing' );
		}
		$result['shipping_address'] = trim( implode( "\n", array_filter( array(
			trim( ( $address['first_name'] ?? '' ) . ' ' . ( $address['last_name'] ?? '' ) ),
			$address['company'] ?? '', $address['address_1'] ?? '', $address['address_2'] ?? '',
			trim( ( $address['postcode'] ?? '' ) . ' ' . ( $address['city'] ?? '' ) ),
			$address['state'] ?? '', $address['country'] ?? '',
		) ) ) );
		$result['billing_phone'] = $order->get_billing_phone();
		$result['costs'] = self::costs( $order );
		$result['customer_note'] = $order->get_customer_note();
		$result['notes'] = array();
		$notes = wc_get_order_notes( array( 'order_id' => $order->get_id(), 'limit' => 100, 'type' => 'internal', 'order' => 'DESC' ) );
		foreach ( $notes as $note ) {
			$result['notes'][] = array( 'date' => $note->date_created ? $note->date_created->format( DATE_ATOM ) : null, 'content' => wp_strip_all_tags( $note->content ) );
		}
		$result['notes_limit'] = 100;
		return $result;
	}

	public static function customers( $from, $to, $page = 1, $search = '' ) {
		$page     = max( 1, (int) $page );
		$scan     = self::scan_orders( $from, $to );
		if ( is_wp_error( $scan ) ) {
			return $scan;
		}
		$currency = self::currency();
		$clients  = array();
		$foreign  = 0;
		foreach ( $scan['orders'] as $order ) {
			if ( $order->get_currency() !== $currency ) {
				$foreign++;
				continue;
			}
			$key     = self::customer_key( $order );
			$created = $order->get_date_created();
			$date    = $created ? $created->format( DATE_ATOM ) : null;
			if ( ! isset( $clients[ $key ] ) ) {
				$clients[ $key ] = array( 'key' => $key, 'name' => self::customer_name( $order ), 'email' => $order->get_billing_email(), 'phone' => $order->get_billing_phone(), 'orders' => 0, 'paid_orders' => 0, 'spent' => 0, 'first_order' => $date, 'last_order' => $date, 'segment' => 'Nouveau sur la période', 'currency' => $currency );
			}
			$client = &$clients[ $key ];
			$client['orders']++;
			if ( $date && ( ! $client['first_order'] || strtotime( $date ) < strtotime( $client['first_order'] ) ) ) {
				$client['first_order'] = $date;
			}
			if ( $date && ( ! $client['last_order'] || strtotime( $date ) > strtotime( $client['last_order'] ) ) ) {
				$client['last_order'] = $date;
			}
			if ( self::settled( $order ) ) {
				$client['paid_orders']++;
				$client['spent'] += self::net_revenue( $order );
			}
			unset( $client );
		}
		$search = trim( sanitize_text_field( (string) $search ) );
		foreach ( $clients as $key => &$client ) {
			$client['spent'] = self::money( $client['spent'] );
			$client['segment'] = $client['paid_orders'] > 1 ? 'Récurrent sur la période' : ( $client['paid_orders'] ? 'Un achat sur la période' : 'Sans achat payé' );
			if ( '' !== $search && false === stripos( remove_accents( $client['name'] . ' ' . $client['email'] . ' ' . $client['phone'] ), remove_accents( $search ) ) ) {
				unset( $clients[ $key ] );
			}
		}
		unset( $client );
		$clients = array_values( $clients );
		usort( $clients, static function ( $a, $b ) { return strcmp( (string) $b['last_order'], (string) $a['last_order'] ); } );
		$total = count( $clients );
		$warnings = $scan['warnings'];
		$warnings[] = 'Clients identifiés par leur e-mail de commande, y compris les invités. Commandes, premier et dernier achat limités à la période ; dépenses payées hors taxes et après remboursements, pas une valeur à vie.';
		if ( $foreign ) {
			$warnings[] = sprintf( '%1$d commande(s) dans une autre devise exclue(s) ; montants uniquement en %2$s.', $foreign, $currency );
		}
		return array( 'items' => array_slice( $clients, ( $page - 1 ) * self::PAGE_SIZE, self::PAGE_SIZE ), 'total' => $total, 'pages' => (int) ceil( $total / self::PAGE_SIZE ), 'page' => $page, 'warnings' => $warnings, 'partial' => $scan['capped'], 'currency' => $currency );
	}

	/** Paginate both products and variations without a query against Woo's tables. */
	public static function stock( $page = 1, $search = '' ) {
		$page = max( 1, (int) $page );
		$empty = array( 'items' => array(), 'total' => 0, 'pages' => 0, 'page' => $page, 'warnings' => array() );
		if ( ! function_exists( 'wc_get_products' ) ) {
			$empty['warnings'][] = 'WooCommerce doit être activé pour afficher les stocks.';
			return $empty;
		}
		$args = array( 'status' => array( 'publish', 'private' ), 'orderby' => 'ID', 'order' => 'ASC', 'paginate' => true, 'limit' => 1, 'return' => 'ids' );
		$search = trim( sanitize_text_field( (string) $search ) );
		$warnings = array();
		if ( '' !== $search ) {
			$store = WC_Data_Store::load( 'product' );
			$ids = $store->search_products( $search, '', true, false, self::SCAN_LIMIT + 1 );
			if ( ! $ids ) {
				return $empty;
			}
			if ( count( $ids ) > self::SCAN_LIMIT ) {
				$warnings[] = 'Recherche partielle : seuls 5 000 produits ou variations sont retenus. Précisez votre recherche.';
				$ids = array_slice( $ids, 0, self::SCAN_LIMIT );
			}
			$args['include'] = $ids;
		}
		$types = array_keys( wc_get_product_types() );
		$normal = wc_get_products( array_merge( $args, array( 'type' => $types ) ) );
		$variations = wc_get_products( array_merge( $args, array( 'type' => 'variation' ) ) );
		$total = (int) $normal->total + (int) $variations->total;
		$offset = ( $page - 1 ) * self::PAGE_SIZE;
		$ids = array();
		if ( $offset < (int) $normal->total ) {
			$normal_page = wc_get_products( array_merge( $args, array( 'type' => $types, 'offset' => $offset, 'limit' => min( self::PAGE_SIZE, (int) $normal->total - $offset ), 'paginate' => false ) ) );
			$ids = array_merge( $ids, $normal_page );
		}
		if ( count( $ids ) < self::PAGE_SIZE && $total > $offset ) {
			$variation_page = wc_get_products( array_merge( $args, array( 'type' => 'variation', 'offset' => max( 0, $offset - (int) $normal->total ), 'limit' => self::PAGE_SIZE - count( $ids ), 'paginate' => false ) ) );
			$ids = array_merge( $ids, $variation_page );
		}
		$items = array();
		$shared_stock = false;
		foreach ( $ids as $id ) {
			$product = wc_get_product( $id );
			if ( ! $product ) {
				continue;
			}
			$owner_id = $product->get_stock_managed_by_id();
			$owner = $owner_id !== $product->get_id() ? wc_get_product( $owner_id ) : $product;
			$managed = $owner && $owner->managing_stock();
			$quantity = $managed ? $owner->get_stock_quantity() : null;
			$shared_stock = $shared_stock || $owner_id !== $product->get_id();
			$items[] = array(
				'id' => $product->get_id(), 'name' => $product->get_name(), 'sku' => $product->get_sku(),
				'quantity' => null === $quantity ? null : (float) $quantity,
				'status' => $product->get_stock_status(), 'manage_stock' => (bool) $managed,
				'price' => '' === $product->get_price() ? null : self::money( $product->get_price() ),
				'currency' => self::currency(), 'edit_url' => get_edit_post_link( $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id(), 'raw' ),
				'stock_owner_id' => $owner_id, 'shared_stock' => $owner_id !== $product->get_id(),
			);
		}
		if ( $shared_stock ) {
			$warnings[] = 'Certaines variations partagent le stock de leur produit parent ; leurs quantités ne doivent pas être additionnées.';
		}
		return array( 'items' => $items, 'total' => $total, 'pages' => (int) ceil( $total / self::PAGE_SIZE ), 'page' => $page, 'warnings' => $warnings );
	}

	public static function save_costs( $id, $data ) {
		$order = self::find_order( $id );
		if ( is_wp_error( $order ) ) {
			return $order;
		}
		if ( ! is_array( $data ) ) {
			return self::error( 'bcrm_cost', 'Le détail des coûts doit être un objet.' );
		}
		$validated = array();
		foreach ( array( 'goods', 'shipping', 'packaging', 'fees' ) as $key ) {
			if ( ! array_key_exists( $key, $data ) ) {
				continue;
			}
			$value = $data[ $key ];
			if ( null === $value || ( is_string( $value ) && '' === trim( $value ) ) ) {
				$validated[ $key ] = null;
				continue;
			}
			if ( ! is_int( $value ) && ! is_float( $value ) && ! is_string( $value ) ) {
				return self::error( 'bcrm_cost', 'Les coûts doivent être des montants positifs ou nuls.' );
			}
			$value = str_replace( ',', '.', trim( (string) $value ) );
			if ( ! preg_match( '/^\d+(?:\.\d{1,6})?$/D', $value ) || ! is_finite( (float) $value ) || (float) $value > 1000000000 ) {
				return self::error( 'bcrm_cost', 'Chaque coût doit être un montant positif ou nul, sans symbole de devise.' );
			}
			$validated[ $key ] = wc_format_decimal( $value, wc_get_price_decimals() );
		}
		if ( ! $validated ) {
			return self::error( 'bcrm_cost', 'Aucun coût à enregistrer.' );
		}
		foreach ( $validated as $key => $value ) {
			if ( null === $value ) {
				$order->delete_meta_data( '_bcrm_cost_' . $key );
			} else {
				$order->update_meta_data( '_bcrm_cost_' . $key, $value );
			}
		}
		$order->save();
		self::invalidate();
		return array( 'success' => true );
	}

	public static function add_note( $id, $note ) {
		$order = self::find_order( $id );
		if ( is_wp_error( $order ) ) {
			return $order;
		}
		if ( ! is_string( $note ) ) {
			return self::error( 'bcrm_note', 'Saisissez une note interne.' );
		}
		$note = trim( sanitize_textarea_field( wp_strip_all_tags( $note ) ) );
		if ( '' === $note || strlen( $note ) > 10000 ) {
			return self::error( 'bcrm_note', 'La note doit contenir entre 1 et 10 000 caractères.' );
		}
		// false explicitly means an internal note: no customer email is triggered.
		$note_id = $order->add_order_note( $note, false, true );
		if ( ! $note_id ) {
			return self::error( 'bcrm_note_save', 'La note n’a pas pu être enregistrée.', 500 );
		}
		return array( 'success' => true );
	}
}
