<?php
/** Isolated validation of the opaque product picture sent in order e-mails. Run through WP-CLI eval-file only. */
global $wpdb;
if ( 'bcrm_validation_' !== $wpdb->prefix || 'local' !== wp_get_environment_type() ) {
    throw new RuntimeException( 'E-mail picture tests require the isolated local validation tables.' );
}
if ( ! function_exists( 'belmains_email_picture_url' ) || ! class_exists( 'WooCommerce' ) ) { throw new RuntimeException( 'The Belmains theme and WooCommerce must be active.' ); }
if ( ! function_exists( 'imagecreatetruecolor' ) || ! function_exists( 'imagepng' ) ) { throw new RuntimeException( 'These tests need the GD extension.' ); }
require_once ABSPATH . 'wp-admin/includes/image.php'; require_once ABSPATH . 'wp-admin/includes/file.php'; require_once ABSPATH . 'wp-admin/includes/media.php';

$checks = 0;
$check = static function ( $condition, $label ) use ( &$checks ) {
    if ( ! $condition ) { throw new RuntimeException( 'FAIL: ' . $label ); }
    ++$checks; echo 'PASS ' . $label . "\n";
};
$attachments = array(); $products = array(); $orders = array(); $made = array();
$upload = static function ( $name, callable $draw ) use ( &$attachments ) {
    $tmp = wp_tempnam( $name ); $draw( $tmp );
    $id = media_handle_sideload( array( 'name' => $name, 'tmp_name' => $tmp ), 0 );
    if ( is_wp_error( $id ) ) { throw new RuntimeException( $id->get_error_message() ); }
    $attachments[] = $id; return $id;
};

try {
    // A cut-out product: a grey disc on a fully transparent square.
    $cutout = $upload( 'gant-detoure-validation.png', static function ( $path ) {
        $image = imagecreatetruecolor( 400, 300 ); imagealphablending( $image, false ); imagesavealpha( $image, true );
        imagefill( $image, 0, 0, imagecolorallocatealpha( $image, 0, 0, 0, 127 ) );
        imagefilledellipse( $image, 200, 150, 160, 160, imagecolorallocate( $image, 120, 120, 120 ) );
        imagepng( $image, $path );
    } );
    $url = belmains_email_picture_url( $cutout );
    $uploads = wp_get_upload_dir();
    $path = $url ? str_replace( trailingslashit( $uploads['baseurl'] ), trailingslashit( $uploads['basedir'] ), $url ) : '';
    if ( $path ) { $made[] = $path; }
    $info = $path && is_file( $path ) ? getimagesize( $path ) : false;
    $check( $info && IMAGETYPE_JPEG === $info[2] && 192 === $info[0] && 192 === $info[1] && 0 === strpos( $url, trailingslashit( $uploads['baseurl'] ) . 'belmains-email/' ), 'a transparent picture gets a square JPEG copy kept with the uploads' );
    $copy = imagecreatefromjpeg( $path );
    $corner = imagecolorsforindex( $copy, imagecolorat( $copy, 2, 2 ) ); $centre = imagecolorsforindex( $copy, imagecolorat( $copy, 96, 96 ) );
    $check( abs( $corner['red'] - 246 ) < 8 && abs( $corner['green'] - 241 ) < 8 && abs( $corner['blue'] - 235 ) < 8, 'what was transparent is the site\'s ivory, not black' );
    $check( abs( $centre['red'] - 120 ) < 12 && abs( $centre['green'] - 120 ) < 12, 'the product itself is kept, centred' );
    $check( $url === belmains_email_picture_url( $cutout ) && 1 === count( glob( dirname( $path ) . '/' . $cutout . '-*.jpg' ) ), 'the copy is made once and reused' );

    $product = new WC_Product_Simple(); $product->set_name( 'Gant · validation photo e-mail' ); $product->set_regular_price( '89.99' ); $product->set_status( 'publish' ); $product->set_image_id( $cutout ); $product->save(); $products[] = $product->get_id();
    $order = wc_create_order(); $order->add_product( $product, 1 ); $order->save(); $orders[] = $order->get_id();
    $items = array_values( $order->get_items() );
    $original = $product->get_image( array( 48, 48 ) );
    $sent = apply_filters( 'woocommerce_order_item_thumbnail', $original, $items[0] );
    $check( false === strpos( $sent, '.png' ) && false !== strpos( wp_kses_post( $sent ), 'src="' . esc_url( $url ) . '"' ), 'the order e-mail shows the opaque copy, and it survives WooCommerce\'s own filtering' );

    $photo = $upload( 'gant-photo-validation.jpg', static function ( $path ) { $image = imagecreatetruecolor( 300, 300 ); imagefill( $image, 0, 0, imagecolorallocate( $image, 200, 180, 160 ) ); imagejpeg( $image, $path, 90 ); } );
    $product->set_image_id( $photo ); $product->save();
    $plain = $product->get_image( array( 48, 48 ) );
    $check( $plain === apply_filters( 'woocommerce_order_item_thumbnail', $plain, array_values( wc_get_order( $order->get_id() )->get_items() )[0] ), 'a JPEG photo has no transparency and is sent as WooCommerce prepared it' );

    $broken = $upload( 'image-illisible-validation.png', static function ( $path ) { $image = imagecreatetruecolor( 10, 10 ); imagepng( $image, $path ); } );
    file_put_contents( get_attached_file( $broken ), 'not an image' );
    $product->set_image_id( $broken ); $product->save();
    $check( '' === belmains_email_picture_url( $broken ) && '' === apply_filters( 'woocommerce_order_item_thumbnail', '<img src="x.png">', array_values( wc_get_order( $order->get_id() )->get_items() )[0] ), 'when no opaque copy can be made, no picture is sent rather than a broken one' );
    echo $checks . " e-mail picture checks passed.\n";
} finally {
    foreach ( $orders as $id ) { $o = wc_get_order( $id ); if ( $o ) { $o->delete( true ); } }
    foreach ( $products as $id ) { $p = wc_get_product( $id ); if ( $p ) { $p->delete( true ); } }
    foreach ( $made as $file ) { @unlink( $file ); }
    foreach ( $attachments as $id ) { foreach ( glob( trailingslashit( wp_get_upload_dir()['basedir'] ) . 'belmains-email/' . $id . '-*.jpg' ) ?: array() as $file ) { @unlink( $file ); } wp_delete_attachment( $id, true ); }
}
