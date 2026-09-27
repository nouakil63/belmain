<?php
/**
 * Helpers de gabarit : lecture des réglages, formatage, images, produit.
 */
defined( 'ABSPATH' ) || exit;

function bm_default( string $key ) {
	return bm_fields_flat()[ $key ]['default'] ?? '';
}

/** Valeur brute d'un réglage (theme_mod ou défaut). */
function bm_get( string $key ) {
	$val = get_theme_mod( $key, null );
	return ( null === $val || '' === $val ) ? bm_default( $key ) : $val;
}

/**
 * Formatage léger : échappe, puis *accent* → <em>, **gras** → <b>, retour ligne → <br>.
 * Miroir JS dans assets/js/customize-preview.js (bmFmt).
 */
function bm_fmt( string $text, bool $br = true ): string {
	$t = esc_html( $text );
	$t = preg_replace( '/\*\*(.+?)\*\*/s', '<b>$1</b>', $t );
	$t = preg_replace( '/\*(.+?)\*/s', '<em>$1</em>', $t );
	$t = str_replace( ' ?', '&nbsp;?', $t );
	$t = str_replace( ' !', '&nbsp;!', $t );
	$t = str_replace( ' :', '&nbsp;:', $t );
	return $br ? nl2br( $t ) : $t;
}

/**
 * Imprime un élément texte relié à l'aperçu en direct : <tag class data-bm="key">…</tag>.
 */
function bm_text( string $key, string $tag = 'span', string $class = '', string $attrs = '' ): void {
	$val = (string) bm_get( $key );
	if ( '' === $val && ! is_customize_preview() ) {
		return;
	}
	printf(
		'<%1$s%2$s data-bm="%3$s"%4$s>%5$s</%1$s>',
		tag_escape( $tag ),
		$class ? ' class="' . esc_attr( $class ) . '"' : '',
		esc_attr( $key ),
		$attrs ? ' ' . $attrs : '',
		bm_fmt( $val )
	);
}

/** Titre héro : une ligne animée par ligne de texte. */
function bm_hero_title( string $key ): void {
	echo '<h1 data-bm="' . esc_attr( $key ) . '" data-bm-type="title">';
	foreach ( bm_lines( $key ) as $line ) {
		echo '<span class="hline"><span>' . bm_fmt( $line, false ) . '</span></span>';
	}
	echo '</h1>';
}

