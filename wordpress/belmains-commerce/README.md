# Belmains Commerce

Extension gratuite qui relie la fiche Belmains à un vrai panier WooCommerce et fournit l’éditeur **Ma boutique**. Elle ne crée automatiquement ni produit, ni page, ni moyen de paiement, ni réglage fiscal. Son activation n’envoie aucun e-mail et ne crée aucune commande.

## Modifier sa boutique

Ouvrir **Ma boutique** dans le menu WordPress. Choisir le produit à travailler, puis une rubrique : informations et prix, images et vidéo, ou textes de la page. Les photos se choisissent dans la médiathèque WordPress ; les listes peuvent être complétées et réordonnées. Le stock et la référence restent accessibles dans la fiche WooCommerce depuis le lien fourni : publier un texte ou un nouveau prix ne réécrit pas le stock.

- **Enregistrer le brouillon** conserve les modifications dans WordPress sans les afficher aux visiteurs. Le brouillon peut être repris depuis un autre appareil.
- **Prévisualiser** enregistre le brouillon puis ouvre un aperçu réservé aux personnes autorisées. L’aperçu ne permet pas d’acheter avec des prix de brouillon.
- **Publier les modifications** applique les contenus et prix au produit déjà affiché.
- **Publier et afficher ce produit** remplace le produit mis en avant sur l’accueil après une confirmation explicite dans l’éditeur.
- **+ Préparer un nouveau produit** duplique les contenus dans un autre produit en brouillon. Sa référence est vide et son stock est à zéro : renseigner son stock réel dans WooCommerce avant la vente. Les photos déjà présentes sont réutilisées, sans copie inutile de fichiers.

Chaque produit conserve son propre contenu et son brouillon. Changer de produit mis en avant ne supprime ni l’ancien produit ni les anciennes commandes. Les modifications non enregistrées sont signalées avant de quitter l’éditeur. Si une autre session a modifié la fiche, recharger les données avant d’enregistrer pour éviter d’écraser son travail.

Le thème Belmains 0.4.0 est nécessaire pour afficher ces contenus sur la version 3 du site. Les champs contrôlent une mise en page prévue pour la boutique : aucun code HTML à écrire. Les données du builder sont enregistrées dans la base WordPress et les nouveaux médias dans la médiathèque ; les ZIP de mise à jour contiennent uniquement le code. Une migration doit transférer aussi la base et les médias.

## Installation

1. Installer et activer WooCommerce, puis cette extension.
2. Créer explicitement un seul produit simple et physique « Le gant de massage Belmains », couleur Gris, prix normal 109,99 €, prix promotionnel 89,99 €. Configurer son stock réel et ses attributs dans WooCommerce.
3. Enregistrer son identifiant dans l’option WordPress `belmains_product_id` (par exemple avec `wp option update belmains_product_id 123`).
4. Utiliser l’euro, configurer les pages WooCommerce, la livraison et le traitement fiscal correspondant à l’activité avant les ventes.
5. Raccorder le thème au formulaire WooCommerce ou à l’action AJAX ci-dessous. Les pages panier, commande et compte, les sessions et les réponses AJAX doivent être exclues du cache public.

La configuration initiale, le stock disponible, les paiements, les taxes, les e-mails et la connexion Iziship restent des responsabilités séparées. Aucun moyen de paiement fictif n’est fourni dans cette extension.

Le stock initial confirmé pour la boutique locale est de **500 gants gris**, avec gestion du stock active et commandes en rupture refusées. Cette valeur est renseignée lors de la préparation explicite du produit ; l’extension ne réinitialise jamais le stock. Lors d’une migration, transférer les données existantes pour conserver les ventes et mouvements de stock déjà enregistrés.

## Offre et stock

La quantité indique toujours le **nombre réel de gants**. Une même référence de stock est utilisée pour les deux offres :

| Quantité | Montant des produits avant éventuel coupon |
| --- | --- |
| 1 | 89,99 € |
| 2 | 149,99 € |
| 3 | 239,98 € |
| 4 | 299,98 € |

