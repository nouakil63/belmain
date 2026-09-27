<section class="faq" id="faq" aria-label="Questions fréquentes" data-bm-section="faq">
	<div class="wrap faq-in">
		<div class="faq-head">
			<?php bm_text( 'faq_eyebrow', 'p', 'eyebrow', 'data-r' ); ?>
			<?php bm_text( 'faq_title', 'h2', '', 'data-r' ); ?>
			<?php bm_text( 'faq_intro', 'p', 'intro', 'data-r' ); ?>
			<a class="btn btn-ghost" href="<?php echo esc_url( bm_link( 'faq_cta_link' ) ); ?>" data-r data-bm="faq_cta_text"><?php echo bm_fmt( bm_get( 'faq_cta_text' ) ); ?></a>
		</div>
		<div class="faq-list" data-r>
			<?php for ( $i = 1; $i <= 6; $i++ ) : if ( '' === trim( (string) bm_get( "faq{$i}_q" ) ) ) { continue; } ?>
				<details>
					<summary><?php echo bm_fmt( bm_get( "faq{$i}_q" ) ); ?></summary>
					<div><?php echo bm_paragraphs( "faq{$i}_a" ); ?></div>
				</details>
			<?php endfor; ?>
		</div>
	</div>
</section>
