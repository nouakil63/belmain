<?php
/**
 * Enregistrement du panneau « Belmains Builder » dans le Customizer,
 * à partir de bm_fields(). Aperçu en direct via assets/js/customize-preview.js.
 */
defined( 'ABSPATH' ) || exit;

add_action( 'customize_register', 'bm_customize_register' );
add_action( 'customize_preview_init', 'bm_customize_preview_js' );
add_action( 'customize_controls_enqueue_scripts', 'bm_customize_controls_js' );

function bm_customize_register( WP_Customize_Manager $wp ): void {
	require_once get_template_directory() . '/inc/class-bm-sortable-control.php';

	$wp->add_panel( 'bm_builder', array(
		'title'       => '★ Belmains Builder',
		'description' => 'Modifiez le produit, les textes, les photos, les couleurs et l\'ordre des sections. L\'aperçu se met à jour en direct ; cliquez « Publier » pour mettre en ligne.',
		'priority'    => 1,
	) );

	$priority = 10;
	foreach ( bm_fields() as $sid => $section ) {
		$wp->add_section( $sid, array(
			'title'    => $section['title'],
			'panel'    => 'bm_builder',
			'priority' => $priority++,
		) );

		foreach ( $section['fields'] as $key => $f ) {
			$transport = $f['transport'] ?? ( in_array( $f['type'], array( 'text', 'rich', 'title', 'color', 'number' ), true ) ? 'postMessage' : 'refresh' );
			$wp->add_setting( $key, array(
				'type'              => 'theme_mod',
				'default'           => $f['default'],
				'transport'         => $transport,
				'sanitize_callback' => bm_sanitizer_for( $f['type'] ),
			) );

			$args = array(
				'label'       => $f['label'],
				'section'     => $sid,
				'description' => $f['description'] ?? '',
			);

			switch ( $f['type'] ) {
				case 'image':
					$wp->add_control( new WP_Customize_Media_Control( $wp, $key, $args + array( 'mime_type' => 'image' ) ) );
					break;
				case 'color':
					$wp->add_control( new WP_Customize_Color_Control( $wp, $key, $args ) );
					break;
				case 'sortable':
					$wp->add_control( new BM_Sortable_Control( $wp, $key, $args + array( 'choices' => $f['choices'] ) ) );
					break;
				case 'product':
					$wp->add_control( $key, $args + array( 'type' => 'select', 'choices' => bm_product_choices() ) );
					break;
				case 'select':
					$wp->add_control( $key, $args + array( 'type' => 'select', 'choices' => $f['choices'] ) );
					break;
				case 'number':
					$wp->add_control( $key, $args + array( 'type' => 'number', 'input_attrs' => array( 'min' => $f['min'] ?? 0, 'max' => $f['max'] ?? 100, 'step' => $f['step'] ?? 1 ) ) );
					break;
				case 'textarea':
				case 'title':
					$wp->add_control( $key, $args + array( 'type' => 'textarea' ) );
					break;
				default:
					$wp->add_control( $key, $args + array( 'type' => 'text' ) );
			}
		}
	}

	// Rafraîchissement sélectif : les sections se rechargent seules quand un champ « refresh » change.
	if ( isset( $wp->selective_refresh ) ) {
		foreach ( bm_fields_flat() as $key => $f ) {
			$transport = $f['transport'] ?? ( in_array( $f['type'], array( 'text', 'rich', 'title', 'color', 'number' ), true ) ? 'postMessage' : 'refresh' );
			if ( 'refresh' !== $transport || in_array( $f['type'], array( 'sortable', 'select', 'product' ), true ) || str_starts_with( $key, 'seo_' ) || str_starts_with( $key, 'menu_' ) || str_starts_with( $key, 'footer_' ) ) {
				continue;
			}
			$part = bm_section_part_for( $key );
			if ( ! $part ) {
				continue;
			}
			$wp->get_setting( $key )->transport = 'postMessage';
			$wp->selective_refresh->add_partial( 'bm_part_' . $key, array(
				'selector'            => '[data-bm-section="' . $part . '"]',
				'settings'            => array( $key ),
				'container_inclusive' => true,
				'render_callback'     => function () use ( $part ) {
					get_template_part( 'template-parts/landing/' . $part );
				},
			) );
		}
	}
}

