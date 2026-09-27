<section class="why" id="pourquoi" aria-label="Pourquoi" data-bm-section="why">
	<div class="wrap">
		<div class="why-grid">
			<?php bm_img( 'why_image', 'photo', 'large', null, 'data-r' ); ?>
			<div class="why-txt">
				<?php bm_text( 'why_eyebrow', 'p', 'eyebrow', 'data-r' ); ?>
				<?php bm_text( 'why_title', 'h2', '', 'data-r' ); ?>
				<?php bm_text( 'why_intro', 'p', 'intro', 'data-r' ); ?>
				<ul class="pains" data-r>
					<?php foreach ( bm_lines( 'why_pains' ) as $line ) : ?><li><?php echo bm_fmt( $line ); ?></li><?php endforeach; ?>
				</ul>
				<a class="btn btn-red" href="<?php echo esc_url( bm_link( 'why_cta_link' ) ); ?>" data-r data-bm="why_cta_text"><?php echo bm_fmt( bm_get( 'why_cta_text' ) ); ?></a>
			</div>
		</div>
		<div class="stats" data-r>
			<?php for ( $i = 1; $i <= 4; $i++ ) : if ( '' === bm_get( "stat{$i}_num" ) ) { continue; } ?>
				<div class="stat">
					<b><span data-count="<?php echo esc_attr( preg_replace( '/[^\d]/', '', bm_get( "stat{$i}_num" ) ) ); ?>" data-bm="stat<?php echo $i; ?>_num"><?php echo esc_html( bm_get( "stat{$i}_num" ) ); ?></span><i data-bm="stat<?php echo $i; ?>_suffix"><?php echo esc_html( bm_get( "stat{$i}_suffix" ) ); ?></i></b>
					<span data-bm="stat<?php echo $i; ?>_label"><?php echo esc_html( bm_get( "stat{$i}_label" ) ); ?></span>
				</div>
			<?php endfor; ?>
		</div>
	</div>
</section>
