<?php
/** Controlled gallery publication; WP-CLI only, never loaded by the theme. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || '1' !== getenv( 'BELMAINS_GALLERY_PUBLISH' ) ) {
    throw new RuntimeException( 'Explicit BELMAINS_GALLERY_PUBLISH=1 through WP-CLI is required.' );
}
$revision = (string) getenv( 'BELMAINS_GALLERY_REVISION' );
if ( ! preg_match( '/\A[a-f0-9]{40}\z/', $revision ) || 'belmains.com' !== wp_parse_url( home_url(), PHP_URL_HOST )
    || 'https' !== wp_parse_url( home_url(), PHP_URL_SCHEME )
    || ! apply_filters( 'belmains_catalog_only', false ) ) {
    throw new RuntimeException( 'A full verified revision and the protected Belmains site are required.' );
}
$site = realpath( rtrim( ABSPATH, '/' ) );
$state = dirname( $site, 2 ) . '/belmains-deployment';
$guard = $site . '/wp-content/mu-plugins/belmains-staging-guard.php';
$current = $state . '/current.json';
$template = $site . '/wp-content/themes/belmains/page-galerie.php';
$candidate_file = __DIR__ . '/public-catalog-guard.php';
foreach ( array( $state, $guard, $current, $template, $candidate_file, $site . '/wp-content', $site . '/wp-content/mu-plugins' ) as $path ) {
    if ( ! file_exists( $path ) || is_link( $path ) ) { throw new RuntimeException( 'An expected ordinary deployment path is missing.' ); }
}
if ( realpath( __DIR__ ) !== realpath( $state . '/source/wordpress/tools' ) || 'belmains' !== get_stylesheet() ) {
    throw new RuntimeException( 'Use the reviewed checkout and the active Belmains theme.' );
}
$git = $state . '/source/.git';
$head = trim( (string) file_get_contents( $git . '/HEAD' ) );
if ( 'ref: refs/heads/codex/version-3-bordeaux' === $head ) {
    $ref = $git . '/refs/heads/codex/version-3-bordeaux';
    $head = is_file( $ref ) ? trim( (string) file_get_contents( $ref ) ) : '';
    if ( '' === $head && is_file( $git . '/packed-refs' ) ) {
        foreach ( file( $git . '/packed-refs', FILE_IGNORE_NEW_LINES ) as $line ) {
            if ( preg_match( '/\A([a-f0-9]{40}) refs\/heads\/codex\/version-3-bordeaux\z/', $line, $match ) ) { $head = $match[1]; break; }
        }
    }
}
if ( $revision !== $head ) { throw new RuntimeException( 'The reviewed source checkout must match the deployed revision.' ); }
require_once $state . '/deploy-runtime.php';
$candidate = file_get_contents( $candidate_file );
if ( false === $candidate || '' === $candidate ) { throw new RuntimeException( 'Candidate guard unavailable.' ); }
token_get_all( $candidate, TOKEN_PARSE );

function belmains_gallery_atomic_write( string $path, string $bytes, int $mode ): void {
    $temp = dirname( $path ) . '/.' . basename( $path ) . '-' . bin2hex( random_bytes( 6 ) ) . '.tmp';
    $handle = fopen( $temp, 'x' );
    if ( false === $handle ) { throw new RuntimeException( 'Cannot create a staging file.' ); }
    try {
        chmod( $temp, $mode );
        if ( strlen( $bytes ) !== fwrite( $handle, $bytes ) || ! fflush( $handle ) ) { throw new RuntimeException( 'Staging write failed.' ); }
        if ( function_exists( 'fsync' ) ) { fsync( $handle ); }
    } catch ( Throwable $error ) {
        fclose( $handle ); unlink( $temp ); throw $error;
    }
    fclose( $handle );
    if ( ! rename( $temp, $path ) ) { unlink( $temp ); throw new RuntimeException( 'Atomic replacement failed.' ); }
}

$trigger_lock = fopen( $state . '/trigger.lock', 'c' );
$deployment_lock = fopen( $state . '/lock', 'c' );
if ( false === $trigger_lock || false === $deployment_lock ) { throw new RuntimeException( 'Cannot open deployment locks.' ); }
$created = false; $page_id = 0; $started = false; $page_before = null; $old_option = null; $option_exists = false;
$old_guard = ''; $old_state = ''; $backup_dir = '';
try {
    if ( ! flock( $trigger_lock, LOCK_EX | LOCK_NB ) || ! flock( $deployment_lock, LOCK_EX | LOCK_NB ) ) {
        throw new RuntimeException( 'A deployment is running; retry publication after completion.' );
    }
    if ( file_exists( $state . '/pending.json' ) ) { throw new RuntimeException( 'A deployment recovery is pending.' ); }
    $old_state = file_get_contents( $current );
    $baseline = json_decode( $old_state, true, 512, JSON_THROW_ON_ERROR );
    $baseline_document = json_decode( $old_state, false, 512, JSON_THROW_ON_ERROR );
    if ( $revision !== ( $baseline['revision'] ?? '' ) || hash_file( 'sha256', $guard ) !== ( $baseline['guard_sha256'] ?? '' ) ) {
        throw new RuntimeException( 'The current revision or guard differs from the recorded baseline.' );
    }
    foreach ( array( 'belmains'=>'themes', 'belmains-commerce'=>'plugins', 'belmains-crm'=>'plugins' ) as $component => $group ) {
        $parent = $site . '/wp-content/' . $group;
        if ( is_link( $parent ) || Belmains_Deploy_Runtime::manifest( $parent . '/' . $component ) != ( $baseline['manifests'][ $component ] ?? null ) ) {
            throw new RuntimeException( 'Live component drift must be reconciled before publication.' );
        }
    }
    $sentinel = '__belmains_gallery_option_absent__';
    $old_option = get_option( 'belmains_gallery_page_id', $sentinel ); $option_exists = $sentinel !== $old_option;
    $configured = absint( $option_exists ? $old_option : 0 );
    $page = $configured ? get_post( $configured ) : null;
    if ( $configured && ( ! $page || 'page' !== $page->post_type || 'galerie' !== $page->post_name
        || ! in_array( $page->post_status, array( 'draft', 'publish' ), true ) || '' !== $page->post_password
        || 'page-galerie.php' !== get_page_template_slug( $configured ) ) ) {
        throw new RuntimeException( 'The configured page is unrelated or is not an owned gallery.' );
    }
    $collision = get_page_by_path( 'galerie', OBJECT, 'page' );
    if ( $collision && ( ! $configured || (int) $collision->ID !== $configured ) ) {
        throw new RuntimeException( 'An unrelated page already uses the gallery slug; nothing was overwritten.' );
    }
    if ( $page ) {
        $page_id = (int) $page->ID;
        $page_before = array( 'ID'=>$page_id, 'post_status'=>$page->post_status, 'post_title'=>$page->post_title,
            'post_name'=>$page->post_name, 'post_content'=>$page->post_content, 'post_password'=>$page->post_password,
            'template'=>get_page_template_slug( $page_id ) );
    }
    $backups = $state . '/gallery-backups';
    if ( is_link( $backups ) || ( ! is_dir( $backups ) && ! mkdir( $backups, 0700 ) ) ) { throw new RuntimeException( 'Private backup directory unavailable.' ); }
    chmod( $backups, 0700 );
    $backup_dir = $backups . '/' . gmdate( 'Ymd-His' ) . '-' . bin2hex( random_bytes( 5 ) );
    if ( ! mkdir( $backup_dir, 0700 ) ) { throw new RuntimeException( 'Cannot create a private gallery backup.' ); }
    $old_guard = file_get_contents( $guard );
    if ( false === $old_guard ) { throw new RuntimeException( 'Cannot read the current guard.' ); }
    belmains_gallery_atomic_write( $backup_dir . '/guard.php', $old_guard, 0600 );
    belmains_gallery_atomic_write( $backup_dir . '/current.json', $old_state, 0600 );
    belmains_gallery_atomic_write( $backup_dir . '/page-option.json', wp_json_encode( array(
        'page'=>$page_before, 'option_exists'=>$option_exists, 'option_value'=>$option_exists ? $old_option : null,
        'revision'=>$revision, 'candidate_guard_sha256'=>hash( 'sha256', $candidate ),
    ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ), 0600 );
    $started = true;
    if ( ! $page_id ) {
        $page_id = wp_insert_post( array( 'post_type'=>'page', 'post_status'=>'draft', 'post_name'=>'galerie', 'post_title'=>'Galerie',
            'post_content'=>'', 'comment_status'=>'closed', 'ping_status'=>'closed',
            'meta_input'=>array( '_wp_page_template'=>'page-galerie.php' ) ), true );
        if ( is_wp_error( $page_id ) || ! $page_id ) { throw new RuntimeException( 'Cannot create the gallery draft.' ); }
        $created = true; $page_id = (int) $page_id;
    }
    update_option( 'belmains_gallery_page_id', $page_id, false );
    if ( $page_id !== absint( get_option( 'belmains_gallery_page_id' ) ) ) { throw new RuntimeException( 'Cannot register the gallery page.' ); }
    $next = $baseline; $next['guard_sha256'] = hash( 'sha256', $candidate );
    $next_document = clone $baseline_document; $next_document->guard_sha256 = $next['guard_sha256'];
    belmains_gallery_atomic_write( $guard, $candidate, 0644 );
    belmains_gallery_atomic_write( $current, json_encode( $next_document, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR ), 0600 );
    if ( is_callable( 'opcache_invalidate' ) ) { opcache_invalidate( $guard, true ); }
    if ( 'publish' !== get_post_status( $page_id ) ) {
        $result = wp_update_post( array( 'ID'=>$page_id, 'post_status'=>'publish' ), true );
        if ( is_wp_error( $result ) || ! $result ) { throw new RuntimeException( 'Cannot publish the gallery page.' ); }
    }
    if ( 'publish' !== get_post_status( $page_id ) || 'page-galerie.php' !== get_page_template_slug( $page_id )
        || $next_document != json_decode( file_get_contents( $current ), false, 512, JSON_THROW_ON_ERROR )
        || hash_file( 'sha256', $guard ) !== $next['guard_sha256'] ) {
        throw new RuntimeException( 'The gallery publication readback did not match.' );
    }
    echo wp_json_encode( array( 'status'=>'gallery_published_sales_closed', 'page_id'=>$page_id, 'url'=>get_permalink( $page_id ),
        'created'=>$created, 'revision'=>$revision, 'previous_guard_sha256'=>$baseline['guard_sha256'],
        'guard_sha256'=>$next['guard_sha256'], 'baseline_updated'=>true, 'backup'=>basename( $backup_dir ) ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n";
} catch ( Throwable $error ) {
    $rollback_errors = array();
    if ( $started ) {
        try {
            belmains_gallery_atomic_write( $guard, $old_guard, 0644 );
            belmains_gallery_atomic_write( $current, $old_state, 0600 );
            if ( is_callable( 'opcache_invalidate' ) ) { opcache_invalidate( $guard, true ); }
        } catch ( Throwable $restore_error ) { $rollback_errors[] = 'guard/state'; }
        try {
            if ( $option_exists ) {
                update_option( 'belmains_gallery_page_id', $old_option, false );
                if ( get_option( 'belmains_gallery_page_id' ) !== $old_option ) { throw new RuntimeException( 'Could not restore the gallery option.' ); }
            } else {
                delete_option( 'belmains_gallery_page_id' );
                if ( '__absent__' !== get_option( 'belmains_gallery_page_id', '__absent__' ) ) { throw new RuntimeException( 'Could not remove the gallery option.' ); }
            }
            if ( $created && $page_id ) {
                // Set a recoverable trash state directly: EMPTY_TRASH_DAYS=0 must never hard-delete this page.
                update_post_meta( $page_id, '_wp_trash_meta_status', 'draft' ); update_post_meta( $page_id, '_wp_trash_meta_time', time() );
                $result = wp_update_post( array( 'ID'=>$page_id, 'post_status'=>'trash' ), true );
                if ( is_wp_error( $result ) || ! $result ) { throw new RuntimeException( 'Could not trash the newly created draft.' ); }
            } elseif ( $page_before ) {
                $result = wp_update_post( array( 'ID'=>$page_id, 'post_status'=>$page_before['post_status'] ), true );
                if ( is_wp_error( $result ) || ! $result ) { throw new RuntimeException( 'Could not restore the existing gallery status.' ); }
            }
        } catch ( Throwable $restore_error ) { $rollback_errors[] = 'page/option'; }
    }
    if ( $rollback_errors ) { throw new RuntimeException( 'Gallery publication failed and rollback needs review: ' . implode( ', ', $rollback_errors ), 0, $error ); }
    throw $error;
} finally { fclose( $deployment_lock ); fclose( $trigger_lock ); }
