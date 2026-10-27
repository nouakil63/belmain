<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
get_header();
$belmains_is_commerce_page = function_exists( 'is_woocommerce' ) && (
    is_cart() || is_checkout() || is_account_page() ||
    ( absint( get_option( 'belmains_tracking_page_id', 0 ) ) && is_page( absint( get_option( 'belmains_tracking_page_id', 0 ) ) ) ) ||
    has_shortcode( (string) get_post_field( 'post_content', get_queried_object_id() ), 'woocommerce_order_tracking' )
);
?>
<main id="main" class="belmains-page-content<?php echo $belmains_is_commerce_page ? ' belmains-commerce-page' : ''; ?>">
<?php while ( have_posts() ) : the_post(); ?>
 <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
 <h1><?php the_title(); ?></h1>
 <?php the_content(); wp_link_pages(); ?>
 </article>
<?php endwhile; ?>
</main>
<?php get_footer(); ?>
