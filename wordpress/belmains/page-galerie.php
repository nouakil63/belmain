<?php
/**
 * Template Name: Galerie Belmains
 * Template Post Type: page
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
get_header();
$videos = array(
    array( 'file' => 'belmains-decouverte', 'title' => 'Découvrir Belmains', 'description' => 'Le gant présenté en images.' ),
    array( 'file' => 'belmains-quotidien', 'title' => 'Belmains au quotidien', 'description' => 'Un autre regard sur le gant Belmains.' ),
    array( 'file' => 'belmains-rituel', 'title' => 'Le rituel Belmains', 'description' => 'Un aperçu du gant et de son utilisation.' ),
);
?>
<main id="main" class="belmains-gallery">
 <div class="belmains-gallery-intro">
  <h1>Belmains en mouvement</h1>
  <p>Découvrez le gant Belmains à travers une sélection de nos vidéos.</p>
 </div>
 <section class="belmains-video-grid" aria-label="Les vidéos Belmains">
 <?php foreach ( $videos as $index => $video ) : ?>
  <figure class="belmains-video">
   <video class="belmains-gallery-video" controls playsinline preload="none" width="540" height="960"
    poster="<?php echo esc_url( get_theme_file_uri( 'assets/gallery/' . $video['file'] . '.webp' ) ); ?>"
    aria-label="<?php echo esc_attr( $video['title'] ); ?>" aria-describedby="belmains-video-description-<?php echo esc_attr( $index ); ?>">
    <source src="<?php echo esc_url( get_theme_file_uri( 'assets/gallery/' . $video['file'] . '.mp4' ) ); ?>" type="video/mp4">
    <p>Votre navigateur ne peut pas lire cette vidéo. <a href="<?php echo esc_url( get_theme_file_uri( 'assets/gallery/' . $video['file'] . '.mp4' ) ); ?>">Ouvrir la vidéo</a>.</p>
   </video>
   <figcaption>
    <h2><?php echo esc_html( $video['title'] ); ?></h2>
    <p id="belmains-video-description-<?php echo esc_attr( $index ); ?>"><?php echo esc_html( $video['description'] ); ?></p>
   </figcaption>
  </figure>
 <?php endforeach; ?>
 </section>
 <div class="belmains-gallery-return">
  <a href="<?php echo esc_url( home_url( '/#fiche-produit' ) ); ?>">Découvrir le gant Belmains</a>
 </div>
</main>
<?php get_footer(); ?>
