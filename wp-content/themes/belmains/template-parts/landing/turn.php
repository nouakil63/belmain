<?php $frames = bm_turn_frames(); ?>
<section class="why turn-wrap" aria-label="Vue 360°" data-bm-section="turn">
	<div class="turn" id="turn" data-frames="<?php echo esc_attr( wp_json_encode( $frames ) ); ?>">
		<div class="turn-sticky">
			<?php bm_text( 'turn_eyebrow', 'p', 'eyebrow mid' ); ?>
			<?php bm_text( 'turn_title', 'h2', 'center' ); ?>
			<div class="turn-stage">
				<?php if ( $frames ) : ?>
					<div class="photo has-img"><img src="<?php echo esc_url( $frames[0] ); ?>" alt="Vue du produit" id="turnImg" loading="lazy"></div>
				<?php else : ?>
					<div class="photo" role="img" aria-label="Emplacement de la séquence 360 degrés : douze photos du produit à fournir">
						<span class="cam" aria-hidden="true">✦</span>
						<b>Séquence 360° — photo <span id="turnNum">1</span>&nbsp;/&nbsp;12</b>
						12 vues du gant à fournir<br>(une photo tous les 30°, fond neutre)
					</div>
				<?php endif; ?>
				<div class="dial" aria-hidden="true">
					<svg viewBox="0 0 88 88">
						<circle cx="44" cy="44" r="41" fill="none" stroke="rgba(212,176,106,.35)" stroke-width="1"/>
						<g id="needle" style="transform-origin:44px 44px">
							<line x1="44" y1="17" x2="44" y2="7" stroke="#d4b06a" stroke-width="2"/>
							<circle cx="44" cy="7" r="3" fill="#a8121f"/>
						</g>
					</svg>
					<b id="turnDeg">0°</b>
					<small>ROTATION</small>
				</div>
			</div>
			<p class="turn-frame">Vue <b id="turnLbl">face — 0°</b></p>
			<p class="turn-hint"><i>↓</i> <span data-bm="turn_hint"><?php echo bm_fmt( bm_get( 'turn_hint' ) ); ?></span></p>
		</div>
	</div>
</section>
