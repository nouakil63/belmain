<?php
/**
 * Template Name: Galerie Belmains
 * Template Post Type: page
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
get_header();
$videos = array(
    array( 'file' => 'belmains-decouverte', 'title' => 'Le gant en action', 'description' => 'Découvrez le gant Belmains de près, porté sur la main, avec un aperçu de ses commandes.' ),
    array( 'file' => 'belmains-quotidien', 'title' => 'Pour les mains qui font tant', 'description' => 'Des gestes du quotidien à la pause sur le canapé : découvrez Belmains, pour soi ou à offrir.' ),
    array( 'file' => 'belmains-rituel', 'title' => 'Une attention pour sa maman', 'description' => 'Une idée cadeau pour remercier sa maman et lui offrir un moment de détente.' ),
);
?>
<main id="main" class="belmains-gallery">
 <div class="belmains-gallery-intro">
  <h1>Belmains en mouvement</h1>
  <p>Découvrez le gant Belmains en action et des idées pour l’offrir à ceux que vous aimez.</p>
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
