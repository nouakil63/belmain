<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class BCRM_App {
    public static function settings() {
        return wp_parse_args( (array) get_option( 'bcrm_settings', array() ), array(
            'analytics_enabled' => true, 'retention_days' => 90, 'low_stock_threshold' => 5, 'shipping_sla_days' => 3,
        ) );
    }
    public static function activate() {
        BCRM_Audience::install();
        BCRM_Support::install();
        BCRM_Marketing::install();
        add_option( 'bcrm_settings', self::settings(), '', false );
        if ( ! wp_next_scheduled( 'bcrm_daily_cleanup' ) ) { wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'bcrm_daily_cleanup' ); }
        update_option( 'bcrm_db_version', BCRM_VERSION, false );
    }
    public static function can_manage() {
        return current_user_can( 'manage_woocommerce' ) || current_user_can( 'manage_options' );
    }
    public static function register() {
        if ( get_option( 'bcrm_db_version' ) !== BCRM_VERSION ) { self::activate(); }
        BCRM_Audience::register();
        BCRM_Tracking::register();
        if ( is_callable( array( 'BCRM_Commerce', 'register' ) ) ) { BCRM_Commerce::register(); }
        add_action( 'admin_menu', function () {
            $cap = current_user_can( 'manage_options' ) ? 'manage_options' : 'manage_woocommerce';
            add_menu_page( 'Belmains — Pilotage', 'Belmains CRM', $cap, 'belmains-crm', array( __CLASS__, 'render' ), 'dashicons-chart-area', 3 );
        } );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
        add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
        add_filter( 'rest_post_dispatch', function ( $response, $server, $request ) {
            if ( str_starts_with( $request->get_route(), '/belmains-crm/v1/' ) ) {
                $response->header( 'Cache-Control', 'no-store, private' );
            }
            return $response;
        }, 10, 3 );
    }
    public static function render() {
        if ( ! self::can_manage() ) { wp_die( 'Accès non autorisé.', '', array( 'response' => 403 ) ); }
        echo '<div id="bcrm-app"><p role="status">Chargement du tableau de bord Belmains…</p><noscript>Activez JavaScript pour consulter votre tableau de bord.</noscript></div>';
    }
    public static function assets( $hook ) {
        if ( 'toplevel_page_belmains-crm' !== $hook ) { return; }
        wp_enqueue_style( 'bcrm-admin', BCRM_URL . 'assets/admin.css', array(), BCRM_VERSION );
        wp_enqueue_script( 'bcrm-admin', BCRM_URL . 'assets/admin.js', array(), BCRM_VERSION, true );
        wp_localize_script( 'bcrm-admin', 'BelmainsCRM', array(
            'api' => esc_url_raw( rest_url( 'belmains-crm/v1/' ) ), 'nonce' => wp_create_nonce( 'wp_rest' ),
            'currency' => function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'EUR',
            'siteName' => get_bloginfo( 'name' ), 'wooUrl' => admin_url( 'admin.php?page=wc-orders' ),
            'settingsUrl' => admin_url( 'admin.php?page=wc-settings&tab=advanced&section=keys' ), 'ajaxUrl' => admin_url( 'admin-ajax.php' ),
        ) );
    }
    public static function range( $request ) {
        $to = $request->get_param( 'to' ) ?: wp_date( 'Y-m-d' );
        $from = $request->get_param( 'from' ) ?: ( new DateTimeImmutable( 'today', wp_timezone() ) )->modify( '-29 days' )->format( 'Y-m-d' );
        foreach ( array( $from, $to ) as $value ) {
            if ( ! is_string( $value ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/D', $value ) ) { return new WP_Error( 'bcrm_date', 'La période est invalide.', array( 'status' => 400 ) ); }
            $date = DateTimeImmutable::createFromFormat( '!Y-m-d', $value, wp_timezone() );
            if ( ! $date || $date->format( 'Y-m-d' ) !== $value ) { return new WP_Error( 'bcrm_date', 'La date est invalide.', array( 'status' => 400 ) ); }
        }
        $start = new DateTimeImmutable( $from, wp_timezone() ); $end = new DateTimeImmutable( $to, wp_timezone() );
        if ( $start > $end || $start->diff( $end )->days > 365 ) { return new WP_Error( 'bcrm_range', 'Choisissez une période de 366 jours maximum, dans le bon ordre.', array( 'status' => 400 ) ); }
        return array( $from, $to );
    }
    private static function data( $request ) {
        $data = $request->get_json_params();
        return is_array( $data ) ? $data : array();
    }
    private static function list_args( $r ) {
        return array( max( 1, absint( $r->get_param( 'page' ) ?: 1 ) ), substr( sanitize_text_field( (string) $r->get_param( 'search' ) ), 0, 100 ), sanitize_key( (string) $r->get_param( 'status' ) ) );
    }
    public static function routes() {
        $private = array( __CLASS__, 'can_manage' );
        $route = function ( $path, $method, $callback, $permission = null ) use ( $private ) {
            register_rest_route( 'belmains-crm/v1', $path, array( 'methods' => $method, 'callback' => $callback, 'permission_callback' => $permission ?: $private ) );
        };
        $route( '/dashboard', 'GET', function ( $r ) {
            $range = self::range( $r ); if ( is_wp_error( $range ) ) { return $range; }
            $integration = BCRM_Tracking::integration();
            return array( 'commerce' => BCRM_Commerce::summary( ...$range ), 'audience' => BCRM_Audience::summary( ...$range ), 'connections' => array(
                'woocommerce' => class_exists( 'WooCommerce' ), 'iziship_last_sync' => $integration['last_sync'], 'site_public_https' => $integration['site_public_https'],
            ), 'generated_at' => wp_date( DATE_ATOM ), 'from' => $range[0], 'to' => $range[1] );
        } );
        foreach ( array( 'orders', 'customers', 'shipments' ) as $kind ) {
            $route( '/' . $kind, 'GET', function ( $r ) use ( $kind ) {
                $range = self::range( $r ); if ( is_wp_error( $range ) ) { return $range; }
                $args = self::list_args( $r );
                if ( 'shipments' === $kind ) { return BCRM_Tracking::shipments( ...array_merge( $range, $args ) ); }
                if ( 'customers' === $kind ) { array_pop( $args ); }
                return BCRM_Commerce::$kind( ...array_merge( $range, $args ) );
            } );
        }
        $route( '/stock', 'GET', function ( $r ) { $a = self::list_args( $r ); return BCRM_Commerce::stock( $a[0], $a[1] ); } );
        $route( '/orders/(?P<id>\d+)', 'GET', function ( $r ) { return BCRM_Commerce::order( absint( $r['id'] ) ); } );
        $route( '/orders/(?P<id>\d+)/costs', 'POST', function ( $r ) { return BCRM_Commerce::save_costs( absint( $r['id'] ), self::data( $r ) ); } );
        $route( '/orders/(?P<id>\d+)/note', 'POST', function ( $r ) {
            $data = self::data( $r ); return BCRM_Commerce::add_note( absint( $r['id'] ), $data['note'] ?? '' );
        } );
        $route( '/orders/(?P<id>\d+)/tracking', 'POST', function ( $r ) { return BCRM_Tracking::save( absint( $r['id'] ), self::data( $r ) ); } );
        $route( '/integration', 'GET', function () { return BCRM_Tracking::integration(); } );
        $route( '/settings', 'GET', function () { return self::settings(); } );
        $route( '/settings', 'POST', function ( $r ) {
            $data = self::data( $r ); $settings = self::settings();
            if ( array_key_exists( 'analytics_enabled', $data ) ) { $settings['analytics_enabled'] = rest_sanitize_boolean( $data['analytics_enabled'] ); }
            foreach ( array( 'retention_days' => array( 30, 365 ), 'low_stock_threshold' => array( 1, 100 ), 'shipping_sla_days' => array( 1, 30 ) ) as $key => $bounds ) {
                if ( isset( $data[ $key ] ) ) {
                    if ( ! is_numeric( $data[ $key ] ) || (int) $data[ $key ] != $data[ $key ] || $data[ $key ] < $bounds[0] || $data[ $key ] > $bounds[1] ) { return new WP_Error( 'bcrm_setting', 'Valeur invalide : ' . $key, array( 'status' => 400 ) ); }
                    $settings[ $key ] = (int) $data[ $key ];
                }
            }
            update_option( 'bcrm_settings', $settings, false ); return $settings;
        }, function () { return current_user_can( 'manage_options' ); } );
        $route( '/tickets', 'GET', function ( $r ) { return BCRM_Support::listing( sanitize_key( (string) $r->get_param( 'status' ) ) ); } );
        $route( '/tickets', 'POST', function ( $r ) { return BCRM_Support::create( self::data( $r ) ); } );
        $route( '/tickets/(?P<id>\d+)', 'POST', function ( $r ) { return BCRM_Support::update( absint( $r['id'] ), self::data( $r ) ); } );
        $route( '/marketing', 'GET', function ( $r ) { $range = self::range( $r ); return is_wp_error( $range ) ? $range : BCRM_Marketing::report( ...$range ); } );
        $route( '/marketing/costs', 'POST', function ( $r ) { return BCRM_Marketing::save_cost( self::data( $r ) ); } );
        $route( '/marketing/costs/(?P<id>\d+)', 'DELETE', function ( $r ) { return BCRM_Marketing::delete_cost( absint( $r['id'] ) ); } );
        $route( '/export', 'GET', array( __CLASS__, 'export' ) );
    }
    public static function export( $r ) {
        $range = self::range( $r ); if ( is_wp_error( $range ) ) { return $range; }
        $type = $r->get_param( 'type' );
        if ( ! in_array( $type, array( 'orders', 'customers' ), true ) ) { return new WP_Error( 'bcrm_export', 'Export inconnu.', array( 'status' => 400 ) ); }
        $rows = array(); $page = 1;
        do {
            $result = BCRM_Commerce::$type( $range[0], $range[1], $page, '' );
            if ( is_wp_error( $result ) ) { return $result; }
            if ( $result['total'] > 5000 || ! empty( $result['partial'] ) ) { return new WP_Error( 'bcrm_export_limit', 'Réduisez la période pour exporter 5 000 lignes maximum.', array( 'status' => 400 ) ); }
            foreach ( $result['items'] as $item ) {
                $rows[] = 'orders' === $type ? array( $item['number'], $item['date'], $item['customer'], $item['email'], $item['status_label'], $item['total'], $item['currency'], $item['tracking']['number'] ?? '' ) : array( $item['name'], $item['email'], $item['orders'], $item['spent'], $item['first_order'], $item['last_order'], $item['segment'] );
            }
            $page++;
        } while ( $page <= $result['pages'] && $page <= 250 );
        $fp = fopen( 'php://temp', 'r+' );
        fwrite( $fp, "\xEF\xBB\xBF" );
        fputcsv( $fp, 'orders' === $type ? array( 'Commande', 'Date', 'Client', 'Email', 'Statut', 'Total TTC', 'Devise', 'Suivi' ) : array( 'Client', 'Email', 'Commandes période', 'Dépenses HT période', 'Première commande période', 'Dernière commande période', 'Segment période' ), ';' );
        foreach ( $rows as $row ) {
            $row = array_map( function ( $v ) { $v = (string) $v; return preg_match( '/^[\s]*[=+\-@\t\r\n]/u', $v ) ? "'" . $v : $v; }, $row );
            fputcsv( $fp, $row, ';' );
        }
        rewind( $fp ); $csv = stream_get_contents( $fp ); fclose( $fp );
        return array( 'filename' => 'belmains-' . $type . '-' . $range[0] . '-' . $range[1] . '.csv', 'csv' => $csv );
    }
}
