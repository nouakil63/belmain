<?php
/** Isolated shell-free runtime tests: no network, WordPress or production files. */
require dirname( __DIR__ ) . '/tools/deploy-runtime.php';
$checks = 0;
function verify_test( bool $condition, string $message ): void {
    global $checks;
    if ( ! $condition ) { throw new RuntimeException( $message ); }
    ++$checks;
}
function refuses( callable $call, string $message ): void {
    try { $call(); } catch ( Throwable $error ) { verify_test( true, $message ); return; }
    throw new RuntimeException( 'Did not refuse: ' . $message );
}
function private_call( object $engine, string $method, ...$args ) {
    return ( new ReflectionMethod( $engine, $method ) )->invokeArgs( $engine, $args );
}
function test_remove( string $root ): void {
    if ( ! is_dir( $root ) ) { return; }
    foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST ) as $entry ) {
        if ( $entry->isDir() && ! $entry->isLink() ) { rmdir( $entry->getPathname() ); } else { unlink( $entry->getPathname() ); }
    }
    rmdir( $root );
}
$root = sys_get_temp_dir() . '/belmains-runtime-test-' . bin2hex( random_bytes( 8 ) );
mkdir( $root, 0700 );
try {
    $site = $root . '/sites/example'; $state = $root . '/private';
    mkdir( $state, 0700 );
    foreach ( array( 'themes/belmains', 'plugins/belmains-commerce', 'plugins/belmains-crm', 'mu-plugins', 'uploads' ) as $relative ) { mkdir( $site . '/wp-content/' . $relative, 0755, true ); }
    foreach ( array( 'themes/belmains', 'plugins/belmains-commerce', 'plugins/belmains-crm' ) as $relative ) { file_put_contents( $site . '/wp-content/' . $relative . '/index.php', '<?php // original' ); }
    $guard = $site . '/wp-content/mu-plugins/belmains-staging-guard.php';
    file_put_contents( $guard, '<?php // keep closed' );
    file_put_contents( $site . '/wp-content/uploads/sentinel', 'retain' );
    $engine = new Belmains_Deploy_Runtime( $site, $state );
    $old = str_repeat( '1', 40 ); $new = str_repeat( '2', 40 );
    $baseline = private_call( $engine, 'snapshot', $old );
    verify_test( count( $baseline['manifests'] ) === 3, 'Three component baseline' );
    private_call( $engine, 'verify', $baseline ); verify_test( true, 'Clean baseline passes' );
    file_put_contents( $guard, '<?php // drift' );
    refuses( static function () use ( $engine, $baseline ) { private_call( $engine, 'verify', $baseline ); }, 'Changed closure guard blocks deployment' );
    file_put_contents( $guard, '<?php // keep closed' );
    $link = $site . '/wp-content/themes/belmains/symlink.php'; symlink( $guard, $link );
    refuses( static function () use ( $site ) { Belmains_Deploy_Runtime::manifest( $site . '/wp-content/themes/belmains' ); }, 'Symlink refused' ); unlink( $link );
    foreach ( array( '../config.php', 'assets/../../x.php', 'wp-config.php', 'assets/secret.jpg', '/absolute.php', 'assets/file.php', 'includes/data.json' ) as $path ) {
        refuses( static function () use ( $path ) { Belmains_Deploy_Runtime::validate_path( 'belmains', $path ); }, 'Unsafe package path refused: ' . $path );
    }
    foreach ( array( 'index.php', 'assets/picture.webp', 'template-parts/product.php' ) as $path ) { Belmains_Deploy_Runtime::validate_path( 'belmains', $path ); verify_test( true, 'Known path accepted' ); }
    $run = array( 'head_sha' => $new, 'head_branch' => 'codex/version-3-bordeaux', 'event' => 'push', 'path' => '.github/workflows/wordpress-checks.yml', 'status' => 'completed', 'conclusion' => 'success' );
    verify_test( Belmains_Deploy_Runtime::approved_run( $run, $new ), 'Exact run approved' );
    foreach ( array( 'head_sha', 'head_branch', 'event', 'path', 'status', 'conclusion' ) as $field ) {
        $changed = $run; $changed[ $field ] = 'wrong'; verify_test( ! Belmains_Deploy_Runtime::approved_run( $changed, $new ), 'Wrong approval field refused: ' . $field );
    }
    $backup = $state . '/backups/prior'; mkdir( $backup, 0700, true );
    foreach ( array( 'belmains' => 'themes', 'belmains-commerce' => 'plugins', 'belmains-crm' => 'plugins' ) as $c => $group ) { private_call( $engine, 'copy_tree', $site . '/wp-content/' . $group . '/' . $c, $backup . '/' . $c ); }
    file_put_contents( $site . '/wp-content/themes/belmains/index.php', '<?php // partial new release' );
    unlink( $site . '/wp-content/plugins/belmains-commerce/index.php' ); rmdir( $site . '/wp-content/plugins/belmains-commerce' );
    $journal = array( 'revision' => $new, 'backup' => $backup, 'baseline' => $baseline );
    file_put_contents( $state . '/pending.json', json_encode( $journal ) ); file_put_contents( $state . '/current.json', json_encode( $baseline ) );
    private_call( $engine, 'recover' );
    verify_test( ! file_exists( $state . '/pending.json' ), 'Interrupted publication journal recovered' );
    private_call( $engine, 'verify', $baseline ); verify_test( true, 'All code restored to baseline' );
    verify_test( 'retain' === file_get_contents( $site . '/wp-content/uploads/sentinel' ), 'Uploads preserved' );
    if ( class_exists( 'ZipArchive' ) ) {
        $archive = $state . '/malformed.zip'; $zip = new ZipArchive(); $zip->open( $archive, ZipArchive::CREATE ); $zip->addFromString( 'belmains/../evil.php', '<?php die();' ); $zip->close();
        $stage = $state . '/stage'; mkdir( $stage, 0700 );
        refuses( static function () use ( $engine, $archive, $stage ) { private_call( $engine, 'unpack', $archive, 'belmains', $stage ); }, 'ZIP traversal refused before file writes' );
        verify_test( ! file_exists( $stage . '/evil.php' ), 'No traversal write' );
    }
    echo 'OK ' . $checks . " isolated runtime checks\n";
} finally { test_remove( $root ); }
