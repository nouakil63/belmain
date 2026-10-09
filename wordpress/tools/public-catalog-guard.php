<?php
/**
 * Plugin Name: Belmains — Vitrine avant ouverture
 * Description: Remplace belmains-staging-guard.php uniquement après validation de publication. N'ouvre pas les ventes.
 */
defined( 'ABSPATH' ) || exit;

add_filter( 'belmains_catalog_only', '__return_true' );
add_filter( 'pre_option_blog_public', '__return_zero' );
add_filter( 'pre_wp_mail', '__return_false', PHP_INT_MAX );
add_filter( 'woocommerce_available_payment_gateways', '__return_empty_array', PHP_INT_MAX );
add_filter( 'woocommerce_is_purchasable', '__return_false', PHP_INT_MAX );
add_filter( 'woocommerce_variation_is_purchasable', '__return_false', PHP_INT_MAX );
add_filter( 'woocommerce_add_to_cart_validation', '__return_false', PHP_INT_MAX );
add_filter( 'woocommerce_update_cart_validation', '__return_false', PHP_INT_MAX );
add_filter( 'action_scheduler_allow_async_request_runner', '__return_false' );
add_filter( 'pre_option_users_can_register', '__return_zero' );

function belmains_catalog_closed_message() { return 'La boutique ouvrira prochainement. Les commandes ne sont pas encore disponibles.'; }
add_action( 'woocommerce_after_checkout_validation', static function ( $data, $errors ) {
    $errors->add( 'belmains_catalog_only', belmains_catalog_closed_message() );
}, PHP_INT_MAX, 2 );
add_filter( 'rest_authentication_errors', static function ( $result ) {
    if ( is_wp_error( $result ) || current_user_can( 'manage_woocommerce' ) || current_user_can( 'manage_options' ) ) { return $result; }
    return new WP_Error( 'belmains_preparation', belmains_catalog_closed_message(), array( 'status' => 503 ) );
}, PHP_INT_MAX );
// Store API checkout must remain closed even for an authenticated administrator.
add_filter( 'rest_pre_dispatch', static function ( $result, $server, $request ) {
    if ( preg_match( '#^/wc/store(?:/v[0-9]+)?/(checkout|cart)(?:/|$)#', $request->get_route() ) && ! in_array( $request->get_method(), array( 'GET', 'HEAD', 'OPTIONS' ), true ) ) {
        return new WP_Error( 'belmains_catalog_only', belmains_catalog_closed_message(), array( 'status' => 503 ) );
    }
    return $result;
}, PHP_INT_MAX, 3 );
// No public form submission or legacy checkout/payment AJAX is allowed.
add_action( 'init', static function () {
    if ( isset( $_REQUEST['wc-ajax'] ) ) {
        wp_die( esc_html( belmains_catalog_closed_message() ), 'Belmains — Ouverture prochaine', array( 'response' => 503 ) );
    }
}, 0 );
add_action( 'admin_post_nopriv_bcrm_contact_submit', static function () {
    wp_die( 'Pour nous contacter : contact@belmains.com', 'Belmains', array( 'response' => 503 ) );
}, 0 );
add_filter( 'comments_open', '__return_false', PHP_INT_MAX );
add_action( 'send_headers', static function () {
    header( 'X-Robots-Tag: noindex, nofollow, noarchive', true );
    nocache_headers();
} );
add_action( 'wp_enqueue_scripts', static function () {
    wp_dequeue_script( 'bcrm-audience' );
    wp_dequeue_style( 'bcrm-consent' );
    remove_action( 'wp_footer', array( 'BCRM_Audience', 'consent' ) );
}, PHP_INT_MAX );
add_action( 'template_redirect', static function () {
    if ( ! in_array( $_SERVER['REQUEST_METHOD'] ?? 'GET', array( 'GET', 'HEAD' ), true ) ) {
        wp_die( esc_html( belmains_catalog_closed_message() ), 'Belmains — Ouverture prochaine', array( 'response' => 503 ) );
    }
    if ( current_user_can( 'manage_woocommerce' ) || current_user_can( 'manage_options' ) ) { return; }
    // Only the published landing page is public; drafts, forms and account pages stay closed.
    if ( ! is_front_page() || is_preview() || isset( $_GET['belmains_preview'] ) ) {
        wp_safe_redirect( home_url( '/#fiche-produit' ), 302 );
        exit;
    }
}, -100 );
add_filter( 'belmains_storefront_value', static function ( $value, $key ) {
    $overrides = array(
        'reviews_enabled' => false,
        'promises_enabled' => false,
        'brand_ribbon_enabled' => false,
        'announcement' => 'Ouverture prochaine · Découvrez le gant Belmains',
        'size_guide_title' => '',
        'footer_text' => "Belmains est une marque exploitée par ECO EXPRESS LIMITED.\n15 Naseby Street, Liverpool, L4 5TT, Royaume-Uni.\nSociété immatriculée sous le numéro 05080017.\nContact : contact@belmains.com",
        'legal_text' => "Éditeur : ECO EXPRESS LIMITED, société immatriculée au Royaume-Uni sous le numéro 05080017.\nSiège : 15 Naseby Street, Liverpool, L4 5TT, Royaume-Uni.\nContact : contact@belmains.com\nHébergement : Infomaniak Network SA, Suisse.\nCette vitrine présente nos produits avant l’ouverture des ventes. Aucune commande ni aucun paiement ne peut être effectué sur ce site actuellement.",
        'delivery_text' => "Nos produits seront expédiés depuis notre plateforme logistique en Espagne. Les modalités définitives de livraison et de retour seront publiées avant l’ouverture des commandes. Pour toute question : contact@belmains.com.",
        'reassurance' => array( array( 'title' => 'Ouverture prochaine', 'text' => 'Découvrez notre produit. Les commandes seront disponibles prochainement.' ), array( 'title' => 'Une question ?', 'text' => 'Écrivez-nous à contact@belmains.com.' ) ),
    );
    if ( 'product_details' === $key && is_array( $value ) ) {
        return array_values( array_filter( $value, static function ( $item ) { return 'Politique de retour' !== ( $item['title'] ?? '' ); } ) );
    }
    return array_key_exists( $key, $overrides ) ? $overrides[ $key ] : $value;
}, 10, 2 );
add_action( 'admin_notices', static function () {
    if ( current_user_can( 'manage_options' ) ) {
        echo '<div class="notice notice-warning"><p><strong>Vitrine publique uniquement :</strong> commandes, paiements, e-mails et mesure d’audience désactivés. La mise en vente nécessite une validation séparée.</p></div>';
    }
} );
