#!/usr/bin/env bash
# Installation initiale du site local (à lancer une fois) :
#   docker compose up -d && docker compose run --rm cli
set -euo pipefail
cd /var/www/html

until wp db check >/dev/null 2>&1; do echo "attente de la base…"; sleep 2; done

if ! wp core is-installed >/dev/null 2>&1; then
  wp core install --url=http://localhost:8080 --title="Belmains" \
    --admin_user=admin --admin_password=admin --admin_email=admin@belmains.local --skip-email
  wp language core install fr_FR --activate || true
fi

wp plugin install woocommerce --activate || wp plugin activate woocommerce
wp plugin install easyship-woocommerce-shipping-rates --activate || echo "Plugin Easyship non installé (facultatif : tarifs au checkout)."
wp plugin activate belmains-crm
wp theme activate belmains
wp rewrite structure '/%postname%/' --hard

# Réglages boutique FR
wp option update woocommerce_currency EUR
wp option update woocommerce_default_country FR
wp option update woocommerce_weight_unit kg
wp option update woocommerce_dimension_unit cm
wp option update woocommerce_enable_guest_checkout yes
wp option update timezone_string Europe/Paris

# Produits de démonstration (idempotent, par SKU)
wp eval '
$mk = function( $name, $sku, $price, $regular, $stock ) {
  if ( wc_get_product_id_by_sku( $sku ) ) { return wc_get_product_id_by_sku( $sku ); }
  $p = new WC_Product_Simple();
  $p->set_name( $name ); $p->set_sku( $sku ); $p->set_regular_price( $regular ); $p->set_sale_price( $price );
  $p->set_manage_stock( true ); $p->set_stock_quantity( $stock ); $p->set_weight( 0.4 );
  $p->set_length( 22 ); $p->set_width( 16 ); $p->set_height( 8 ); $p->set_status( "publish" );
  $p->set_short_description( "Gant de massage des mains : 5 modes, compresse chaude 3 niveaux, acupression. Compact, rechargeable en 15 min." );
  $p->save(); return $p->get_id();
};
$a = $mk( "Gant de massage Belmains", "BM-GANT-1", "79", "99", 100 );
$b = $mk( "Lot de 2 gants Belmains", "BM-GANT-2", "139", "198", 50 );
$c = $mk( "Lot de 3 gants Belmains", "BM-GANT-3", "189", "297", 30 );
if ( ! get_theme_mod( "product_id" ) ) { set_theme_mod( "product_id", $a ); set_theme_mod( "lot2_product", $b ); set_theme_mod( "lot3_product", $c ); }
echo "Produits : $a, $b, $c\n";
'
echo
echo "✔ Site prêt : http://localhost:8080  — admin / admin"
echo "  Tableau de bord CRM : http://localhost:8080/wp-admin/admin.php?page=belmains"
echo "  Builder            : http://localhost:8080/wp-admin/customize.php?autofocus[panel]=bm_builder"
