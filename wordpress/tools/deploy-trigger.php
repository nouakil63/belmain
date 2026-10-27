<?php
/**
 * Install as SITE/belmains-deploy-check.php. Native Infomaniak URL task entry point.
 * No WordPress bootstrap, authentication material, user commands, refs or paths.
 * The public GET can only ask the pinned private poller to check the fixed branch;
 * the poller requires the exact revision's successful GitHub validation.
 */
header( 'Cache-Control: no-store, private' );
header( 'X-Robots-Tag: noindex, nofollow' );
if ( ! in_array( $_SERVER['REQUEST_METHOD'] ?? '', array( 'GET', 'HEAD' ), true ) ) {
    http_response_code( 405 );
    exit;
}
if ( 'HEAD' === $_SERVER['REQUEST_METHOD'] ) {
    http_response_code( 204 );
    exit;
}
$state = dirname( __DIR__, 2 ) . '/belmains-deployment';
$script = $state . '/deploy-pull.py';
if ( ! is_dir( $state ) || ! is_file( $script ) || is_link( $state ) || is_link( $script )
    || ! is_file( $state . '/current.json' ) || ! is_callable( 'proc_open' ) ) {
    http_response_code( 503 );
    exit;
}
$lock = fopen( $state . '/trigger.lock', 'c' );
if ( false === $lock ) {
    http_response_code( 503 );
    exit;
}
if ( ! flock( $lock, LOCK_EX | LOCK_NB ) ) {
    fclose( $lock );
    header( 'Retry-After: 60' );
    http_response_code( 503 );
    exit;
}
$last_file = $state . '/trigger-last';
$last = is_file( $last_file ) ? (int) file_get_contents( $last_file ) : 0;
if ( time() - $last < 55 ) {
    fclose( $lock );
    header( 'Retry-After: 60' );
    http_response_code( 503 );
    exit;
}
file_put_contents( $last_file, (string) time(), LOCK_EX );
chmod( $last_file, 0600 );
$log = $state . '/run.log';
if ( is_file( $log ) && filesize( $log ) > 2 * 1024 * 1024 ) {
    rename( $log, $state . '/run-previous.log' );
}
$descriptors = array(
    0 => array( 'file', '/dev/null', 'r' ),
    1 => array( 'file', $log, 'a' ),
    2 => array( 'file', $log, 'a' ),
);
ignore_user_abort( true );
set_time_limit( 600 );
$process = proc_open(
    array( '/usr/bin/python3', $script, '--site', __DIR__, '--state', $state ),
    $descriptors,
    $pipes,
    $state,
    null,
    array( 'bypass_shell' => true )
);
if ( ! is_resource( $process ) ) {
    fclose( $lock );
    http_response_code( 503 );
    exit;
}
chmod( $log, 0600 );
$deadline = time() + 550;
$exit_code = -1;
while ( true ) {
    $status = proc_get_status( $process );
    if ( ! $status['running'] ) {
        $exit_code = $status['exitcode'];
        break;
    }
    if ( time() >= $deadline ) {
        proc_terminate( $process, 15 );
        break;
    }
    usleep( 100000 );
}
$close_code = proc_close( $process );
fclose( $lock );
http_response_code( 0 === $exit_code || 0 === $close_code ? 204 : 503 );
