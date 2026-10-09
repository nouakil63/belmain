<?php
/** Isolated public-contact validation. Run through WP-CLI eval-file only. */
global $wpdb;
if ( 'bcrm_validation_' !== $wpdb->prefix || 'local' !== wp_get_environment_type() ) {
    throw new RuntimeException( 'Contact tests require the isolated local validation tables.' );
}
if ( ! class_exists( 'BCRM_Contact' ) || ! class_exists( 'BCRM_Support' ) ) { throw new RuntimeException( 'Belmains CRM must be active.' ); }

$table = $wpdb->prefix . 'bcrm_tickets';
$tag = 'CONTACT-QA-' . wp_generate_uuid4();
$email = strtolower( $tag ) . '@example.invalid';
$tokens = array();
$saved_user = get_current_user_id();
$saved_cookie = $_COOKIE;
$saved_get = $_GET;
$saved_page = get_option( 'belmains_contact_page_id', null );
$pages = array();
$ip = '2001:db8:' . dechex( random_int( 1, 65535 ) ) . '::12';
$other_ip = '2001:db8:' . dechex( random_int( 1, 65535 ) ) . '::15';
$emails = array( $email );
$flash_keys = array();
$additional_locks = array();
$hash = static function( $value ) { return hash_hmac( 'sha256', $value, wp_salt( 'auth' ) ); };
$mail_count = 0;
$block_mail = static function() use ( &$mail_count ) { ++$mail_count; return false; };
add_filter( 'pre_wp_mail', $block_mail );
$checks = 0;
$check = static function( $condition, $label ) use ( &$checks ) {
    if ( ! $condition ) { throw new RuntimeException( 'FAIL: ' . $label ); }
    ++$checks;
    echo 'PASS ' . $label . "\n";
};
$fixture = static function( $changes = array() ) use ( &$tokens, $tag, $email ) {
    $token = str_replace( '-', '', wp_generate_uuid4() );
    $tokens[] = $token;
    return array_merge( array( 'request_token' => $token, 'bcrm_contact_nonce' => wp_create_nonce( 'bcrm_contact_' . $token ), 'name' => 'Client de validation', 'email' => $email, 'subject' => $tag, 'message' => 'Message réservé à la validation locale du formulaire.', 'order_reference' => 'CMD-1234', 'website' => '' ), $changes );
};
$server = array( 'REQUEST_METHOD' => 'POST', 'REMOTE_ADDR' => $ip, 'HTTP_ORIGIN' => home_url(), 'HTTP_SEC_FETCH_SITE' => 'same-origin' );
$is_error = static function( $result, $status ) { return is_wp_error( $result ) && $status === ( $result->get_error_data()['status'] ?? 0 ); };
$count = static function() use ( $wpdb, $table, $tag ) { return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE subject = %s", $tag ) ); };

try {
    wp_set_current_user( 0 );
    $_GET = array();
    $check( shortcode_exists( 'belmains_contact' ), 'Public shortcode is registered' );
    $check( false !== has_action( 'admin_post_nopriv_bcrm_contact_submit' ), 'Guest POST handler is registered' );
    $check( false !== has_action( 'admin_post_bcrm_contact_submit' ), 'Authenticated POST handler is registered' );
    $input = $fixture();
    $check( $is_error( BCRM_Contact::submit( $input, array_merge( $server, array( 'REQUEST_METHOD' => 'GET' ) ) ), 405 ), 'GET never creates a ticket' );
    $check( $is_error( BCRM_Contact::submit( $input, array_merge( $server, array( 'HTTP_ORIGIN' => 'https://foreign.example' ) ) ), 403 ), 'Foreign origin is rejected' );
    $check( $is_error( BCRM_Contact::submit( $input, array_merge( $server, array( 'HTTP_ORIGIN' => 'null' ) ) ), 403 ), 'Null origin is rejected' );
    $check( $is_error( BCRM_Contact::submit( $input, array_merge( $server, array( 'HTTP_SEC_FETCH_SITE' => 'cross-site' ) ) ), 403 ), 'Cross-site browser request is rejected' );
    $check( $is_error( BCRM_Contact::submit( array_merge( $input, array( 'bcrm_contact_nonce' => '' ) ), $server ), 403 ), 'Missing nonce is rejected' );
    $check( $is_error( BCRM_Contact::submit( array_merge( $input, array( 'request_token' => str_repeat( 'a', 32 ) ) ), $server ), 403 ), 'Nonce is bound to its request token' );
    foreach ( array( 'name', 'email', 'subject', 'message', 'order_reference', 'request_token', 'bcrm_contact_nonce', 'website' ) as $field ) {
        $check( $is_error( BCRM_Contact::submit( array_merge( $input, array( $field => array( 'bad' ) ) ), $server ), 400 ), $field . ' rejects array values' );
    }
    $check( $is_error( BCRM_Contact::submit( $fixture( array( 'website' => 'spam' ) ), $server ), 400 ), 'Honeypot does not create a ticket' );
    foreach ( array( 'name' => '', 'email' => 'bad-email', 'subject' => 'x', 'message' => 'short' ) as $field => $value ) {
        $result = BCRM_Contact::submit( $fixture( array( $field => $value ) ), $server );
        $check( $is_error( $result, 400 ) && isset( $result->get_error_data()['fields'][ $field ] ), $field . ' provides a field-specific error' );
    }
    foreach ( array( 'name' => 101, 'email' => 201, 'subject' => 161, 'message' => 5001, 'order_reference' => 81 ) as $field => $length ) {
        $result = BCRM_Contact::submit( $fixture( array( $field => str_repeat( 'é', $length ) ) ), $server );
        $check( $is_error( $result, 400 ) && isset( $result->get_error_data()['fields'][ $field ] ), $field . ' enforces its Unicode length limit' );
    }
    $check( 0 === $count(), 'Invalid submissions did not create tickets' );
    $input['priority'] = 'urgent';
    $input['status'] = 'closed';
    $input['order_id'] = 1234;
    $input['name'] = '<b>Client de validation</b>';
    $input['message'] .= '<script>alert(1)</script>';
    $result = BCRM_Contact::submit( $input, $server );
    $check( array( 'success' => true ) === $result, 'Guest submission returns success without ticket ID or PII' );
    $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE subject = %s ORDER BY id DESC LIMIT 1", $tag ), ARRAY_A );
    $check( 1 === $count() && $email === $row['customer_email'], 'Ticket is saved in the private CRM support table' );
    $check( 'normal' === $row['priority'] && 'open' === $row['status'], 'Client cannot override priority or status' );
    $check( 0 === (int) $row['order_id'] && str_contains( $row['message'], 'CMD-1234' ), 'Order reference stays unverified text, never an arbitrary order association' );
    $check( str_contains( $row['message'], 'Nom : Client de validation' ) && ! str_contains( $row['message'], '<script>' ) && ! str_contains( $row['message'], '<b>' ), 'Support content is plain sanitized text' );
    $check( ! str_contains( wp_json_encode( $row ), $ip ), 'Ticket does not store the visitor IP address' );
    $check( array( 'success' => true ) === BCRM_Contact::submit( $input, $server ) && 1 === $count(), 'Identical retry is idempotent' );
    $changed = array_merge( $input, array( 'message' => 'Un message différent avec le même jeton.' ) );
    $check( $is_error( BCRM_Contact::submit( $changed, $server ), 409 ) && 1 === $count(), 'Token cannot be reused for changed content' );

    $busy = $fixture();
    $lock_key = 'bcrm_contact_lock_' . $hash( $busy['request_token'] );
    add_option( $lock_key, time() + MINUTE_IN_SECONDS, '', false );
    $check( $is_error( BCRM_Contact::submit( $busy, $server ), 409 ) && 1 === $count(), 'Concurrent request lock prevents duplicate creation' );
    delete_option( $lock_key );
    update_option( $lock_key, time() - 1, false );
    BCRM_Contact::cleanup();
    $check( false === get_option( $lock_key ), 'Interrupted request locks can be cleaned' );

    $bucket_ip = 'bcrm_contact_ip_' . $hash( $ip );
    $bucket_email = 'bcrm_contact_email_' . $hash( $email );
    foreach ( array( $bucket_ip, $bucket_email ) as $bucket ) {
        $bucket_lock = 'bcrm_contact_lock_' . $hash( 'rate:' . $bucket );
        $additional_locks[] = $bucket_lock;
        add_option( $bucket_lock, ( time() + MINUTE_IN_SECONDS ) . ':other-request', '', false );
        $concurrent = $fixture();
        $concurrent_server = $server;
        if ( $bucket === $bucket_email ) { $concurrent_server['REMOTE_ADDR'] = $other_ip; }
        else { $concurrent['email'] = 'concurrent-' . $email; $emails[] = $concurrent['email']; }
        $before = (int) get_transient( $bucket );
        $check( $is_error( BCRM_Contact::submit( $concurrent, $concurrent_server ), 409 ) && 1 === $count() && $before === (int) get_transient( $bucket ), ( $bucket === $bucket_ip ? 'IP' : 'Email' ) . ' shared lease prevents a different simultaneous token bypassing quotas' );
        delete_option( $bucket_lock );
        $check( false === get_option( 'bcrm_contact_lock_' . $hash( $concurrent['request_token'] ) ), 'Rejected concurrent request releases its own token lease' );
    }
    $lease_method = new ReflectionMethod( BCRM_Contact::class, 'release_lock' );
    $lease_method->setAccessible( true );
    $replacement_key = 'bcrm_contact_lock_' . $hash( 'replacement-' . $tag );
    $additional_locks[] = $replacement_key;
    add_option( $replacement_key, ( time() + MINUTE_IN_SECONDS ) . ':new-owner', '', false );
    $lease_method->invoke( null, $replacement_key, ( time() - 1 ) . ':old-owner' );
    $check( false !== get_option( $replacement_key ), 'An expired owner cannot delete a replacement lease' );
    delete_option( $replacement_key );

    $acquire_method = new ReflectionMethod( BCRM_Contact::class, 'acquire_lock' );
    $acquire_method->setAccessible( true );
    $race_key = 'bcrm_contact_lock_' . $hash( 'stale-cache-' . $tag );
    $additional_locks[] = $race_key;
    $first_lease = $acquire_method->invoke( null, $race_key );
    $check( is_string( $first_lease ) && '' !== $first_lease, 'First database-only lock acquisition succeeds' );
    // Reproduce a second process whose option cache still says the key is absent.
    $notoptions = wp_cache_get( 'notoptions', 'options' );
    $notoptions = is_array( $notoptions ) ? $notoptions : array();
    $notoptions[ $race_key ] = true;
    wp_cache_delete( $race_key, 'options' );
    wp_cache_set( 'notoptions', $notoptions, 'options' );
    $check( false === get_option( $race_key ), 'Race fixture forces a stale absent-option cache' );
    $second_lease = $acquire_method->invoke( null, $race_key );
    $stored_lease = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $race_key ) );
    $check( false === $second_lease && $first_lease === $stored_lease, 'Stale cache cannot grant a second owner or overwrite the first lease' );
    $check( 1 === (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name = %s", $race_key ) ), 'Unique database index retains exactly one lock row' );
    $lease_method->invoke( null, $race_key, $first_lease );
    $next_lease = $acquire_method->invoke( null, $race_key );
    $check( is_string( $next_lease ) && $first_lease !== $next_lease, 'Released database lock can be acquired by a new owner' );
    $lease_method->invoke( null, $race_key, $next_lease );

    $without_origin = $server;
    unset( $without_origin['HTTP_ORIGIN'] );
    $check( array( 'success' => true ) === BCRM_Contact::submit( $fixture(), $without_origin ), 'Valid native POST without Origin header is accepted' );
    $check( array( 'success' => true ) === BCRM_Contact::submit( $fixture(), $server ), 'Third message in the window is accepted' );
    $check( $is_error( BCRM_Contact::submit( $fixture(), array_merge( $server, array( 'REMOTE_ADDR' => $other_ip ) ) ), 429 ), 'Email rate limit survives an IP change' );
    $check( array( 'success' => true ) === BCRM_Contact::submit( $input, $server ) && 3 === $count(), 'Identical retry succeeds after rate limit without adding a ticket' );
    foreach ( array( 1, 2, 3 ) as $index ) {
        $fresh_email = 'v' . $index . '-' . $email;
        $emails[] = $fresh_email;
        $result = BCRM_Contact::submit( $fixture( array( 'email' => $fresh_email ) ), $server );
        $check( 3 === $index ? $is_error( $result, 429 ) : array( 'success' => true ) === $result, 'IP rate limit step ' . $index );
    }
    $option_names = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( '_transient_bcrm_contact_' ) . '%' ) );
    $check( ! str_contains( implode( '|', $option_names ), $email ) && ! str_contains( implode( '|', $option_names ), $ip ), 'Rate and idempotency keys contain only salted hashes' );

    $html = do_shortcode( '[belmains_contact]' );
    $check( str_contains( $html, 'name="bcrm_contact_nonce"' ) && str_contains( $html, 'name="request_token"' ) && str_contains( $html, 'admin-post.php' ), 'Rendered form contains native endpoint and submission credentials' );
    $check( str_contains( $html, 'for="bcrm-contact-email"' ) && str_contains( $html, 'name="message" rows="7"' ) && str_contains( $html, 'ne vous inscrivent à aucune liste publicitaire' ), 'Rendered form includes accessible labels and support-only privacy text' );
    $respond = new ReflectionMethod( BCRM_Contact::class, 'response' );
    $respond->setAccessible( true );
    $flash_count = static function() use ( $wpdb ) { return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( '_transient_bcrm_contact_flash_' ) . '%' ) ); };
    $baseline = $flash_count();
    $security_cases = array(
        array( array_merge( $fixture(), array( 'bcrm_contact_nonce' => '' ) ), $server ),
        array( $fixture(), array_merge( $server, array( 'REQUEST_METHOD' => 'GET' ) ) ),
        array( $fixture(), array_merge( $server, array( 'HTTP_ORIGIN' => 'https://foreign.example' ) ) ),
        array( $fixture( array( 'website' => 'filled' ) ), $server ),
        array( $fixture(), $server ), // This identity has already reached its quota.
    );
    foreach ( $security_cases as $index => $case ) {
        for ( $repeat = 0; $repeat < 5; ++$repeat ) { $response = $respond->invoke( null, $case[0], $case[1] ); }
        $check( $baseline === $flash_count() && '' === $response['cookie'] && ! str_contains( $response['redirect'], $email ), 'Rejected response case ' . $index . ' allocates no flash data or PII URL' );
    }
    $response = $respond->invoke( null, $input, $server );
    $check( $baseline === $flash_count() && '' === $response['cookie'] && str_contains( $response['redirect'], 'contact_status=received' ), 'Idempotent success allocates no flash data' );
    $flash_key = 'bcrm_contact_flash_' . $hash( $ip );
    $flash_keys[] = $flash_key;
    $invalid = $fixture( array( 'email' => 'bad-email', 'name' => 'Test <script>alert(1)</script>', 'message' => 'Message à conserver pour correction.' ) );
    $response = $respond->invoke( null, $invalid, $server );
    $old_cookie = $response['cookie'];
    for ( $repeat = 0; $repeat < 20; ++$repeat ) { $response = $respond->invoke( null, $fixture( array( 'email' => 'bad-email', 'name' => 'Test <script>alert(1)</script>', 'message' => 'Message à conserver pour correction.' ) ), $server ); }
    $check( $baseline + 1 === $flash_count() && ! str_contains( $response['redirect'], 'bad-email' ), 'Repeated valid-nonce invalid forms occupy only one expiring flash slot per IP' );
    $_COOKIE['belmains_contact_feedback'] = $old_cookie;
    $check( ! str_contains( do_shortcode( '[belmains_contact]' ), 'Message à conserver pour correction.' ) && false !== get_transient( $flash_key ), 'An older cookie cannot read or consume another browser submission in the same IP slot' );
    $_COOKIE['belmains_contact_feedback'] = $response['cookie'];
    $html = do_shortcode( '[belmains_contact]' );
    $check( str_contains( $html, 'aria-invalid="true"' ) && str_contains( $html, 'Message à conserver pour correction.' ) && ! str_contains( $html, '<script>' ), 'Private flash preserves safe values and highlights the invalid field' );
    $check( false === get_transient( $flash_key ), 'Flash data is consumed once' );
    $_COOKIE['belmains_contact_feedback'] = array( 'bad' );
    $check( str_contains( do_shortcode( '[belmains_contact]' ), 'belmains-contact-form' ), 'Malformed feedback cookie is ignored safely' );
    $_GET['contact_status'] = 'limited';
    $check( str_contains( do_shortcode( '[belmains_contact]' ), 'Réessayez dans 15 minutes.' ), 'Stateless quota response displays a useful public message' );
    $_GET['contact_status'] = '<script>alert(1)</script>';
    $check( ! str_contains( do_shortcode( '[belmains_contact]' ), '<script>' ), 'Unknown public status codes are ignored' );
    $_GET = array();

    $page_id = wp_insert_post( array( 'post_title' => $tag, 'post_type' => 'page', 'post_status' => 'publish', 'post_content' => '[belmains_contact]' ) );
    $pages[] = $page_id;
    update_option( 'belmains_contact_page_id', $page_id, false );
    $check( get_permalink( $page_id ) === BCRM_Contact::url(), 'Redirect destination uses the configured published contact page' );
    wp_update_post( array( 'ID' => $page_id, 'post_status' => 'draft' ) );
    $check( home_url( '/contact/' ) === BCRM_Contact::url(), 'Draft contact page is never exposed by redirect' );
    $check( 0 === $mail_count, 'Contact flow sends no email' );
    echo wp_json_encode( array( 'success' => true, 'checks' => $checks ), JSON_PRETTY_PRINT ) . "\n";
} finally {
    $wpdb->delete( $table, array( 'subject' => $tag ), array( '%s' ) );
    foreach ( $tokens as $token ) {
        delete_transient( 'bcrm_contact_done_' . $hash( $token ) );
        delete_option( 'bcrm_contact_lock_' . $hash( $token ) );
    }
    foreach ( array( $ip, $other_ip ) as $value ) { delete_transient( 'bcrm_contact_ip_' . $hash( $value ) ); }
    foreach ( $emails as $value ) { delete_transient( 'bcrm_contact_email_' . $hash( $value ) ); }
    foreach ( $flash_keys as $key ) { delete_transient( $key ); }
    foreach ( $additional_locks as $key ) { delete_option( $key ); }
    foreach ( $pages as $id ) { wp_delete_post( $id, true ); }
    if ( null === $saved_page ) { delete_option( 'belmains_contact_page_id' ); } else { update_option( 'belmains_contact_page_id', $saved_page, false ); }
    $_COOKIE = $saved_cookie;
    $_GET = $saved_get;
    wp_set_current_user( $saved_user );
    remove_filter( 'pre_wp_mail', $block_mail );
}
