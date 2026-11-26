<?php
/**
 * Meta (Facebook / Instagram): consent gate in front of the official "Meta for WooCommerce" extension.
 *
 * The official extension owns the pixel identifier, the browser events and the server events
 * (Conversions API). Left alone it starts both on the first page view. This class lets it run only
 * for a visitor who accepted the advertising purpose, never before the opening and never for a
 * connected manager. The decision is taken on the server, per request: without the accord the
 * extension builds no tracker, so nothing of it reaches the page and nothing is sent to Meta.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class BCRM_Meta {
    /** Front scripts the official extension enqueues even when its tracker is off. */
    private const OFFICIAL_SCRIPTS = array( 'wc-facebook-signals', 'wc-facebook-pixel-events', 'facebook-capi-param-builder' );

    public static function register() {
        add_filter( 'facebook_for_woocommerce_integration_pixel_enabled', array( __CLASS__, 'allow_tracking' ), PHP_INT_MAX );
        add_filter( 'facebook_signals_held', array( __CLASS__, 'hold_signals' ), PHP_INT_MAX );
        add_filter( 'pre_transient_wc_facebook_aam_settings', array( __CLASS__, 'skip_pixel_settings_lookup' ) );
        add_action( 'init', array( __CLASS__, 'purchase_on_confirmation_only' ), PHP_INT_MAX );
        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'silence_scripts' ), PHP_INT_MAX );
        add_action( 'wp_footer', array( __CLASS__, 'landing_view_content' ), 5 );
        add_action( 'send_headers', array( __CLASS__, 'private_response' ) );
        add_action( 'wp_head', array( __CLASS__, 'domain_verification' ), 1 );
        add_action( 'admin_init', array( __CLASS__, 'privacy_policy_content' ) );
    }

    public static function installed() { return function_exists( 'facebook_for_woocommerce' ); }

    /** The pixel chosen when the official extension was connected to Meta; empty until then. */
    public static function pixel_id() {
        if ( ! self::installed() ) { return ''; }
        $id = get_option( 'wc_facebook_pixel_id', '' );
        return is_string( $id ) && preg_match( '/^\d{5,30}$/D', $id ) ? $id : '';
    }

    /** The catalogue shown before the opening never measures anything, whatever the settings. */
    private static function closed() { return (bool) apply_filters( 'belmains_catalog_only', false ); }

    public static function active() { return '' !== self::pixel_id() && ! self::closed() && class_exists( 'WooCommerce' ); }

    public static function status() {
        if ( ! self::installed() ) { return 'missing'; }
        if ( '' === self::pixel_id() ) { return 'unconnected'; }
        return self::active() ? 'on' : 'closed';
    }

    private static function consented() { return 'yes' === ( $_COOKIE['bcrm_ads'] ?? '' ); }

    /** True only for the request of a visitor who accepted advertising on an open shop. */
    public static function allowed() { return self::active() && self::consented() && ! BCRM_App::can_manage(); }

    /** Without the accord the official extension builds no tracker: no pixel, no server event. */
    public static function allow_tracking( $enabled ) { return (bool) $enabled && self::allowed(); }

    /** Second lock: any server event attempted outside an accepted visit stays held. */
    public static function hold_signals( $held ) { return (bool) $held || ! self::allowed(); }

    /**
     * Before building its tracker the official extension asks Meta, from the server, how the pixel
     * is configured. On a visit without accord no tracker is built: the question is answered here,
     * so the page neither waits for Meta nor contacts it.
     */
    public static function skip_pixel_settings_lookup( $cached ) {
        if ( false !== $cached || is_admin() || '' === self::pixel_id() || self::allowed() ) { return $cached; }
        return wp_json_encode( array( 'enableAutomaticMatching' => false, 'enabledAutomaticMatchingFields' => array(), 'pixelId' => self::pixel_id() ) );
    }

    /** Callbacks the official tracker registered on a hook, as array( priority, callable ). */
    private static function tracker_callbacks( $hook, $method ) {
        global $wp_filter;
        $found = array();
        if ( empty( $wp_filter[ $hook ] ) ) { return $found; }
        foreach ( $wp_filter[ $hook ]->callbacks as $priority => $callbacks ) {
            foreach ( $callbacks as $callback ) {
                $function = $callback['function'];
                if ( is_array( $function ) && $function[0] instanceof WC_Facebookcommerce_EventsTracker && $method === $function[1] ) {
                    $found[] = array( $priority, $function );
                }
            }
        }
        return $found;
    }

    /**
     * The official extension reports a purchase as soon as the order exists, payment still pending.
     * A refused or abandoned payment would then count as a sale. Its two early hooks are removed so
     * that the purchase is reported from the confirmation page only, in the browser and on the server.
     */
    public static function purchase_on_confirmation_only() {
        if ( ! self::installed() || ! apply_filters( 'bcrm_meta_purchase_on_confirmation_only', true ) ) { return; }
        foreach ( array( 'woocommerce_new_order', 'woocommerce_checkout_update_order_meta' ) as $hook ) {
            foreach ( self::tracker_callbacks( $hook, 'inject_purchase_event' ) as $entry ) { remove_action( $hook, $entry[1], $entry[0] ); }
        }
    }

    /**
     * The landing page is the product page of this shop, but the official extension only reports a
     * product view from WooCommerce's own product template. Its handler is run here for the product
     * shown on the landing page.
     */
    public static function landing_view_content() {
        if ( ! self::allowed() || ! is_front_page() || ! function_exists( 'belmains_commerce_product' ) ) { return; }
        $product = belmains_commerce_product();
        $handlers = self::tracker_callbacks( 'woocommerce_after_single_product', 'inject_view_content_event' );
        if ( ! $product instanceof WC_Product || ! $handlers ) { return; }
        $previous = $GLOBALS['post'] ?? null;
        $GLOBALS['post'] = get_post( $product->get_id() );
        try { call_user_func( $handlers[0][1] ); } finally { $GLOBALS['post'] = $previous; }
    }

    /** Whether the banner must reload the page after an accord: the tracker is decided on the server. */
    public static function reload_on_accord() {
        return 'GET' === ( $_SERVER['REQUEST_METHOD'] ?? 'GET' )
            && ! ( function_exists( 'is_checkout' ) && is_checkout() && ! is_order_received_page() );
    }

    /** Browser side of the gate, or null while advertising is not offered at all. */
    public static function config() {
        if ( ! self::active() ) { return null; }
        return array( 'live' => self::allowed(), 'reload' => self::reload_on_accord() );
    }

    /** Nothing of the official extension is left in a page served without the accord. */
    public static function silence_scripts() {
        if ( ! self::installed() || self::allowed() ) { return; }
        foreach ( self::OFFICIAL_SCRIPTS as $handle ) { wp_dequeue_script( $handle ); }
    }

    /** A page carrying the pixel belongs to one visitor: shared caches must not keep it. */
    public static function private_response() {
        if ( ! self::allowed() || headers_sent() ) { return; }
        if ( ! defined( 'DONOTCACHEPAGE' ) ) { define( 'DONOTCACHEPAGE', true ); }
        header( 'Cache-Control: private, no-cache, max-age=0, must-revalidate' );
    }

    /** Accept the bare code or the whole meta tag copied from Meta Business; null means refused. */
    public static function sanitize_domain_verification( $value ) {
        if ( ! is_string( $value ) ) { return null; }
        $value = trim( $value );
        if ( preg_match( '/content\s*=\s*["\']([^"\']*)["\']/i', $value, $match ) ) { $value = trim( $match[1] ); }
        return preg_match( '/^(?:[A-Za-z0-9]{16,64})?$/D', $value ) ? $value : null;
    }

    /** Ownership proof for Meta Business. It is a static tag: no script, no cookie, no visitor data. */
    public static function domain_verification() {
        $code = BCRM_App::settings()['meta_domain_verification'] ?? '';
        if ( ! is_front_page() || ! is_string( $code ) || ! preg_match( '/^[A-Za-z0-9]{16,64}$/D', $code ) ) { return; }
        echo '<meta name="facebook-domain-verification" content="' . esc_attr( $code ) . '">' . "\n";
    }

    /** Suggested wording in WordPress' privacy policy guide; nothing is published automatically. */
    public static function privacy_policy_content() {
        if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) { return; }
        $content = '<p class="privacy-policy-tutorial">Texte proposé par Belmains CRM. Adaptez-le puis reportez-le dans votre politique de confidentialité avant de connecter l’extension Meta.</p>'
            . '<h3>Mesure d’audience</h3><p>Avec votre accord, nous mesurons les visites et le parcours d’achat. Ces statistiques pseudonymes restent sur notre site et sont conservées ' . (int) BCRM_App::settings()['retention_days'] . ' jours.</p>'
            . '<h3>Publicité Meta (Facebook et Instagram)</h3><p>Avec votre accord, le pixel de Meta Platforms Ireland Ltd. dépose des traceurs dans votre navigateur. Notre site transmet aussi à Meta, depuis votre navigateur et depuis notre serveur, les pages consultées, les ajouts au panier, les passages en commande et vos achats (produits, montant, numéro de commande), accompagnés de votre adresse IP, de l’identité de votre navigateur et des identifiants publicitaires de Meta. Si la correspondance avancée est activée, les coordonnées saisies lors de la commande (e-mail, nom, téléphone, ville, code postal, pays) sont transmises sous forme hachée.</p>'
            . '<p>Ces informations servent à mesurer l’efficacité de nos publicités et à vous présenter nos annonces sur Facebook et Instagram. Elles sont traitées par Meta selon sa propre politique de confidentialité et peuvent être transférées hors de l’Union européenne.</p>'
            . '<p>Votre choix est conservé six mois. Vous pouvez le modifier à tout moment avec le lien « Gérer mes choix de confidentialité » présent en bas de chaque page. Sans accord, aucun traceur publicitaire n’est déposé et aucune information n’est transmise à Meta.</p>';
        wp_add_privacy_policy_content( 'Belmains CRM', wp_kses_post( $content ) );
    }
}