/** Lignes non vides d'un champ textarea. */
function bm_lines( string $key ): array {
	return array_values( array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', (string) bm_get( $key ) ) ), 'strlen' ) );
}

/** Paragraphes (séparés par une ligne vide). */
function bm_paragraphs( string $key ): string {
	$out = '';
	foreach ( preg_split( '/(\r\n|\r|\n){2,}/', trim( (string) bm_get( $key ) ) ) as $p ) {
		if ( trim( $p ) !== '' ) {
			$out .= '<p>' . bm_fmt( trim( $p ) ) . '</p>';
		}
	}
	return $out;
}

/** Liens « Libellé | URL » (un par ligne). */
function bm_links( string $key ): array {
	$out = array();
	foreach ( bm_lines( $key ) as $line ) {
		$parts = array_map( 'trim', explode( '|', $line, 2 ) );
		$out[] = array( 'label' => $parts[0], 'url' => $parts[1] ?? '#' );
	}
	return $out;
}

/**
 * Résout une URL saisie dans le builder : ancre (#…), chemin relatif (/…), URL complète,
 * ou jeton WooCommerce : {boutique} {panier} {commande} {compte} {commandes} {retours} {produit}.
 */
function bm_resolve_url( string $url ): string {
	$url = trim( $url );
	if ( '' === $url ) {
		return '#';
	}
	if ( str_starts_with( $url, '{' ) && bm_wc() ) {
		$account = wc_get_page_permalink( 'myaccount' );
		$map     = array(
			'{boutique}'  => wc_get_page_permalink( 'shop' ),
			'{panier}'    => wc_get_cart_url(),
			'{commande}'  => wc_get_checkout_url(),
			'{compte}'    => $account,
			'{commandes}' => wc_get_endpoint_url( 'orders', '', $account ),
			'{retours}'   => wc_get_endpoint_url( 'retours', '', $account ),
			'{produit}'   => bm_product() ? bm_product()->get_permalink() : home_url( '/#produit' ),
		);
		if ( isset( $map[ $url ] ) ) {
			return $map[ $url ];
		}
	}
	if ( str_starts_with( $url, '#' ) ) {
		return ( is_front_page() ? '' : home_url( '/' ) ) . $url;
	}
	if ( str_starts_with( $url, '/' ) ) {
		return home_url( $url );
	}
	return esc_url( $url );
}

/** URL d'un champ « lien » du builder. */
function bm_link( string $key ): string {
	return bm_resolve_url( (string) bm_get( $key ) );
}

/**
 * Image d'un champ (ID d'attachement) ou emplacement « photo à fournir ».
 */
function bm_img( string $key, string $class = 'photo', string $size = 'large', ?string $fallback_label = null, string $attrs = '' ): void {
	$id = (int) bm_get( $key );
	$f  = bm_fields_flat()[ $key ] ?? array();
	if ( $id && wp_get_attachment_image_src( $id, $size ) ) {
		printf( '<div class="%s has-img" data-bm-img="%s" %s>%s</div>', esc_attr( $class ), esc_attr( $key ), $attrs, wp_get_attachment_image( $id, $size, false, array( 'loading' => 'lazy' ) ) );
		return;
	}
	$label = $fallback_label ?? ( $f['placeholder'] ?? 'photo à fournir' );
	printf(
		'<div class="%s" data-bm-img="%s" role="img" aria-label="Photo à fournir" %s><span class="cam" aria-hidden="true">✦</span><b>Photo à fournir</b>%s</div>',
		esc_attr( $class ),
		esc_attr( $key ),
		$attrs,
		nl2br( esc_html( $label ) )
	);
}

/** Image produit WooCommerce (ID d'attachement) sinon emplacement. */
function bm_product_img( string $key, ?int $attachment_id, string $class = 'photo', string $size = 'large', string $fallback = '' ): void {
	if ( (int) bm_get( $key ) ) {
		bm_img( $key, $class, $size, $fallback );
		return;
	}
	if ( $attachment_id && wp_get_attachment_image_src( $attachment_id, $size ) ) {
		printf( '<div class="%s has-img" data-bm-img="%s">%s</div>', esc_attr( $class ), esc_attr( $key ), wp_get_attachment_image( $attachment_id, $size, false, array( 'loading' => 'lazy' ) ) );
		return;
	}
	bm_img( $key, $class, $size, $fallback );
}

/* ------------------------------------------------------------- WooCommerce */

function bm_wc(): bool {
	return class_exists( 'WooCommerce' );
}

/** Produit principal : réglage, sinon premier produit publié. */
function bm_product(): ?WC_Product {
	static $product = false;
	if ( false !== $product ) {
		return $product;
	}
	$product = null;
	if ( ! bm_wc() ) {
		return null;
	}
	$id = (int) bm_get( 'product_id' );
	if ( $id ) {
		$product = wc_get_product( $id ) ?: null;
	}
	if ( ! $product ) {
		$found   = wc_get_products( array( 'limit' => 1, 'status' => 'publish', 'orderby' => 'date', 'order' => 'ASC' ) );
		$product = $found[0] ?? null;
	}
	return $product;
}

function bm_lot( int $i ): ?array {
	if ( ! bm_wc() ) {
		return null;
	}
	$id      = (int) bm_get( "lot{$i}_product" );
	$product = $id ? wc_get_product( $id ) : ( 1 === $i ? bm_product() : null );
	if ( ! $product ) {
		return null;
	}
	$main   = bm_product();
	$saving = '';
	if ( $main && $i > 1 && (float) $main->get_price() > 0 && (float) $product->get_price() > 0 ) {
		$pct = 1 - (float) $product->get_price() / ( (float) $main->get_price() * $i );
		if ( $pct > 0.005 ) {
			$saving = 'Économisez ' . round( $pct * 100 ) . ' %';
		}
	}
	return array(
		'product' => $product,
		'label'   => (string) bm_get( "lot{$i}_label" ),
		'tag'     => (string) bm_get( "lot{$i}_tag" ),
		'saving'  => $saving,
		'price'   => $product->get_price_html(),
		'url'     => bm_cart_url( $product ),
	);
}

function bm_cart_url( ?WC_Product $product ): string {
	if ( ! $product ) {
		return '#';
	}
	if ( $product->is_type( 'simple' ) && $product->is_purchasable() && $product->is_in_stock() ) {
		return add_query_arg( 'add-to-cart', $product->get_id(), wc_get_checkout_url() );
	}
	return $product->get_permalink();
}

function bm_price_block(): array {
	$p       = bm_product();
	$price   = (string) bm_get( 'product_price' );
	$compare = (string) bm_get( 'product_compare' );
	$badge   = (string) bm_get( 'product_badge' );
	if ( $p ) {
		if ( '' === $price ) {
			$price = wp_strip_all_tags( wc_price( (float) $p->get_price() ) );
		}
		if ( '' === $compare && $p->is_on_sale() && (float) $p->get_regular_price() > (float) $p->get_price() ) {
			$compare = wp_strip_all_tags( wc_price( (float) $p->get_regular_price() ) );
			if ( '' === $badge ) {
				$badge = 'Économisez ' . round( ( 1 - (float) $p->get_price() / (float) $p->get_regular_price() ) * 100 ) . ' %';
			}
		}
	}
	return array( 'price' => $price ?: '— €', 'compare' => $compare, 'badge' => $badge );
}

/** Ordre et visibilité des sections : [ id => bool ]. */
function bm_sections(): array {
	$choices = bm_fields_flat()['sections_order']['choices'] ?? array();
	$out     = array();
	foreach ( explode( ',', (string) bm_get( 'sections_order' ) ) as $pair ) {
		$parts = explode( ':', trim( $pair ) );
		if ( isset( $choices[ $parts[0] ] ) ) {
			$out[ $parts[0] ] = '0' !== ( $parts[1] ?? '1' );
		}
	}
	foreach ( $choices as $id => $label ) { // sections ajoutées après coup
		if ( ! array_key_exists( $id, $out ) ) {
			$out[ $id ] = true;
		}
	}
	return $out;
}

/** Variables CSS calculées depuis les réglages. */
function bm_css_vars(): string {
	$vars = array();
	foreach ( bm_fields_flat() as $key => $f ) {
		if ( ! empty( $f['var'] ) ) {
			$vars[] = $f['var'] . ':' . bm_get( $key ) . ( $f['unit'] ?? '' );
		}
	}
	$vars[] = '--display:"' . bm_get( 'font_display' ) . '",Georgia,"Times New Roman",serif';
	$vars[] = '--sans:"' . bm_get( 'font_sans' ) . '","Avenir Next",system-ui,sans-serif';
	return ':root{' . implode( ';', $vars ) . '}';
}

function bm_fonts_url(): string {
	$display = rawurlencode( bm_get( 'font_display' ) );
	$sans    = rawurlencode( bm_get( 'font_sans' ) );
	return "https://fonts.googleapis.com/css2?family={$display}:ital,wght@0,500;0,600;0,700;1,500;1,600&family={$sans}:ital,wght@0,300;0,400;0,500;0,600;1,400&display=swap";
}

/** Balise <img> ou emplacements pour la séquence 360 (JSON des URLs). */
function bm_turn_frames(): array {
	$frames = array();
	for ( $i = 1; $i <= 12; $i++ ) {
		$id = (int) bm_get( "turn_img_$i" );
		$src = $id ? wp_get_attachment_image_url( $id, 'large' ) : '';
		if ( $src ) {
			$frames[] = $src;
		}
	}
	return $frames;
}
