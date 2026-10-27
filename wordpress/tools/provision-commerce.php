<?php
/** Explicit one-time setup: wp eval-file ... with BELMAINS_INITIAL_STOCK set. Never run on activation. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! function_exists( 'wc_get_product' ) ) { throw new RuntimeException( 'Run with WP-CLI and WooCommerce active.' ); }
$initial_stock = getenv( 'BELMAINS_INITIAL_STOCK' );
if ( false === $initial_stock || ! ctype_digit( $initial_stock ) ) { throw new RuntimeException( 'Set BELMAINS_INITIAL_STOCK to the confirmed physical stock.' ); }
$product_id = absint( get_option( 'belmains_product_id' ) );
$product = $product_id ? wc_get_product( $product_id ) : false;
if ( ! $product ) {
    $existing_sku = wc_get_product_id_by_sku( 'BELMAINS-GRIS' );
    if ( $existing_sku ) { throw new RuntimeException( 'SKU already exists. Review it and configure belmains_product_id explicitly.' ); }
    $product = new WC_Product_Simple();
    $product->set_name( 'Le gant de massage Belmains — Gris' );
    $product->set_slug( 'gant-massage-belmains-gris' );
    $product->set_sku( 'BELMAINS-GRIS' );
    $product->set_status( 'publish' );
    $product->set_catalog_visibility( 'hidden' );
    $product->set_regular_price( '109.99' );
    $product->set_sale_price( '89.99' );
    $product->set_manage_stock( true );
    $product->set_stock_quantity( (int) $initial_stock );
    $product->set_stock_status( (int) $initial_stock > 0 ? 'instock' : 'outofstock' );
    $product->set_backorders( 'no' );
    $product->set_tax_status( 'taxable' );
    $product->set_short_description( 'Le rituel bien-être pour vos mains. Coloris gris. 5 modes de massage et 3 niveaux de chaleur.' );
    $colour = new WC_Product_Attribute();
    $colour->set_name( 'Couleur' ); $colour->set_options( array( 'Gris' ) ); $colour->set_visible( true );
    $product->set_attributes( array( $colour ) );
    $product->update_meta_data( '_belmains_initial_stock_confirmed', current_time( 'mysql' ) );
    $product_id = $product->save();
    update_option( 'belmains_product_id', $product_id, false );
    // Import a local owned image once, so normal WooCommerce cart thumbnails work.
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';
    $source = get_theme_file_path( 'assets/gant-trois-quarts-1100.webp' );
    if ( is_readable( $source ) ) {
        $temporary = wp_tempnam( 'gant-belmains.webp' );
        copy( $source, $temporary );
        $attachment = media_handle_sideload( array( 'name' => 'gant-belmains-gris.webp', 'tmp_name' => $temporary ), $product_id, 'Gant de massage Belmains gris' );
        if ( ! is_wp_error( $attachment ) ) { $product->set_image_id( $attachment ); $product->save(); }
        else { @unlink( $temporary ); WP_CLI::warning( 'Product image was not imported: ' . $attachment->get_error_message() ); }
    }
}

$pages = array(
    'woocommerce_cart_page_id' => array( 'Panier', 'panier', '[woocommerce_cart]' ),
    'woocommerce_checkout_page_id' => array( 'Commande', 'commande', '[woocommerce_checkout]' ),
    'woocommerce_myaccount_page_id' => array( 'Mon compte', 'mon-compte', '[woocommerce_my_account]' ),
    'belmains_tracking_page_id' => array( 'Suivre ma commande', 'suivi-commande', '[woocommerce_order_tracking]' ),
);
foreach ( $pages as $option => $definition ) {
    $id = absint( get_option( $option ) );
    $existing = $id ? get_post( $id ) : null;
    $data = array( 'post_type' => 'page', 'post_title' => $definition[0], 'post_name' => $definition[1], 'post_status' => 'publish', 'post_content' => $definition[2] );
    if ( $existing && 'page' === $existing->post_type ) {
        $content = trim( $existing->post_content );
        $expected = $content === $definition[2];
        if ( ! $expected ) {
            $blocks = array_values( array_filter( parse_blocks( $content ), function ( $block ) { return $block['blockName'] || '' !== trim( $block['innerHTML'] ); } ) );
            $allowed_block = array( 'woocommerce_cart_page_id' => 'woocommerce/cart', 'woocommerce_checkout_page_id' => 'woocommerce/checkout' )[ $option ] ?? null;
            if ( 1 === count( $blocks ) ) {
                $block = $blocks[0];
                $expected = ( 'core/shortcode' === $block['blockName'] && trim( $block['innerHTML'] ) === $definition[2] )
                    || ( $allowed_block && $block['blockName'] === $allowed_block );
            }
        }
        if ( ! $expected && $existing->post_content !== $definition[2] ) { throw new RuntimeException( 'Existing page contains custom content; review before replacing: ' . $id ); }
        $data['ID'] = $id;
        $id = wp_update_post( $data, true );
    } else { $id = wp_insert_post( $data, true ); }
    if ( is_wp_error( $id ) ) { throw new RuntimeException( $id->get_error_message() ); }
    update_option( $option, $id, false );
}

foreach ( array(
    'woocommerce_checkout_privacy_policy_text' => 'Vos données sont utilisées pour traiter votre commande et assurer son suivi.',
    'woocommerce_registration_privacy_policy_text' => 'Vos données sont utilisées pour gérer votre compte et vos commandes.',
) as $option => $translation ) {
    if ( str_starts_with( (string) get_option( $option ), 'Your personal data will be used' ) ) { update_option( $option, $translation ); }
}
if ( 'F j, Y' === get_option( 'date_format' ) ) { update_option( 'date_format', 'j F Y' ); }

if ( ! get_option( 'belmains_commerce_provisioned' ) ) {
    update_option( 'woocommerce_currency', 'EUR' );
    update_option( 'timezone_string', 'Europe/Paris' );
    update_option( 'woocommerce_default_country', 'FR' );
    update_option( 'woocommerce_allowed_countries', 'specific' );
    update_option( 'woocommerce_specific_allowed_countries', array( 'FR' ) );
    update_option( 'woocommerce_ship_to_countries', 'specific' );
    update_option( 'woocommerce_specific_ship_to_countries', array( 'FR' ) );
    update_option( 'woocommerce_enable_guest_checkout', 'yes' );
    update_option( 'woocommerce_enable_signup_and_login_from_checkout', 'yes' );
    update_option( 'woocommerce_price_num_decimals', 2 );
    update_option( 'woocommerce_price_decimal_sep', ',' );
    update_option( 'woocommerce_price_thousand_sep', ' ' );
    update_option( 'woocommerce_currency_pos', 'right_space' );
    // Do not guess VAT rates or configure a payment gateway. These remain a launch task.
    $zones = WC_Shipping_Zones::get_zones();
    $zone = null;
    foreach ( $zones as $zone_data ) {
        if ( 'France — Belmains' === $zone_data['zone_name'] ) { $zone = new WC_Shipping_Zone( $zone_data['id'] ); break; }
    }
    if ( ! $zone ) {
        $zone = new WC_Shipping_Zone(); $zone->set_zone_name( 'France — Belmains' ); $zone->set_zone_order( 0 );
        $zone->add_location( 'FR', 'country' ); $zone->save();
        $method = $zone->add_shipping_method( 'free_shipping' );
        update_option( 'woocommerce_free_shipping_' . $method . '_settings', array( 'title' => 'Livraison Offerte à domicile', 'requires' => '', 'min_amount' => '0' ) );
    }
    update_option( 'belmains_shipping_zone_id', $zone->get_id(), false );
    update_option( 'belmains_commerce_provisioned', current_time( 'mysql' ), false );
}
flush_rewrite_rules( false );
WC_Cache_Helper::get_transient_version( 'shipping', true );
WP_CLI::success( 'Belmains product and commerce pages ready. Product ID: ' . $product_id . '. Existing stock is never reset on rerun.' );
