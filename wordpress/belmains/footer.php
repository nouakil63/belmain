<?php if ( ! defined( 'ABSPATH' ) ) { exit; } ?>
<?php if ( ! is_front_page() ) : ?>
<footer class="belmains-page-footer">© <?php echo esc_html( wp_date( 'Y' ) ); ?> Belmains · Le bien-être entre vos mains</footer>
<?php endif; ?>
<?php wp_footer(); ?>
</body>
</html>
