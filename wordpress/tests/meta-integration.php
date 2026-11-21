<?php
/**
 * Meta advertising gate checks. Run with wp eval-file ONLY on the isolated bcrm_validation_ installation.
 *
 * Belmains CRM lets the official "Meta for WooCommerce" extension run only after the advertising
 * accord. The suite inspects settings, markup and the decisions handed to that extension. Calls
 * to Meta are recorded, never delivered: the isolated installation blocks external HTTP.
 * With the official extension active the whole suite runs; without it only the closed state is
 * checked. Settings, cookies, options, hooks and fixtures are restored in the finally block.
 */
global $wpdb, $wp_filter;
if ( 'bcrm_validation_' !== $wpdb->prefix || 'local' !== wp_get_environment_type() ) {
    throw new RuntimeException( 'Meta tests require the isolated local validation tables.' );
}
if ( ! class_exists( 'BCRM_Meta' ) || ! function_exists( 'WC' ) ) {
    throw new RuntimeException( 'Activate WooCommerce and Belmains CRM in the isolated copy first.' );
}
add_filter( 'pre_wp_mail', '__return_false' );

$meta_checks = 0;
$meta_check = static function ( $ok, $name ) use ( &$meta_checks ) {
    if ( ! $ok ) { throw new RuntimeException( 'FAIL: ' . $name ); }
    $meta_checks++;
    echo 'PASS ' . $name . "\n";
};
$meta_settings = static function ( $method = 'GET', $data = null ) {
    $request = new WP_REST_Request( $method, '/belmains-crm/v1/settings' );
    if ( null !== $data ) { $request->set_header( 'Content-Type', 'application/json' ); $request->set_body( wp_json_encode( $data ) ); }
    return rest_do_request( $request );
};
$meta_banner = static function () { ob_start(); BCRM_Audience::consent(); return ob_get_clean(); };
$meta_closed = static function () { return true; };
$meta_enabled = static function () { return apply_filters( 'facebook_for_woocommerce_integration_pixel_enabled', true ); };
$meta_held = static function () { return apply_filters( 'facebook_signals_held', false ); };
// Calls attempted towards Meta during the suite. The isolated installation refuses them afterwards.
$meta_calls = array();
$meta_recorder = static function ( $pre, $args, $url ) use ( &$meta_calls ) {
    if ( preg_match( '#^https://graph\.facebook\.com/#', (string) $url ) ) { $meta_calls[] = is_string( $args['body'] ?? null ) ? $args['body'] : wp_json_encode( $args['body'] ?? '' ); }
    return new WP_Error( 'isolated_validation', 'External HTTP blocked.' );
};
/** Hooks a given tracker instance holds, as "hook:method". */
$meta_hooks = static function ( $tracker ) use ( &$wp_filter ) {
    $found = array();
    foreach ( $wp_filter as $hook => $registered ) {
        foreach ( $registered->callbacks as $priority => $callbacks ) {
            foreach ( $callbacks as $callback ) {
                if ( is_array( $callback['function'] ) && $callback['function'][0] === $tracker ) { $found[] = array( $hook, $callback['function'][1], $priority ); }
            }
        }
    }
    return $found;
};

$administrators = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
if ( ! $administrators ) { throw new RuntimeException( 'Administrator fixture missing.' ); }
$admin_id = (int) $administrators[0];
$saved = array(
    'user' => get_current_user_id(), 'cookie' => $_COOKIE, 'method' => $_SERVER['REQUEST_METHOD'] ?? null,
    'settings' => get_option( 'bcrm_settings', null ), 'pixel' => get_option( 'wc_facebook_pixel_id', null ), 'token' => get_option( 'wc_facebook_access_token', null ), 'config' => get_option( 'facebook_config', null ),
    'agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
    'query' => $GLOBALS['wp_query'] ?? null, 'post' => $GLOBALS['post'] ?? null,
);
$fixtures = array( 'products' => array(), 'trackers' => array(), 'filters' => array() );
$pixel = '123456789012345';
$official = BCRM_Meta::installed();

