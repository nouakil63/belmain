<?php
/** Trusted private deployment engine. No shell, WordPress bootstrap or request input. */
final class Belmains_Deploy_Runtime {
    private const REPO = 'nouakil63/belmain';
    private const BRANCH = 'codex/version-3-bordeaux';
    private const WORKFLOW = '.github/workflows/wordpress-checks.yml';
    private const COMPONENTS = array( 'belmains' => 'themes', 'belmains-commerce' => 'plugins', 'belmains-crm' => 'plugins' );
    private string $site;
    private string $state;
    private string $guard;

    public function __construct( string $site, string $state ) {
        if ( is_link( $site ) || is_link( $state ) || false === realpath( $site ) || false === realpath( $state ) ) {
            throw new RuntimeException( 'Invalid site or private state directory' );
        }
        $this->site = realpath( $site );
        $this->state = realpath( $state );
        if ( str_starts_with( $this->state . '/', dirname( $this->site ) . '/' ) ) {
            throw new RuntimeException( 'Private state must be outside sites' );
        }
        $this->guard = $this->site . '/wp-content/mu-plugins/belmains-staging-guard.php';
        $this->check_parents();
    }

    private function check_parents(): void {
        foreach ( array( '/wp-content', '/wp-content/themes', '/wp-content/plugins', '/wp-content/mu-plugins' ) as $relative ) {
            if ( is_link( $this->site . $relative ) || ! is_dir( $this->site . $relative ) ) {
                throw new RuntimeException( 'Runtime parent is not an ordinary directory' );
            }
        }
    }

    private function live( string $component ): string {
        return $this->site . '/wp-content/' . self::COMPONENTS[ $component ] . '/' . $component;
    }