La formule est `floor(quantité / 2) × 149,99 + (quantité % 2) × 89,99`. Elle s’applique à chaque calcul du panier et après restauration de session, y compris aux achats via l’API Store de WooCommerce. Deux ajouts successifs d’un gant déclenchent donc aussi l’offre duo. Les champs de prix envoyés par le navigateur ne sont pas lus.

Les prix unitaires proviennent du produit WooCommerce. L’offre duo est paramétrée par produit dans **Ma boutique**, puis utilisée par la fiche et le panier après publication. Les montants ci-dessus correspondent au gant actuel et peuvent évoluer depuis l’éditeur. Désactiver l’offre duo fait revenir au prix unitaire pour chaque article. Les produits déjà présents dans un panier conservent leur propre règle d’offre quand le produit mis en avant change. La promotion duo est appliquée dans l’unité fiscale de prix configurée dans WooCommerce ; pour afficher exactement les montants convenus au client, la configuration TTC/HT et les taux doivent être cohérents. L’extension bloque le produit mis en avant si la boutique utilise une autre devise.

Les calculs répartissent des centimes entiers entre les lignes. WooCommerce reçoit le prix unitaire non arrondi, puis calcule et arrondit le montant de la ligne. Le panier classique affiche « Offre duo appliquée » au lieu d’un trompeur prix unitaire arrondi à 75,00 €. Le montant facturé est celui du total WooCommerce ; les remboursements utilisent les totaux et taxes enregistrés sur la commande. Pour un remboursement d’une seule unité d’un duo, vérifier le montant partiel proposé par WooCommerce : 149,99 € ne peut pas se diviser en deux montants identiques au centime.

Les coupons WooCommerce restent possibles et s’appliquent après le tarif de l’offre. La limite technique est de 999 gants par panier, avec une quantité entière et les contrôles de stock habituels de WooCommerce. Un panier contenant une quantité indisponible reste bloqué par WooCommerce au passage de commande.

## Contrat avec le thème

`belmains_commerce_product()` retourne le produit WooCommerce configuré et publié, ou `false`. `Belmains_Commerce::frontend_config()` expose une configuration sérialisable :

```text
available, product_id, ajax_url, nonce, cart_url, checkout_url,
cart_count, currency, single_cents, duo_cents, regular_cents, max_quantity
```

En cas d’absence de produit valide, seule la clé `available: false` est retournée. Le thème doit afficher une indisponibilité et désactiver l’ajout.

L’action AJAX est obtenue avec `WC_AJAX::get_endpoint('belmains_add_to_cart')`. Envoyer un formulaire POST encodé avec `product_id`, `quantity` et `nonce` (`wp_create_nonce('belmains_add_to_cart')`). Elle utilise la session panier WooCommerce de l’acheteur, connecté ou invité. Le nonce WordPress protège cette action ; il ne représente pas une authentification client.

Succès : réponse JSON WordPress `{ success: true, data: { cart_count, cart_url, checkout_url, cart_total, cart_hash, notices_html, fragments } }`. `cart_total` et `notices_html` sont du HTML WooCommerce. Les fragments incluent le mini-panier standard et `span.belmains-cart-count`.

Échec : `{ success: false, data: { message, notices_html } }`, avec HTTP 400 (données invalides), 403 (nonce absent/expiré), 405 (méthode incorrecte) ou 409 (produit/stock indisponible). Les messages de stock détaillés de WooCommerce sont présents dans `notices_html`. Le thème doit gérer les erreurs réseau et éviter les doubles clics pendant l’ajout.

Sans JavaScript, un formulaire POST classique WooCommerce avec `add-to-cart=<product_id>` et `quantity=<nombre de gants>` fonctionne également. Les liens, pages, notices et sessions de WooCommerce restent natifs.

## Compatibilité et maintenance

WordPress 6.3+, PHP 8.0+, WooCommerce. Utilisation exclusive des API WooCommerce et compatibilité HPOS déclarée. Aucune table personnalisée, aucun secret, aucun appel à un service externe, aucun abonnement. La suppression de l’extension ne supprime ni produit, ni client, ni commande.

La présentation de l’offre dans les colonnes du panier cible le panier classique WooCommerce. Les montants calculés restent valides avec l’API Store ; un thème utilisant les blocs WooCommerce devra adapter son affichage des prix unitaires pour expliquer la répartition du duo.