try {
    add_filter( 'pre_http_request', $meta_recorder, PHP_INT_MAX, 3 );
    update_option( 'bcrm_settings', array( 'analytics_enabled' => true ), false );
    delete_option( 'wc_facebook_pixel_id' );
    unset( $_COOKIE['bcrm_ads'], $_COOKIE['bcrm_consent'] );
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Belmains validation)';
    // Placeholder connection so that the official extension attempts its server calls; nothing is delivered.
    if ( $official ) { update_option( 'wc_facebook_access_token', 'VALIDATION_ONLY_TOKEN' ); }

    // Settings: administrators only; the pixel identifier is no longer a Belmains setting.
    wp_set_current_user( 0 );
    $meta_check( 401 === $meta_settings( 'POST', array( 'meta_domain_verification' => '' ) )->get_status(), 'anonymous cannot change the Meta settings' );
    $reader = username_exists( 'crm_validation_reader' ) ?: wp_insert_user( array( 'user_login' => 'crm_validation_reader', 'user_pass' => wp_generate_password( 32 ), 'user_email' => 'reader@example.invalid', 'role' => 'subscriber' ) );
    wp_set_current_user( $reader );
    $meta_check( 403 === $meta_settings( 'POST', array( 'meta_domain_verification' => '' ) )->get_status(), 'subscriber cannot change the Meta settings' );
    wp_set_current_user( $admin_id );
    $initial = $meta_settings()->get_data();
    $meta_check( '' === $initial['meta_domain_verification'] && '' === $initial['meta_pixel'] && ! array_key_exists( 'meta_pixel_id', $initial ), 'no pixel identifier is stored by Belmains CRM' );
    $meta_check( ( $official ? 'unconnected' : 'missing' ) === $initial['meta_status'], 'status tells whether the official extension is absent or not connected' );
    foreach ( array( 'short', 'has space in the verification code', array( 'x' ), '<meta name="facebook-domain-verification" content="bad value!">' ) as $index => $invalid ) {
        $meta_check( 400 === $meta_settings( 'POST', array( 'meta_domain_verification' => $invalid ) )->get_status() && '' === BCRM_App::settings()['meta_domain_verification'], 'invalid domain verification ' . $index . ' rejected and not saved' );
    }
    $response = $meta_settings( 'POST', array( 'meta_pixel_id' => $pixel, 'meta_domain_verification' => '<meta name="facebook-domain-verification" content="abcdefghij0123456789klmnopqrst" />' ) );
    $meta_check( 200 === $response->get_status() && 'abcdefghij0123456789klmnopqrst' === BCRM_App::settings()['meta_domain_verification'], 'verification code extracted from the pasted Meta tag' );
    $meta_check( ! array_key_exists( 'meta_pixel_id', BCRM_App::settings() ) && '' === BCRM_Meta::pixel_id(), 'a posted pixel identifier is ignored: only the official extension provides it' );
    $meta_check( true === BCRM_App::settings()['analytics_enabled'] && 90 === BCRM_App::settings()['retention_days'], 'other preferences are preserved' );

    // Domain ownership tag: front page only, static.
    $front = static function () { return 'posts'; };
    add_filter( 'pre_option_show_on_front', $front );
    $GLOBALS['wp_query'] = new WP_Query();
    ob_start(); BCRM_Meta::domain_verification(); $tag = ob_get_clean();
    $meta_check( '' === $tag, 'no verification tag outside the front page' );
    $GLOBALS['wp_query']->is_home = true;
    ob_start(); BCRM_Meta::domain_verification(); $tag = ob_get_clean();
    $meta_check( '<meta name="facebook-domain-verification" content="abcdefghij0123456789klmnopqrst">' === trim( $tag ), 'verification tag printed on the front page' );

    // Not connected: advertising is not offered and nothing may run, even with a stale accord.
    wp_set_current_user( 0 );
    $_COOKIE['bcrm_ads'] = 'yes';
    $meta_check( ! BCRM_Meta::active() && ! BCRM_Meta::allowed() && null === BCRM_Meta::config(), 'no pixel chosen: advertising is not offered' );
    $meta_check( false === $meta_enabled() && true === $meta_held(), 'no pixel chosen: tracker refused and signals held' );
    $legacy = $meta_banner();
    $meta_check( str_contains( $legacy, 'Aucun outil publicitaire n’est utilisé.' ) && str_contains( $legacy, 'Mes choix de mesure d’audience' ) && ! str_contains( $legacy, 'Meta' ), 'audience-only banner is unchanged while Meta is not connected' );
    unset( $_COOKIE['bcrm_ads'] );

    if ( ! $official ) {
        update_option( 'wc_facebook_pixel_id', $pixel, false );
        $meta_check( '' === BCRM_Meta::pixel_id() && 'missing' === BCRM_Meta::status(), 'a leftover pixel option means nothing without the official extension' );
        echo "SKIP gate, banner and tracker checks: activate the official Meta for WooCommerce extension to run them.\n";
        echo $meta_checks . " Meta gate checks passed (official extension absent).\n";
        return;
    }

    update_option( 'wc_facebook_pixel_id', 'not-a-number', false );
    $meta_check( '' === BCRM_Meta::pixel_id() && 'unconnected' === BCRM_Meta::status(), 'malformed pixel option is treated as not connected' );
    update_option( 'wc_facebook_pixel_id', $pixel, false );
    $meta_check( $pixel === BCRM_Meta::pixel_id() && 'on' === BCRM_Meta::status() && BCRM_Meta::active(), 'pixel chosen in the official extension opens the advertising purpose' );
    wp_set_current_user( $admin_id );
    $connected = $meta_settings()->get_data();
    $meta_check( 'on' === $connected['meta_status'] && $pixel === $connected['meta_pixel'], 'settings report the connected pixel read-only' );
    $meta_check( '' === $meta_banner(), 'no banner for a connected manager' );

    // The gate: explicit "yes" only, on an open shop, never for a manager.
    wp_set_current_user( 0 );
    $meta_check( false === $meta_enabled() && true === $meta_held() && false === BCRM_Meta::config()['live'], 'no answer: tracker refused and signals held' );
    foreach ( array( 'no', '', '1', 'YES', 'true', 'yes ' ) as $value ) {
        $_COOKIE['bcrm_ads'] = $value;
        $meta_check( false === $meta_enabled() && true === $meta_held(), 'cookie value ' . wp_json_encode( $value ) . ' is not an accord' );
    }
    $_COOKIE['bcrm_ads'] = array( 'yes' );
    $meta_check( false === $meta_enabled(), 'malformed cookie is not an accord' );
    $_COOKIE['bcrm_ads'] = 'yes';
    $meta_check( true === $meta_enabled() && false === $meta_held() && true === BCRM_Meta::config()['live'], 'explicit accord lets the official extension run' );
    $meta_check( false === apply_filters( 'facebook_for_woocommerce_integration_pixel_enabled', false ) && true === apply_filters( 'facebook_signals_held', true ), 'an earlier refusal by another component is never overturned' );
    wp_set_current_user( $admin_id );
    $meta_check( false === $meta_enabled() && true === $meta_held(), 'connected manager is never tracked, accord or not' );
    wp_set_current_user( 0 );
    add_filter( 'belmains_catalog_only', $meta_closed );
    $meta_check( false === $meta_enabled() && true === $meta_held() && null === BCRM_Meta::config() && 'closed' === BCRM_Meta::status(), 'protected catalogue keeps Meta closed despite an accord' );
    $closed_banner = $meta_banner();
    $meta_check( str_contains( $closed_banner, 'Aucun outil publicitaire' ) && ! str_contains( $closed_banner, 'data-bcrm-purpose' ), 'protected catalogue never asks for advertising consent' );
    remove_filter( 'belmains_catalog_only', $meta_closed );

    // The official extension asks Meta for the pixel settings from the server: not on a visit without accord.
    $lookup = static function () { return apply_filters( 'pre_transient_wc_facebook_aam_settings', false, 'wc_facebook_aam_settings' ); };
    unset( $_COOKIE['bcrm_ads'] );
    $answer = json_decode( (string) $lookup(), true );
    $meta_check( is_array( $answer ) && $pixel === $answer['pixelId'] && false === $answer['enableAutomaticMatching'], 'without accord the pixel settings lookup is answered locally' );
    $meta_check( 'kept' === apply_filters( 'pre_transient_wc_facebook_aam_settings', 'kept', 'wc_facebook_aam_settings' ), 'an answer already provided is left alone' );
    $_COOKIE['bcrm_ads'] = 'yes';
    $meta_check( false === $lookup(), 'with the accord the official extension reads its real pixel settings' );
    unset( $_COOKIE['bcrm_ads'] );

    // An accord reloads the page once, except where a reload would lose the visitor's input.
    unset( $_COOKIE['bcrm_ads'] );
    $meta_check( true === BCRM_Meta::config()['reload'], 'a page fetched with GET may be reloaded after the accord' );
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $meta_check( false === BCRM_Meta::config()['reload'], 'a page answering a form is never reloaded' );
    $_SERVER['REQUEST_METHOD'] = 'GET';
    add_filter( 'woocommerce_is_checkout', '__return_true' );
    $meta_check( is_checkout() && false === BCRM_Meta::config()['reload'], 'the checkout form is never reloaded' );
    remove_filter( 'woocommerce_is_checkout', '__return_true' );
    $meta_check( true === BCRM_Meta::config()['reload'], 'other pages are reloaded again' );

    // Banner: two separate purposes, nothing ticked, honest about what Meta receives.
    $both = $meta_banner();
    $meta_check( str_contains( $both, 'data-bcrm-purpose="audience"' ) && str_contains( $both, 'data-bcrm-purpose="ads"' ), 'banner separates audience and advertising purposes' );
    $meta_check( str_contains( $both, 'data-bcrm-consent="yes"' ) && str_contains( $both, 'data-bcrm-consent="no"' ) && str_contains( $both, 'data-bcrm-consent="custom"' ), 'accept, refuse and customise are offered together' );
    $meta_check( ! preg_match( '/<input[^>]*\bchecked\b/i', $both ), 'no purpose is ticked in advance' );
    $meta_check( str_contains( $both, 'Meta Platforms Ireland' ) && ! str_contains( $both, 'Aucun outil publicitaire' ), 'banner names Meta and drops the no-advertising statement' );
    $meta_check( str_contains( $both, 'depuis notre serveur' ) && str_contains( $both, 'adresse IP' ) && str_contains( $both, 'hachée' ), 'banner states the server transmission, the IP address and the hashed contact details' );
    $meta_check( ! str_contains( $both, $pixel ) && ! str_contains( $both, 'facebook.net' ) && ! str_contains( $both, '<script' ), 'banner markup loads nothing from Meta' );
    update_option( 'bcrm_settings', array_merge( BCRM_App::settings(), array( 'analytics_enabled' => false ) ), false );
    $ads_only = $meta_banner();
    $meta_check( str_contains( $ads_only, 'Meta Platforms Ireland' ) && ! str_contains( $ads_only, 'data-bcrm-consent="custom"' ) && str_contains( $ads_only, 'data-bcrm-consent="no"' ), 'advertising alone is asked with a plain accept or refuse' );
    delete_option( 'wc_facebook_pixel_id' );
    $meta_check( '' === $meta_banner() && null === BCRM_Meta::config(), 'nothing is asked when both purposes are off' );
    update_option( 'wc_facebook_pixel_id', $pixel, false );
    update_option( 'bcrm_settings', array_merge( BCRM_App::settings(), array( 'analytics_enabled' => true ) ), false );

    // Scripts the official extension enqueues on its own are removed from a page served without accord.
    $handles = array( 'wc-facebook-signals', 'wc-facebook-pixel-events', 'facebook-capi-param-builder' );
    foreach ( $handles as $handle ) { wp_enqueue_script( $handle, 'https://example.invalid/' . $handle . '.js', array(), '1', true ); }
    BCRM_Meta::silence_scripts();
    $meta_check( array() === array_filter( $handles, static function ( $handle ) { return wp_script_is( $handle, 'enqueued' ); } ), 'official scripts are dequeued without accord' );
    foreach ( $handles as $handle ) { wp_enqueue_script( $handle ); }
    $_COOKIE['bcrm_ads'] = 'yes';
    BCRM_Meta::silence_scripts();
    $meta_check( 3 === count( array_filter( $handles, static function ( $handle ) { return wp_script_is( $handle, 'enqueued' ); } ) ), 'official scripts are kept after the accord' );
    foreach ( $handles as $handle ) { wp_dequeue_script( $handle ); wp_deregister_script( $handle ); }

    // The official tracker itself: built empty without accord, complete with it.
    unset( $_COOKIE['bcrm_ads'] );
    $silent = new WC_Facebookcommerce_EventsTracker( array(), null );
    $fixtures['trackers'][] = $silent;
    $meta_check( array() === $meta_hooks( $silent ), 'without accord the official tracker registers no hook at all' );
    $_COOKIE['bcrm_ads'] = 'yes';
    $live = new WC_Facebookcommerce_EventsTracker( array(), null );
    $fixtures['trackers'][] = $live;
    $names = static function () use ( $meta_hooks, $live ) { return array_map( static function ( $entry ) { return $entry[0] . ':' . $entry[1]; }, $meta_hooks( $live ) ); };
    $meta_check( in_array( 'wp_head:inject_base_pixel', $names(), true ) && in_array( 'woocommerce_thankyou:inject_purchase_event', $names(), true ), 'with the accord the official tracker registers its pixel and purchase hooks' );
    $meta_check( in_array( 'woocommerce_new_order:inject_purchase_event', $names(), true ) && in_array( 'woocommerce_checkout_update_order_meta:inject_purchase_event', $names(), true ), 'the official tracker reports a purchase from order creation by default' );
    $keep_official = static function () { return false; };
    add_filter( 'bcrm_meta_purchase_on_confirmation_only', $keep_official );
    BCRM_Meta::purchase_on_confirmation_only();
    $meta_check( in_array( 'woocommerce_new_order:inject_purchase_event', $names(), true ), 'the official purchase timing can be kept with the Belmains filter' );
    remove_filter( 'bcrm_meta_purchase_on_confirmation_only', $keep_official );
    BCRM_Meta::purchase_on_confirmation_only();
    $meta_check( ! in_array( 'woocommerce_new_order:inject_purchase_event', $names(), true ) && ! in_array( 'woocommerce_checkout_update_order_meta:inject_purchase_event', $names(), true ) && in_array( 'woocommerce_thankyou:inject_purchase_event', $names(), true ), 'purchase is reported from the confirmation page only: an unpaid order is not a sale' );

    // The official extension copies the chosen pixel into its own pixel option from the administration.
    update_option( 'facebook_config', array( 'pixel_id' => $pixel ) );
    ob_start(); $live->inject_base_pixel(); $base = ob_get_clean();
    $meta_check( str_contains( $base, 'connect.facebook.net' ) && str_contains( $base, $pixel ), 'with the accord the official pixel is printed for the chosen identifier' );

    // The landing page is the product page: the product view is reported there, accord only.
    $product = new WC_Product_Simple();
    $product->set_name( 'Gant · validation Meta' ); $product->set_sku( 'BCRM-META-VALIDATION' ); $product->set_regular_price( '89.99' ); $product->set_status( 'publish' ); $product->save();
    $fixtures['products'][] = $product->get_id();
    $landing_product = static function () use ( $product ) { return $product->get_id(); };
    $euro = static function () { return 'EUR'; };
    $fixtures['filters'] = array( array( 'pre_option_belmains_product_id', $landing_product ), array( 'pre_option_woocommerce_currency', $euro ) );
    foreach ( $fixtures['filters'] as $filter ) { add_filter( $filter[0], $filter[1] ); }
    if ( function_exists( 'belmains_commerce_product' ) && belmains_commerce_product() ) {
        // The official extension queues the browser event for the footer; its own log of tracked events is read here.
        $viewed = static function () use ( $live ) {
            return array_values( array_filter( $live->get_tracked_events(), static function ( $event ) { return 'ViewContent' === $event->get_name(); } ) );
        };
        $meta_calls = array();
        BCRM_Meta::landing_view_content();
        $event = $viewed()[0] ?? null;
        $meta_check( 1 === count( $viewed() ) && str_contains( (string) ( $event->get_custom_data()['content_ids'] ?? '' ), (string) $product->get_id() ) && 'EUR' === $event->get_custom_data()['currency'], 'landing page hands the product view to the official tracker' );
        $meta_check( str_contains( implode( '', $meta_calls ), '"event_name":"ViewContent"' ) && str_contains( implode( '', $meta_calls ), $event->get_id() ), 'the product view is sent to the server with the browser event identifier' );
        $meta_check( ( $GLOBALS['post'] ?? null ) === $saved['post'], 'the page being displayed is left untouched by the product view' );
        $meta_calls = array();
        unset( $_COOKIE['bcrm_ads'] );
        BCRM_Meta::landing_view_content();
        $meta_check( 1 === count( $viewed() ) && array() === $meta_calls, 'without accord the landing page reports nothing' );
        $_COOKIE['bcrm_ads'] = 'yes';
        $GLOBALS['wp_query'] = new WP_Query();
        BCRM_Meta::landing_view_content();
        $meta_check( 1 === count( $viewed() ) && array() === $meta_calls, 'no product view outside the landing page' );
    } else {
        echo "SKIP landing product view: Belmains Commerce is not active in this copy.\n";
    }

    // Privacy policy wording offered to the owner.
    $guide = new ReflectionMethod( 'BCRM_Meta', 'privacy_policy_content' );
    $source = implode( '', array_slice( file( $guide->getFileName() ), $guide->getStartLine() - 1, $guide->getEndLine() - $guide->getStartLine() + 1 ) );
    $meta_check( str_contains( $source, 'depuis notre serveur' ) && str_contains( $source, 'numéro de commande' ) && str_contains( $source, 'hachée' ), 'suggested privacy wording covers server events, order number and hashed contact details' );

    echo $meta_checks . " Meta gate checks passed with the official extension.\n";
} finally {
    remove_filter( 'pre_http_request', $meta_recorder, PHP_INT_MAX );
    remove_filter( 'belmains_catalog_only', $meta_closed );
    if ( isset( $front ) ) { remove_filter( 'pre_option_show_on_front', $front ); }
    if ( isset( $keep_official ) ) { remove_filter( 'bcrm_meta_purchase_on_confirmation_only', $keep_official ); }
    remove_filter( 'woocommerce_is_checkout', '__return_true' );
    foreach ( $fixtures['filters'] as $filter ) { remove_filter( $filter[0], $filter[1] ); }
    foreach ( $fixtures['trackers'] as $tracker ) { foreach ( $meta_hooks( $tracker ) as $entry ) { remove_filter( $entry[0], array( $tracker, $entry[1] ), $entry[2] ); } }
    $_COOKIE = $saved['cookie'];
    if ( null === $saved['method'] ) { unset( $_SERVER['REQUEST_METHOD'] ); } else { $_SERVER['REQUEST_METHOD'] = $saved['method']; }
    $GLOBALS['wp_query'] = $saved['query']; $GLOBALS['post'] = $saved['post'];
    foreach ( $fixtures['products'] as $id ) { $fixture = wc_get_product( $id ); if ( $fixture ) { $fixture->delete( true ); } }
    if ( null === $saved['settings'] ) { delete_option( 'bcrm_settings' ); } else { update_option( 'bcrm_settings', $saved['settings'], false ); }
    if ( null === $saved['pixel'] ) { delete_option( 'wc_facebook_pixel_id' ); } else { update_option( 'wc_facebook_pixel_id', $saved['pixel'] ); }
    if ( null === $saved['token'] ) { delete_option( 'wc_facebook_access_token' ); } else { update_option( 'wc_facebook_access_token', $saved['token'] ); }
    if ( null === $saved['config'] ) { delete_option( 'facebook_config' ); } else { update_option( 'facebook_config', $saved['config'] ); }
    if ( null === $saved['agent'] ) { unset( $_SERVER['HTTP_USER_AGENT'] ); } else { $_SERVER['HTTP_USER_AGENT'] = $saved['agent']; }
    wp_set_current_user( $saved['user'] );
}
