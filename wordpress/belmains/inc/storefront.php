<?php
/** Rendering helpers for the native product editor. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function belmains_storefront_view() {
    static $view;
    if ( null !== $view ) { return $view; }
    if ( class_exists( 'Belmains_Builder' ) ) { $view = Belmains_Builder::view(); return $view; }
    $values = array();
    if ( function_exists( 'belmains_builder_schema' ) ) {
        foreach ( belmains_builder_schema() as $section ) {
            foreach ( $section['fields'] as $field ) { $values[ $field['key'] ] = $field['default']; }
        }
    }
    $view = array( 'product' => function_exists( 'belmains_commerce_product' ) ? belmains_commerce_product() : null, 'values' => $values, 'preview' => false );
    return $view;
}

function belmains_value( $key, $fallback = '' ) {
    $view = belmains_storefront_view();
    return apply_filters( 'belmains_storefront_value', $view['values'][ $key ] ?? $fallback, $key );
}

function belmains_catalog_only() { return (bool) apply_filters( 'belmains_catalog_only', false ); }

function belmains_rich( $value ) { return wp_kses( (string) $value, array( 'em' => array(), 'strong' => array(), 'br' => array() ) ); }
function belmains_paragraphs( $value ) { return wpautop( esc_html( (string) $value ) ); }
function belmains_media_url( $media ) { return is_array( $media ) ? (string) ( $media['url'] ?? '' ) : ''; }

function belmains_image( $media, $class = '', $eager = false, $thumbnail = false ) {
    if ( ! belmains_media_url( $media ) ) { return; }
    $attrs = array( 'class' => $class, 'alt' => $media['alt'] ?? '', 'loading' => $eager ? 'eager' : 'lazy', 'decoding' => 'async' );
    if ( $eager ) { $attrs['fetchpriority'] = 'high'; }
    if ( ! empty( $media['id'] ) && wp_attachment_is_image( (int) $media['id'] ) ) {
        echo wp_get_attachment_image( (int) $media['id'], $thumbnail ? 'thumbnail' : 'full', false, $attrs );
        return;
    }
    $url = belmains_media_url( $media );
    if ( $thumbnail && preg_match( '~/assets/(belmains-0[1-5]-[^/]+)-v1\.webp$~', $url ) ) { $url = preg_replace( '/-v1\.webp$/', '-thumb-v1.webp', $url ); }
    echo '<img src="' . esc_url( $url ) . '" alt="' . esc_attr( $attrs['alt'] ) . '" class="' . esc_attr( $class ) . '" loading="' . esc_attr( $attrs['loading'] ) . '" decoding="async"' . ( $eager ? ' fetchpriority="high"' : '' ) . '>';
}

function belmains_icon( $name = 'check' ) {
    $paths = array(
        'check' => '<path d="m5 12 4 4L19 6"/>',
        'heart' => '<path d="M20.8 4.8a5.5 5.5 0 0 0-7.8 0L12 6l-1-1.2a5.5 5.5 0 0 0-7.8 7.8L12 21l8.8-8.4a5.5 5.5 0 0 0 0-7.8Z"/>',
        'truck' => '<path d="M1 4h13v13H1zM14 9h5l4 5v3h-9M5 17a2 2 0 1 0 0 4 2 2 0 0 0 0-4M19 17a2 2 0 1 0 0 4 2 2 0 0 0 0-4"/>',
        'bag' => '<path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4zM3 6h18M16 10a4 4 0 0 1-8 0"/>',
        'sun' => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M2 12h2M20 12h2M5 5l2 2M17 17l2 2M5 19l2-2M17 7l2-2"/>',
    );
    echo '<svg class="ui-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ( $paths[ $name ] ?? $paths['check'] ) . '</svg>';
}

function belmains_heading( $text, $suffix ) {
    if ( ! trim( (string) $text ) ) { return; }
    echo '<div id="shopify-section-rich_text_' . esc_attr( $suffix ) . '" class="theme-section"><div class="isolate"><div class="rich-text content-container rich-text--full-width section-rich_text_' . esc_attr( $suffix ) . '-padding"><div class="rich-text__wrapper rich-text__wrapper--center page-width"><div class="rich-text__blocks center"><h2 class="rich-text__heading rte inline-richtext h2">' . belmains_rich( $text ) . '</h2></div></div></div></div></div>';
}

function belmains_editorial( $item, $index ) {
    $has_image = (bool) belmains_media_url( $item['image'] ?? array() );
    $id = 0 === $index ? 'ndtUdi' : 'XqDidL';
    echo '<div class="theme-section"><section class="iwt iwt-image_texte_' . esc_attr( $id ) . ' image-pos-left"><div class="iwt-inner' . ( $has_image ? '' : ' belmains-text-only' ) . '">';
    if ( $has_image ) {
        echo '<div class="iwt-image"><div class="iwt-image-wrap iwt-image-wrap--' . ( 0 === $index ? 'adapt' : 'large' ) . '">';
        belmains_image( $item['image'], 'iwt-image-tag' ); echo '</div></div>';
    }
    echo '<div class="iwt-content"><div class="eyebrow"><span class="eyebrow-dash"></span>' . esc_html( $item['title'] ?? '' ) . '</div>';
    if ( ! empty( $item['text'] ) ) { echo '<p class="iwt-intro">' . nl2br( esc_html( $item['text'] ) ) . '</p>'; }
    $bullets = array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', $item['bullets'] ?? '' ) ) );
    if ( $bullets ) { echo '<ul class="iwt-list">'; foreach ( $bullets as $bullet ) { echo '<li><span class="iwt-list-check" aria-hidden="true">'; belmains_icon(); echo '</span><span>' . esc_html( $bullet ) . '</span></li>'; } echo '</ul>'; }
    if ( ! empty( $item['button'] ) ) { echo '<a href="#fiche-produit" class="btn btn-primary">' . esc_html( $item['button'] ) . ' <span aria-hidden="true">→</span></a>'; }
    echo '</div></div></section></div>';
}

function belmains_display_offers() {
    $view = belmains_storefront_view();
    $offer = $view['product'] && method_exists( 'Belmains_Commerce', 'offer_for_product' ) ? Belmains_Commerce::offer_for_product( $view['product'] ) : array( 'single_cents' => $view['product'] ? (int) round( (float) $view['product']->get_price() * 100 ) : 0, 'regular_cents' => $view['product'] ? (int) round( (float) $view['product']->get_regular_price() * 100 ) : 0, 'duo_enabled' => false, 'duo_cents' => 0, 'duo_regular_cents' => 0 );
    if ( $view['preview'] ) {
        $v = $view['values'];
        $offer = array( 'single_cents' => (int) round( (float) ( '' !== $v['sale_price'] ? $v['sale_price'] : $v['regular_price'] ) * 100 ), 'regular_cents' => (int) round( (float) $v['regular_price'] * 100 ), 'duo_enabled' => ! empty( $v['duo_enabled'] ), 'duo_cents' => (int) round( (float) $v['duo_price'] * 100 ), 'duo_regular_cents' => (int) round( (float) $v['duo_compare_price'] * 100 ) );
    }
    return $offer;
}

function belmains_price( $cents ) {
    return function_exists( 'wc_price' ) ? wp_kses_post( wc_price( $cents / 100 ) ) : esc_html( number_format_i18n( $cents / 100, 2 ) . ' €' );
}

function belmains_legal_url( $kind ) {
    $id = absint( get_option( 'belmains_' . $kind . '_page_id', 0 ) );
    return $id && 'publish' === get_post_status( $id ) ? get_permalink( $id ) : '';
}

function belmains_builder_colors() {
    $color = sanitize_hex_color( belmains_value( 'brand_color', '#63182e' ) ) ?: '#63182e';
    $accent = sanitize_hex_color( belmains_value( 'brand_accent', '#bf9756' ) ) ?: '#bf9756';
    wp_add_inline_style( 'belmains-wordpress', ':root{--belmains-brand:' . $color . ';--belmains-accent:' . $accent . ';--red:' . $color . ';--gold:' . $accent . '}' );
}
add_action( 'wp_enqueue_scripts', 'belmains_builder_colors', 20 );

function belmains_has_seo_plugin() {
    return defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) || defined( 'AIOSEO_VERSION' );
}
add_filter( 'pre_get_document_title', function ( $title ) {
    if ( is_front_page() && ! belmains_has_seo_plugin() && belmains_value( 'seo_title' ) ) { return sanitize_text_field( belmains_value( 'seo_title' ) ); }
    return $title;
} );
add_action( 'wp_head', function () {
    if ( is_front_page() && ! belmains_has_seo_plugin() && belmains_value( 'seo_description' ) ) { echo '<meta name="description" content="' . esc_attr( belmains_value( 'seo_description' ) ) . '">' . "\n"; }
}, 2 );
