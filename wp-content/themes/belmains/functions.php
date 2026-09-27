<?php
/**
 * Thème Belmains — page d'accueil produit pilotée par le builder (Customizer) + WooCommerce.
 */
defined( 'ABSPATH' ) || exit;

define( 'BM_THEME_VERSION', '0.1.0' );

require_once get_template_directory() . '/inc/fields.php';
require_once get_template_directory() . '/inc/template-tags.php';
require_once get_template_directory() . '/inc/customizer.php';
require_once get_template_directory() . '/inc/class-bm-menu-walker.php';

add_action( 'after_setup_theme', function () {
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'html5', array( 'search-form', 'gallery', 'caption', 'style', 'script' ) );
	add_theme_support( 'customize-selective-refresh-widgets' );
	add_theme_support( 'woocommerce', array( 'product_grid' => array( 'default_columns' => 3 ) ) );
	add_theme_support( 'wc-product-gallery-zoom' );
	add_theme_support( 'wc-product-gallery-lightbox' );
	register_nav_menus( array( 'primary' => 'Menu principal (facultatif : remplace les 4 liens du builder)' ) );
	load_theme_textdomain( 'belmains', get_template_directory() . '/languages' );
} );

add_action( 'wp_enqueue_scripts', function () {
	wp_enqueue_style( 'bm-fonts', bm_fonts_url(), array(), null );
	wp_enqueue_style( 'bm-landing', get_template_directory_uri() . '/assets/css/landing.css', array(), BM_THEME_VERSION );
	wp_add_inline_style( 'bm-landing', bm_css_vars() );
	wp_enqueue_style( 'bm-theme', get_stylesheet_uri(), array( 'bm-landing' ), BM_THEME_VERSION );
	wp_enqueue_script( 'bm-landing', get_template_directory_uri() . '/assets/js/landing.js', array(), BM_THEME_VERSION, true );
} );

// Préconnexion Google Fonts.
add_filter( 'wp_resource_hints', function ( $urls, $relation ) {
	if ( 'preconnect' === $relation ) {
		$urls[] = array( 'href' => 'https://fonts.googleapis.com', 'crossorigin' );
		$urls[] = array( 'href' => 'https://fonts.gstatic.com', 'crossorigin' );
	}
	return $urls;
}, 10, 2 );

// SEO de la page d'accueil (titre, description, Open Graph, JSON-LD produit + FAQ).
add_filter( 'pre_get_document_title', function ( $title ) {
	return is_front_page() && bm_get( 'seo_title' ) ? bm_get( 'seo_title' ) : $title;
} );

add_action( 'wp_head', function () {
	if ( ! is_front_page() ) {
		return;
	}
	$desc = bm_get( 'seo_description' );
	$p    = bm_product();
	$img  = $p && $p->get_image_id() ? wp_get_attachment_image_url( $p->get_image_id(), 'large' ) : ( (int) bm_get( 'hero_image' ) ? wp_get_attachment_image_url( (int) bm_get( 'hero_image' ), 'large' ) : '' );
	echo '<meta name="description" content="' . esc_attr( $desc ) . '">' . "\n";
	echo '<link rel="canonical" href="' . esc_url( home_url( '/' ) ) . '">' . "\n";
	echo '<meta property="og:type" content="website"><meta property="og:locale" content="fr_FR">' . "\n";
	echo '<meta property="og:title" content="' . esc_attr( bm_get( 'seo_title' ) ) . '"><meta property="og:description" content="' . esc_attr( $desc ) . '">' . "\n";
	if ( $img ) {
		echo '<meta property="og:image" content="' . esc_url( $img ) . '">' . "\n";
	}
	echo '<meta name="twitter:card" content="summary_large_image">' . "\n";

	$ld = array();
	if ( $p ) {
		$ld[] = array(
			'@context'    => 'https://schema.org',
			'@type'       => 'Product',
			'name'        => $p->get_name(),
			'brand'       => array( '@type' => 'Brand', 'name' => bm_get( 'brand_part1' ) . bm_get( 'brand_part2' ) ),
			'description' => wp_strip_all_tags( $p->get_short_description() ?: bm_get( 'product_intro' ) ),
			'image'       => $img ? array( $img ) : array(),
			'sku'         => $p->get_sku(),
			'offers'      => array(
				'@type'         => 'Offer',
				'priceCurrency' => get_woocommerce_currency(),
				'price'         => (string) $p->get_price(),
				'availability'  => $p->is_in_stock() ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
				'url'           => home_url( '/' ),
			),
		);
	}
	$faq = array();
	for ( $i = 1; $i <= 6; $i++ ) {
		if ( bm_get( "faq{$i}_q" ) && bm_get( "faq{$i}_a" ) ) {
			$faq[] = array( '@type' => 'Question', 'name' => bm_get( "faq{$i}_q" ), 'acceptedAnswer' => array( '@type' => 'Answer', 'text' => bm_get( "faq{$i}_a" ) ) );
		}
	}
	if ( $faq && ( bm_sections()['faq'] ?? true ) ) {
		$ld[] = array( '@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $faq );
	}
	foreach ( $ld as $obj ) {
		echo '<script type="application/ld+json">' . wp_json_encode( $obj, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . '</script>' . "\n";
	}
}, 5 );

// Message « ajouté au panier » redirigé vers la commande quand on utilise ?add-to-cart= depuis la landing.
add_filter( 'woocommerce_add_to_cart_redirect', function ( $url ) {
	return ( ! empty( $_REQUEST['add-to-cart'] ) && is_front_page() ) ? wc_get_checkout_url() : $url;
} );

// Nombre d'articles dans le panier (fragment AJAX).
add_filter( 'woocommerce_add_to_cart_fragments', function ( $fragments ) {
	$fragments['.bm-cart-count'] = '<span class="bm-cart-count">' . ( WC()->cart ? (int) WC()->cart->get_cart_contents_count() : 0 ) . '</span>';
	return $fragments;
} );

// Retire les styles WooCommerce par défaut trop envahissants ? Non : on les garde et on les habille (style.css).
add_filter( 'body_class', function ( $classes ) {
	$classes[] = 'bm';
	return $classes;
} );
