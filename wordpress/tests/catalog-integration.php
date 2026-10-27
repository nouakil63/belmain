<?php
/** Run with wp eval-file on the isolated local validation installation only. */
global $wpdb;
if ( 'bcrm_validation_' !== $wpdb->prefix || 'local' !== wp_get_environment_type() ) {
    throw new RuntimeException( 'Requires isolated local validation tables.' );
}
require dirname( __DIR__ ) . '/tools/public-catalog-guard.php';
$check = static function ( $ok, $name ) {
    if ( ! $ok ) { throw new RuntimeException( 'FAIL ' . $name ); }
    echo 'PASS ' . $name . "\n";
};
$product = new WC_Product_Simple();
$product->set_status( 'publish' );
$product->set_regular_price( '89.99' );
$product->set_price( '89.99' );
$check( ! $product->is_purchasable(), 'WooCommerce disallows purchase independently of the button' );
$check( ! apply_filters( 'woocommerce_add_to_cart_validation', true, 0, 1 ), 'Classic add-to-cart is rejected' );
$errors = new WP_Error();
do_action( 'woocommerce_after_checkout_validation', array(), $errors );
$check( $errors->has_errors(), 'Classic checkout is rejected before order creation' );
$check( array() === WC()->payment_gateways()->get_available_payment_gateways(), 'No payment gateway is available' );
wp_set_current_user( 0 );
$check( is_wp_error( apply_filters( 'rest_authentication_errors', null ) ), 'Anonymous REST access stays closed' );
$check( is_wp_error( apply_filters( 'rest_authentication_errors', true ) ), 'Earlier authentication success cannot expose REST to guests' );
$original_error = new WP_Error( 'bad_nonce' );
$check( $original_error === apply_filters( 'rest_authentication_errors', $original_error ), 'Existing authentication failures are preserved' );
$administrators = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
if ( ! $administrators ) { throw new RuntimeException( 'Administrator fixture missing' ); }
wp_set_current_user( $administrators[0] );
$check( null === apply_filters( 'rest_authentication_errors', null ), 'Authorized administration remains available' );
foreach ( array( '/wc/store/v1/checkout', '/wc/store/v1/cart/add-item' ) as $route ) {
    $request = new WP_REST_Request( 'POST', $route );
    $check( is_wp_error( apply_filters( 'rest_pre_dispatch', null, rest_get_server(), $request ) ), 'Store API write rejected even for admin: ' . $route );
}
$check( true === apply_filters( 'belmains_storefront_value', true, 'reviews_enabled' ), 'Published testimonials remain visible when enabled by the merchant' );
$check( false === apply_filters( 'belmains_storefront_value', false, 'reviews_enabled' ), 'Merchant can still hide testimonials' );
$check( false === wp_mail( 'nobody@example.invalid', 'catalog guard check', 'Must never be sent' ), 'Mail is suppressed' );
$check( 0 === (int) get_option( 'blog_public' ), 'Indexing stays disabled' );
$check( 0 === (int) get_option( 'users_can_register' ), 'Public account registration stays disabled' );
// Exercise the explicit mail-test exception without ever reaching a mail transport.
require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';
require_once ABSPATH . 'wp-admin/includes/screen.php';
$saved = array( $_GET, $_POST, $_SERVER['REQUEST_METHOD'] ?? null, $GLOBALS['current_screen'] ?? null, get_current_user_id() );
try {
    set_current_screen( 'dashboard' );
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_GET = array( 'page' => 'wp-mail-smtp-tools', 'tab' => 'test' );
    $_POST = array( 'wp-mail-smtp-post' => '1', '_wpnonce' => wp_create_nonce( 'wp-mail-smtp-test' ), 'wp-mail-smtp' => array( 'test' => array( 'email' => 'nobody@example.invalid' ) ) );
    $mail = array( 'to' => 'nobody@example.invalid', 'headers' => array( 'X-Mailer-Type:WPMailSMTP/Admin/Test', 'Content-Type: text/html' ), 'attachments' => array() );
    $check( null === belmains_catalog_mail_gate( null, $mail ), 'Explicit nonce-verified administrator SMTP test may reach transport' );
    $check( false === apply_filters( 'pre_wp_mail', null, $mail ), 'Isolated validation still blocks even an authorized SMTP test' );
    $check( false === belmains_catalog_mail_gate( false, $mail ), 'Previous mail suppression is never overridden' );
    $check( false === belmains_catalog_mail_gate( null, array_merge( $mail, array( 'headers' => array() ) ) ), 'Ordinary mail in the same request stays blocked' );
    $check( false === belmains_catalog_mail_gate( null, array_merge( $mail, array( 'to' => array( 'nobody@example.invalid', 'other@example.invalid' ) ) ) ), 'Additional recipients stay blocked' );
    $check( false === belmains_catalog_mail_gate( null, array_merge( $mail, array( 'to' => 'other@example.invalid' ) ) ), 'Recipient must match the submitted test form' );
    $check( false === belmains_catalog_mail_gate( null, array_merge( $mail, array( 'headers' => array( 'X-Mailer-Type:WPMailSMTP/Admin/Test', 'Bcc: other@example.invalid' ) ) ) ), 'Hidden copy recipients stay blocked' );
    $check( false === belmains_catalog_mail_gate( null, array_merge( $mail, array( 'headers' => array( "X-Mailer-Type:WPMailSMTP/Admin/Test\r\nCc: other@example.invalid" ) ) ) ), 'Injected header recipients stay blocked' );
    $check( false === belmains_catalog_mail_gate( null, array_merge( $mail, array( 'attachments' => array( '/private/example.txt' ) ) ) ), 'Attachments are excluded from the test exception' );
    $valid_nonce = $_POST['_wpnonce'];
    $_POST['_wpnonce'] = 'invalid';
    $check( false === belmains_catalog_mail_gate( null, $mail ), 'Invalid nonce cannot permit mail' );
    $_POST['_wpnonce'] = $valid_nonce;
    wp_set_current_user( 0 );
    $check( false === belmains_catalog_mail_gate( null, $mail ), 'Unauthenticated requests cannot permit mail' );
    wp_set_current_user( $saved[4] );
    $_GET['tab'] = 'settings';
    $check( false === belmains_catalog_mail_gate( null, $mail ), 'Other admin pages cannot permit mail' );
    $_GET['tab'] = 'test';
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $check( false === belmains_catalog_mail_gate( null, $mail ), 'Read-only requests cannot permit mail' );
} finally {
    list( $_GET, $_POST, $request_method, $GLOBALS['current_screen'], $user_id ) = $saved;
    if ( null === $request_method ) { unset( $_SERVER['REQUEST_METHOD'] ); } else { $_SERVER['REQUEST_METHOD'] = $request_method; }
    wp_set_current_user( $user_id );
}
echo "Catalog checks complete; no order or product persisted.\n";
