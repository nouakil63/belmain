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
 <a href="<?php echo esc_url( home_url( '/#fiche-produit' ) ); ?>">Découvrir le gant Belmains</a>
</header>
<?php endif; ?>
