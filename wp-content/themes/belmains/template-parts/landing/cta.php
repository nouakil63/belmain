<section class="cta-final" aria-label="Commander" data-bm-section="cta">
	<div class="wrap">
		<?php bm_text( 'cta_title', 'h2', '', 'data-r' ); ?>
		<?php bm_text( 'cta_text', 'p', '', 'data-r' ); ?>
		<a class="btn big" style="width:auto" href="<?php echo esc_url( bm_link( 'cta_button_link' ) ); ?>" data-r><span data-bm="cta_button_text"><?php echo bm_fmt( bm_get( 'cta_button_text' ) ); ?></span> <span class="fl">→</span></a>
	</div>
</section>
