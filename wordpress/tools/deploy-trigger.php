<?php
/** Install at SITE/belmains-deploy-check.php. Fixed, shell-free deployment trigger. */
header( 'Cache-Control: no-store, private' );
header( 'X-Robots-Tag: noindex, nofollow' );
if ( ! in_array( $_SERVER['REQUEST_METHOD'] ?? '', array( 'GET', 'HEAD' ), true ) ) { http_response_code( 405 ); exit; }
if ( 'HEAD' === $_SERVER['REQUEST_METHOD'] ) { http_response_code( 204 ); exit; }
$state = dirname( __DIR__, 2 ) . '/belmains-deployment';
$runtime = $state . '/deploy-runtime.php';
if ( ! is_dir( $state ) || ! is_file( $runtime ) || is_link( $state ) || is_link( $runtime ) || ! is_file( $state . '/current.json' ) || ! class_exists( 'ZipArchive' ) ) { http_response_code( 503 ); exit; }
$lock = fopen( $state . '/trigger.lock', 'c' );
if ( false === $lock ) { http_response_code( 503 ); exit; }
if ( ! flock( $lock, LOCK_EX | LOCK_NB ) ) { fclose( $lock ); header( 'Retry-After: 60' ); http_response_code( 503 ); exit; }
$last_file = $state . '/trigger-last';
$last = is_file( $last_file ) ? (int) file_get_contents( $last_file ) : 0;
if ( time() - $last < 55 ) { fclose( $lock ); header( 'Retry-After: 60' ); http_response_code( 503 ); exit; }
file_put_contents( $last_file, (string) time(), LOCK_EX ); chmod( $last_file, 0600 );
ignore_user_abort( true ); set_time_limit( 600 );
$engine = null;
try {
    require $runtime;
    $engine = new Belmains_Deploy_Runtime( __DIR__, $state );
    $engine->poll();
    http_response_code( 204 );
} catch ( Throwable $error ) {
    if ( null !== $engine ) { $engine->log( 'DEPLOYMENT_FAILED ' . get_class( $error ) . ': ' . $error->getMessage() ); }
    http_response_code( 503 );
} finally { fclose( $lock ); }
