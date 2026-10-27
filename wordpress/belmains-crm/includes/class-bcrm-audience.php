<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
class BCRM_Audience {
    private static function table() { global $wpdb; return $wpdb->prefix . 'bcrm_events'; }
    public static function install() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $table = self::table(); $collate = $wpdb->get_charset_collate();
        dbDelta( "CREATE TABLE $table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            event_key varchar(80) NOT NULL,
            recorded_at datetime NOT NULL,
            visitor_hash char(64) NOT NULL,
            session_hash char(64) NOT NULL,
            event_type varchar(30) NOT NULL,
            page_path varchar(190) NOT NULL DEFAULT '/',
            source varchar(100) NOT NULL DEFAULT 'direct',
            device varchar(20) NOT NULL DEFAULT 'desktop',
            PRIMARY KEY  (id),
            UNIQUE KEY event_key (event_key),
            KEY recorded_type (recorded_at,event_type),
            KEY visitor (visitor_hash),
            KEY session (session_hash)
        ) $collate;" );
    }
    public static function register() {
        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
        add_action( 'wp_footer', array( __CLASS__, 'consent' ) );
        add_action( 'bcrm_daily_cleanup', array( __CLASS__, 'cleanup' ) );
        add_action( 'rest_api_init', function () {
            register_rest_route( 'belmains-crm/v1', '/collect', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'collect' ), 'permission_callback' => '__return_true' ) );
        } );
        add_action( 'woocommerce_checkout_order_created', array( __CLASS__, 'attach_consent' ), 10, 1 );
        add_action( 'woocommerce_store_api_checkout_order_processed', array( __CLASS__, 'attach_consent' ), 10, 1 );
        add_action( 'woocommerce_payment_complete', array( __CLASS__, 'purchase' ), 20 );
        add_action( 'woocommerce_order_status_changed', function ( $id, $from, $to ) {
            if ( in_array( $to, array( 'processing', 'completed' ), true ) ) { self::purchase( $id ); }
        }, 20, 3 );
    }
    private static function enabled() { return ! empty( BCRM_App::settings()['analytics_enabled'] ); }
    public static function enqueue() {
        if ( ! self::enabled() || BCRM_App::can_manage() ) { return; }
        wp_enqueue_style( 'bcrm-consent', BCRM_URL . 'assets/audience.css', array(), BCRM_VERSION );
        wp_enqueue_script( 'bcrm-audience', BCRM_URL . 'assets/audience.js', array(), BCRM_VERSION, true );
        wp_localize_script( 'bcrm-audience', 'BelmainsAudience', array( 'endpoint' => esc_url_raw( rest_url( 'belmains-crm/v1/collect' ) ), 'cookiePath' => '/', 'isProduct' => function_exists( 'is_product' ) && is_product(), 'isCheckout' => function_exists( 'is_checkout' ) && is_checkout() && ! is_order_received_page() ) );
    }
    public static function consent() {
        if ( ! self::enabled() || BCRM_App::can_manage() ) { return; }
        echo '<aside id="bcrm-consent" class="bcrm-consent" aria-label="Mesure d’audience" hidden><div><strong>Nous aider à améliorer Belmains</strong><p>Acceptez-vous la mesure des visites et du parcours d’achat ? Ces statistiques restent sur notre site. Aucun outil publicitaire n’est utilisé.</p></div><div class="bcrm-consent-actions"><button type="button" data-bcrm-consent="yes">Accepter</button><button type="button" data-bcrm-consent="no">Refuser</button></div></aside><button type="button" id="bcrm-privacy-choice" class="bcrm-privacy-choice">Mes choix de mesure d’audience</button>';
    }
    private static function cookie_ids() {
        if ( ( $_COOKIE['bcrm_consent'] ?? '' ) !== 'yes' ) { return false; }
        $ids = array( $_COOKIE['bcrm_v'] ?? '', $_COOKIE['bcrm_s'] ?? '' );
        foreach ( $ids as $id ) { if ( ! is_string( $id ) || ! preg_match( '/^[a-f0-9-]{32,40}$/D', $id ) ) { return false; } }
        return $ids;
    }
    private static function hash( $id ) { return hash_hmac( 'sha256', $id, wp_salt( 'auth' ) ); }
    public static function collect( $request ) {
        if ( ! self::enabled() || BCRM_App::can_manage() || is_user_logged_in() && current_user_can( 'edit_posts' ) ) { return new WP_REST_Response( null, 204 ); }
        $host = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
        $origin = $request->get_header( 'origin' ) ?: $request->get_header( 'referer' );
        if ( ! $origin || strtolower( (string) wp_parse_url( $origin, PHP_URL_HOST ) ) !== $host ) { return new WP_Error( 'bcrm_origin', 'Origine non autorisée.', array( 'status' => 403 ) ); }
        if ( strlen( $request->get_body() ) > 2048 ) { return new WP_Error( 'bcrm_size', 'Événement trop long.', array( 'status' => 413 ) ); }
        $ids = self::cookie_ids();
        if ( ! $ids ) { return new WP_Error( 'bcrm_consent', 'Mesure non autorisée.', array( 'status' => 403 ) ); }
        $data = $request->get_json_params();
        $allowed = array( 'pageview', 'product_view', 'add_to_cart', 'checkout' );
        foreach ( array( 'event', 'id', 'path', 'source', 'device' ) as $field ) {
            if ( isset( $data[ $field ] ) && ! is_string( $data[ $field ] ) ) { return new WP_Error( 'bcrm_event', 'Événement invalide.', array( 'status' => 400 ) ); }
        }
        if ( ! is_array( $data ) || ! in_array( $data['event'] ?? '', $allowed, true ) || ! preg_match( '/^[a-f0-9-]{32,40}$/D', (string) ( $data['id'] ?? '' ) ) ) { return new WP_Error( 'bcrm_event', 'Événement invalide.', array( 'status' => 400 ) ); }
        $rate_key = 'bcrm_rate_' . substr( self::hash( $_SERVER['REMOTE_ADDR'] ?? 'local' ), 0, 32 );
        $rate = (int) get_transient( $rate_key );
        if ( $rate >= 120 ) { return new WP_Error( 'bcrm_rate', 'Trop de requêtes.', array( 'status' => 429 ) ); }
        set_transient( $rate_key, $rate + 1, MINUTE_IN_SECONDS );
        // Do not store URL queries, fragments, IP addresses, user agents or customer identities.
        $path = wp_parse_url( (string) ( $data['path'] ?? '/' ), PHP_URL_PATH );
        $path = is_string( $path ) && str_starts_with( $path, '/' ) ? substr( sanitize_text_field( $path ), 0, 190 ) : '/';
        // Checkout/account/order paths may contain private order keys or customer identifiers.
        if ( preg_match( '~/(wp-admin|wp-login|my-account|mon-compte|order-received|view-order|order-pay)(/|\.|$)~i', $path ) ) { $path = '/espace-prive/'; }
        $source = strtolower( substr( preg_replace( '/[^a-zA-Z0-9._-]/', '', (string) ( $data['source'] ?? 'direct' ) ), 0, 100 ) );
        $device = in_array( $data['device'] ?? '', array( 'mobile', 'tablet', 'desktop' ), true ) ? $data['device'] : 'desktop';
        global $wpdb;
        $table = self::table();
        $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO $table (event_key,recorded_at,visitor_hash,session_hash,event_type,page_path,source,device) VALUES (%s,%s,%s,%s,%s,%s,%s,%s)",
            $data['id'], current_time( 'mysql' ), self::hash( $ids[0] ), self::hash( $ids[1] ), $data['event'], $path, $source ?: 'direct', $device ) );
        return new WP_REST_Response( null, 204 );
    }
    public static function attach_consent( $order ) {
        if ( ! self::enabled() || BCRM_App::can_manage() ) { return; }
        $ids = self::cookie_ids(); if ( ! $ids ) { return; }
        if ( ! $order->get_id() ) { return; }
        set_transient( 'bcrm_order_consent_' . $order->get_id(), array( 'visitor' => self::hash( $ids[0] ), 'session' => self::hash( $ids[1] ) ), 7 * DAY_IN_SECONDS );
        // First-party campaign attribution only after consent; no visitor identifier is retained on the order.
        foreach ( array( 'bcrm_src' => '_bcrm_source', 'bcrm_cmp' => '_bcrm_campaign' ) as $cookie => $meta ) {
            $value = substr( preg_replace( '/[^a-zA-Z0-9._-]/', '', (string) ( $_COOKIE[ $cookie ] ?? '' ) ), 0, 100 );
            if ( $value ) { $order->update_meta_data( $meta, $value ); }
        }
        $order->save();
    }
    public static function purchase( $order_id ) {
        if ( ! self::enabled() || ! function_exists( 'wc_get_order' ) ) { return; }
        $order = wc_get_order( $order_id );
        if ( ! $order || ! $order->is_paid() ) { return; }
        $consent = get_transient( 'bcrm_order_consent_' . $order->get_id() );
        if ( ! is_array( $consent ) ) { return; }
        $visitor = $consent['visitor'] ?? ''; $session = $consent['session'] ?? '';
        if ( ! preg_match( '/^[a-f0-9]{64}$/D', (string) $visitor ) || ! preg_match( '/^[a-f0-9]{64}$/D', (string) $session ) ) { return; }
        global $wpdb; $table = self::table();
        $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO $table (event_key,recorded_at,visitor_hash,session_hash,event_type,page_path,source,device) VALUES (%s,%s,%s,%s,'purchase','/commande/','commande','unknown')", self::hash( 'order:' . $order->get_id() ), current_time( 'mysql' ), $visitor, $session ) );
        delete_transient( 'bcrm_order_consent_' . $order->get_id() );
    }
    public static function summary( $from, $to ) {
        global $wpdb; $table = self::table(); $start = $from . ' 00:00:00'; $end = $to . ' 23:59:59';
        $totals = $wpdb->get_row( $wpdb->prepare( "SELECT COUNT(DISTINCT CASE WHEN event_type='pageview' THEN visitor_hash END) visitors, COUNT(DISTINCT CASE WHEN event_type='pageview' THEN session_hash END) sessions,
            SUM(event_type='pageview') pageviews, SUM(event_type='product_view') product_views, SUM(event_type='add_to_cart') add_to_cart, SUM(event_type='checkout') checkout,
            SUM(event_type='purchase') tracked_orders, COUNT(DISTINCT CASE WHEN event_type='purchase' THEN visitor_hash END) buyers
            FROM $table WHERE recorded_at BETWEEN %s AND %s", $start, $end ), ARRAY_A );
        foreach ( $totals as &$value ) { $value = (int) $value; } unset( $value );
        $buyers_seen = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT purchases.visitor_hash) FROM $table purchases WHERE purchases.event_type='purchase' AND purchases.recorded_at BETWEEN %s AND %s AND EXISTS (SELECT 1 FROM $table views WHERE views.visitor_hash=purchases.visitor_hash AND views.event_type='pageview' AND views.recorded_at BETWEEN %s AND %s)", $start, $end, $start, $end ) );
        $totals['conversion'] = $totals['visitors'] ? round( 100 * $buyers_seen / $totals['visitors'], 2 ) : null;
        unset( $totals['buyers'] );
        $totals['enabled'] = self::enabled();
        $totals['daily'] = $wpdb->get_results( $wpdb->prepare( "SELECT DATE(recorded_at) date, COUNT(DISTINCT visitor_hash) visitors, COUNT(*) pageviews FROM $table WHERE recorded_at BETWEEN %s AND %s AND event_type='pageview' GROUP BY DATE(recorded_at) ORDER BY date", $start, $end ), ARRAY_A );
        $totals['sources'] = $wpdb->get_results( $wpdb->prepare( "SELECT source, COUNT(DISTINCT visitor_hash) visitors FROM $table WHERE recorded_at BETWEEN %s AND %s AND event_type='pageview' GROUP BY source ORDER BY visitors DESC LIMIT 12", $start, $end ), ARRAY_A );
        $totals['devices'] = $wpdb->get_results( $wpdb->prepare( "SELECT device, COUNT(DISTINCT visitor_hash) visitors FROM $table WHERE recorded_at BETWEEN %s AND %s AND event_type='pageview' GROUP BY device ORDER BY visitors DESC", $start, $end ), ARRAY_A );
        $totals['coverage_note'] = 'Mesure après consentement, depuis l’installation, administrateurs exclus. Conversion = visiteurs vus et ayant une commande payée tracée sur la période / visiteurs mesurés. Paiement tracé jusqu’à 7 jours après la commande. Les commandes de la maquette ne comptent pas. Conservation des événements : ' . BCRM_App::settings()['retention_days'] . ' jours.';
        return $totals;
    }
    public static function cleanup() {
        global $wpdb; $table = self::table();
        $cutoff = ( new DateTimeImmutable( 'now', wp_timezone() ) )->modify( '-' . (int) BCRM_App::settings()['retention_days'] . ' days' )->format( 'Y-m-d H:i:s' );
        $wpdb->query( $wpdb->prepare( "DELETE FROM $table WHERE recorded_at < %s", $cutoff ) );
    }
}
