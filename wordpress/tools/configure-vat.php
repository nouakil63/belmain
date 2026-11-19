<?php
/** Explicit one-time VAT setup through WP-CLI; never invoked by a plugin request. */
function belmains_configure_vat( string $csv, string $backup_dir, bool $isolated = false ): array {
    global $wpdb;
    if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! class_exists( 'WC_Tax' ) || ! class_exists( 'Belmains_Commerce' ) ) {
        throw new RuntimeException( 'WP-CLI, WooCommerce and Belmains Commerce are required.' );
    }
    if ( $isolated ) {
        if ( 'bcrm_validation_' !== $wpdb->prefix || 'local' !== wp_get_environment_type() || ! defined( 'WP_HTTP_BLOCK_EXTERNAL' ) || ! WP_HTTP_BLOCK_EXTERNAL || ! defined( 'DISABLE_WP_CRON' ) || ! DISABLE_WP_CRON ) {
            throw new RuntimeException( 'Isolated VAT configuration requires the disposable local installation.' );
        }
    } elseif ( '1' !== getenv( 'BELMAINS_VAT_APPLY' ) || 'belmains.com' !== wp_parse_url( home_url(), PHP_URL_HOST ) || ! apply_filters( 'belmains_catalog_only', false ) ) {
        throw new RuntimeException( 'Explicit BELMAINS_VAT_APPLY=1 and the protected Belmains production site are required.' );
    }
    $guard = ABSPATH . 'wp-content/mu-plugins/belmains-staging-guard.php';
    if ( ! $isolated && ( ! is_file( $guard ) || is_link( $guard ) ) ) { throw new RuntimeException( 'The existing closure guard is required.' ); }
    $guard_hash = is_file( $guard ) ? hash_file( 'sha256', $guard ) : null;
    if ( version_compare( Belmains_Commerce::VERSION, '0.2.2', '<' ) || false !== apply_filters( 'woocommerce_adjust_non_base_location_prices', true ) ) {
        throw new RuntimeException( 'The fixed TTC price policy must be installed first.' );
    }
    foreach ( array( 'woocommerce_currency' => 'EUR', 'woocommerce_allowed_countries' => 'specific', 'woocommerce_ship_to_countries' => 'specific' ) as $key => $expected ) {
        if ( get_option( $key ) !== $expected ) { throw new RuntimeException( 'Unexpected setting: ' . $key ); }
    }
    foreach ( array( 'woocommerce_specific_allowed_countries', 'woocommerce_specific_ship_to_countries' ) as $key ) {
        if ( array( 'FR' ) !== array_values( (array) get_option( $key ) ) ) { throw new RuntimeException( 'Current France-only destinations must be preserved.' ); }
    }
    $expected = array( 'AT'=>20, 'BE'=>21, 'BG'=>20, 'CY'=>19, 'CZ'=>21, 'DE'=>19, 'DK'=>25, 'EE'=>24, 'ES'=>21, 'FI'=>25.5, 'FR'=>20, 'GR'=>24, 'HR'=>25, 'HU'=>27, 'IE'=>23, 'IT'=>22, 'LT'=>21, 'LU'=>17, 'LV'=>21, 'MT'=>18, 'NL'=>21, 'PL'=>23, 'PT'=>23, 'RO'=>21, 'SE'=>25, 'SI'=>22, 'SK'=>23 );
    $handle = fopen( $csv, 'r' );
    if ( ! $handle ) { throw new RuntimeException( 'Reviewed VAT CSV is missing.' ); }
    $headers = fgetcsv( $handle, 0, ",", '"', "" ); $desired = array();
    try {
        if ( array( 'Country code', 'State code', 'ZIP/Postcode', 'City', 'Rate %', 'Tax name', 'Priority', 'Compound', 'Shipping', 'Tax class' ) !== $headers ) { throw new RuntimeException( 'Unexpected VAT CSV headers.' ); }
        while ( false !== ( $row = fgetcsv( $handle, 0, ",", '"', "" ) ) ) {
            if ( 10 !== count( $row ) ) { throw new RuntimeException( 'Malformed VAT row.' ); }
            list( $country, $state, $postcode, $city, $rate, $name, $priority, $compound, $shipping, $class ) = $row;
            if ( ! isset( $expected[ $country ] ) || isset( $desired[ $country ] ) || ! is_numeric( $rate ) || (float) $rate !== (float) $expected[ $country ] || array( '*', '*', '*', '1', '0', '1', '' ) !== array( $state, $postcode, $city, $priority, $compound, $shipping, $class ) || $name !== 'TVA ' . $country ) { throw new RuntimeException( 'Unreviewed or duplicate VAT row.' ); }
            $desired[ $country ] = array( 'tax_rate_country'=>$country, 'tax_rate_state'=>'', 'tax_rate'=>number_format( (float) $rate, 4, '.', '' ), 'tax_rate_name'=>$name, 'tax_rate_priority'=>1, 'tax_rate_compound'=>0, 'tax_rate_shipping'=>1, 'tax_rate_order'=>0, 'tax_rate_class'=>'' );
        }
    } finally { fclose( $handle ); }
    if ( 27 !== count( $desired ) ) { throw new RuntimeException( 'Exactly 27 reviewed rates are required.' ); }
    $all_rates = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}woocommerce_tax_rates ORDER BY tax_rate_id", ARRAY_A );
    $locations = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}woocommerce_tax_rate_locations ORDER BY location_id", ARRAY_A );
    $ids = array();
    foreach ( $all_rates as $row ) {
        if ( '' !== $row['tax_rate_class'] ) { continue; }
        $country = $row['tax_rate_country'];
        if ( ! isset( $desired[ $country ] ) || isset( $ids[ $country ] ) ) { throw new RuntimeException( 'Conflicting existing standard VAT rates: nothing changed.' ); }
        foreach ( $desired[ $country ] as $key => $value ) {
            if ( 'tax_rate_order' !== $key && (string) $row[ $key ] !== (string) $value ) { throw new RuntimeException( 'Conflicting existing standard VAT rate: ' . $country ); }
        }
        foreach ( $locations as $location ) { if ( (int) $location['tax_rate_id'] === (int) $row['tax_rate_id'] ) { throw new RuntimeException( 'Existing standard VAT rate has geographic restrictions.' ); } }
        $ids[ $country ] = (int) $row['tax_rate_id'];
    }
    $options = array( 'woocommerce_prices_include_tax'=>'yes', 'woocommerce_tax_based_on'=>'shipping', 'woocommerce_tax_display_shop'=>'incl', 'woocommerce_tax_display_cart'=>'incl', 'woocommerce_tax_round_at_subtotal'=>'no', 'woocommerce_calc_taxes'=>'yes' );
    $before = array(); foreach ( $options as $key => $_ ) { $before[ $key ] = array( 'exists'=>null !== get_option( $key, null ), 'value'=>get_option( $key, null ) ); }
    $untouched = array(); foreach ( array( 'woocommerce_default_country', 'woocommerce_currency', 'woocommerce_allowed_countries', 'woocommerce_ship_to_countries', 'woocommerce_specific_allowed_countries', 'woocommerce_specific_ship_to_countries' ) as $key ) { $untouched[ $key ] = get_option( $key ); }
    $product = wc_get_product( Belmains_Commerce::product_id() );
    if ( ! $product || 'taxable' !== $product->get_tax_status() || '' !== $product->get_tax_class() ) { throw new RuntimeException( 'The existing product must use the standard taxable class.' ); }
    $product_before = array( 'id'=>$product->get_id(), 'stock'=>$product->get_stock_quantity(), 'price'=>$product->get_price( 'edit' ), 'regular'=>$product->get_regular_price( 'edit' ), 'duo'=>$product->get_meta( '_belmains_duo_price', true ), 'status'=>$product->get_status() );
    $parent = realpath( dirname( $backup_dir ) );
    if ( false === $parent || is_link( $backup_dir ) || ( ! $isolated && ( $parent !== realpath( dirname( rtrim( ABSPATH, '/' ), 2 ) . '/belmains-deployment' ) ) ) ) { throw new RuntimeException( 'Backup must be inside the existing private deployment directory.' ); }
    if ( ! is_dir( $backup_dir ) && ! mkdir( $backup_dir, 0700 ) ) { throw new RuntimeException( 'Private backup directory unavailable.' ); }
    chmod( $backup_dir, 0700 );
    $backup = $backup_dir . '/vat-' . gmdate( 'Ymd-His' ) . '-' . bin2hex( random_bytes( 4 ) ) . '.json';
    $saved = array( 'created_at'=>gmdate( 'c' ), 'options'=>$before, 'untouched_options'=>$untouched, 'rates'=>$all_rates, 'locations'=>$locations, 'product'=>$product_before, 'guard_sha256'=>$guard_hash );
    $backup_handle = fopen( $backup, 'x' );
    if ( false === $backup_handle ) { throw new RuntimeException( 'Cannot create private VAT backup.' ); }
    chmod( $backup, 0600 ); $encoded = wp_json_encode( $saved, JSON_PRETTY_PRINT );
    if ( strlen( $encoded ) !== fwrite( $backup_handle, $encoded ) ) { fclose( $backup_handle ); throw new RuntimeException( 'Incomplete private VAT backup.' ); }
    fflush( $backup_handle ); fclose( $backup_handle ); $created = array(); $reused = count( $ids );
    try {
        foreach ( $desired as $country => $row ) {
            if ( isset( $ids[ $country ] ) ) { continue; }
            $id = (int) WC_Tax::_insert_tax_rate( $row );
            if ( ! $id ) { throw new RuntimeException( 'Could not insert VAT rate: ' . $country ); }
            $created[] = $id; $ids[ $country ] = $id;
        }
        foreach ( $options as $key => $value ) { update_option( $key, $value ); if ( get_option( $key ) !== $value ) { throw new RuntimeException( 'VAT option update failed.' ); } }
        WC_Cache_Helper::invalidate_cache_group( 'taxes' );
        $checked = array();
        foreach ( $expected as $country => $rate ) {
            $found = WC_Tax::find_rates( array( 'country'=>$country, 'state'=>'', 'postcode'=>'75001', 'city'=>'Paris', 'tax_class'=>'' ) );
            if ( 1 !== count( $found ) || ! isset( $found[ $ids[ $country ] ] ) || (float) $found[ $ids[ $country ] ]['rate'] !== (float) $rate ) { throw new RuntimeException( 'Destination lookup failed: ' . $country ); }
            foreach ( array( 89.99, 149.99 ) as $gross ) {
                $tax = array_sum( WC_Tax::calc_tax( $gross, $found, true ) );
                if ( abs( round( $tax, 2 ) - round( $gross * $rate / ( 100 + $rate ), 2 ) ) > 0.001 ) { throw new RuntimeException( 'Inclusive VAT extraction failed: ' . $country ); }
            }
            $checked[ $country ] = (float) $rate;
        }
        foreach ( $untouched as $key => $value ) { if ( get_option( $key ) !== $value ) { throw new RuntimeException( 'Unrelated option changed.' ); } }
        $fresh = wc_get_product( $product->get_id() );
        $product_after = array( 'id'=>$fresh->get_id(), 'stock'=>$fresh->get_stock_quantity(), 'price'=>$fresh->get_price( 'edit' ), 'regular'=>$fresh->get_regular_price( 'edit' ), 'duo'=>$fresh->get_meta( '_belmains_duo_price', true ), 'status'=>$fresh->get_status() );
        if ( $product_after !== $product_before || ( null !== $guard_hash && hash_file( 'sha256', $guard ) !== $guard_hash ) ) { throw new RuntimeException( 'Product or closure guard changed.' ); }
        return array( 'status'=>'configured_sales_closed', 'inserted'=>count( $created ), 'reused'=>$reused, 'rates'=>$checked, 'settings'=>$options, 'shipping_countries_unchanged'=>true, 'stock_unchanged'=>true, 'guard_unchanged'=>true, 'existing_orders_recalculated'=>false, 'backup_file'=>basename( $backup ) );
    } catch ( Throwable $error ) {
        foreach ( $created as $id ) { WC_Tax::_delete_tax_rate( $id ); }
        foreach ( $before as $key => $old ) { if ( $old['exists'] ) { update_option( $key, $old['value'] ); } else { delete_option( $key ); } }
        WC_Cache_Helper::invalidate_cache_group( 'taxes' );
        throw $error;
    }
}
if ( ! defined( 'BELMAINS_VAT_CONFIG_LIBRARY' ) ) {
    $csv = dirname( __DIR__ ) . '/data/vat-eu27-standard.csv';
    $backup = dirname( rtrim( ABSPATH, '/' ), 2 ) . '/belmains-deployment/vat-backups';
    echo wp_json_encode( belmains_configure_vat( $csv, $backup ), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) . "\n";
}
