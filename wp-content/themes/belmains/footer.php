<footer class="foot">
	<div class="wrap">
		<div class="foot-in">
			<div>
				<p class="brand"><span class="b1"><?php echo esc_html( bm_get( 'brand_part1' ) ); ?></span><span><?php echo esc_html( bm_get( 'brand_part2' ) ); ?></span></p>
				<?php bm_text( 'footer_tagline', 'p' ); ?>
			</div>
			<div>
				<?php bm_text( 'footer_col2_title', 'h4' ); ?>
				<p><?php foreach ( bm_links( 'footer_col2_links' ) as $l ) : ?><a href="<?php echo esc_url( bm_resolve_url( $l['url'] ) ); ?>"><?php echo esc_html( $l['label'] ); ?></a><br><?php endforeach; ?></p>
			</div>
			<div>
				<?php bm_text( 'footer_col3_title', 'h4' ); ?>
				<p><?php foreach ( bm_links( 'footer_col3_links' ) as $l ) : ?><a href="<?php echo esc_url( bm_resolve_url( $l['url'] ) ); ?>"><?php echo esc_html( $l['label'] ); ?></a><br><?php endforeach; ?></p>
			</div>
			<div>
				<?php bm_text( 'footer_nl_title', 'h4' ); ?>
				<?php bm_text( 'footer_nl_text', 'p' ); ?>
				<?php if ( bm_get( 'footer_nl_action' ) ) : ?>
					<form class="nl" action="<?php echo esc_url( bm_get( 'footer_nl_action' ) ); ?>" method="post" target="_blank">
						<input type="email" name="EMAIL" placeholder="votre@email.fr" required aria-label="Votre e-mail">
						<button type="submit"><b>S'inscrire</b></button>
					</form>
				<?php elseif ( is_customize_preview() ) : ?>
					<div class="nl"><span>votre@email.fr</span><b>S'inscrire</b></div>
				<?php endif; ?>
			</div>
		</div>
		<div class="foot-bot">
			<?php bm_text( 'footer_copyright', 'span' ); ?>
			<span><?php $legal = bm_links( 'footer_legal' ); foreach ( $legal as $i => $l ) : ?><?php echo $i ? ' · ' : ''; ?><a href="<?php echo esc_url( bm_resolve_url( $l['url'] ) ); ?>"><?php echo esc_html( $l['label'] ); ?></a><?php endforeach; ?></span>
		</div>
	</div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
