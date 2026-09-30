<?php
/**
 * Plugin Name: Belmains CRM
 * Description: Pilotage privé de la boutique : commandes, clients, audience consentie, stocks, SAV et expéditions.
 * Version: 0.1.0
 * Requires at least: 6.3
 * Requires PHP: 8.0
 * Author: Belmains
 * License: GPL-2.0-or-later
 * Text Domain: belmains-crm
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
define( 'BCRM_VERSION', '0.1.0' );
define( 'BCRM_PATH', plugin_dir_path( __FILE__ ) );
define( 'BCRM_URL', plugin_dir_url( __FILE__ ) );
require_once BCRM_PATH . 'includes/class-bcrm-commerce.php';
require_once BCRM_PATH . 'includes/class-bcrm-tracking.php';
require_once BCRM_PATH . 'includes/class-bcrm-audience.php';
require_once BCRM_PATH . 'includes/class-bcrm-support.php';
require_once BCRM_PATH . 'includes/class-bcrm-marketing.php';
require_once BCRM_PATH . 'includes/class-bcrm-app.php';

register_activation_hook( __FILE__, array( 'BCRM_App', 'activate' ) );
register_deactivation_hook( __FILE__, function () { wp_clear_scheduled_hook( 'bcrm_daily_cleanup' ); } );
add_action( 'before_woocommerce_init', function () {
    if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
    }
} );
add_action( 'plugins_loaded', array( 'BCRM_App', 'register' ), 30 );
