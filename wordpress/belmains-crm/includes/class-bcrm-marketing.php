<?php
/**
 * Manually recorded advertising costs and consented order attribution.
 *
 * This module neither contacts advertising platforms nor attributes untracked orders.
 * @package BelmainsCRM
 */

defined( 'ABSPATH' ) || exit;

class BCRM_Marketing {

	const DISPLAY_LIMIT = 5000;

	private static function table() {
		global $wpdb;
		return $wpdb->prefix . 'bcrm_marketing_costs';
	}

	/** Called by the plugin activation / schema upgrade path. */
	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table   = self::table();
		$charset = $wpdb->get_charset_collate();
		dbDelta( "CREATE TABLE $table (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			date date NOT NULL,
			source varchar(80) NOT NULL,
			campaign varchar(160) NOT NULL DEFAULT '',
			amount decimal(18,6) NOT NULL,
			currency varchar(8) NOT NULL,
			note varchar(500) NOT NULL DEFAULT '',
			PRIMARY KEY  (id),
			KEY currency_date (currency,date),
			KEY cost_date (date)
		) $charset;" );
	}

	private static function error( $code, $message, $status = 400 ) {
		return new WP_Error( $code, $message, array( 'status' => $status ) );
	}

	private static function currency() {
		return function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'EUR';
	}

	private static function money( $amount ) {
		return round( (float) $amount, function_exists( 'wc_get_price_decimals' ) ? wc_get_price_decimals() : 2 );
	}

	private static function iso_date( $value ) {
		if ( ! is_string( $value ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/D', $value ) ) {
			return false;
		}
		$date = DateTimeImmutable::createFromFormat( '!Y-m-d', $value, wp_timezone() );
		return $date && $date->format( 'Y-m-d' ) === $value && (int) $date->format( 'Y' ) >= 1900 ? $date : false;
	}

	private static function length( $value ) {
		return function_exists( 'mb_strlen' ) ? mb_strlen( $value, 'UTF-8' ) : strlen( $value );
	}

	private static function source( $value ) {
		$value = trim( sanitize_text_field( $value ) );
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $value, 'UTF-8' ) : strtolower( $value );
	}

	private static function key( $source, $campaign ) {
		return hash( 'sha256', wp_json_encode( array( $source, $campaign ) ) );
	}

	private static function campaign_row( $source, $campaign ) {
		return array( 'source' => $source, 'campaign' => $campaign, 'orders' => 0, 'revenue' => 0.0, 'spend' => 0.0, 'roas' => null );
	}

	/**
	 * Revenue follows the creation-date cohort and excludes taxes / known refunds.
	 * Costs follow the manually entered spend date. The ratio is observed ROAS,
	 * not ROI or profit; it cannot recover visitors who declined attribution.
	 *
	 * @return array|WP_Error
	 */
	public static function report( $from, $to ) {
		global $wpdb;
		$start = self::iso_date( $from );
		$end   = self::iso_date( $to );
		if ( ! $start || ! $end || $end < $start || $start->diff( $end )->days > 365 ) {
			return self::error( 'bcrm_marketing_dates', 'Choisissez une période valide de 366 jours maximum.' );
		}
		$table    = self::table();
		$currency = self::currency();
		// These queries access only this plugin's own table, never WooCommerce tables.
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT id,date,source,campaign,amount,currency,note FROM $table WHERE currency = %s AND date BETWEEN %s AND %s ORDER BY date DESC,id DESC LIMIT %d",
			$currency, $from, $to, self::DISPLAY_LIMIT + 1
		), ARRAY_A );
		if ( ! is_array( $rows ) ) {
			return self::error( 'bcrm_marketing_read', 'Les dépenses marketing ne sont pas disponibles. Vérifiez l’installation du module.', 503 );
		}
		$cost_totals = $wpdb->get_row( $wpdb->prepare(
			"SELECT COUNT(*) AS entries,COALESCE(SUM(amount),0) AS spend FROM $table WHERE currency = %s AND date BETWEEN %s AND %s",
			$currency, $from, $to
		), ARRAY_A );
		// BINARY grouping preserves campaign case, as UTM campaign values are case-sensitive.
		$cost_groups = $wpdb->get_results( $wpdb->prepare(
			"SELECT BINARY source AS source,BINARY campaign AS campaign,SUM(amount) AS spend FROM $table WHERE currency = %s AND date BETWEEN %s AND %s GROUP BY BINARY source,BINARY campaign ORDER BY spend DESC LIMIT %d",
			$currency, $from, $to, self::DISPLAY_LIMIT + 1
		), ARRAY_A );
		if ( ! is_array( $cost_totals ) || ! is_array( $cost_groups ) ) {
			return self::error( 'bcrm_marketing_read', 'Impossible de calculer les dépenses marketing.', 503 );
		}
		$foreign_costs = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM $table WHERE currency <> %s AND date BETWEEN %s AND %s",
			$currency, $from, $to
		) );
		if ( null === $foreign_costs ) {
			return self::error( 'bcrm_marketing_read', 'Impossible de vérifier la devise des dépenses.', 503 );
		}
		$warnings  = array();
		$campaigns = array();
		$groups_capped = count( $cost_groups ) > self::DISPLAY_LIMIT;
		foreach ( array_slice( $cost_groups, 0, self::DISPLAY_LIMIT ) as $group ) {
			$key = self::key( $group['source'], $group['campaign'] );
			$campaigns[ $key ] = self::campaign_row( $group['source'], $group['campaign'] );
			$campaigns[ $key ]['spend'] = (float) $group['spend'];
		}
		$commerce_available = class_exists( 'BCRM_Commerce' ) && function_exists( 'wc_get_orders' );
		$scan = $commerce_available ? BCRM_Commerce::scan_orders( $from, $to ) : array( 'orders' => array(), 'capped' => false, 'warnings' => array( 'WooCommerce doit être activé pour attribuer des commandes aux campagnes.' ) );
		if ( is_wp_error( $scan ) ) {
			return $scan;
		}
		$warnings = array_merge( $warnings, $scan['warnings'] );
		$totals = array( 'spend' => (float) $cost_totals['spend'], 'revenue' => 0.0, 'orders' => 0, 'roas' => null );
		$unattributed = 0;
		$foreign_orders = 0;
		$invalid_attribution = 0;
		foreach ( $scan['orders'] as $order ) {
			if ( ! $order->is_paid() && null === $order->get_date_paid() && (float) $order->get_total_refunded() <= 0 ) {
				continue;
			}
			if ( $order->get_currency() !== $currency ) {
				$foreign_orders++;
				continue;
			}
			$raw_source   = $order->get_meta( '_bcrm_source', true );
			$raw_campaign = $order->get_meta( '_bcrm_campaign', true );
			if ( ! is_string( $raw_source ) || ! is_string( $raw_campaign ) || self::length( $raw_source ) > 80 || self::length( $raw_campaign ) > 160 ) {
				$unattributed++;
				$invalid_attribution++;
				continue;
			}
			$source   = self::source( $raw_source );
			$campaign = trim( sanitize_text_field( $raw_campaign ) );
			if ( '' === $source ) {
				$unattributed++;
				continue;
			}
			$key = self::key( $source, $campaign );
			if ( ! isset( $campaigns[ $key ] ) && ! $groups_capped ) {
				$campaigns[ $key ] = self::campaign_row( $source, $campaign );
			}
			$revenue = (float) $order->get_total() - (float) $order->get_total_tax() - ( (float) $order->get_total_refunded() - (float) $order->get_total_tax_refunded() );
			if ( isset( $campaigns[ $key ] ) ) {
				$campaigns[ $key ]['orders']++;
				$campaigns[ $key ]['revenue'] += $revenue;
			}
			$totals['orders']++;
			$totals['revenue'] += $revenue;
		}
		$attribution_complete = $commerce_available && ! $scan['capped'];
		$orders_without_spend = false;
		foreach ( $campaigns as &$campaign_row ) {
			$orders_without_spend = $orders_without_spend || ( $campaign_row['orders'] > 0 && $campaign_row['spend'] <= 0 );
			$campaign_row['roas'] = $attribution_complete && $campaign_row['spend'] > 0 && $campaign_row['orders'] > 0 ? round( $campaign_row['revenue'] / $campaign_row['spend'], 2 ) : null;
			$campaign_row['spend'] = self::money( $campaign_row['spend'] );
			$campaign_row['revenue'] = self::money( $campaign_row['revenue'] );
		}
		unset( $campaign_row );
		$totals['roas'] = $attribution_complete && ! $groups_capped && ! $orders_without_spend && $totals['spend'] > 0 && $totals['orders'] > 0 ? round( $totals['revenue'] / $totals['spend'], 2 ) : null;
		$totals['spend'] = self::money( $totals['spend'] );
		$totals['revenue'] = self::money( $totals['revenue'] );
		usort( $campaigns, static function ( $a, $b ) {
			return ( $b['spend'] <=> $a['spend'] ) ?: ( $b['revenue'] <=> $a['revenue'] );
		} );
		$items = array();
		foreach ( array_slice( $rows, 0, self::DISPLAY_LIMIT ) as $row ) {
			$items[] = array( 'id' => (int) $row['id'], 'date' => $row['date'], 'source' => $row['source'], 'campaign' => $row['campaign'], 'amount' => self::money( $row['amount'] ), 'currency' => $row['currency'], 'note' => $row['note'] );
		}
		$display_capped = count( $rows ) > self::DISPLAY_LIMIT || count( $campaigns ) > self::DISPLAY_LIMIT || count( $cost_groups ) > self::DISPLAY_LIMIT;
		if ( $display_capped ) {
			$warnings[] = 'Affichage limité aux 5 000 dépenses et campagnes retenues. Le total des dépenses inclut toutes les saisies de la période ; réduisez la période pour consulter tout le détail.';
		}
		if ( $foreign_costs || $foreign_orders ) {
			$warnings[] = sprintf( '%1$d dépense(s) et %2$d commande(s) payée(s) dans une autre devise exclues. Tous les montants affichés sont en %3$s, sans conversion.', (int) $foreign_costs, $foreign_orders, $currency );
		}
		if ( $unattributed ) {
			$warnings[] = sprintf( '%d commande(s) payée(s) sans attribution exploitable exclue(s) du chiffre d’affaires marketing. Elles restent dans le rapport commercial.', $unattributed );
		}
		if ( $invalid_attribution ) {
			$warnings[] = 'Certaines métadonnées source/campagne dépassent les formats attendus et ont été ignorées.';
		}
		if ( $orders_without_spend ) {
			$warnings[] = 'ROAS global non calculé : certaines sources ou campagnes ont des commandes attribuées sans dépense correspondante. Leurs ventes, notamment organiques ou directes, ne doivent pas gonfler le rendement publicitaire.';
		}
		$warnings[] = 'Attribution uniquement lorsque la source est enregistrée sur la commande avec consentement. Le ROAS observé compare le chiffre d’affaires attribué hors taxes, net de remboursements, aux dépenses publicitaires hors taxes saisies ; ce n’est ni un bénéfice ni un ROI.';
		$warnings[] = 'Commandes regroupées par date de création ; dépenses par date de saisie indiquée. Les remboursements connus sont rattachés à la commande d’origine. Les montants publicitaires sont manuels et ne sont pas synchronisés avec les régies.';
		if ( ! $totals['orders'] || ! $totals['spend'] ) {
			$warnings[] = 'ROAS non calculé sans commande attribuée et dépense renseignée. Une campagne sans vente attribuée n’est pas considérée automatiquement comme sans vente réelle.';
		}
		return array(
			'items' => $items,
			'campaigns' => array_slice( $campaigns, 0, self::DISPLAY_LIMIT ),
			'totals' => $totals,
			'warnings' => $warnings,
			'currency' => $currency,
			'partial' => (bool) $scan['capped'],
			'display_capped' => $display_capped,
			'unattributed_orders' => $unattributed,
			'cost_entries' => (int) $cost_totals['entries'],
		);
	}

	/** Insert one manual advertising expense in the current shop currency. */
	public static function save_cost( $data ) {
		global $wpdb;
		if ( ! is_array( $data ) || ! self::iso_date( $data['date'] ?? null ) ) {
			return self::error( 'bcrm_marketing_date', 'La dépense doit avoir une date valide au format AAAA-MM-JJ.' );
		}
		foreach ( array( 'source' => 80, 'campaign' => 160, 'note' => 500 ) as $field => $limit ) {
			$value = $data[ $field ] ?? '';
			if ( ! is_string( $value ) || self::length( $value ) > $limit ) {
				return self::error( 'bcrm_marketing_text', sprintf( 'Le champ %1$s doit être un texte de %2$d caractères maximum.', $field, $limit ) );
			}
		}
		$source = self::source( $data['source'] ?? '' );
		$campaign = trim( sanitize_text_field( $data['campaign'] ?? '' ) );
		$note = trim( sanitize_textarea_field( $data['note'] ?? '' ) );
		if ( '' === $source ) {
			return self::error( 'bcrm_marketing_source', 'Indiquez la source publicitaire, par exemple google ou meta.' );
		}
		$amount = $data['amount'] ?? null;
		if ( ! is_int( $amount ) && ! is_float( $amount ) && ! is_string( $amount ) ) {
			return self::error( 'bcrm_marketing_amount', 'Indiquez un montant publicitaire hors taxes strictement positif.' );
		}
		$amount = (string) $amount;
		if ( ! preg_match( '/^\d+(?:\.\d{1,6})?$/D', $amount ) || ! is_finite( (float) $amount ) || (float) $amount <= 0 || (float) $amount > 1000000000 ) {
			return self::error( 'bcrm_marketing_amount', 'Le montant doit être strictement positif, sans symbole de devise, avec un point décimal si nécessaire.' );
		}
		$saved = $wpdb->insert( self::table(), array(
			'date' => $data['date'], 'source' => $source, 'campaign' => $campaign,
			'amount' => $amount, 'currency' => self::currency(), 'note' => $note,
		), array( '%s', '%s', '%s', '%s', '%s', '%s' ) );
		if ( false === $saved ) {
			return self::error( 'bcrm_marketing_save', 'La dépense n’a pas pu être enregistrée.', 500 );
		}
		return array( 'success' => true, 'id' => (int) $wpdb->insert_id );
	}

	/** Delete only a single manual cost row, never its campaign or any order. */
	public static function delete_cost( $id ) {
		global $wpdb;
		if ( ! is_scalar( $id ) || ! preg_match( '/^[1-9]\d*$/D', (string) $id ) || (float) $id > PHP_INT_MAX ) {
			return self::error( 'bcrm_marketing_id', 'Identifiant de dépense invalide.' );
		}
		$deleted = $wpdb->delete( self::table(), array( 'id' => (int) $id ), array( '%d' ) );
		if ( false === $deleted ) {
			return self::error( 'bcrm_marketing_delete', 'La dépense n’a pas pu être supprimée.', 500 );
		}
		if ( 0 === $deleted ) {
			return self::error( 'bcrm_marketing_missing', 'Dépense introuvable.', 404 );
		}
		return array( 'success' => true, 'id' => (int) $id );
	}
}
