<section class="hero" aria-label="Présentation" data-bm-section="hero">
	<div class="wrap hero-in">
		<div class="hero-txt">
			<?php bm_text( 'hero_eyebrow', 'p', 'eyebrow' ); ?>
			<?php bm_hero_title( 'hero_title' ); ?>
			<?php bm_text( 'hero_intro', 'p', 'intro' ); ?>
			<div class="hero-cta">
				<a class="btn btn-red" href="<?php echo esc_url( bm_link( 'hero_cta1_link' ) ); ?>"><span data-bm="hero_cta1_text"><?php echo bm_fmt( bm_get( 'hero_cta1_text' ) ); ?></span> <span class="fl">→</span></a>
				<?php if ( bm_get( 'hero_cta2_text' ) || is_customize_preview() ) : ?>
					<a class="btn btn-ghost" href="<?php echo esc_url( bm_link( 'hero_cta2_link' ) ); ?>" data-bm="hero_cta2_text"><?php echo bm_fmt( bm_get( 'hero_cta2_text' ) ); ?></a>
				<?php endif; ?>
			</div>
			<p class="proof"><span class="stars" aria-hidden="true">★★★★★</span> <span data-bm="hero_proof"><?php echo bm_fmt( bm_get( 'hero_proof' ) ); ?></span></p>
		</div>
		<div class="hero-media">
			<div class="seal" aria-hidden="true">
				<svg viewBox="0 0 128 128">
					<defs><path id="circ" d="M64,14 a50,50 0 1,1 -0.01,0"/></defs>
					<text><textPath href="#circ"><?php echo esc_html( bm_get( 'hero_seal_text' ) ); ?></textPath></text>
					<text class="heart" x="64" y="72" text-anchor="middle">♥</text>
				</svg>
			</div>
			<?php bm_img( 'hero_image', 'photo', 'large', null, 'id="heroPhoto"' ); ?>
			<div class="chip c1"><?php bm_text( 'hero_chip1_small', 'small' ); ?><?php bm_text( 'hero_chip1_text', 'b' ); ?></div>
			<div class="chip c2"><?php bm_text( 'hero_chip2_small', 'small' ); ?><?php bm_text( 'hero_chip2_text', 'b' ); ?></div>
		</div>
	</div>
</section>
