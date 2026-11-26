<?php
/** Configuration tests only on disposable local WordPress; never production. */
global $wpdb;
if ( 'bcrm_validation_' !== $wpdb->prefix || 'local' !== wp_get_environment_type() || ! defined( 'WP_HTTP_BLOCK_EXTERNAL' ) || ! WP_HTTP_BLOCK_EXTERNAL || ! defined( 'DISABLE_WP_CRON' ) || ! DISABLE_WP_CRON ) { throw new RuntimeException( 'VAT import tests require isolated local tables and blocked network/cron.' ); }
add_filter( 'pre_wp_mail', '__return_false', PHP_INT_MAX );
add_filter( 'pre_http_request', static function () { return new WP_Error( 'isolated_test', 'External HTTP blocked.' ); }, PHP_INT_MAX );
define( 'BELMAINS_VAT_CONFIG_LIBRARY', true );
require dirname( __DIR__ ) . '/tools/configure-vat.php';
if ( (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}woocommerce_tax_rates WHERE tax_rate_class = ''" ) ) { throw new RuntimeException( 'The isolated standard tax class must be empty before the import test.' ); }
$checks = 0; $options = array(); $product = null; $created = array(); $conflict_id = 0;
$root = sys_get_temp_dir() . '/belmains-vat-config-' . bin2hex( random_bytes( 6 ) ); mkdir( $root, 0700 );
$csv = dirname( __DIR__ ) . '/data/vat-eu27-standard.csv';
$check = static function ( bool $value, string $name ) use ( &$checks ) { if ( ! $value ) { throw new RuntimeException( 'FAIL: ' . $name ); } ++$checks; };
$refuses = static function ( callable $call, string $name ) use ( $check ) { try { $call(); } catch ( Throwable $error ) { $check( true, $name ); return; } throw new RuntimeException( 'Expected refusal: ' . $name ); };
$save_option = static function ( string $key, $value ) use ( &$options ) { if ( ! array_key_exists( $key, $options ) ) { $options[ $key ] = array( 'exists'=>null !== get_option( $key, null ), 'value'=>get_option( $key, null ) ); } update_option( $key, $value ); };
$before_orders = wc_get_orders( array( 'limit'=>1, 'paginate'=>true, 'return'=>'ids' ) )->total;
try {
    foreach ( array( 'woocommerce_currency'=>'EUR', 'woocommerce_allowed_countries'=>'specific', 'woocommerce_specific_allowed_countries'=>array( 'FR' ), 'woocommerce_ship_to_countries'=>'specific', 'woocommerce_specific_ship_to_countries'=>array( 'FR' ), 'woocommerce_prices_include_tax'=>'yes', 'woocommerce_tax_based_on'=>'shipping', 'woocommerce_tax_display_shop'=>'incl', 'woocommerce_tax_display_cart'=>'incl', 'woocommerce_tax_round_at_subtotal'=>'no', 'woocommerce_calc_taxes'=>'no' ) as $key => $value ) { $save_option( $key, $value ); }
    $product = new WC_Product_Simple(); $product->set_name( 'Isolated VAT configuration fixture' ); $product->set_status( 'publish' ); $product->set_regular_price( '109.99' ); $product->set_sale_price( '89.99' ); $product->set_tax_status( 'taxable' ); $product->set_tax_class( '' ); $product->set_manage_stock( true ); $product->set_stock_quantity( 500 ); $product->save();
    $save_option( 'belmains_product_id', $product->get_id() );
    $base_country = get_option( 'woocommerce_default_country' );
    $refuses( static function () use ( $csv, $root ) { belmains_configure_vat( $csv, $root . '/backups', false ); }, 'Production entry rejects the isolated site' );
    $before_failure = array(); foreach ( array( 'woocommerce_calc_taxes', 'woocommerce_prices_include_tax', 'woocommerce_tax_based_on', 'woocommerce_tax_display_shop', 'woocommerce_tax_display_cart', 'woocommerce_tax_round_at_subtotal' ) as $key ) { $before_failure[ $key ] = get_option( $key ); }
    $force_disabled = static function () { return 'no'; };
    add_filter( 'pre_update_option_woocommerce_calc_taxes', $force_disabled, PHP_INT_MAX );
    try { $refuses( static function () use ( $csv, $root ) { belmains_configure_vat( $csv, $root . '/backups', true ); }, 'Post-import option failure triggers rollback' ); }
    finally { remove_filter( 'pre_update_option_woocommerce_calc_taxes', $force_disabled, PHP_INT_MAX ); }
    $check( 0 === (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}woocommerce_tax_rates WHERE tax_rate_class = ''" ), 'Rollback removes only newly inserted standard rates' );
    foreach ( $before_failure as $key => $value ) { $check( get_option( $key ) === $value, 'Rollback restores option ' . $key ); }
    $check( 500 === wc_get_product( $product->get_id() )->get_stock_quantity(), 'Rollback preserves stock' );
    $check( $before_orders === wc_get_orders( array( 'limit'=>1, 'paginate'=>true, 'return'=>'ids' ) )->total, 'Failed configuration creates no orders' );
    $result = belmains_configure_vat( $csv, $root . '/backups', true );
    $created = array_map( 'intval', $wpdb->get_col( "SELECT tax_rate_id FROM {$wpdb->prefix}woocommerce_tax_rates WHERE tax_rate_class = ''" ) );
    $check( 27 === $result['inserted'] && 0 === $result['reused'] && 27 === count( $created ), '27 standard rates imported' );
    $check( 'yes' === get_option( 'woocommerce_calc_taxes' ), 'Taxes enabled only after import' );
    $check( 'shipping' === get_option( 'woocommerce_tax_based_on' ) && 'incl' === get_option( 'woocommerce_tax_display_cart' ), 'Shipping-based inclusive settings' );
    $check( $base_country === get_option( 'woocommerce_default_country' ), 'Store base country preserved' );
    $check( array( 'FR' ) === get_option( 'woocommerce_specific_ship_to_countries' ) && array( 'FR' ) === get_option( 'woocommerce_specific_allowed_countries' ), 'Destinations not expanded' );
    foreach ( $result['rates'] as $country => $expected ) {
        $found = WC_Tax::find_rates( array( 'country'=>$country, 'state'=>'', 'postcode'=>'75001', 'city'=>'Paris', 'tax_class'=>'' ) );
        $rate = current( $found );
        $check( 1 === count( $found ) && (float) $rate['rate'] === (float) $expected && 'yes' === $rate['shipping'] && 'no' === $rate['compound'], $country . ' actual standard rate and flags' );
    }
    $check( 0 === (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}woocommerce_tax_rate_locations" ), 'No postcode wildcard rows or territorial expansion inserted' );
    $again = belmains_configure_vat( $csv, $root . '/backups', true );
    $check( 0 === $again['inserted'] && 27 === $again['reused'], 'Repeat application reuses exact rates' );
    $check( 27 === (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}woocommerce_tax_rates WHERE tax_rate_class = ''" ), 'No duplicate standard rates' );
    $france = $wpdb->get_var( "SELECT tax_rate_id FROM {$wpdb->prefix}woocommerce_tax_rates WHERE tax_rate_country='FR' AND tax_rate_class=''" );
    WC_Tax::_update_tax_rate( $france, array( 'tax_rate'=>'21.0000' ) );
    $before_conflict = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}woocommerce_tax_rates ORDER BY tax_rate_id", ARRAY_A );
    $refuses( static function () use ( $csv, $root ) { belmains_configure_vat( $csv, $root . '/backups', true ); }, 'Conflicting existing rate is rejected' );
    $check( $before_conflict === $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}woocommerce_tax_rates ORDER BY tax_rate_id", ARRAY_A ), 'Conflict rejected before any rate writes' );
    WC_Tax::_update_tax_rate( $france, array( 'tax_rate'=>'20.0000' ) );
    $bad_csv = $root . '/duplicate.csv'; $contents = file_get_contents( $csv ); $lines = preg_split( '/\r?\n/', trim( $contents ) ); file_put_contents( $bad_csv, rtrim( $contents ) . "\n" . $lines[1] . "\n" );
    $refuses( static function () use ( $bad_csv, $root ) { belmains_configure_vat( $bad_csv, $root . '/backups', true ); }, 'Duplicate CSV country rejected' );
    $fresh = wc_get_product( $product->get_id() );
    $check( 500 === $fresh->get_stock_quantity() && '89.99' === $fresh->get_price( 'edit' ), 'Stock and product price preserved' );
    $check( $before_orders === wc_get_orders( array( 'limit'=>1, 'paginate'=>true, 'return'=>'ids' ) )->total, 'No orders created by configuration checks' );
    $backups = glob( $root . '/backups/*.json' );
    $check( 3 === count( $backups ), 'Private backups include the failed attempt and both successful applications' );
    foreach ( $backups as $backup ) { $check( 0600 === ( fileperms( $backup ) & 0777 ), 'VAT backup permissions are private' ); }
    echo wp_json_encode( array( 'count'=>$checks, 'status'=>'passed', 'production_touched'=>false, 'orders_created'=>false ), JSON_PRETTY_PRINT ) . "\n";
} finally {
    foreach ( $created as $id ) { WC_Tax::_delete_tax_rate( $id ); }
    if ( $product ) { $product->delete( true ); }
    foreach ( $options as $key => $old ) { if ( $old['exists'] ) { update_option( $key, $old['value'] ); } else { delete_option( $key ); } }
    WC_Cache_Helper::invalidate_cache_group( 'taxes' );
    foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST ) as $entry ) { if ( $entry->isDir() ) { rmdir( $entry->getPathname() ); } else { unlink( $entry->getPathname() ); } } rmdir( $root );
}
