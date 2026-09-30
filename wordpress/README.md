# Boutique WordPress Belmains

Le thème `belmains/` reprend le design retenu, sa palette bordeaux, ses textes, la vidéo du gant en rotation et les cinq visuels français. Les maquettes 01 et 02 et leur sélecteur sont retirés du site de présentation.

## État du livrable

- Thème classique installable, version 0.2.0 ; WordPress 6.3 minimum, PHP 8.0 minimum.
- Accueil rendu par WordPress, sans iframe ni constructeur de pages.
- Images, vidéo, polices, styles et scripts livrés avec le thème ; leurs adresses s’adaptent au domaine d’installation.
- Galerie, zoom, navigation mobile et animations repris du site validé.
- Cinq exemples d’avis pour la maquette ; une mention unique « Avis de démonstration » en bas de page identifie ces textes, prénoms et notes fictifs.
- Pages WordPress ordinaires éditables dans l’administration, avec le même univers graphique.
- Aucun produit ni aucune commande créés automatiquement. Aucun changement des réglages du site à l’activation.

La fiche produit utilise maintenant le panier WooCommerce avec le plugin séparé `belmains-commerce/`. Les pages panier, commande, compte et suivi utilisent les fonctions WooCommerce natives et le design Belmains. Le formulaire de contact et les avis de l’accueil restent des démonstrations. L’accueil utilise un modèle PHP dédié dans `template-parts/landing.php`, et non des blocs éditables. Les paiements et Iziship restent à configurer avant les ventes.

## Installation, une fois l’hébergement choisi

1. Installer WordPress sur l’hébergement de préparation et activer HTTPS.
2. Régler le site en français et choisir « Demander aux moteurs de recherche de ne pas indexer ce site » pendant la préparation.
3. Dans **Apparence → Thèmes → Ajouter → Téléverser un thème**, importer `belmains-wordpress-theme-0.2.0.zip`, puis activer **Belmains**.
4. Ouvrir l’accueil et contrôler la vidéo, les cinq visuels, le zoom et l’affichage mobile. Le thème fournit directement l’accueil, sans import de page requis.
5. Installer WooCommerce et le plugin `belmains-commerce/`, puis configurer le produit et les pages comme indiqué ci-dessous. Poursuivre la configuration des paiements avant toute mise en vente.

## CRM et pilotage de la boutique

Le plugin séparé `belmains-crm/` ajoute huit rubriques privées dans WordPress : vue d’ensemble et audience consentie, commandes, clients, expéditions, stocks, marketing, service client et connexions. Il fonctionne avec WooCommerce sans abonnement CRM et sans extension de suivi payante. Consulter sa [notice d’installation et d’utilisation](belmains-crm/README.md) pour les indicateurs, leurs limites, les droits d’accès et le raccordement Iziship.

Les chiffres viennent des données enregistrées, sans données de démonstration générées. La marge exige les coûts historiques de commande ; les dépenses publicitaires sont saisies manuellement. Les visiteurs ne sont comptés qu’après consentement. Le compte Iziship est actif, mais sa connexion reste à effectuer après publication d’une adresse HTTPS accessible : `http://belmains.local/` ne peut pas recevoir les échanges de leurs serveurs. Le numéro de suivi prévu dans `tracking_number` permet d’indiquer une expédition, pas de déduire une livraison.

Le thème, Belmains Commerce et le CRM ont des rôles séparés. Le CRM reçoit les commandes WooCommerce créées par le parcours commercial ; il n’active aucun moyen de paiement.

## Panier et commande WooCommerce

Le plugin [Belmains Commerce](belmains-commerce/README.md) applique côté serveur les offres validées : 89,99 € pour un gant et 149,99 € pour deux. Chaque paire complète bénéficie du tarif duo ; un gant supplémentaire est à 89,99 €. Les quantités sont des gants physiques sur un seul SKU gris, pas des lots virtuels. Les totaux sont recalculés après modification du panier et rechargement de session ; les prix envoyés par le navigateur ne sont pas utilisés.

Le stock initial confirmé pour la boutique locale est de **500 gants gris**, avec suivi de stock et commandes en rupture désactivées. WooCommerce réserve/déduit le stock selon son cycle de commande et le restitue lors d’une annulation ou d’un remboursement avec remise en stock. Aucun test de commande n’est créé dans les données commerciales réelles.

Le script explicite `tools/provision-commerce.php` prépare un nouveau site : produit, image, pages natives françaises, livraison offerte à domicile en France et devise EUR. Il s’exécute avec WP-CLI (`wp eval-file chemin/wordpress/tools/provision-commerce.php`) et la variable d’environnement `BELMAINS_INITIAL_STOCK` renseignée avec le stock confirmé. Il ne s’exécute jamais à l’activation. Une deuxième exécution conserve le stock existant ; les pages avec contenu personnalisé nécessitent une vérification avant modification. Sur une migration du site existant, transférer sa base et ses médias plutôt que recréer son stock.

La configuration de TVA est laissée à confirmer : aucun taux ni régime fiscal n’est inventé. Aucun moyen de paiement de test n’est livré dans le plugin. La validation complète du passage de commande utilise une passerelle réservée à une copie locale isolée, sans débit et sans envoi d’e-mail.

La mention de livraison validée est « Livraison Offerte — Livraison à domicile ou en point relais sous 48h/72h ». Le panier propose pour l’instant la livraison à domicile en France ; le transporteur et la sélection du point relais restent à connecter. La page de suivi utilise le numéro de commande et l’e-mail de facturation, puis affiche les informations réelles enregistrées. Les coordonnées du vendeur, les textes de vente et de retour, les dimensions du produit et les avis réels restent à renseigner. Les indicateurs de satisfaction déjà présents dans la maquette restent à justifier avant l’ouverture publique.

Le domaine et l’hébergeur seront choisis plus tard, à la demande du client. Aucun achat, changement DNS ou déploiement WordPress distant n’a été effectué.

## Développement et vérification

Le fichier `blueprint.json` sert uniquement au WordPress de test local. Il active le thème, désactive l’indexation et crée une page de validation. Ne pas l’exécuter sur un site existant.

Validation sur WordPress 7.1.2 et WooCommerce 11.1.2 : accueil et galerie, ajout de l’offre duo, modification à trois gants (239,98 €), restauration du panier et passage d’une commande simulée jusqu’au CRM. Le stock de la copie passe de 500 à 497 ; celui de la boutique reste à 500, sans commande de test. Le formulaire et le récapitulatif sont contrôlés sur ordinateur et téléphone, sans débordement horizontal.

Les scripts de la galerie ne sont chargés que sur l’accueil. La suite `tests/commerce-integration.php` compte 57 contrôles réussis sur les prix, le stock, les commandes, l’API et les accès. `tests/tracking-form-integration.php` vérifie le suivi invité autorisé par WooCommerce et le refus des accès non autorisés. Ces suites sont réservées à la base locale isolée `bcrm_validation_`. Ne pas exécuter de tests de création de commandes sur la boutique commerciale.

La base technique suit les points d’intégration documentés par WordPress : [structure d’un thème](https://developer.wordpress.org/themes/core-concepts/theme-structure/) et [chargement des styles et scripts](https://developer.wordpress.org/themes/core-concepts/including-assets/). Le test local utilise [WordPress Playground](https://developer.wordpress.org/playground/handbook/guides/programmatic-playground-cli/).
