<?php
/**
 * Plugin Name: Belmains — Boutique ouverte
 * Description: Remplace la vitrine d'avant ouverture une fois les ventes ouvertes par tools/open-shop.php. Ne restreint rien.
 */
defined( 'ABSPATH' ) || exit;

// The deployment receiver requires this file and records its fingerprint: it must never be
// edited or deleted online. To close sales again, run tools/open-shop.php with BELMAINS_SHOP=close.
add_filter( 'belmains_shop_open', '__return_true' );
