<?php
/**
 * Plugin Name: Belmains — Protection de la préparation
 * Description: À installer uniquement comme mu-plugin sur la copie de préparation.
 */

defined( 'ABSPATH' ) || exit;

// Fail closed: removing this file is a deliberate launch operation.
add_filter( 'pre_option_blog_public', '__return_zero' );
add_filter( 'pre_wp_mail', '__return_false', PHP_INT_MAX );
add_filter( 'woocommerce_available_payment_gateways', '__return_empty_array', PHP_INT_MAX );
add_filter( 'action_scheduler_allow_async_request_runner', '__return_false' );
add_filter( 'rest_authentication_errors', static function ( $result ) {
    if ( $result || current_user_can( 'manage_woocommerce' ) || current_user_can( 'manage_options' ) ) {
        return $result;
    }
    return new WP_Error( 'belmains_preparation', 'Boutique en cours de préparation.', array( 'status' => 503 ) );
} );

add_action( 'send_headers', static function () {
    header( 'X-Robots-Tag: noindex, nofollow, noarchive', true );
    nocache_headers();
} );

add_action( 'template_redirect', static function () {
    if ( current_user_can( 'manage_woocommerce' ) || current_user_can( 'manage_options' ) ) {
        return;
    }
    wp_die(
        '<h1>Belmains</h1><p>Notre boutique est en cours de préparation. À très bientôt.</p>',
        'Belmains — Bientôt disponible',
        array( 'response' => 503 )
    );
}, -100 );

add_action( 'admin_notices', static function () {
    if ( current_user_can( 'manage_options' ) ) {
        echo '<div class="notice notice-warning"><p><strong>Site de préparation :</strong> accès public, paiements et envois d’e-mails désactivés.</p></div>';
    }
} );
