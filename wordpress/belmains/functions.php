<?php
/** Belmains storefront theme. Products and pages are provisioned separately. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
require_once get_theme_file_path( 'inc/storefront.php' );

function belmains_setup() {
    add_theme_support( 'title-tag' );
    add_theme_support( 'post-thumbnails' );
    add_theme_support( 'html5', array( 'search-form', 'gallery', 'caption', 'style', 'script' ) );
    add_theme_support( 'responsive-embeds' );
    add_theme_support( 'woocommerce' );
    load_theme_textdomain( 'belmains', get_template_directory() . '/languages' );
}
add_action( 'after_setup_theme', 'belmains_setup' );

function belmains_enqueue_assets() {
    $version = wp_get_theme()->get( 'Version' );
    wp_enqueue_style( 'belmains-fonts', get_theme_file_uri( 'assets/fonts.css' ), array(), $version );
    wp_enqueue_style( 'belmains-base', get_theme_file_uri( 'base.css' ), array( 'belmains-fonts' ), $version );
    wp_enqueue_style( 'belmains-sections', get_theme_file_uri( 'sections.css' ), array( 'belmains-base' ), $version );
    wp_enqueue_style( 'belmains-layout', get_theme_file_uri( 'maquette.css' ), array( 'belmains-sections' ), $version );
    wp_enqueue_style( 'belmains-wordpress', get_stylesheet_uri(), array( 'belmains-layout' ), $version );
    if ( class_exists( 'WooCommerce' ) ) {
        wp_enqueue_style( 'belmains-commerce', get_theme_file_uri( 'woocommerce.css' ), array( 'belmains-wordpress' ), $version );
    }
    if ( is_front_page() ) {
        $args = array( 'strategy' => 'defer', 'in_footer' => true );
        wp_enqueue_script( 'belmains-gsap', get_theme_file_uri( 'assets/gsap.min.js' ), array(), $version, $args );
        wp_enqueue_script( 'belmains-scrolltrigger', get_theme_file_uri( 'assets/ScrollTrigger.min.js' ), array( 'belmains-gsap' ), $version, $args );
        wp_enqueue_script( 'belmains-interactions', get_theme_file_uri( 'interactions.js' ), array( 'belmains-scrolltrigger' ), $version, $args );
        $config = class_exists( 'Belmains_Commerce' ) ? Belmains_Commerce::frontend_config() : array( 'available' => false );
        $config = array_merge( $config, belmains_display_offers() );
        wp_localize_script( 'belmains-interactions', 'BelmainsShop', $config );
    }
}
add_action( 'wp_enqueue_scripts', 'belmains_enqueue_assets' );

function belmains_body_classes( $classes ) {
    $classes[] = is_front_page() ? 'belmains-landing' : 'belmains-page';
    return $classes;
}
add_filter( 'body_class', 'belmains_body_classes' );
add_filter( 'woocommerce_return_to_shop_redirect', function () { return home_url( '/#fiche-produit' ); } );

function belmains_migration_notice() {
    if ( ! current_user_can( 'manage_options' ) ) { return; }
    echo '<div class="notice notice-info"><p>' . esc_html__( 'Belmains — préparation de la boutique : le panier utilise WooCommerce et le contact enregistre les demandes dans le SAV du CRM. Avant l’ouverture, finaliser la fiscalité, les paiements, l’envoi des e-mails, Iziship et les pages commerciales. Les avis de démonstration sont réservés à la préparation.', 'belmains' ) . '</p></div>';
}
add_action( 'admin_notices', 'belmains_migration_notice' );

function belmains_tracking_url() {
    $page_id = absint( get_option( 'belmains_tracking_page_id' ) );
    return $page_id && 'publish' === get_post_status( $page_id ) ? get_permalink( $page_id ) : home_url( '/#contact' );
}

function belmains_contact_url() {
    $page_id = absint( get_option( 'belmains_contact_page_id' ) );
    return $page_id && 'publish' === get_post_status( $page_id ) ? get_permalink( $page_id ) : home_url( '/#contact' );
}

add_action( 'wp', function () {
    if ( ! class_exists( 'WooCommerce' ) ) { return; }
    remove_action( 'woocommerce_before_main_content', 'woocommerce_output_content_wrapper', 10 );
    remove_action( 'woocommerce_after_main_content', 'woocommerce_output_content_wrapper_end', 10 );
    add_action( 'woocommerce_before_main_content', function () { echo '<main id="main" class="belmains-page-content">'; }, 10 );
    add_action( 'woocommerce_after_main_content', function () { echo '</main>'; }, 10 );
} );
