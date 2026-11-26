<?php if ( ! defined( 'ABSPATH' ) ) { exit; } ?>
<?php if ( ! is_front_page() ) : ?>
<footer class="belmains-page-footer">
 <nav class="belmains-footer-nav" aria-label="Navigation de bas de page">
  <a href="<?php echo esc_url( home_url( '/' ) ); ?>">Accueil</a>
  <?php $belmains_footer_gallery_url = belmains_gallery_url(); ?>
  <?php if ( $belmains_footer_gallery_url ) : ?><a href="<?php echo esc_url( $belmains_footer_gallery_url ); ?>"<?php echo belmains_is_gallery() ? ' aria-current="page"' : ''; ?>>Galerie</a><?php endif; ?>
  <a href="<?php echo esc_url( belmains_contact_url() ); ?>">Contact</a>
 </nav>
 <?php $belmains_footer_legal = belmains_legal_links(); ?>
 <?php if ( $belmains_footer_legal ) : ?>
 <nav class="belmains-footer-nav belmains-footer-legal" aria-label="Informations légales">
  <?php foreach ( $belmains_footer_legal as $belmains_footer_link ) : ?><a href="<?php echo esc_url( $belmains_footer_link[0] ); ?>"><?php echo esc_html( $belmains_footer_link[1] ); ?></a><?php endforeach; ?>
 </nav>
 <?php endif; ?>
 <p>© <?php echo esc_html( wp_date( 'Y' ) . ' ' . belmains_value( 'brand_name', 'Belmains' ) ); ?></p>
</footer>
<?php endif; ?>
<?php wp_footer(); ?>
</body>
</html>
