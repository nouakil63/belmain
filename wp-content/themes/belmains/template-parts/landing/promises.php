<section class="promises" aria-label="Nos promesses" data-bm-section="promises">
	<div class="wrap">
		<?php bm_text( 'promises_eyebrow', 'p', 'eyebrow mid', 'data-r' ); ?>
		<?php bm_text( 'promises_title', 'h2', '', 'data-r' ); ?>
		<div class="cards">
			<?php for ( $i = 1; $i <= 4; $i++ ) : if ( '' === bm_get( "promise{$i}_title" ) ) { continue; } ?>
				<div class="card d<?php echo $i - 1; ?>" data-r>
					<i aria-hidden="true" data-bm="promise<?php echo $i; ?>_icon"><?php echo esc_html( bm_get( "promise{$i}_icon" ) ); ?></i>
					<?php bm_text( "promise{$i}_title", 'h3' ); ?>
					<?php bm_text( "promise{$i}_text", 'p' ); ?>
				</div>
			<?php endfor; ?>
		</div>
	</div>
</section>
