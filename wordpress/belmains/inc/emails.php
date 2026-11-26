<?php
/**
 * Product pictures in order e-mails. Mail clients paint a transparent picture on black, so a cut-out
 * product photo is sent as an opaque copy on the site's ivory; when no copy can be made, no picture is sent.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Address of an opaque square JPEG copy of an attachment, made once and kept with the uploads. '' on failure. */
function belmains_email_picture_url( $attachment_id, $size = 192 ) {
    $source = get_attached_file( $attachment_id );
    $uploads = wp_get_upload_dir();
    if ( ! $source || ! is_readable( $source ) || ! empty( $uploads['error'] ) ) { return ''; }
    $name = 'belmains-email/' . (int) $attachment_id . '-' . (int) $size . '-' . substr( md5( $source . '|' . filemtime( $source ) . '|' . filesize( $source ) ), 0, 12 ) . '.jpg';
    $path = trailingslashit( $uploads['basedir'] ) . $name;
    if ( is_file( $path ) && filesize( $path ) > 0 ) { return trailingslashit( $uploads['baseurl'] ) . $name; }
    if ( ! function_exists( 'imagecreatefromstring' ) || ! function_exists( 'imagejpeg' ) ) { return ''; }
    $bytes = file_get_contents( $source );
    $image = false === $bytes ? false : @imagecreatefromstring( $bytes );
    if ( ! $image ) { return ''; }
    $width = imagesx( $image ); $height = imagesy( $image );
    if ( $width < 1 || $height < 1 ) { return ''; }
    $canvas = imagecreatetruecolor( $size, $size );
    imagefill( $canvas, 0, 0, imagecolorallocate( $canvas, 246, 241, 235 ) );
    $scale = min( $size / $width, $size / $height );
    $w = max( 1, (int) round( $width * $scale ) ); $h = max( 1, (int) round( $height * $scale ) );
    imagealphablending( $canvas, true );
    imagecopyresampled( $canvas, $image, (int) floor( ( $size - $w ) / 2 ), (int) floor( ( $size - $h ) / 2 ), 0, 0, $w, $h, $width, $height );
    if ( ! wp_mkdir_p( dirname( $path ) ) ) { return ''; }
    $temp = $path . '.' . wp_generate_password( 8, false ) . '.tmp';
    if ( ! imagejpeg( $canvas, $temp, 88 ) || ! rename( $temp, $path ) ) { @unlink( $temp ); return ''; }
    return trailingslashit( $uploads['baseurl'] ) . $name;
}

add_filter( 'woocommerce_order_item_thumbnail', function ( $image, $item ) {
    $product = is_object( $item ) && is_callable( array( $item, 'get_product' ) ) ? $item->get_product() : null;
    $id = $product ? (int) $product->get_image_id() : 0;
    if ( ! $id ) { return $image; }
    // A JPEG has no transparency: WooCommerce's own picture is safe as it is.
    if ( 'image/jpeg' === get_post_mime_type( $id ) ) { return $image; }
    $url = belmains_email_picture_url( $id );
    return $url ? '<img src="' . esc_url( $url ) . '" alt="" width="48" height="48" style="display:block;width:48px;height:48px;border:0;border-radius:8px;">' : '';
}, 20, 2 );
