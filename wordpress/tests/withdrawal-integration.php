<?php
/** Isolated validation of the online withdrawal function. Run through WP-CLI eval-file only. */
global $wpdb;
if ( 'bcrm_validation_' !== $wpdb->prefix || 'local' !== wp_get_environment_type() ) {
    throw new RuntimeException( 'Withdrawal tests require the isolated local validation tables.' );
}
if ( ! class_exists( 'BCRM_Withdrawal' ) || ! class_exists( 'WooCommerce' ) || ! function_exists( 'belmains_legal_links' ) ) { throw new RuntimeException( 'Belmains CRM, WooCommerce and the Belmains theme must be active.' ); }

$table = $wpdb->prefix . 'bcrm_tickets';
$tag = 'RETRACT-QA-' . strtoupper( substr( str_replace( '-', '', wp_generate_uuid4() ), 0, 10 ) );
$email = strtolower( $tag ) . '@example.invalid';
$checks = 0;
$check = static function ( $condition, $label ) use ( &$checks ) {
    if ( ! $condition ) { throw new RuntimeException( 'FAIL: ' . $label ); }
    ++$checks; echo 'PASS ' . $label . "\n";
};
$saved = array( 'page' => get_option( 'belmains_withdrawal_page_id', null ), 'get' => $_GET, 'cookie' => $_COOKIE );
$closed = false; $mail_ok = true; $mails = array(); $pages = array(); $orders = array(); $tokens = array();
$guard_mail = function_exists( 'belmains_catalog_mail_gate' ) && remove_filter( 'pre_wp_mail', 'belmains_catalog_mail_gate', PHP_INT_MAX );
$catalog = static function () use ( &$closed ) { return $closed; };
$mailer = static function ( $pre, $atts ) use ( &$mails, &$mail_ok ) { $mails[] = $atts; return $mail_ok; };
add_filter( 'belmains_catalog_only', $catalog, PHP_INT_MAX );
add_filter( 'pre_wp_mail', $mailer, PHP_INT_MAX, 2 );
$fixture = static function ( $changes = array() ) use ( &$tokens, $tag, $email ) {
    $token = str_replace( '-', '', wp_generate_uuid4() ); $tokens[] = $token;
    return array_merge( array( 'request_token' => $token, 'bcrm_withdrawal_nonce' => wp_create_nonce( 'bcrm_withdrawal_' . $token ), 'name' => 'Cliente de validation', 'email' => $email, 'order_reference' => $tag, 'details' => 'Un seul gant renvoyé.', 'website' => '' ), $changes );
};
$server = static function ( $changes = array() ) { return array_merge( array( 'REQUEST_METHOD' => 'POST', 'REMOTE_ADDR' => '2001:db8:' . dechex( random_int( 1, 65535 ) ) . '::7', 'HTTP_ORIGIN' => home_url(), 'HTTP_SEC_FETCH_SITE' => 'same-origin' ), $changes ); };
$is_error = static function ( $result, $status ) { return is_wp_error( $result ) && $status === ( $result->get_error_data()['status'] ?? 0 ); };
$tickets = static function ( $like ) use ( $wpdb, $table ) { return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table WHERE subject LIKE %s ORDER BY id", '%' . $wpdb->esc_like( $like ) . '%' ), ARRAY_A ); };

try {
    $check( shortcode_exists( 'belmains_retractation' ) && 'Renoncer au contrat ici' === BCRM_Withdrawal::LINK_LABEL && 'Confirmer la rétractation' === BCRM_Withdrawal::CONFIRM_LABEL, 'the function is registered with its two statutory labels' );

    // Before the opening there is no order to withdraw from: no link, no form, no recording.
    $page = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'draft', 'post_title' => 'Validation rétractation', 'post_content' => '[belmains_retractation]' ), true );
    if ( is_wp_error( $page ) ) { throw new RuntimeException( $page->get_error_message() ); }
    $pages[] = $page; update_option( 'belmains_withdrawal_page_id', $page, false );
    $check( '' === BCRM_Withdrawal::url() && ! isset( belmains_legal_links()['withdrawal'] ), 'no link is offered while the page is a draft' );
    wp_update_post( array( 'ID' => $page, 'post_status' => 'publish' ) );
    $closed = true;
    $check( '' === BCRM_Withdrawal::url() && ! isset( belmains_legal_links()['withdrawal'] ) && $is_error( BCRM_Withdrawal::submit( $fixture(), $server() ), 503 ), 'the pre-opening showcase offers no link and records nothing' );
    $check( false === strpos( BCRM_Withdrawal::shortcode(), '<form' ), 'the pre-opening showcase shows no statement form' );
    $closed = false;
    $links = belmains_legal_links();
    $check( get_permalink( $page ) === BCRM_Withdrawal::url() && isset( $links['withdrawal'] ) && 'Renoncer au contrat ici' === $links['withdrawal'][1], 'once open, both footers carry the link « Renoncer au contrat ici »' );

    // Refusals record nothing and send nothing.
    $check( $is_error( BCRM_Withdrawal::submit( $fixture(), $server( array( 'REQUEST_METHOD' => 'GET' ) ) ), 405 ), 'a GET request is refused' );
    $check( $is_error( BCRM_Withdrawal::submit( $fixture(), $server( array( 'HTTP_ORIGIN' => 'https://attacker.invalid' ) ) ), 403 ) && $is_error( BCRM_Withdrawal::submit( $fixture(), $server( array( 'HTTP_SEC_FETCH_SITE' => 'cross-site' ) ) ), 403 ), 'a cross-site submission is refused' );
    $check( $is_error( BCRM_Withdrawal::submit( $fixture( array( 'bcrm_withdrawal_nonce' => 'forged' ) ), $server() ), 403 ), 'a forged nonce is refused' );
    $check( $is_error( BCRM_Withdrawal::submit( $fixture( array( 'website' => 'https://spam.invalid' ) ), $server() ), 400 ), 'the honeypot refuses automated submissions' );
    $invalid = BCRM_Withdrawal::submit( $fixture( array( 'name' => ' ', 'email' => 'not-an-email', 'order_reference' => '' ) ), $server() );
    $check( $is_error( $invalid, 400 ) && array( 'name', 'email', 'order_reference' ) === array_keys( $invalid->get_error_data()['fields'] ), 'name, e-mail and order number are required; details are optional' );
    $check( array() === $tickets( $tag ) && array() === $mails, 'refused statements are neither recorded nor acknowledged' );

    // A statement is recorded and acknowledged at once, whether or not an order matches.
    $input = $fixture();
    $before = time();
    $result = BCRM_Withdrawal::submit( $input, $server() );
    $rows = $tickets( $tag );
    $check( is_array( $result ) && true === $result['success'] && true === $result['mailed'] && $result['received'] >= $before, 'a valid statement returns its time of receipt' );
    $check( 1 === count( $rows ) && 'high' === $rows[0]['priority'] && 'open' === $rows[0]['status'] && '0' === (string) $rows[0]['order_id'] && $email === $rows[0]['customer_email'] && false !== strpos( $rows[0]['message'], 'à vérifier' ) && false !== strpos( $rows[0]['message'], 'Un seul gant renvoyé.' ), 'it is recorded in the support inbox with high priority, unmatched orders flagged for checking' );
    $stamp = BCRM_Withdrawal::stamp( $result['received'] );
    $to = static function ( $mail ) { return is_array( $mail['to'] ) ? implode( ',', $mail['to'] ) : $mail['to']; };
    $check( 2 === count( $mails ) && $email === $to( $mails[0] ) && false !== strpos( $mails[0]['subject'], 'Accusé de réception' ) && false !== strpos( $mails[0]['message'], $stamp ) && false !== strpos( $mails[0]['message'], $tag ) && false !== strpos( $mails[0]['message'], 'Cliente de validation' ) && false !== strpos( $mails[0]['message'], 'Un seul gant renvoyé.' ), 'the customer receives an acknowledgement with the date, time and content of the statement' );
    $check( $email !== $to( $mails[1] ) && is_email( $to( $mails[1] ) ) && false !== strpos( $mails[1]['subject'], 'Rétractation reçue' ) && false !== strpos( implode( "\n", (array) $mails[1]['headers'] ), 'Reply-To: ' . $email ), 'the shop is told at once, with the customer as reply address' );
    $again = BCRM_Withdrawal::submit( $input, $server() );
    $check( is_array( $again ) && $again['received'] === $result['received'] && 1 === count( $tickets( $tag ) ) && 2 === count( $mails ), 'a repeated confirmation is answered without a second record or e-mail' );
    $check( $is_error( BCRM_Withdrawal::submit( array_merge( $input, array( 'details' => 'Autre contenu.' ) ), $server() ), 409 ), 'the same form cannot carry a different statement' );

    // An order is attached only when number and billing e-mail both match.
    $order = wc_create_order(); $order->set_billing_email( $email ); $order->set_billing_first_name( 'Validation' ); $order->save(); $orders[] = $order->get_id();
    $other = wc_create_order(); $other->set_billing_email( 'someone-else@example.invalid' ); $other->save(); $orders[] = $other->get_id();
    $mails = array();
    BCRM_Withdrawal::submit( $fixture( array( 'order_reference' => 'n° ' . $order->get_id(), 'details' => '' ) ), $server() );
    $matched = $tickets( 'commande n° ' . $order->get_id() );
    $notes = wc_get_order_notes( array( 'order_id' => $order->get_id() ) );
    $check( 1 === count( $matched ) && (string) $order->get_id() === (string) $matched[0]['order_id'] && false !== strpos( $matched[0]['message'], 'rattachée à la commande' ) && 1 === count( $notes ) && false !== strpos( $notes[0]->content, 'Déclaration de rétractation reçue' ) && ! $notes[0]->customer_note, 'a matching order is attached and receives a private note' );
    BCRM_Withdrawal::submit( $fixture( array( 'order_reference' => (string) $other->get_id() ) ), $server() );
    $foreign = $tickets( 'commande ' . $other->get_id() );
    $check( 1 === count( $foreign ) && '0' === (string) $foreign[0]['order_id'] && array() === wc_get_order_notes( array( 'order_id' => $other->get_id() ) ), 'another customer’s order number is recorded but never attached' );

    // A failed acknowledgement is neither hidden from the customer nor from the shop.
    $mail_ok = false;
    $failed = BCRM_Withdrawal::submit( $fixture( array( 'order_reference' => $tag . '-MAIL', 'email' => 'b-' . $email ) ), $server() );
    $row = $tickets( $tag . '-MAIL' );
    $check( is_array( $failed ) && false === $failed['mailed'] && 1 === count( $row ) && 'urgent' === $row[0]['priority'] && false !== strpos( $row[0]['message'], 'n’a pas pu être envoyé' ), 'a statement whose acknowledgement could not be e-mailed is still recorded, as urgent' );
    $mail_ok = true;
    $check( $is_error( BCRM_Withdrawal::submit( $fixture( array( 'order_reference' => $tag . '-LIMIT' ) ), $server() ), 429 ) && array() === $tickets( $tag . '-LIMIT' ), 'a fourth statement for the same address within fifteen minutes is refused' );

    // What the customer sees.
    $_GET = array( 'commande' => '4521' ); $_COOKIE = array();
    $form = BCRM_Withdrawal::shortcode();
    $check( false !== strpos( $form, '>Confirmer la rétractation</button>' ) && false !== strpos( $form, 'name="order_reference"' ) && false !== strpos( $form, 'value="4521"' ) && false !== strpos( $form, 'value="bcrm_withdrawal_submit"' ) && false !== strpos( $form, '14 jours' ), 'the form asks for name, order and e-mail, pre-fills the order from the link and is sent with « Confirmer la rétractation »' );
    $proof = substr( hash_hmac( 'sha256', 'withdrawal-receipt:' . $result['received'] . ':1', wp_salt( 'auth' ) ), 0, 32 );
    $_GET = array( 'retractation' => 'recue', 'le' => (string) $result['received'], 'mail' => '1', 'preuve' => $proof );
    $receipt = BCRM_Withdrawal::shortcode();
    $check( false !== strpos( $receipt, esc_html( $stamp ) ) && false !== strpos( $receipt, 'accusé de réception' ) && false === strpos( $receipt, '<form' ), 'the confirmation screen states the date and time of receipt' );
    $_GET['le'] = (string) ( $result['received'] - 86400 );
    $check( false !== strpos( BCRM_Withdrawal::shortcode(), '<form' ), 'a forged confirmation address shows the form, not a false receipt' );
    $_GET = array( 'retractation' => 'recue', 'le' => (string) $result['received'], 'mail' => '0', 'preuve' => substr( hash_hmac( 'sha256', 'withdrawal-receipt:' . $result['received'] . ':0', wp_salt( 'auth' ) ), 0, 32 ) );
    $check( false !== strpos( BCRM_Withdrawal::shortcode(), 'n’a pas pu être envoyé' ), 'the customer is told when the acknowledgement e-mail could not be sent' );
    $_GET = array();

    // Order e-mails and order pages carry the link during the whole withdrawal period.
    ob_start(); BCRM_Withdrawal::email_notice( $order, false, false ); $html = ob_get_clean();
    ob_start(); BCRM_Withdrawal::email_notice( $order, false, true ); $plain = ob_get_clean();
    ob_start(); BCRM_Withdrawal::email_notice( $order, true, false ); $admin = ob_get_clean();
    ob_start(); BCRM_Withdrawal::order_notice( $order ); $account = ob_get_clean();
    $link = 'commande=' . $order->get_order_number();
    $check( false !== strpos( $html, $link ) && false !== strpos( $html, '>Renoncer au contrat ici</a>' ) && false !== strpos( $plain, $link ) && false === strpos( $plain, '<' ) && '' === $admin && false !== strpos( $account, $link ), 'customer e-mails and order pages link to the function with the order number; shop e-mails do not' );
    $closed = true;
    ob_start(); BCRM_Withdrawal::email_notice( $order, false, false ); BCRM_Withdrawal::order_notice( $order ); $silent = ob_get_clean();
    $check( '' === $silent, 'nothing is added to e-mails or order pages before the opening' );
    $closed = false;
    echo $checks . " withdrawal checks passed.\n";
} finally {
    remove_filter( 'belmains_catalog_only', $catalog, PHP_INT_MAX );
    remove_filter( 'pre_wp_mail', $mailer, PHP_INT_MAX );
    if ( $guard_mail ) { add_filter( 'pre_wp_mail', 'belmains_catalog_mail_gate', PHP_INT_MAX, 2 ); }
    $_GET = $saved['get']; $_COOKIE = $saved['cookie'];
    foreach ( $orders as $id ) { $wpdb->query( $wpdb->prepare( "DELETE FROM $table WHERE order_id = %d", $id ) ); $o = wc_get_order( $id ); if ( $o ) { $o->delete( true ); } }
    $wpdb->query( $wpdb->prepare( "DELETE FROM $table WHERE customer_email IN (%s, %s)", $email, 'b-' . $email ) );
    foreach ( $pages as $id ) { wp_delete_post( $id, true ); }
    if ( null === $saved['page'] ) { delete_option( 'belmains_withdrawal_page_id' ); } else { update_option( 'belmains_withdrawal_page_id', $saved['page'], false ); }
    $hash = static function ( $value ) { return hash_hmac( 'sha256', $value, wp_salt( 'auth' ) ); };
    foreach ( $tokens as $token ) { delete_transient( 'bcrm_withdrawal_done_' . $hash( $token ) ); }
    delete_transient( 'bcrm_withdrawal_email_' . $hash( $email ) ); delete_transient( 'bcrm_withdrawal_email_' . $hash( 'b-' . $email ) );
    $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_bcrm\_withdrawal\_ip\_%' OR option_name LIKE '\_transient\_timeout\_bcrm\_withdrawal\_ip\_%'" );
}
