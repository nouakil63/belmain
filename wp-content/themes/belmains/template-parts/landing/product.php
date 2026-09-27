<?php
$product = bm_product();
$price   = bm_price_block();
$gallery = $product ? array_map( 'intval', array_values( $product->get_gallery_image_ids() ) ) : array();
$title   = bm_get( 'product_title' ) ?: ( $product ? $product->get_name() : 'Gant de massage Belmains' );
$intro   = bm_get( 'product_intro' );
$cta_url = bm_cart_url( $product );
?>
<section class="product" id="produit" aria-label="Fiche produit" data-bm-section="product">
	<div class="wrap pr-in">
		<div class="pr-gal">
			<?php bm_product_img( 'product_img_main', $product ? (int) $product->get_image_id() : null, 'photo main', 'large' ); ?>
			<div class="thumbs" data-r>
				<?php for ( $i = 2; $i <= 4; $i++ ) : ?>
					<?php bm_product_img( "product_img_$i", $gallery[ $i - 2 ] ?? null, 'photo', 'medium_large', bm_fields_flat()[ "product_img_$i" ]['placeholder'] ); ?>
				<?php endfor; ?>
			</div>
		</div>
		<div class="pr-info">
			<?php bm_text( 'product_eyebrow', 'p', 'eyebrow', 'data-r' ); ?>
			<h2 class="pr-title" data-r data-bm="product_title"><?php echo bm_fmt( $title ); ?></h2>
			<p class="proof" data-r><span class="stars" aria-hidden="true">★★★★★</span> <span data-bm="product_proof"><?php echo bm_fmt( bm_get( 'product_proof' ) ); ?></span></p>
			<p class="price" data-r>
				<b><?php echo esc_html( $price['price'] ); ?></b>
				<?php if ( $price['compare'] ) : ?><s><?php echo esc_html( $price['compare'] ); ?></s><?php endif; ?>
				<?php if ( $price['badge'] ) : ?><span class="badge"><?php echo esc_html( $price['badge'] ); ?></span><?php endif; ?>
			</p>
			<?php bm_text( 'product_intro', 'p', 'intro', 'data-r' ); ?>
			<ul class="checks" data-r>
				<?php foreach ( bm_lines( 'product_checks' ) as $line ) : ?><li><?php echo bm_fmt( $line ); ?></li><?php endforeach; ?>
			</ul>

			<?php if ( '1' === (string) bm_get( 'lots_show' ) ) :
				$lots = array_filter( array( bm_lot( 1 ), bm_lot( 2 ), bm_lot( 3 ) ) );
				if ( count( $lots ) > 1 ) : ?>
					<div class="lots" data-r>
						<?php bm_text( 'lots_title', 'h3' ); ?>
						<?php $first = true; foreach ( $lots as $lot ) : ?>
							<button class="lot" type="button" aria-pressed="<?php echo $first ? 'true' : 'false'; ?>" data-url="<?php echo esc_url( $lot['url'] ); ?>">
								<span class="rad" aria-hidden="true"></span>
								<span><?php echo esc_html( $lot['label'] ); ?><?php if ( $lot['tag'] ) : ?> <em><?php echo esc_html( $lot['tag'] ); ?></em><?php endif; ?></span>
								<small><?php echo esc_html( $lot['saving'] ); ?></small>
								<b><?php echo wp_kses_post( $lot['price'] ); ?></b>
							</button>
						<?php $first = false; endforeach; ?>
					</div>
				<?php endif;
			endif; ?>

			<a class="btn btn-red big" id="bmBuy" href="<?php echo esc_url( $cta_url ); ?>" data-r <?php echo ( ! $product || ! $product->is_in_stock() ) ? 'aria-disabled="true"' : ''; ?>>
				<span data-bm="product_cta_text"><?php echo bm_fmt( bm_get( 'product_cta_text' ) ); ?></span> <span class="fl">→</span>
			</a>
			<?php if ( $product && ! $product->is_in_stock() ) : ?><p class="pay">Rupture de stock — réassort en cours</p><?php endif; ?>
			<?php bm_text( 'pay_text', 'p', 'pay', 'data-r' ); ?>

			<div class="reas" data-r>
				<?php for ( $i = 1; $i <= 3; $i++ ) : if ( '' === bm_get( "reas{$i}_title" ) ) { continue; } ?>
					<div><?php bm_text( "reas{$i}_title", 'b' ); ?><?php bm_text( "reas{$i}_text", 'span' ); ?></div>
				<?php endfor; ?>
			</div>

			<div class="tabs" data-r>
				<?php for ( $i = 1; $i <= 3; $i++ ) : if ( '' === bm_get( "tab{$i}_title" ) ) { continue; } ?>
					<details>
						<summary data-bm="tab<?php echo $i; ?>_title"><?php echo bm_fmt( bm_get( "tab{$i}_title" ) ); ?></summary>
						<div><?php echo bm_paragraphs( "tab{$i}_content" ); ?></div>
					</details>
				<?php endfor; ?>
			</div>
		</div>
	</div>
</section>
