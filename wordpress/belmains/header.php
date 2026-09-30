<?php if ( ! defined( 'ABSPATH' ) ) { exit; } ?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="theme-color" content="#63182e">
<?php if ( ! has_site_icon() ) : ?>
<link rel="icon" href="<?php echo esc_url( get_theme_file_uri( 'assets/belmains-favicon.png' ) ); ?>">
<?php endif; ?>
<?php wp_head(); ?>
</head>
<body id="accueil" <?php body_class(); ?>>
<?php wp_body_open(); ?>
<?php if ( ! is_front_page() ) : ?>
<a class="skip-link" href="#main">Aller au contenu</a>
<header class="belmains-page-header">
 <a href="<?php echo esc_url( home_url( '/' ) ); ?>"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/belmains-logo.png' ) ); ?>" alt="Belmains — accueil" width="719" height="183"></a>
 <?php if ( function_exists( 'wc_get_cart_url' ) ) : ?>
 <nav class="belmains-commerce-nav" aria-label="Navigation de la boutique">
  <a href="<?php echo esc_url( home_url( '/' ) ); ?>">Accueil</a>
  <a href="<?php echo esc_url( wc_get_cart_url() ); ?>"<?php echo is_cart() ? ' aria-current="page"' : ''; ?>>Panier</a>
  <?php $belmains_tracking_page_id = absint( get_option( 'belmains_tracking_page_id', 0 ) ); ?>
  <?php if ( $belmains_tracking_page_id && 'publish' === get_post_status( $belmains_tracking_page_id ) ) : ?>
  <a href="<?php echo esc_url( get_permalink( $belmains_tracking_page_id ) ); ?>"<?php echo is_page( $belmains_tracking_page_id ) ? ' aria-current="page"' : ''; ?>>Suivre ma commande</a>
  <?php endif; ?>
 </nav>
 <?php else : ?>
 <a href="<?php echo esc_url( home_url( '/#fiche-produit' ) ); ?>">Découvrir le gant Belmains</a>
 <?php endif; ?>
</header>
<?php endif; ?>
