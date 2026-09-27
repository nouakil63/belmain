<section class="testi" id="avis" aria-label="Avis clients" data-bm-section="reviews">
	<div class="wrap">
		<?php bm_text( 'reviews_eyebrow', 'p', 'eyebrow mid', 'data-r' ); ?>
		<?php bm_text( 'reviews_title', 'h2', '', 'data-r' ); ?>
		<?php bm_text( 'reviews_intro', 'p', 'intro center', 'data-r style="margin-inline:auto"' ); ?>
		<div class="t-cards">
			<?php for ( $i = 1; $i <= 3; $i++ ) :
				$quote = trim( (string) bm_get( "review{$i}_quote" ) ); ?>
				<article class="t-card d<?php echo $i - 1; ?>" data-r>
					<span class="stars" aria-hidden="true">★★★★★</span>
					<?php if ( '' === $quote ) : ?>
						<span class="todo">Avis client réel à insérer — ne pas publier de témoignage inventé</span>
						<blockquote>«&nbsp;…&nbsp;»</blockquote>
					<?php else : ?>
						<blockquote>«&nbsp;<?php echo bm_fmt( $quote ); ?>&nbsp;»</blockquote>
					<?php endif; ?>
					<footer><?php bm_text( "review{$i}_name", 'b' ); ?><?php bm_text( "review{$i}_city", 'span' ); ?></footer>
				</article>
			<?php endfor; ?>
		</div>
	</div>
</section>
