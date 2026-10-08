<?php if ( ! defined( 'ABSPATH' ) ) { exit; } ?>
<?php if ( ! is_front_page() ) : ?>
<footer class="belmains-page-footer">© <?php echo esc_html( wp_date( 'Y' ) . ' ' . belmains_value( 'brand_name', 'Belmains' ) ); ?></footer>
<?php endif; ?>
<?php wp_footer(); ?>
</body>
</html>
