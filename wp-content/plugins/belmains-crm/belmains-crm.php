<?php
/**
 * Plugin Name:       Belmains CRM
 * Plugin URI:        https://www.belmains.fr/
 * Description:       Tableau de bord Belmains : commandes, suivi d'expédition (Easyship), mouvements de stock et retours SAV. S'appuie sur WooCommerce.
 * Version:           0.1.0
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Requires Plugins:  woocommerce
 * Author:            Belmains
 * Text Domain:       belmains-crm
 * WC requires at least: 8.0
 */

defined( 'ABSPATH' ) || exit;

define( 'BM_CRM_VERSION', '0.1.0' );
define( 'BM_CRM_FILE', __FILE__ );
define( 'BM_CRM_DIR', plugin_dir_path( __FILE__ ) );
define( 'BM_CRM_URL', plugin_dir_url( __FILE__ ) );

require_once BM_CRM_DIR . 'includes/helpers.php';
require_once BM_CRM_DIR . 'includes/class-bm-install.php';
require_once BM_CRM_DIR . 'includes/class-bm-settings.php';
require_once BM_CRM_DIR . 'includes/class-bm-easyship.php';
require_once BM_CRM_DIR . 'includes/class-bm-orders.php';
require_once BM_CRM_DIR . 'includes/class-bm-shipments.php';
require_once BM_CRM_DIR . 'includes/class-bm-stock.php';
require_once BM_CRM_DIR . 'includes/class-bm-returns.php';
require_once BM_CRM_DIR . 'includes/class-bm-admin.php';

register_activation_hook( __FILE__, array( 'BM_Install', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'BM_Install', 'deactivate' ) );

// Compatibilité HPOS (tables de commandes dédiées WooCommerce).
add_action( 'before_woocommerce_init', function () {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
} );

add_action( 'plugins_loaded', 'bm_crm_boot', 20 );

function bm_crm_boot(): void {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', function () {
			echo '<div class="notice notice-error"><p><strong>Belmains CRM</strong> : WooCommerce doit être installé et activé.</p></div>';
		} );
		return;
	}

	BM_Settings::init();
	BM_Easyship::init();
	BM_Orders::init();
	BM_Shipments::init();
	BM_Stock::init();
	BM_Returns::init();
	BM_Admin::init();
}
