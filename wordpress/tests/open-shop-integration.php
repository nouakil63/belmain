<?php
/**
 * Opening tool checks. Run with wp eval-file ONLY on the isolated bcrm_validation_ installation.
 *
 * A throwaway copy of the private deployment state is built next to the disposable site, the
 * shop is opened and closed there, and everything is removed in the finally block. Nothing is
 * opened anywhere else: the tool itself refuses any host other than belmains.com unless it runs
 * on these isolated tables.
 */
global $wpdb;
if ( 'bcrm_validation_' !== $wpdb->prefix || 'local' !== wp_get_environment_type() ) {
    throw new RuntimeException( 'Opening tests require the isolated local validation tables.' );
}
if ( ! class_exists( 'WooCommerce' ) || ! function_exists( 'belmains_commerce_product' ) || 'belmains' !== get_stylesheet() ) {
    throw new RuntimeException( 'Activate WooCommerce, Belmains Commerce and the Belmains theme in the isolated copy first.' );
}
add_filter( 'pre_wp_mail', '__return_false' );

$site = realpath( rtrim( ABSPATH, '/' ) );
$state = dirname( $site, 2 ) . '/belmains-deployment';
if ( file_exists( $state ) ) { throw new RuntimeException( 'A deployment state already exists next to this copy; refusing to touch it.' ); }
$tools = $state . '/source/wordpress/tools';
$guard = $site . '/wp-content/mu-plugins/belmains-staging-guard.php';
$revision = str_repeat( 'c', 40 );

$checks = 0;
$check = static function ( $ok, $name ) use ( &$checks ) {
    if ( ! $ok ) { throw new RuntimeException( 'FAIL: ' . $name ); }
    $checks++;
    echo 'PASS ' . $name . "\n";
};
$refuses = static function ( callable $call, $needle, $name ) use ( $check ) {
    try { $call(); } catch ( Throwable $error ) { $check( false !== strpos( $error->getMessage(), $needle ), $name . ' (' . $error->getMessage() . ')' ); return; }
    throw new RuntimeException( 'FAIL, not refused: ' . $name );
};
$remove = static function ( $root ) use ( $state ) {
    if ( $root !== $state || 'belmains-deployment' !== basename( $root ) || ! is_dir( $root ) || is_link( $root ) ) { return; }
    foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST ) as $entry ) {
        if ( $entry->isDir() && ! $entry->isLink() ) { rmdir( $entry->getPathname() ); } else { unlink( $entry->getPathname() ); }
    }
    rmdir( $root );
};
/** Subjects of the report lines at a given level. */
$subjects = static function ( Belmains_Shop_Opening $tool, $level ) {
    return array_values( array_map( static function ( $line ) { return $line[1]; }, array_filter( $tool->lines(), static function ( $line ) use ( $level ) { return $level === $line[0]; } ) ) );
};
$stored = static function ( $name ) use ( $wpdb ) { return $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $name ) ); };

$saved = array( 'guard' => is_file( $guard ) ? file_get_contents( $guard ) : null, 'options' => array(), 'gateways' => array(), 'env' => array() );
$fixtures = array( 'pages' => array(), 'products' => array(), 'rates' => array(), 'zones' => array(), 'filters' => array() );
$touched = array( 'woocommerce_coming_soon', 'woocommerce_terms_page_id', 'wp_page_for_privacy_policy', 'blog_public', 'woocommerce_cheque_settings',
    'belmains_legal_page_id', 'belmains_terms_page_id', 'belmains_privacy_page_id', 'belmains_returns_page_id', 'belmains_withdrawal_page_id', 'belmains_contact_page_id', 'belmains_product_id' );