/** Gabarit de section auquel appartient un champ (pour le rafraîchissement partiel). */
function bm_section_part_for( string $key ): string {
	$map = array(
		'hero_'     => 'hero',
		'why_'      => 'why',
		'stat'      => 'why',
		'turn_'     => 'turn',
		'product_'  => 'product',
		'lot'       => 'product',
		'reas'      => 'product',
		'tab'       => 'product',
		'pay_'      => 'product',
		'promise'   => 'promises',
		'review'    => 'reviews',
		'faq'       => 'faq',
		'cta_'      => 'cta',
	);
	foreach ( $map as $prefix => $part ) {
		if ( str_starts_with( $key, $prefix ) ) {
			return $part;
		}
	}
	return '';
}

function bm_sanitizer_for( string $type ): callable {
	switch ( $type ) {
		case 'image':
		case 'product':
			return 'absint';
		case 'color':
			return 'sanitize_hex_color';
		case 'number':
			return function ( $v ) { return is_numeric( $v ) ? (string) (float) $v : ''; };
		case 'textarea':
		case 'title':
		case 'rich':
			return 'sanitize_textarea_field';
		case 'sortable':
			return function ( $v ) { return preg_replace( '/[^a-z0-9_:,]/', '', (string) $v ); };
		case 'select':
			return 'sanitize_text_field';
		default:
			return 'sanitize_text_field';
	}
}

function bm_product_choices(): array {
	$choices = array( '' => '— Automatique (premier produit) —' );
	if ( ! class_exists( 'WooCommerce' ) ) {
		return $choices;
	}
	foreach ( wc_get_products( array( 'limit' => 100, 'status' => 'publish', 'orderby' => 'title', 'order' => 'ASC' ) ) as $p ) {
		$choices[ $p->get_id() ] = $p->get_name() . ( $p->get_sku() ? ' (' . $p->get_sku() . ')' : '' ) . ' — ' . wp_strip_all_tags( wc_price( (float) $p->get_price() ) );
	}
	return $choices;
}

function bm_customize_preview_js(): void {
	wp_enqueue_script( 'bm-customize-preview', get_template_directory_uri() . '/assets/js/customize-preview.js', array( 'customize-preview', 'jquery' ), BM_THEME_VERSION, true );
	$fields = array();
	foreach ( bm_fields_flat() as $key => $f ) {
		$fields[ $key ] = array( 'type' => $f['type'], 'var' => $f['var'] ?? '', 'unit' => $f['unit'] ?? '' );
	}
	wp_localize_script( 'bm-customize-preview', 'BM_PREVIEW', array( 'fields' => $fields ) );
}

function bm_customize_controls_js(): void {
	wp_enqueue_script( 'bm-customize-controls', get_template_directory_uri() . '/assets/js/customize-controls.js', array( 'customize-controls', 'jquery-ui-sortable' ), BM_THEME_VERSION, true );
	wp_add_inline_style( 'customize-controls', '
		.bm-sortable{margin:0;padding:0;list-style:none;border:1px solid #dcdcde;border-radius:4px;background:#fff}
		.bm-sortable li{display:flex;align-items:center;gap:8px;padding:8px 10px;border-bottom:1px solid #f0f0f1;cursor:grab;background:#fff}
		.bm-sortable li:last-child{border-bottom:0}
		.bm-sortable li.ui-sortable-helper{box-shadow:0 4px 14px rgba(0,0,0,.15)}
		.bm-sortable .bm-handle{color:#a7aaad}
		.bm-sortable label{flex:1;margin:0}
		#accordion-panel-bm_builder > .accordion-section-title{background:#181d25;color:#fff}
		#accordion-panel-bm_builder > .accordion-section-title:hover{background:#a8121f}
	' );
}
