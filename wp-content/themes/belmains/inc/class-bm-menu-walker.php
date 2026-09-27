<?php
defined( 'ABSPATH' ) || exit;

/** Walker minimal : liens simples sans <li> pour la barre de navigation. */
class BM_Menu_Walker extends Walker_Nav_Menu {
	public function start_el( &$output, $item, $depth = 0, $args = null, $id = 0 ) {
		$classes = in_array( 'current-menu-item', (array) $item->classes, true ) ? ' class="on"' : '';
		$output .= '<a href="' . esc_url( $item->url ) . '"' . $classes . '>' . esc_html( $item->title ) . '</a>';
	}
	public function end_el( &$output, $item, $depth = 0, $args = null ) {}
}
