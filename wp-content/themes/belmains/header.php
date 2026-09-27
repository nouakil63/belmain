<!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<?php bm_text( 'annbar_text', 'div', 'annbar' ); ?>

<header class="hdr" id="hdr">
	<div class="wrap hdr-in">
		<nav class="menu" aria-label="Navigation principale">
			<?php if ( has_nav_menu( 'primary' ) ) :
				wp_nav_menu( array( 'theme_location' => 'primary', 'container' => false, 'items_wrap' => '%3$s', 'depth' => 1, 'walker' => new BM_Menu_Walker() ) );
			else :
				for ( $i = 1; $i <= 4; $i++ ) :
					if ( '' === bm_get( "menu_{$i}_label" ) ) { continue; } ?>
					<a href="<?php echo esc_url( bm_link( "menu_{$i}_link" ) ); ?>" data-bm="menu_<?php echo $i; ?>_label"><?php echo bm_fmt( bm_get( "menu_{$i}_label" ) ); ?></a>
				<?php endfor;
			endif; ?>
		</nav>
		<a class="brand" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="<?php echo esc_attr( bm_get( 'brand_part1' ) . bm_get( 'brand_part2' ) ); ?> — accueil"><span class="b1" data-bm="brand_part1"><?php echo esc_html( bm_get( 'brand_part1' ) ); ?></span><span data-bm="brand_part2"><?php echo esc_html( bm_get( 'brand_part2' ) ); ?></span></a>
		<div class="acts">
			<?php bm_text( 'header_locale', 'span' ); ?>
			<?php if ( bm_wc() ) : ?>
				<a class="cart" href="<?php echo esc_url( wc_get_cart_url() ); ?>"><span data-bm="cart_label"><?php echo esc_html( bm_get( 'cart_label' ) ); ?></span>&nbsp;(<span class="bm-cart-count"><?php echo WC()->cart ? (int) WC()->cart->get_cart_contents_count() : 0; ?></span>)</a>
			<?php endif; ?>
		</div>
	</div>
</header>