    public static function manifest( string $root ): array {
        if ( is_link( $root ) || ! is_dir( $root ) ) {
            throw new RuntimeException( 'Component is not an ordinary directory' );
        }
        $result = array();
        $iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::SELF_FIRST );
        foreach ( $iterator as $entry ) {
            if ( $entry->isLink() ) {
                throw new RuntimeException( 'Symlink refused' );
            }
            if ( $entry->isDir() ) { continue; }
            if ( ! $entry->isFile() ) { throw new RuntimeException( 'Special file refused' ); }
            $relative = substr( $entry->getPathname(), strlen( $root ) + 1 );
            $result[ $relative ] = hash_file( 'sha256', $entry->getPathname() );
        }
        if ( ! $result ) { throw new RuntimeException( 'Empty component refused' ); }
        ksort( $result, SORT_STRING );
        return $result;
    }

    private function snapshot( string $revision ): array {
        $this->check_parents();
        if ( ! preg_match( '/\A[a-f0-9]{40}\z/', $revision ) || is_link( $this->guard ) || ! is_file( $this->guard ) ) {
            throw new RuntimeException( 'Invalid revision or closure guard' );
        }
        $manifests = array();
        foreach ( self::COMPONENTS as $c => $_ ) { $manifests[ $c ] = self::manifest( $this->live( $c ) ); }
        return array( 'revision' => $revision, 'guard_sha256' => hash_file( 'sha256', $this->guard ), 'manifests' => $manifests );
    }

    private function json( string $file ): array {
        $value = json_decode( file_get_contents( $file ), true, 512, JSON_THROW_ON_ERROR );
        if ( ! is_array( $value ) ) { throw new RuntimeException( 'Invalid state JSON' ); }
        return $value;
    }

    private function write_json( string $file, array $value ): void {
        $temp = $file . '.tmp-' . bin2hex( random_bytes( 5 ) );
        if ( false === file_put_contents( $temp, json_encode( $value, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR ), LOCK_EX ) ) {
            throw new RuntimeException( 'Private state write failed' );
        }
        chmod( $temp, 0600 );
        if ( ! rename( $temp, $file ) ) { throw new RuntimeException( 'Private state replacement failed' ); }
    }

    private function verify( array $baseline ): void {
        if ( $this->snapshot( $baseline['revision'] ) != $baseline ) {
            throw new RuntimeException( 'Live drift or closure guard change: reconcile before deployment' );
        }
    }

    private function get( string $url, int $limit = 8388608 ): array {
        $headers = array( 'User-Agent: Belmains-deployment/1.0', 'Accept: application/vnd.github+json' );
        if ( function_exists( 'curl_init' ) ) {
            $handle = curl_init( $url );
            $body = '';
            curl_setopt_array( $handle, array(
                CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 5,
                CURLOPT_CONNECTTIMEOUT => 15, CURLOPT_TIMEOUT => 90,
                CURLOPT_HTTPHEADER => $headers, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
                CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
                CURLOPT_WRITEFUNCTION => static function ( $curl, string $chunk ) use ( &$body, $limit ): int {
                    if ( strlen( $body ) + strlen( $chunk ) > $limit ) { return 0; }
                    $body .= $chunk; return strlen( $chunk );
                },
            ) );
            $ok = curl_exec( $handle );
            $status = (int) curl_getinfo( $handle, CURLINFO_RESPONSE_CODE );
            curl_close( $handle );
            if ( false === $ok ) { throw new RuntimeException( 'HTTPS transfer failed' ); }
            return array( $status, $body );
        }
        $context = stream_context_create( array( 'http' => array( 'method' => 'GET', 'header' => implode( "\r\n", $headers ), 'timeout' => 90, 'ignore_errors' => true, 'max_redirects' => 5 ), 'ssl' => array( 'verify_peer' => true, 'verify_peer_name' => true ) ) );
        $stream = fopen( $url, 'rb', false, $context );
        if ( false === $stream ) { throw new RuntimeException( 'HTTPS transfer failed' ); }
        $body = stream_get_contents( $stream, $limit + 1 ); fclose( $stream );
        if ( false === $body || strlen( $body ) > $limit ) { throw new RuntimeException( 'HTTPS response limit exceeded' ); }
        $status = 0;
        foreach ( $http_response_header ?? array() as $line ) {
            if ( preg_match( '#^HTTP/\S+\s+(\d{3})#', $line, $match ) ) { $status = (int) $match[1]; }
        }
        return array( $status, $body );
    }

    private function api( string $path ): array {
        list( $status, $body ) = $this->get( 'https://api.github.com/repos/' . self::REPO . $path );
        if ( in_array( $status, array( 403, 429 ), true ) ) { throw new RuntimeException( 'GitHub API temporarily rate limited' ); }
        if ( 200 !== $status ) { throw new RuntimeException( 'GitHub API status ' . $status ); }
        $data = json_decode( $body, true, 512, JSON_THROW_ON_ERROR );
        if ( ! is_array( $data ) ) { throw new RuntimeException( 'Invalid GitHub response' ); }
        return $data;
    }

    public static function approved_run( array $run, string $sha ): bool {
        return ( $run['head_sha'] ?? '' ) === $sha && ( $run['head_branch'] ?? '' ) === self::BRANCH
            && ( $run['event'] ?? '' ) === 'push' && ( $run['path'] ?? '' ) === self::WORKFLOW
            && ( $run['status'] ?? '' ) === 'completed' && ( $run['conclusion'] ?? '' ) === 'success';
    }

    private function approved( string $sha ): bool {
        $data = $this->api( '/actions/workflows/wordpress-checks.yml/runs?branch=' . rawurlencode( self::BRANCH ) . '&event=push&head_sha=' . $sha . '&per_page=100' );
        $runs = array_filter( $data['workflow_runs'] ?? array(), static function ( array $run ) use ( $sha ): bool {
            return ( $run['head_sha'] ?? '' ) === $sha && ( $run['head_branch'] ?? '' ) === self::BRANCH && ( $run['event'] ?? '' ) === 'push' && ( $run['path'] ?? '' ) === self::WORKFLOW;
        } );
        usort( $runs, static function ( array $a, array $b ): int { return array( $b['id'], $b['run_attempt'] ?? 0 ) <=> array( $a['id'], $a['run_attempt'] ?? 0 ); } );
        return isset( $runs[0] ) && self::approved_run( $runs[0], $sha );
    }

    public static function validate_path( string $component, string $relative ): void {
        $parts = explode( '/', $relative );
        $forbidden = array( 'wp-config.php', 'wp-config-sample.php', 'wp-admin', 'wp-includes', 'wp-content', 'uploads', 'tests', 'test', 'tools', 'private', 'secrets', 'credentials', 'backup', 'backups', 'cache', 'node_modules', 'vendor', '__pycache__' );
        foreach ( $parts as $part ) {
            if ( '' === $part || '.' === $part[0] || strpbrk( $part, ":\\\0" ) || in_array( strtolower( $part ), $forbidden, true ) ) {
                throw new RuntimeException( 'Forbidden package path' );
            }
        }
        $name = end( $parts ); $extension = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
        $stem = strtolower( pathinfo( $name, PATHINFO_FILENAME ) );
        if ( array_intersect( preg_split( '/[._-]+/', $stem ), array( 'config', 'private', 'secret', 'secrets', 'credential', 'credentials', 'backup', 'backups', 'test', 'tests', 'dump', 'export', 'log' ) ) ) { throw new RuntimeException( 'Private filename refused' ); }
        $allowed = false;
        if ( 1 === count( $parts ) ) {
            $allowed = in_array( $extension, array( 'php', 'css', 'js' ), true ) || in_array( strtolower( $name ), array( 'readme.md', 'readme.txt', 'shipping.md', 'license', 'license.md', 'license.txt' ), true );
        } elseif ( 'assets' === $parts[0] ) {
            $allowed = in_array( $extension, array( 'css', 'js', 'png', 'jpg', 'jpeg', 'webp', 'gif', 'avif', 'svg', 'ico', 'ttf', 'otf', 'woff', 'woff2', 'mp4', 'webm' ), true );
        } elseif ( 'belmains' === $component && in_array( $parts[0], array( 'template-parts', 'inc' ), true ) ) { $allowed = 'php' === $extension;
        } elseif ( 'belmains' !== $component && 'includes' === $parts[0] ) { $allowed = 'php' === $extension; }
        if ( ! $allowed ) { throw new RuntimeException( 'Unexpected runtime file' ); }
    }

    private function unpack( string $archive, string $component, string $stage ): void {
        $zip = new ZipArchive();
        if ( true !== $zip->open( $archive, ZipArchive::CHECKCONS ) ) { throw new RuntimeException( 'Invalid release archive' ); }
        $seen = array(); $total = 0;
        try {
            for ( $index = 0; $index < $zip->numFiles; $index++ ) {
                $entry = $zip->statIndex( $index ); $name = $entry['name'];
                if ( ! str_starts_with( $name, $component . '/' ) ) { throw new RuntimeException( 'Invalid archive root' ); }
                $relative = substr( $name, strlen( $component ) + 1 );
                if ( str_ends_with( $name, '/' ) ) { throw new RuntimeException( 'Explicit archive directories refused' ); }
                self::validate_path( $component, $relative );
                $key = strtolower( $name );
                if ( isset( $seen[ $key ] ) ) { throw new RuntimeException( 'Duplicate archive path' ); }
                $seen[ $key ] = true;
                $opsys = 0; $attributes = 0;
                $zip->getExternalAttributesIndex( $index, $opsys, $attributes );
                $kind = ( $attributes >> 16 ) & 0170000;
                if ( 0 !== $kind && 0100000 !== $kind ) { throw new RuntimeException( 'Non-regular archive entry' ); }
                $total += $entry['size'];
                if ( $entry['size'] > 100 * 1024 * 1024 || $total > 300 * 1024 * 1024 ) { throw new RuntimeException( 'Uncompressed size limit exceeded' ); }
                $body = $zip->getFromIndex( $index );
                if ( false === $body || strlen( $body ) !== $entry['size'] ) { throw new RuntimeException( 'Archive extraction failed' ); }
                if ( 'php' === strtolower( pathinfo( $name, PATHINFO_EXTENSION ) ) ) { token_get_all( $body, TOKEN_PARSE ); }
                $target = $stage . '/' . $component . '/' . $relative;
                if ( ! is_dir( dirname( $target ) ) && ! mkdir( dirname( $target ), 0755, true ) ) { throw new RuntimeException( 'Staging directory failed' ); }
                if ( false === file_put_contents( $target, $body ) ) { throw new RuntimeException( 'Staging write failed' ); }
                chmod( $target, 0644 );
            }
        } finally { $zip->close(); }
        self::manifest( $stage . '/' . $component );
    }

    private function source( string $sha, string $stage ): void {
        $tag = 'belmains-deploy-' . $sha;
        $release = $this->api( '/releases/tags/' . $tag );
        if ( ( $release['tag_name'] ?? '' ) !== $tag || ( $release['target_commitish'] ?? '' ) !== $sha || ! empty( $release['draft'] ) ) { throw new RuntimeException( 'Release identity mismatch' ); }
        $assets = array();
        foreach ( $release['assets'] ?? array() as $asset ) {
            $name = $asset['name'];
            if ( isset( $assets[ $name ] ) ) { throw new RuntimeException( 'Duplicate release asset' ); }
            $expected = 'https://github.com/' . self::REPO . '/releases/download/' . $tag . '/' . rawurlencode( $name );
            if ( $expected !== $asset['browser_download_url'] ) { throw new RuntimeException( 'Unexpected release asset URL' ); }
            $assets[ $name ] = $asset['browser_download_url'];
        }
        if ( ! isset( $assets['SHA256SUMS'] ) ) { throw new RuntimeException( 'Missing checksum asset' ); }
        list( $status, $body ) = $this->get( $assets['SHA256SUMS'], 16384 );
        if ( 200 !== $status ) { throw new RuntimeException( 'Checksum transfer failed' ); }
        $sums = array();
        foreach ( preg_split( '/\R/', trim( $body ) ) as $line ) {
            if ( ! preg_match( '/\A([a-f0-9]{64})  ([A-Za-z0-9._-]+\.zip)\z/', $line, $match ) || isset( $sums[ $match[2] ] ) ) { throw new RuntimeException( 'Invalid release checksum manifest' ); }
            $sums[ $match[2] ] = $match[1];
        }
        if ( 3 !== count( $sums ) ) { throw new RuntimeException( 'Expected exactly three release archives' ); }
        foreach ( array( 'belmains' => 'belmains-wordpress-theme', 'belmains-commerce' => 'belmains-commerce', 'belmains-crm' => 'belmains-crm' ) as $component => $prefix ) {
            $names = array_values( array_filter( array_keys( $sums ), static function ( string $name ) use ( $prefix ): bool { return (bool) preg_match( '/\A' . preg_quote( $prefix, '/' ) . '-[0-9][A-Za-z0-9._-]*\.zip\z/', $name ); } ) );
            if ( 1 !== count( $names ) || ! isset( $assets[ $names[0] ] ) ) { throw new RuntimeException( 'Missing or ambiguous component archive' ); }
            $name = $names[0]; list( $status, $archive ) = $this->get( $assets[ $name ], 120 * 1024 * 1024 );
            if ( 200 !== $status || ! hash_equals( $sums[ $name ], hash( 'sha256', $archive ) ) ) { throw new RuntimeException( 'Release checksum mismatch' ); }
            $path = $stage . '/' . $name; file_put_contents( $path, $archive ); unset( $archive );
            $this->unpack( $path, $component, $stage );
            unlink( $path );
        }
    }

    private function copy_tree( string $source, string $target ): void {
        self::manifest( $source );
        if ( ! mkdir( $target, 0755, true ) ) { throw new RuntimeException( 'Copy directory failed' ); }
        foreach ( self::manifest( $source ) as $relative => $_ ) {
            $path = $target . '/' . $relative;
            if ( ! is_dir( dirname( $path ) ) ) { mkdir( dirname( $path ), 0755, true ); }
            if ( ! copy( $source . '/' . $relative, $path ) ) { throw new RuntimeException( 'Private backup copy failed' ); }
            chmod( $path, 0644 );
        }
    }

    private function remove_tree( string $path ): void {
        if ( ! str_starts_with( $path, $this->state . '/' ) || is_link( $path ) ) { throw new RuntimeException( 'Invalid private temporary path' ); }
        if ( ! is_dir( $path ) ) { return; }
        $iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $path, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
        foreach ( $iterator as $entry ) {
            if ( $entry->isDir() && ! $entry->isLink() ) { rmdir( $entry->getPathname() ); } else { unlink( $entry->getPathname() ); }
        }
        rmdir( $path );
    }

    private function temp( string $prefix ): string {
        $temp = $this->state . '/' . $prefix . '-' . bin2hex( random_bytes( 8 ) );
        if ( ! mkdir( $temp, 0700 ) ) { throw new RuntimeException( 'Private temporary directory failed' ); }
        return $temp;
    }

    private function invalidate_opcache(): void {
        if ( ! is_callable( 'opcache_invalidate' ) ) { return; }
        foreach ( self::COMPONENTS as $c => $_ ) {
            foreach ( self::manifest( $this->live( $c ) ) as $relative => $_hash ) {
                if ( 'php' === strtolower( pathinfo( $relative, PATHINFO_EXTENSION ) ) ) {
                    @opcache_invalidate( $this->live( $c ) . '/' . $relative, true );
                }
            }
        }
    }

    private function restore( array $journal ): void {
        $backup = realpath( $journal['backup'] );
        if ( false === $backup || ! str_starts_with( $backup, $this->state . '/backups/' ) ) { throw new RuntimeException( 'Invalid recovery backup' ); }
        $temp = $this->temp( 'rollback' );
        try {
            foreach ( self::COMPONENTS as $c => $_ ) {
                if ( self::manifest( $backup . '/' . $c ) != $journal['baseline']['manifests'][ $c ] ) { throw new RuntimeException( 'Recovery checksum mismatch' ); }
                $this->copy_tree( $backup . '/' . $c, $temp . '/' . $c );
            }
            foreach ( self::COMPONENTS as $c => $_ ) {
                if ( file_exists( $this->live( $c ) ) && ! rename( $this->live( $c ), $temp . '/retired-' . $c ) ) { throw new RuntimeException( 'Recovery rename failed' ); }
                if ( ! rename( $temp . '/' . $c, $this->live( $c ) ) ) { throw new RuntimeException( 'Recovery installation failed' ); }
            }
            $this->invalidate_opcache();
            $this->write_json( $this->state . '/current.json', $journal['baseline'] );
            unlink( $this->state . '/pending.json' );
        } finally { $this->remove_tree( $temp ); }
    }

    private function recover(): void {
        $pending = $this->state . '/pending.json';
        if ( ! is_file( $pending ) ) { return; }
        $journal = $this->json( $pending ); $current = $this->json( $this->state . '/current.json' );
        if ( $current['revision'] === $journal['revision'] && $this->snapshot( $current['revision'] ) == $current ) { unlink( $pending ); return; }
        $this->restore( $journal );
    }

    private function smoke( string $sha ): void {
        $query = '?belmains_revision=' . $sha;
        list( $status, $body ) = $this->get( 'https://belmains.com/' . $query );
        if ( 200 !== $status || false === strpos( $body, 'Belmains' ) ) { throw new RuntimeException( 'Homepage check failed' ); }
        list( $status, $body ) = $this->get( 'https://belmains.com/wp-json/' . $query );
        if ( 503 !== $status || false === strpos( $body, 'belmains_preparation' ) ) { throw new RuntimeException( 'REST closure check failed' ); }
        list( $status, $body ) = $this->get( 'https://belmains.com/wp-content/themes/belmains/style.css' . $query );
        if ( 200 !== $status || hash( 'sha256', $body ) !== hash_file( 'sha256', $this->live( 'belmains' ) . '/style.css' ) ) { throw new RuntimeException( 'Public asset checksum check failed' ); }
    }

    private function publish( string $sha, string $stage, array $baseline ): void {
        $this->verify( $baseline ); $expected = array();
        foreach ( self::COMPONENTS as $c => $_ ) { $expected[ $c ] = self::manifest( $stage . '/' . $c ); }
        $backups = $this->state . '/backups';
        if ( ! is_dir( $backups ) ) { mkdir( $backups, 0700 ); }
        $backup = $backups . '/' . gmdate( 'Ymd\THis\Z' ) . '-' . substr( $sha, 0, 12 ) . '-' . bin2hex( random_bytes( 3 ) );
        mkdir( $backup, 0700 );
        foreach ( self::COMPONENTS as $c => $_ ) {
            $this->copy_tree( $this->live( $c ), $backup . '/' . $c );
            if ( self::manifest( $backup . '/' . $c ) != $baseline['manifests'][ $c ] ) { throw new RuntimeException( 'Live change during backup' ); }
        }
        $this->verify( $baseline );
        $journal = array( 'revision' => $sha, 'backup' => $backup, 'baseline' => $baseline );
        $this->write_json( $this->state . '/pending.json', $journal );
        try {
            foreach ( self::COMPONENTS as $c => $_ ) {
                if ( ! rename( $this->live( $c ), $stage . '/previous-' . $c ) || ! rename( $stage . '/' . $c, $this->live( $c ) ) ) { throw new RuntimeException( 'Publication rename failed' ); }
            }
            $candidate = $this->snapshot( $sha );
            if ( $candidate['manifests'] != $expected || $candidate['guard_sha256'] !== $baseline['guard_sha256'] ) { throw new RuntimeException( 'Post-publication manifest mismatch' ); }
            $this->invalidate_opcache();
            $this->smoke( $sha );
            $this->write_json( $this->state . '/current.json', $candidate );
            unlink( $this->state . '/pending.json' );
            $this->log( 'DEPLOYED ' . $sha );
        } catch ( Throwable $error ) { $this->restore( $journal ); throw $error; }
    }

    public function log( string $message ): void {
        $file = $this->state . '/run.log';
        if ( is_file( $file ) && filesize( $file ) > 2 * 1024 * 1024 ) { rename( $file, $this->state . '/run-previous.log' ); }
        file_put_contents( $file, gmdate( 'c' ) . ' ' . str_replace( array( "\r", "\n" ), ' ', $message ) . "\n", FILE_APPEND | LOCK_EX ); chmod( $file, 0600 );
    }

    public function poll(): void {
        $lock = fopen( $this->state . '/lock', 'c' );
        if ( false === $lock ) { throw new RuntimeException( 'Deployment lock unavailable' ); }
        if ( ! flock( $lock, LOCK_EX | LOCK_NB ) ) { fclose( $lock ); throw new RuntimeException( 'Deployment already running; retry' ); }
        try {
            $this->recover(); $baseline = $this->json( $this->state . '/current.json' ); $this->verify( $baseline );
            $ref = $this->api( '/git/ref/heads/' . self::BRANCH ); $sha = $ref['object']['sha'] ?? '';
            if ( ! preg_match( '/\A[a-f0-9]{40}\z/', $sha ) || ( $ref['object']['type'] ?? '' ) !== 'commit' ) { throw new RuntimeException( 'Invalid publication revision' ); }
            if ( $sha === $baseline['revision'] ) { return; }
            $retry_file = $this->state . '/retry.json'; $retry = is_file( $retry_file ) ? $this->json( $retry_file ) : array();
            if ( ( $retry['revision'] ?? '' ) === $sha && ( $retry['after'] ?? 0 ) > time() ) { throw new RuntimeException( 'Validation retry pending ' . $sha ); }
            $this->write_json( $retry_file, array( 'revision' => $sha, 'after' => time() + 120 ) );
            if ( ! $this->approved( $sha ) ) { throw new RuntimeException( 'WAITING_FOR_VALIDATION ' . $sha ); }
            $stage = $this->temp( 'release' );
            try { $this->source( $sha, $stage ); $this->publish( $sha, $stage, $baseline ); }
            finally { $this->remove_tree( $stage ); }
        } finally { fclose( $lock ); }
    }
}
