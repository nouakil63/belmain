<?php
defined( 'ABSPATH' ) || exit;

class BM_Install {

	const DB_VERSION = '1';

	public static function activate(): void {
		self::create_tables();

		if ( ! get_option( 'bm_crm_settings' ) ) {
			add_option( 'bm_crm_settings', array(
				'easyship_token'       => '',
				'easyship_sandbox'     => '1',
				'easyship_webhook_key' => wp_generate_password( 32, false ),
				'origin_company'       => 'Belmains',
				'origin_line1'         => '',
				'origin_city'          => '',
				'origin_postcode'      => '',
				'origin_country'       => 'FR',
				'origin_phone'         => '',
				'origin_email'         => get_option( 'admin_email' ),
				'parcel_weight'        => '0.45',
				'parcel_length'        => '22',
				'parcel_width'         => '16',
				'parcel_height'        => '8',
				'low_stock'            => '10',
				'return_window'        => '30',
				'notify_email'         => get_option( 'admin_email' ),
			) );
		}

		if ( ! wp_next_scheduled( 'bm_crm_sync_tracking' ) ) {
			wp_schedule_event( time() + 300, 'twicedaily', 'bm_crm_sync_tracking' );
		}

		update_option( 'bm_crm_db_version', self::DB_VERSION );

		// Les CPT / endpoints sont enregistrés à l'init : on force le flush au prochain chargement.
		update_option( 'bm_crm_flush_rewrite', '1' );
	}

	public static function deactivate(): void {
		wp_clear_scheduled_hook( 'bm_crm_sync_tracking' );
		flush_rewrite_rules();
	}

	public static function create_tables(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();

		$movements = "CREATE TABLE {$wpdb->prefix}bm_stock_movements (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			product_id BIGINT UNSIGNED NOT NULL,
			qty_before INT NULL,
			qty_delta INT NOT NULL,
			qty_after INT NULL,
			reason VARCHAR(40) NOT NULL DEFAULT 'autre',
			note TEXT NULL,
			order_id BIGINT UNSIGNED NULL,
			user_id BIGINT UNSIGNED NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY product_id (product_id),
			KEY order_id (order_id),
			KEY created_at (created_at)
		) $charset;";

		$events = "CREATE TABLE {$wpdb->prefix}bm_shipment_events (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			order_id BIGINT UNSIGNED NOT NULL,
			shipment_id VARCHAR(64) NULL,
			status VARCHAR(40) NOT NULL,
			message TEXT NULL,
			location VARCHAR(190) NULL,
			source VARCHAR(20) NOT NULL DEFAULT 'manual',
			event_at DATETIME NOT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY order_id (order_id),
			KEY shipment_id (shipment_id)
		) $charset;";

		dbDelta( $movements );
		dbDelta( $events );
	}
}
