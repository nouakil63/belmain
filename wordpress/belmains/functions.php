<?php
/** Belmains presentation theme: no commerce mutations or automatic page creation. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function belmains_setup() {
    add_theme_support( 'title-tag' );
    add_theme_support( 'post-thumbnails' );
    add_theme_support( 'html5', array( 'search-form', 'gallery', 'caption', 'style', 'script' ) );
    add_theme_support( 'responsive-embeds' );
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
    if ( is_front_page() ) {
        $args = array( 'strategy' => 'defer', 'in_footer' => true );
        wp_enqueue_script( 'belmains-gsap', get_theme_file_uri( 'assets/gsap.min.js' ), array(), $version, $args );
        wp_enqueue_script( 'belmains-scrolltrigger', get_theme_file_uri( 'assets/ScrollTrigger.min.js' ), array( 'belmains-gsap' ), $version, $args );
        wp_enqueue_script( 'belmains-interactions', get_theme_file_uri( 'interactions.js' ), array( 'belmains-scrolltrigger' ), $version, $args );
    }
}
add_action( 'wp_enqueue_scripts', 'belmains_enqueue_assets' );

function belmains_body_classes( $classes ) {
    $classes[] = is_front_page() ? 'belmains-landing' : 'belmains-page';
    return $classes;
}
add_filter( 'body_class', 'belmains_body_classes' );

function belmains_migration_notice() {
    if ( ! current_user_can( 'manage_options' ) ) { return; }
    echo '<div class="notice notice-info"><p>' . esc_html__( 'Belmains — migration visuelle : le panier, le suivi et les formulaires de l’accueil sont encore des démonstrations. Le raccordement WooCommerce est la prochaine étape, avant toute ouverture des ventes.', 'belmains' ) . '</p></div>';
}
add_action( 'admin_notices', 'belmains_migration_notice' );
