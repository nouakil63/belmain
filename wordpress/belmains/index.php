<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
get_header();
?>
<main id="main" class="belmains-page-content">
<?php if ( have_posts() ) : ?>
 <?php if ( ! is_singular() ) : ?><h1><?php echo esc_html( get_bloginfo( 'name' ) ); ?></h1><?php endif; ?>
 <?php while ( have_posts() ) : the_post(); ?>
 <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
 <?php if ( is_singular() ) : ?><h1><?php the_title(); ?></h1><?php the_content(); wp_link_pages(); ?>
 <?php else : ?><h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2><?php the_excerpt(); ?><?php endif; ?>
 </article>
 <?php endwhile; the_posts_pagination(); ?>
<?php else : ?><h1>Page introuvable</h1><p>Cette page n’est pas disponible.</p><a href="<?php echo esc_url( home_url( '/' ) ); ?>">Revenir à l’accueil</a><?php endif; ?>
</main>
<?php get_footer(); ?>
