<?php
defined( 'ABSPATH' ) || exit;

/**
 * Lit un réglage du plugin (option unique `bm_crm_settings`).
 */
function bm_setting( string $key, $default = '' ) {
	$opts = get_option( 'bm_crm_settings', array() );
	return isset( $opts[ $key ] ) && '' !== $opts[ $key ] ? $opts[ $key ] : $default;
}

/**
 * Statuts de suivi d'expédition normalisés (indépendants du transporteur).
 */
function bm_tracking_statuses(): array {
	return array(
		'none'             => array( 'label' => 'Pas d\'expédition', 'color' => '#6b6257' ),
		'pending'          => array( 'label' => 'À expédier', 'color' => '#b45309' ),
		'label_created'    => array( 'label' => 'Étiquette créée', 'color' => '#1d4ed8' ),
		'in_transit'       => array( 'label' => 'En transit', 'color' => '#0e7490' ),
		'out_for_delivery' => array( 'label' => 'En cours de livraison', 'color' => '#0f766e' ),
		'delivered'        => array( 'label' => 'Livrée', 'color' => '#15803d' ),
		'exception'        => array( 'label' => 'Incident', 'color' => '#b91c1c' ),
		'returned'         => array( 'label' => 'Retournée à l\'expéditeur', 'color' => '#7c2d12' ),
		'cancelled'        => array( 'label' => 'Annulée', 'color' => '#374151' ),
	);
}

function bm_tracking_label( string $status ): string {
	$all = bm_tracking_statuses();
	return $all[ $status ]['label'] ?? ucfirst( $status );
}

/**
 * Pastille de statut (HTML) réutilisée dans toutes les pages admin.
 */
function bm_badge( string $status, ?string $label = null ): string {
	$all   = bm_tracking_statuses();
	$color = $all[ $status ]['color'] ?? '#374151';
	$label = $label ?? bm_tracking_label( $status );
	return sprintf(
		'<span class="bm-badge" style="--bm-badge:%s">%s</span>',
		esc_attr( $color ),
		esc_html( $label )
	);
}

/**
 * Statuts de retour SAV.
 */
function bm_return_statuses(): array {
	return array(
		'demandee'   => array( 'label' => 'Demandée', 'color' => '#b45309' ),
		'acceptee'   => array( 'label' => 'Acceptée', 'color' => '#1d4ed8' ),
		'refusee'    => array( 'label' => 'Refusée', 'color' => '#b91c1c' ),
		'en_transit' => array( 'label' => 'Colis en retour', 'color' => '#0e7490' ),
		'recue'      => array( 'label' => 'Reçue', 'color' => '#0f766e' ),
		'remboursee' => array( 'label' => 'Remboursée', 'color' => '#15803d' ),
		'echangee'   => array( 'label' => 'Échangée', 'color' => '#15803d' ),
		'cloturee'   => array( 'label' => 'Clôturée', 'color' => '#374151' ),
	);
}

function bm_return_badge( string $status ): string {
	$all   = bm_return_statuses();
	$color = $all[ $status ]['color'] ?? '#374151';
	$label = $all[ $status ]['label'] ?? ucfirst( $status );
	return sprintf( '<span class="bm-badge" style="--bm-badge:%s">%s</span>', esc_attr( $color ), esc_html( $label ) );
}

function bm_return_reasons(): array {
	return array(
		'defectueux'   => 'Produit défectueux / ne fonctionne pas',
		'endommage'    => 'Colis ou produit endommagé à la réception',
		'erreur'       => 'Erreur de commande (mauvais article / quantité)',
		'convient_pas' => 'Ne me convient pas',
		'changement'   => 'Changement d\'avis',
		'autre'        => 'Autre',
	);
}

function bm_admin_url( string $page, array $args = array() ): string {
	return add_query_arg( array_merge( array( 'page' => $page ), $args ), admin_url( 'admin.php' ) );
}

function bm_log( string $message, array $context = array(), string $level = 'info' ): void {
	if ( function_exists( 'wc_get_logger' ) ) {
		wc_get_logger()->log( $level, $message, array_merge( array( 'source' => 'belmains-crm' ), $context ) );
	}
}

function bm_order_edit_link( int $order_id ): string {
	$order = wc_get_order( $order_id );
	if ( ! $order ) {
		return '';
	}
	return $order->get_edit_order_url();
}

function bm_format_date( $date ): string {
	if ( ! $date ) {
		return '—';
	}
	if ( $date instanceof WC_DateTime ) {
		return $date->date_i18n( 'd/m/Y H:i' );
	}
	return date_i18n( 'd/m/Y H:i', is_numeric( $date ) ? (int) $date : strtotime( (string) $date ) );
}

/**
 * Statuts WooCommerce considérés « payés » côté Belmains (commandes à traiter ou traitées).
 */
function bm_paid_statuses(): array {
	return array( 'processing', 'bm-expediee', 'bm-livree', 'completed', 'bm-retour' );
}
