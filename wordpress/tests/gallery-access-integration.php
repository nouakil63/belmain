<?php
/** Real routing-policy checks on disposable local WordPress only; no network calls. */
global $wpdb;
if ( 'bcrm_validation_' !== $wpdb->prefix || 'local' !== wp_get_environment_type()
    || ! defined( 'WP_HTTP_BLOCK_EXTERNAL' ) || ! WP_HTTP_BLOCK_EXTERNAL
    || ! defined( 'DISABLE_WP_CRON' ) || ! DISABLE_WP_CRON ) {
    throw new RuntimeException( 'Gallery routing checks require isolated local tables and blocked network/cron.' );
}
add_filter( 'pre_wp_mail', '__return_false', PHP_INT_MAX );
add_filter( 'pre_http_request', static function () { return new WP_Error( 'isolated_test', 'External HTTP blocked.' ); }, PHP_INT_MAX );
require_once dirname( __DIR__ ) . '/tools/public-catalog-guard.php';
final class Belmains_Gallery_Test_Redirect extends RuntimeException {}
final class Belmains_Gallery_Test_Denied extends RuntimeException {}
$redirect = static function ( $location, $status ) {
    if ( 302 !== $status || home_url( '/#fiche-produit' ) !== $location ) { throw new RuntimeException( 'Unexpected blocked-page redirect.' ); }
    throw new Belmains_Gallery_Test_Redirect( 'redirect' );
};
$die = static function ( $handler ) {
    return static function ( $message, $title, $args ) {
        if ( 503 !== ( $args['response'] ?? null ) ) { throw new RuntimeException( 'Unexpected method-denial status.' ); }
        throw new Belmains_Gallery_Test_Denied( 'denied' );
    };
};
add_filter( 'wp_redirect', $redirect, PHP_INT_MAX, 2 );
add_filter( 'wp_die_handler', $die, PHP_INT_MAX );
$checks = 0; $pages = array(); $saved_options = array();
$saved = array( $_GET, $_SERVER['REQUEST_METHOD'] ?? null, $GLOBALS['wp_query'] ?? null, $GLOBALS['wp_the_query'] ?? null, get_current_user_id() );
$option = static function ( $key, $value ) use ( &$saved_options ) {
    $sentinel = '__belmains_gallery_missing__';
    if ( ! array_key_exists( $key, $saved_options ) ) { $old = get_option( $key, $sentinel ); $saved_options[ $key ] = array( 'exists'=>$sentinel !== $old, 'value'=>$old ); }
    update_option( $key, $value );
};
$check = static function ( $condition, $name ) use ( &$checks ) {
    if ( ! $condition ) { throw new RuntimeException( 'FAIL: ' . $name ); } ++$checks;
};
$make_page = static function ( $title, $status = 'publish', $template = '' ) use ( &$pages ) {
    $id = wp_insert_post( array( 'post_type'=>'page', 'post_status'=>$status, 'post_title'=>$title, 'post_content'=>'Isolated routing fixture',
        'meta_input'=>array( '_wp_page_template'=>$template ) ), true );
    if ( is_wp_error( $id ) || ! $id ) { throw new RuntimeException( 'Could not create an isolated page fixture.' ); }
    $pages[] = (int) $id; return (int) $id;
};
$request = static function ( $page_id, $method = 'GET', $query = array(), $get = array() ) {
    $_GET = $get; $_SERVER['REQUEST_METHOD'] = $method;
    $GLOBALS['wp_query'] = new WP_Query( array_merge( array( 'page_id'=>$page_id, 'post_status'=>'any' ), $query ) );
    $GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];
    try { belmains_catalog_template_redirect(); return 'allowed'; }
    catch ( Belmains_Gallery_Test_Redirect $error ) { return 'redirect'; }
    catch ( Belmains_Gallery_Test_Denied $error ) { return 'denied'; }
};
try {
    wp_set_current_user( 0 );
    $front = $make_page( 'Gallery test front' );
    $gallery = $make_page( 'Gallery test page', 'publish', 'page-galerie.php' );
    $other = $make_page( 'Gallery test other page' );
    $draft = $make_page( 'Gallery test draft', 'draft', 'page-galerie.php' );
    $option( 'show_on_front', 'page' ); $option( 'page_on_front', $front ); $option( 'belmains_gallery_page_id', $gallery );
    $check( 'allowed' === $request( $front ), 'Published landing page still allowed' );
    $check( 'allowed' === $request( $gallery ), 'Configured published gallery GET allowed' );
    $check( 'allowed' === $request( $gallery, 'HEAD' ), 'Configured published gallery HEAD allowed' );
    $check( 'redirect' === $request( $other ), 'Ordinary published page remains closed' );
    $option( 'belmains_gallery_page_id', 0 );
    $check( 'redirect' === $request( $gallery ), 'Unconfigured gallery template is not a public route' );
    $option( 'belmains_gallery_page_id', $draft );
    $check( 'redirect' === $request( $draft ), 'Configured draft gallery remains private' );
    $option( 'belmains_gallery_page_id', $gallery );
    update_post_meta( $gallery, '_wp_page_template', 'default' );
    $check( 'redirect' === $request( $gallery ), 'Wrong gallery template is not exposed' );
    update_post_meta( $gallery, '_wp_page_template', 'page-galerie.php' );
    wp_update_post( array( 'ID'=>$gallery, 'post_password'=>'isolated-gallery-password' ) );
    $check( 'redirect' === $request( $gallery ), 'Password-protected gallery remains private' );
    wp_update_post( array( 'ID'=>$gallery, 'post_password'=>'' ) );
    $check( 'redirect' === $request( $gallery, 'GET', array( 'preview'=>true ) ), 'Native preview is not exposed' );
    $check( 'redirect' === $request( $gallery, 'GET', array(), array( 'belmains_preview'=>0 ) ), 'Builder preview query is not exposed, including zero value' );
    $check( 'redirect' === $request( $gallery, 'GET', array( 'feed'=>'rss2' ) ), 'Gallery feed is not exposed' );
    $check( 'redirect' === $request( $gallery, 'GET', array( 'embed'=>true ) ), 'Gallery embed route is not exposed' );
    foreach ( array( 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS' ) as $method ) {
        $check( 'denied' === $request( $gallery, $method ), 'Gallery rejects method ' . $method );
    }
    $check( is_wp_error( apply_filters( 'rest_authentication_errors', null ) ), 'Gallery publication does not open anonymous REST' );
    $check( false === apply_filters( 'woocommerce_is_purchasable', true ), 'Gallery publication does not permit purchases' );
    $check( array() === apply_filters( 'woocommerce_available_payment_gateways', array( 'test'=>'blocked' ) ), 'Payment gateways stay unavailable' );
    $check( false === apply_filters( 'pre_wp_mail', null, array() ), 'Gallery publication does not permit email' );
    $check( 0 === (int) get_option( 'blog_public' ), 'Gallery remains non-indexable during preparation' );
    echo wp_json_encode( array( 'count'=>$checks, 'status'=>'passed', 'production_touched'=>false, 'orders_created'=>false ), JSON_PRETTY_PRINT ) . "\n";
} finally {
    remove_filter( 'wp_redirect', $redirect, PHP_INT_MAX ); remove_filter( 'wp_die_handler', $die, PHP_INT_MAX );
    foreach ( $pages as $id ) { wp_delete_post( $id, true ); }
    foreach ( $saved_options as $key => $old ) { if ( $old['exists'] ) { update_option( $key, $old['value'] ); } else { delete_option( $key ); } }
    list( $_GET, $method, $GLOBALS['wp_query'], $GLOBALS['wp_the_query'], $user_id ) = $saved;
    if ( null === $method ) { unset( $_SERVER['REQUEST_METHOD'] ); } else { $_SERVER['REQUEST_METHOD'] = $method; }
    wp_set_current_user( $user_id );
}
