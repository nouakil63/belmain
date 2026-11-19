<?php if ( ! defined( 'ABSPATH' ) ) { exit; } ?>
<?php if ( ! is_front_page() ) : ?>
<footer class="belmains-page-footer">
 <nav class="belmains-footer-nav" aria-label="Navigation de bas de page">
  <a href="<?php echo esc_url( home_url( '/' ) ); ?>">Accueil</a>
  <?php $belmains_footer_gallery_url = belmains_gallery_url(); ?>
  <?php if ( $belmains_footer_gallery_url ) : ?><a href="<?php echo esc_url( $belmains_footer_gallery_url ); ?>"<?php echo belmains_is_gallery() ? ' aria-current="page"' : ''; ?>>Galerie</a><?php endif; ?>
  <a href="<?php echo esc_url( belmains_contact_url() ); ?>">Contact</a>
 </nav>
 <p>© <?php echo esc_html( wp_date( 'Y' ) . ' ' . belmains_value( 'brand_name', 'Belmains' ) ); ?></p>
</footer>
<?php endif; ?>
<?php wp_footer(); ?>
</body>
</html>