foreach ( $touched as $name ) { $saved['options'][ $name ] = $stored( $name ); }
foreach ( array( 'BELMAINS_SHOP_ENABLE_GATEWAYS', 'BELMAINS_SHOP_INDEX' ) as $name ) { $saved['env'][ $name ] = getenv( $name ); putenv( $name ); }
$filter = static function ( $hook, $callback ) use ( &$fixtures ) { add_filter( $hook, $callback, PHP_INT_MAX ); $fixtures['filters'][] = array( $hook, $callback ); return $callback; };
$unfilter = static function ( $hook, $callback ) { remove_filter( $hook, $callback, PHP_INT_MAX ); };

try {
    // A copy of the server layout: reviewed checkout, previous trusted engine, pre-opening guard, recorded baseline.
    mkdir( $tools, 0700, true ); chmod( $state, 0700 );
    foreach ( glob( dirname( __DIR__ ) . '/tools/*.php' ) as $file ) { copy( $file, $tools . '/' . basename( $file ) ); }
    mkdir( $state . '/source/.git/refs/heads/codex', 0700, true );
    file_put_contents( $state . '/source/.git/HEAD', "ref: refs/heads/codex/version-3-bordeaux\n" );
    file_put_contents( $state . '/source/.git/refs/heads/codex/version-3-bordeaux', $revision . "\n" );
    $previous_engine = "<?php\n/** Previous trusted engine: knows nothing about an open shop. */\n";
    file_put_contents( $state . '/deploy-runtime.php', $previous_engine ); chmod( $state . '/deploy-runtime.php', 0600 );
    $closed_guard = file_get_contents( $tools . '/public-catalog-guard.php' );
    file_put_contents( $guard, $closed_guard );
    define( 'BELMAINS_SHOP_LIBRARY', true );
    require_once $tools . '/deploy-runtime.php';
    require_once $tools . '/open-shop.php';
    $engine = new Belmains_Deploy_Runtime( $site, $state );
    $baseline = ( new ReflectionMethod( $engine, 'snapshot' ) )->invoke( $engine, $revision );
    file_put_contents( $state . '/current.json', json_encode( $baseline, JSON_UNESCAPED_SLASHES ) ); chmod( $state . '/current.json', 0600 );
    $initial_state = file_get_contents( $state . '/current.json' );

    // Shop fixtures: every one starts in the state that must block an opening.
    foreach ( WC()->payment_gateways()->payment_gateways() as $id => $gateway ) { $saved['gateways'][ $id ] = $gateway->enabled; $gateway->enabled = 'no'; }
    $filter( 'pre_option_woocommerce_currency', static function () { return 'EUR'; } );
    $taxes_on = false; $pixel = '';
    $filter( 'pre_option_woocommerce_calc_taxes', static function () use ( &$taxes_on ) { return $taxes_on ? 'yes' : 'no'; } );
    $filter( 'pre_option_wc_facebook_pixel_id', static function () use ( &$pixel ) { return $pixel; } );
    update_option( 'woocommerce_coming_soon', 'yes' );
    delete_option( 'woocommerce_terms_page_id' ); delete_option( 'belmains_product_id' ); delete_option( 'belmains_withdrawal_page_id' );
    $template = '<p><strong>Document de travail — à compléter et valider avant publication.</strong></p><p>Belmains est une marque exploitée par [nom légal], dont le siège est situé [adresse professionnelle].</p>';
    foreach ( array( 'legal', 'terms', 'privacy', 'returns' ) as $kind ) {
        $id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'draft', 'post_title' => 'Validation ' . $kind, 'post_content' => $template ), true );
        if ( is_wp_error( $id ) ) { throw new RuntimeException( $id->get_error_message() ); }
        $fixtures['pages'][ $kind ] = $id; update_option( 'belmains_' . $kind . '_page_id', $id, false );
    }
    $unpublished = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'draft', 'post_title' => 'Validation WordPress privacy default', 'post_content' => '' ) );
    $fixtures['pages']['default'] = $unpublished; update_option( 'wp_page_for_privacy_policy', $unpublished );
    if ( ! array_filter( array_map( static function ( $zone ) { return ( new WC_Shipping_Zone( $zone['id'] ) )->get_shipping_methods( true ); }, WC_Shipping_Zones::get_zones() ) ) ) {
        $zone = new WC_Shipping_Zone(); $zone->set_zone_name( 'Validation ouverture' ); $zone->add_location( 'FR', 'country' ); $zone->save(); $zone->add_shipping_method( 'free_shipping' );
        $fixtures['zones'][] = $zone->get_id();
    }

    // Read-only report: every missing prerequisite is named, nothing is modified.
    $tool = new Belmains_Shop_Opening(); $tool->check();
    $blocking = $subjects( $tool, 'BLOQUANT' );
    $check( ! array_diff( array( 'Paiement', 'TVA', 'Produit', 'Mentions légales', 'Conditions générales de vente', 'Politique de confidentialité', 'Livraison et retours', 'Rétractation en ligne' ), $blocking ), 'report blocks on payment, VAT, product, the four template legal pages and the missing withdrawal function page' );
    $check( in_array( 'Récepteur de publication', $subjects( $tool, 'OK' ), true ) && in_array( 'Copie relue', $subjects( $tool, 'OK' ), true ), 'report recognises the receiver baseline and the reviewed checkout' );
    $check( in_array( 'Moteur de publication', $subjects( $tool, 'ATTENTION' ), true ), 'report announces the replacement of the previous trusted engine' );
    $check( file_get_contents( $guard ) === $closed_guard && file_get_contents( $state . '/current.json' ) === $initial_state && file_get_contents( $state . '/deploy-runtime.php' ) === $previous_engine && ! is_dir( $state . '/opening-backups' ), 'the report changes no file' );
    $refuses( static function () use ( $revision ) { ( new Belmains_Shop_Opening() )->open( $revision ); }, 'Ouverture refusée', 'opening is refused while a blocking point remains' );
    $check( file_get_contents( $guard ) === $closed_guard && 'yes' === $stored( 'woocommerce_coming_soon' ), 'a refused opening changes nothing' );

    // Prerequisites, one by one.
    $product = new WC_Product_Simple();
    $product->set_name( 'Gant · validation ouverture' ); $product->set_sku( 'BELMAINS-OUVERTURE-VALIDATION' ); $product->set_regular_price( '89.99' ); $product->set_status( 'publish' );
    $product->set_manage_stock( true ); $product->set_stock_quantity( 0 ); $product->set_stock_status( 'outofstock' ); $product->save();
    $fixtures['products'][] = $product->get_id(); update_option( 'belmains_product_id', $product->get_id(), false );
    $tool = new Belmains_Shop_Opening(); $tool->check();
    $check( in_array( 'Produit', $subjects( $tool, 'BLOQUANT' ), true ), 'an out-of-stock product blocks the opening' );
    $product->set_stock_quantity( 500 ); $product->set_stock_status( 'instock' ); $product->save();
    $text = '<h2>Texte validé</h2>' . str_repeat( '<p>Belmains est une marque exploitée par ECO EXPRESS LIMITED, 15 Naseby Street, Liverpool L4 5TT, Royaume-Uni. Contact : contact@belmains.com.</p>', 5 );
    foreach ( array( 'legal', 'terms', 'privacy', 'returns' ) as $kind ) { wp_update_post( array( 'ID' => $fixtures['pages'][ $kind ], 'post_content' => $text . ( 'returns' === $kind ? '<p>[belmains_contact]</p>' : '' ) ) ); }
    wp_update_post( array( 'ID' => $fixtures['pages']['legal'], 'post_content' => '<p>Trop court.</p>' ) );
    $tool = new Belmains_Shop_Opening(); $tool->check();
    $check( in_array( 'Mentions légales', $subjects( $tool, 'BLOQUANT' ), true ) && ! in_array( 'Livraison et retours', $subjects( $tool, 'BLOQUANT' ), true ), 'a nearly empty legal page blocks; a registered shortcode is not mistaken for a template field' );
    wp_update_post( array( 'ID' => $fixtures['pages']['legal'], 'post_content' => $text ) );
    $tool = new Belmains_Shop_Opening(); $tool->check();
    $check( array( 'Paiement', 'TVA', 'Conditions générales de vente', 'Rétractation en ligne' ) === $subjects( $tool, 'BLOQUANT' ), 'terms of sale silent about the online withdrawal function block, and so does its missing page' );
    wp_update_post( array( 'ID' => $fixtures['pages']['terms'], 'post_content' => $text . '<p>Rétractation en ligne : lien « Renoncer au contrat ici » en bas de chaque page.</p>' ) );
    $withdrawal = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'draft', 'post_title' => 'Validation rétractation', 'post_content' => '<p>Formulaire à venir.</p>' ), true );
    if ( is_wp_error( $withdrawal ) ) { throw new RuntimeException( $withdrawal->get_error_message() ); }
    $fixtures['pages']['withdrawal'] = $withdrawal; update_option( 'belmains_withdrawal_page_id', $withdrawal, false );
    $tool = new Belmains_Shop_Opening(); $tool->check();
    $check( in_array( 'Rétractation en ligne', $subjects( $tool, 'BLOQUANT' ), true ), 'a withdrawal page without the statement form still blocks' );
    wp_update_post( array( 'ID' => $withdrawal, 'post_content' => '[belmains_retractation]' ) );
    $tool = new Belmains_Shop_Opening(); $tool->check();
    $check( array( 'Paiement', 'TVA' ) === $subjects( $tool, 'BLOQUANT' ), 'only payment and VAT still block once product, legal pages and the withdrawal function are ready' );
    $allowing = new Belmains_Shop_Opening( array( 'taxes', 'unknown' ) ); $allowing->check();
    $check( ! in_array( 'TVA', $subjects( $allowing, 'BLOQUANT' ), true ) && in_array( 'TVA', $subjects( $allowing, 'ATTENTION' ), true ), 'missing VAT can only be accepted by naming it' );
    $taxes_on = true;
    if ( ! $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}woocommerce_tax_rates WHERE tax_rate_class = ''" ) ) {
        $fixtures['rates'][] = WC_Tax::_insert_tax_rate( array( 'tax_rate_country' => 'FR', 'tax_rate' => '20.0000', 'tax_rate_name' => 'TVA', 'tax_rate_priority' => 1, 'tax_rate_shipping' => 1, 'tax_rate_order' => 1, 'tax_rate_class' => '' ) );
    }
    putenv( 'BELMAINS_SHOP_ENABLE_GATEWAYS=cheque,not_a_gateway' );
    $tool = new Belmains_Shop_Opening(); $tool->check();
    $check( array( 'Paiement' ) === $subjects( $tool, 'BLOQUANT' ), 'an unknown payment gateway name blocks instead of being ignored' );
    putenv( 'BELMAINS_SHOP_ENABLE_GATEWAYS=cheque' );
    $tool = new Belmains_Shop_Opening(); $tool->check();
    $check( array() === $subjects( $tool, 'BLOQUANT' ), 'no blocking point remains with a named gateway, VAT, product and legal pages' );
    $check( in_array( 'Mode « bientôt disponible »', $subjects( $tool, 'ATTENTION' ), true ), 'the WooCommerce coming-soon mode is announced' );

    // Advertising: a connected pixel requires the privacy page to name Meta; an ungated Google tag blocks.
    if ( class_exists( 'BCRM_Meta' ) && BCRM_Meta::installed() ) {
        $pixel = '123456789012345';
        $tool = new Belmains_Shop_Opening(); $tool->check();
        $check( array( 'Politique de confidentialité' ) === $subjects( $tool, 'BLOQUANT' ), 'a connected Meta pixel blocks while the privacy page does not mention Meta' );
        wp_update_post( array( 'ID' => $fixtures['pages']['privacy'], 'post_content' => $text . '<h3>Publicité Meta</h3><p>Avec votre accord, Meta Platforms Ireland Ltd. reçoit vos pages vues et vos achats.</p>' ) );
        $tool = new Belmains_Shop_Opening(); $tool->check();
        $check( array() === $subjects( $tool, 'BLOQUANT' ), 'the privacy page naming Meta lifts the block' );
        $pixel = '';
    } else {
        echo "SKIP Meta privacy check: the official Meta extension is not active in this copy.\n";
    }
    $plugins = $filter( 'option_active_plugins', static function ( $active ) { $active[] = 'google-listings-and-ads/google-listings-and-ads.php'; return $active; } );
    $ads = $filter( 'pre_option_gla_ads_id', static function () { return 1234567890; } );
    $tool = new Belmains_Shop_Opening(); $tool->check();
    $check( array( 'Google' ) === $subjects( $tool, 'BLOQUANT' ), 'a Google Ads tag outside the consent banner blocks the opening' );
    $unfilter( 'pre_option_gla_ads_id', $ads );
    $tool = new Belmains_Shop_Opening(); $tool->check();
    $check( array() === $subjects( $tool, 'BLOQUANT' ) && in_array( 'Google', $subjects( $tool, 'ATTENTION' ), true ), 'the Google extension without an Ads account is only reported' );
    $unfilter( 'option_active_plugins', $plugins );

    // Refusals that must leave everything in place.
    $refuses( static function () { ( new Belmains_Shop_Opening() )->open( 'abc' ); }, '40 caractères', 'a short revision is refused' );
    $refuses( static function () { ( new Belmains_Shop_Opening() )->open( str_repeat( 'd', 40 ) ); }, 'pas celle qui est publiée', 'a revision other than the published one is refused' );
    $refuses( static function () use ( $revision ) { ( new Belmains_Shop_Opening() )->close( $revision ); }, 'pas ouverte', 'closing a shop that is not open is refused' );
    file_put_contents( $state . '/source/.git/refs/heads/codex/version-3-bordeaux', str_repeat( 'e', 40 ) . "\n" );
    $refuses( static function () use ( $revision ) { ( new Belmains_Shop_Opening() )->open( $revision ); }, 'Ouverture refusée', 'a reviewed checkout on another revision is refused' );
    file_put_contents( $state . '/source/.git/refs/heads/codex/version-3-bordeaux', $revision . "\n" );
    file_put_contents( $guard, $closed_guard . "\n// edited online\n" );
    $refuses( static function () use ( $revision ) { ( new Belmains_Shop_Opening() )->open( $revision ); }, 'récepteur de publication', 'a guard edited online is refused before any change' );
    file_put_contents( $guard, $closed_guard );
    $check( file_get_contents( $state . '/current.json' ) === $initial_state && file_get_contents( $state . '/deploy-runtime.php' ) === $previous_engine && 'draft' === get_post_status( $fixtures['pages']['terms'] ), 'every refusal left the state, the engine and the pages untouched' );

    // A failure in the middle of the opening puts everything back.
    $failures = 1;
    $failure = $filter( 'pre_update_option_woocommerce_coming_soon', static function ( $value ) use ( &$failures ) { if ( $failures-- > 0 ) { throw new RuntimeException( 'simulated storage failure' ); } return $value; } );
    $refuses( static function () use ( $revision ) { ( new Belmains_Shop_Opening() )->open( $revision ); }, 'état précédent rétabli', 'a failure during the opening is rolled back' );
    $check( file_get_contents( $guard ) === $closed_guard && file_get_contents( $state . '/current.json' ) === $initial_state && file_get_contents( $state . '/deploy-runtime.php' ) === $previous_engine, 'rollback restored the guard, the recorded state and the previous engine byte for byte' );
    $failures = 99;
    $refuses( static function () use ( $revision ) { ( new Belmains_Shop_Opening() )->open( $revision ); }, 'retour arrière incomplet (réglage woocommerce_coming_soon)', 'a setting that cannot be put back is named, and the files are still restored' );
    $unfilter( 'pre_update_option_woocommerce_coming_soon', $failure );
    $check( file_get_contents( $guard ) === $closed_guard && file_get_contents( $state . '/current.json' ) === $initial_state && file_get_contents( $state . '/deploy-runtime.php' ) === $previous_engine, 'even then the guard, the recorded state and the previous engine are restored' );
    $check( 'yes' === $stored( 'woocommerce_coming_soon' ) && 'draft' === get_post_status( $fixtures['pages']['legal'] ) && 'preparation' === ( new Belmains_Deploy_Runtime( $site, $state ) )->status()['mode'], 'rollback left the settings, the pages and the preparation mode' );

    // Opening.
    putenv( 'BELMAINS_SHOP_INDEX=1' );
    $wpdb->update( $wpdb->options, array( 'option_value' => '0' ), array( 'option_name' => 'blog_public' ) ); wp_cache_delete( 'blog_public', 'options' ); wp_cache_delete( 'alloptions', 'options' );
    $result = ( new Belmains_Shop_Opening() )->open( $revision );
    $check( 'shop_open' === $result['status'] && $revision === $result['revision'] && array( 'cheque' ) === $result['enabled_gateways'] && 5 === count( $result['published_pages'] ), 'opening reports the open shop, the published pages and the enabled gateway' );
    $open_guard = file_get_contents( $tools . '/open-guard.php' );
    $recorded = json_decode( file_get_contents( $state . '/current.json' ), true );
    $check( file_get_contents( $guard ) === $open_guard && 'open' === $recorded['mode'] && hash( 'sha256', $open_guard ) === $recorded['guard_sha256'] && $recorded['manifests'] === $baseline['manifests'] && $revision === $recorded['revision'], 'the open guard is installed and recorded with the open mode; revision and manifests are unchanged' );
    $check( file_get_contents( $state . '/deploy-runtime.php' ) === file_get_contents( $tools . '/deploy-runtime.php' ) && 0600 === ( fileperms( $state . '/deploy-runtime.php' ) & 0777 ), 'the trusted engine is replaced by the reviewed one, still private' );
    $check( 'open' === ( new Belmains_Deploy_Runtime( $site, $state ) )->status()['mode'], 'the receiver accepts the new state without drift' );
    $check( false === strpos( $open_guard, 'belmains_catalog_only' ) && false === strpos( $open_guard, 'pre_wp_mail' ) && false === strpos( $open_guard, 'rest_authentication_errors' ), 'the open guard restricts nothing' );
    $statuses = array_map( 'get_post_status', array_intersect_key( $fixtures['pages'], array_flip( array( 'legal', 'terms', 'privacy', 'returns' ) ) ) );
    $check( array( 'publish' ) === array_values( array_unique( $statuses ) ), 'the four legal pages are published' );
    $check( 'publish' === get_post_status( $fixtures['pages']['withdrawal'] ), 'the withdrawal function page is published with the opening' );
    $check( 'no' === $stored( 'woocommerce_coming_soon' ) && '1' === $stored( 'blog_public' ), 'coming-soon mode is off and indexing is allowed as requested' );
    $check( (string) $fixtures['pages']['terms'] === $stored( 'woocommerce_terms_page_id' ) && (string) $fixtures['pages']['privacy'] === $stored( 'wp_page_for_privacy_policy' ), 'checkout terms and privacy links point to the published Belmains pages' );
    $check( 'yes' === ( maybe_unserialize( $stored( 'woocommerce_cheque_settings' ) )['enabled'] ?? '' ), 'the named payment gateway is enabled' );
    $backups = glob( $state . '/opening-backups/*-open-*' ); $last = end( $backups );
    $check( 3 === count( $backups ) && 0700 === ( fileperms( $last ) & 0777 ) && file_get_contents( $last . '/guard.php' ) === $closed_guard && file_get_contents( $last . '/current.json' ) === $initial_state && file_get_contents( $last . '/deploy-runtime.php' ) === $previous_engine && is_file( $last . '/settings.json' ), 'a private backup keeps the previous guard, state, engine and settings' );
    $refuses( static function () use ( $revision ) { ( new Belmains_Shop_Opening() )->open( $revision ); }, 'déjà ouverte', 'opening twice is refused' );
    $tool = new Belmains_Shop_Opening(); $tool->check();
    $check( in_array( 'Moteur de publication', $subjects( $tool, 'OK' ), true ) && in_array( 'Référencement', $subjects( $tool, 'OK' ), true ), 'the report describes the open shop' );
    file_put_contents( $guard, $open_guard . "\n// edited online\n" );
    $refuses( static function () use ( $site, $state ) { ( new Belmains_Deploy_Runtime( $site, $state ) )->status(); }, 'Live drift', 'an open guard edited online still blocks the receiver' );
    file_put_contents( $guard, $open_guard );

    // Closing again.
    $refuses( static function () { ( new Belmains_Shop_Opening() )->close( str_repeat( 'd', 40 ) ); }, 'Fermeture refusée', 'closing with another revision is refused' );
    $result = ( new Belmains_Shop_Opening() )->close( $revision );
    $recorded = json_decode( file_get_contents( $state . '/current.json' ), true );
    $check( 'shop_closed' === $result['status'] && file_get_contents( $guard ) === $closed_guard && ! array_key_exists( 'mode', $recorded ) && hash( 'sha256', $closed_guard ) === $recorded['guard_sha256'], 'closing puts the pre-opening guard back and records preparation' );
    $check( $recorded === $baseline && 'preparation' === ( new Belmains_Deploy_Runtime( $site, $state ) )->status()['mode'], 'the recorded state is the initial baseline again and the receiver accepts it' );
    $check( 'publish' === get_post_status( $fixtures['pages']['terms'] ) && file_get_contents( $state . '/deploy-runtime.php' ) === file_get_contents( $tools . '/deploy-runtime.php' ), 'closing keeps the published pages and the current engine' );
    $refuses( static function () use ( $revision ) { ( new Belmains_Shop_Opening() )->close( $revision ); }, 'pas ouverte', 'closing twice is refused' );

    echo $checks . " opening checks passed.\n";
} finally {
    foreach ( $fixtures['filters'] as $entry ) { remove_filter( $entry[0], $entry[1], PHP_INT_MAX ); }
    foreach ( $saved['env'] as $name => $value ) { false === $value ? putenv( $name ) : putenv( $name . '=' . $value ); }
    $remove( $state );
    if ( null === $saved['guard'] ) { if ( is_file( $guard ) ) { unlink( $guard ); } } else { file_put_contents( $guard, $saved['guard'] ); }
    foreach ( $fixtures['pages'] as $id ) { wp_delete_post( $id, true ); }
    foreach ( $fixtures['products'] as $id ) { $fixture = wc_get_product( $id ); if ( $fixture ) { $fixture->delete( true ); } }
    foreach ( $fixtures['rates'] as $id ) { WC_Tax::_delete_tax_rate( $id ); }
    foreach ( $fixtures['zones'] as $id ) { WC_Shipping_Zones::delete_zone( $id ); }
    foreach ( $saved['options'] as $name => $value ) { if ( null === $value ) { delete_option( $name ); } else { update_option( $name, maybe_unserialize( $value ) ); } }
    foreach ( $saved['gateways'] as $id => $enabled ) { $gateways = WC()->payment_gateways()->payment_gateways(); if ( isset( $gateways[ $id ] ) ) { $gateways[ $id ]->enabled = $enabled; } }
}
