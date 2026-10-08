# Boutique WordPress Belmains

Le thème `belmains/` reprend le design retenu, sa palette bordeaux, ses textes, la vidéo du gant en rotation et les cinq visuels français. Les maquettes 01 et 02 et leur sélecteur sont retirés du site de présentation.

## État du livrable

- Thème classique installable, version 0.4.1 ; WordPress 6.3 minimum, PHP 8.0 minimum.
- Accueil rendu par WordPress, sans iframe ni constructeur de pages.
- Images, vidéo, polices, styles et scripts livrés avec le thème ; leurs adresses s’adaptent au domaine d’installation.
- Galerie, zoom, navigation mobile et animations repris du site validé.
- Cinq exemples d’avis pour la maquette ; une mention unique « Avis de démonstration » en bas de page identifie ces textes, prénoms et notes fictifs.
- Pages WordPress ordinaires éditables dans l’administration, avec le même univers graphique.
- Aucun produit ni aucune commande créés automatiquement. Aucun changement des réglages du site à l’activation.

La fiche produit utilise le panier WooCommerce avec le plugin séparé `belmains-commerce/`. Son éditeur **Ma boutique** permet de modifier les contenus et de préparer un autre produit sans toucher au code, avec des brouillons conservés dans WordPress et un aperçu privé. Le modèle PHP conserve la présentation validée de la version 3. Les pages panier, commande, compte et suivi utilisent les fonctions WooCommerce natives et le design Belmains. Le contact enregistre les demandes dans le SAV privé du CRM. Les avis de l’accueil restent des démonstrations. Le raccordement des paiements et d’Iziship reste à finaliser avant les ventes.

## Installation, une fois l’hébergement choisi

1. Installer WordPress sur l’hébergement de préparation et activer HTTPS.
2. Régler le site en français et choisir « Demander aux moteurs de recherche de ne pas indexer ce site » pendant la préparation.
3. Dans **Apparence → Thèmes → Ajouter → Téléverser un thème**, importer `belmains-wordpress-theme-0.4.1.zip`, puis activer **Belmains**.
4. Ouvrir l’accueil et contrôler la vidéo, les cinq visuels, le zoom et l’affichage mobile. Le thème fournit directement l’accueil, sans import de page requis.
5. Installer WooCommerce et le plugin `belmains-commerce/`, puis configurer le produit et les pages comme indiqué ci-dessous. Poursuivre la configuration des paiements avant toute mise en vente.

## CRM et pilotage de la boutique

Le plugin séparé `belmains-crm/` ajoute huit rubriques privées dans WordPress : vue d’ensemble et audience consentie, commandes, clients, expéditions, stocks, marketing, service client et connexions. Il fonctionne avec WooCommerce sans abonnement CRM et sans extension de suivi payante. Consulter sa [notice d’installation et d’utilisation](belmains-crm/README.md) pour les indicateurs, leurs limites, les droits d’accès et le raccordement Iziship.

Les chiffres viennent des données enregistrées, sans données de démonstration générées. La marge exige les coûts historiques de commande ; les dépenses publicitaires sont saisies manuellement. Les visiteurs ne sont comptés qu’après consentement. Le compte Iziship est actif, mais sa connexion reste à effectuer après publication d’une adresse HTTPS accessible : `http://belmains.local/` ne peut pas recevoir les échanges de leurs serveurs. Le numéro de suivi prévu dans `tracking_number` permet d’indiquer une expédition, pas de déduire une livraison.

Le thème, Belmains Commerce et le CRM ont des rôles séparés. Le CRM reçoit les commandes WooCommerce créées par le parcours commercial ; il n’active aucun moyen de paiement.

## Panier et commande WooCommerce

### Éditeur Ma boutique

Après activation de Belmains Commerce 0.2.1, ouvrir **Ma boutique** dans le menu WordPress. Choisir le produit puis la rubrique à modifier. Enregistrer le brouillon pour reprendre plus tard, prévisualiser pour contrôler le rendu, puis publier pour appliquer les changements. Le bouton destiné à un autre produit indique explicitement qu’il sera affiché sur l’accueil. La duplication crée un produit en brouillon avec un stock à zéro et une référence vide : renseigner les caractéristiques et le stock réels avant de le proposer à la vente. Voir le [mode d’emploi de l’éditeur](belmains-commerce/README.md#modifier-sa-boutique).

Les prix unitaires proviennent de WooCommerce et l’offre duo est réglée par produit. Les contenus, les visuels et les offres sont propres à chaque fiche. Le stock et les anciennes commandes ne sont pas réécrits lors de la publication d’un contenu. Les aperçus privés n’affichent pas les brouillons aux visiteurs et désactivent l’achat depuis leur présentation.

Le plugin [Belmains Commerce](belmains-commerce/README.md) applique côté serveur les offres validées : 89,99 € pour un gant et 149,99 € pour deux. Chaque paire complète bénéficie du tarif duo ; un gant supplémentaire est à 89,99 €. Les quantités sont des gants physiques sur un seul SKU gris, pas des lots virtuels. Les totaux sont recalculés après modification du panier et rechargement de session ; les prix envoyés par le navigateur ne sont pas utilisés.

Le stock initial confirmé pour la boutique locale est de **500 gants gris**, avec suivi de stock et commandes en rupture désactivées. WooCommerce réserve/déduit le stock selon son cycle de commande et le restitue lors d’une annulation ou d’un remboursement avec remise en stock. Aucun test de commande n’est créé dans les données commerciales réelles.

Le script explicite `tools/provision-commerce.php` prépare un nouveau site : produit, image, pages natives françaises, livraison offerte à domicile en France et devise EUR. Il s’exécute avec WP-CLI (`wp eval-file chemin/wordpress/tools/provision-commerce.php`) et la variable d’environnement `BELMAINS_INITIAL_STOCK` renseignée avec le stock confirmé. Il ne s’exécute jamais à l’activation. Une deuxième exécution conserve le stock existant ; les pages avec contenu personnalisé nécessitent une vérification avant modification. Sur une migration du site existant, transférer sa base et ses médias plutôt que recréer son stock.

La configuration de TVA est laissée à confirmer : aucun taux ni régime fiscal n’est inventé. Aucun moyen de paiement de test n’est livré dans le plugin. La validation complète du passage de commande utilise une passerelle réservée à une copie locale isolée, sans débit et sans envoi d’e-mail.

### WooPayments

Le 8 octobre 2026, WooPayments 11.1.0 a été installé et activé sur `belmains.local`, après sauvegarde de la base et de la configuration. La boutique conserve ses prix en EUR. Les réglages préparent le paiement par carte avec capture automatique, en mode test ; les cartes enregistrées et les boutons de paiement express sont désactivés. La passerelle reste désactivée au paiement tant que le compte n’est pas raccordé. Aucun compte WooPayments n’a été créé ou connecté, aucun compte bancaire n’a été ajouté et aucune transaction WooPayments n’a été exécutée.

Sur cette installation locale, `WCPAY_DEV_MODE=true` dans `wp-config.php` impose aussi le mode développement de WooPayments. Ce réglage ne crée pas de compte de test. L’installation du module et les options sont propres à WordPress : WooPayments n’est pas inclus dans les ZIP du thème ou des plugins Belmains. Pour une nouvelle installation, installer séparément l’extension officielle ; pour une migration, transférer les réglages de la base avec le site. Conserver les sauvegardes de base et de configuration hors de GitHub et du répertoire public.

Si WooPayments est finalement retenu, son raccordement nécessite un site accessible en HTTPS et le compte WordPress.com de la propriétaire. Le pays d’immatriculation à sélectionner pour ce compte de paiement est le **Royaume-Uni**, pour ECO EXPRESS LIMITED ; l’expédition depuis l’Espagne ne change pas ce pays d’immatriculation. La propriétaire doit effectuer les validations d’identité, d’entreprise, de conditions et du compte de versement dans l’interface sécurisée. À la mise en service, revoir explicitement la constante de développement, l’état test/réel du compte et le réglage de test avant d’activer les encaissements réels. Effectuer ensuite les essais de paiement et de remboursement autorisés ; les contrôles locaux ne prouvent pas le fonctionnement des transactions. Voir le [guide de démarrage](https://woocommerce.com/document/woopayments/startup-guide/) et la [documentation des comptes de test](https://woocommerce.com/document/woopayments/testing-and-troubleshooting/test-accounts/).

Contrôles après installation : module actif, test effectif, mode réel désactivé, compte absent, passerelle indisponible au paiement, réglages conservés après les migrations du module, pages principales accessibles. Stock : 500 gants ; commandes : zéro ; aucun taux de TVA ni réglage de livraison modifié.

### Revolut et hébergement — préparation du 8 octobre 2026

Le client confirme désormais la validation de Revolut Business et l’activation du service Merchant. Le domaine `belmains.com` et la messagerie `contact@belmains.com` sont gérés chez Infomaniak. L’existence d’un hébergement Web reste à vérifier dans le Manager ; la session disponible demande une connexion. Le DNS public pointe encore le site vers Shopify (`23.227.38.65` et `www` vers `shops.myshopify.com`), avec les serveurs DNS et MX Infomaniak. Aucune bascule DNS ni modification des mails n’a été effectuée.

Revolut Gateway for WooCommerce **4.22.12**, publié par Revolut, a été installé depuis WordPress.org après sauvegarde privée de la base. Les empreintes officielles du paquet ont été vérifiées avant intervention. La configuration prépare le mode **Sandbox**, la capture automatique, aucun enregistrement de carte, aucune méthode de paiement activée, aucun bouton express et aucune bannière promotionnelle. Aucun identifiant, jeton OAuth, compte, webhook ni transaction n’a été créé ou connecté par cette préparation. WooPayments conserve ses réglages test et sa passerelle désactivée.

Le paquet officiel comportait un chemin de chargement incompatible avec cette installation Windows : `src/index.php:3` concaténait `__DIR__ . './../vendor/autoload.php'`. Une correction locale d’une ligne utilise `dirname( __DIR__ ) . '/vendor/autoload.php'`, sans toucher à la logique de paiement. Le ZIP original reste sauvegardé hors du dépôt. Une vérification future des empreintes signalera donc cette différence connue ; une mise à jour fournisseur peut l’écraser et doit être contrôlée. Le module tiers et cette copie locale ne sont pas inclus dans les ZIP Belmains.

Après correction, **43 contrôles locaux ont réussi** : mode test, méthodes désactivées, absence de connexion, conservation des prix, du stock et des réglages WooPayments, tables Revolut vides et pages principales sans erreur. Le module sans compte connecté provoquait toutefois des appels externes et des chargements de 12 à 17 secondes. Il a donc été **laissé installé mais désactivé**, avec tous ses réglages conservés ; l’accueil revient à environ une seconde. Aucun paiement de test n’a été simulé sans compte Sandbox.

La suite : vérifier l’hébergement Web Infomaniak, migrer la boutique sur une préparation HTTPS, puis contrôler la version officielle Revolut et la compatibilité avant activation. Dans **WooCommerce → Réglages → Paiements → Revolut → API Settings**, la connexion OAuth est disponible en mode **Live** et doit être autorisée par la propriétaire ; le mode Sandbox utilise une clé de test distincte. Garder les moyens de paiement désactivés pendant le raccordement, vérifier les webhooks et effectuer les essais autorisés avant la mise en vente. Ne pas activer WooPayments en parallèle par défaut. [Installation officielle](https://developer.revolut.com/docs/guides/merchant/accept-payments/no-code/plugins/woocommerce/installation), [connexion et modes](https://developer.revolut.com/docs/guides/merchant/accept-payments/no-code/plugins/woocommerce/configuration).

Pour accéder à l’hébergement sans partager le mot de passe de la cliente, elle peut inviter le compte Infomaniak du prestataire comme utilisateur externe avec les droits techniques sur l’hébergement et, pour la bascule, le domaine. Conserver les enregistrements mail existants. [Droits par produit Infomaniak](https://www.infomaniak.com/fr/support/faq/1610/gerer-les-acces-produits-dun-utilisateur-dorganisation).

La mention de livraison validée est « Livraison Offerte — Livraison à domicile ou en point relais sous 48h/72h ». Le panier propose pour l’instant la livraison à domicile en France ; le transporteur et la sélection du point relais restent à connecter. La page de suivi utilise le numéro de commande et l’e-mail de facturation, puis affiche les informations réelles enregistrées. Les coordonnées du vendeur, les textes de vente et de retour, les dimensions du produit et les avis réels restent à renseigner. Le compteur de satisfaction non justifié et les affirmations non confirmées de recharge en 15 minutes et de réponse en 24 heures ont été retirés. Les avis de démonstration restent à remplacer avant l’ouverture publique.

Le domaine retenu est désormais `belmains.com` chez Infomaniak ; l’hébergement Web reste à confirmer. Aucun achat, changement DNS ou déploiement WordPress distant n’a été effectué.

## Développement et vérification

Le fichier `blueprint.json` sert uniquement au WordPress de test local. Il active le thème, désactive l’indexation et crée une page de validation. Ne pas l’exécuter sur un site existant.

Validation sur WordPress 7.1.2 et WooCommerce 11.1.2 : accueil et galerie, ajout de l’offre duo, modification à trois gants (239,98 €), restauration du panier et passage d’une commande simulée jusqu’au CRM. Le stock de la copie passe de 500 à 497 ; celui de la boutique reste à 500, sans commande de test. Le formulaire et le récapitulatif sont contrôlés sur ordinateur et téléphone, sans débordement horizontal.

Les scripts de la galerie ne sont chargés que sur l’accueil. La suite `tests/commerce-integration.php` compte 57 contrôles réussis sur les prix, le stock, les commandes, l’API et les accès. `tests/tracking-form-integration.php` vérifie le suivi invité autorisé par WooCommerce et le refus des accès non autorisés. Ces suites sont réservées à la base locale isolée `bcrm_validation_`. Ne pas exécuter de tests de création de commandes sur la boutique commerciale.

L’éditeur Ma boutique a été vérifié avec 66 contrôles d’intégration sur la copie isolée : droits, brouillons, aperçu, publication, duplication, concurrence entre sessions et retour à l’ancien produit. Des essais DOM simulés couvrent 22 interactions de l’administration et 70 interactions de la vitrine, dont les prix, la galerie facultative et le blocage des achats dans un aperçu. Le navigateur était indisponible lors de ces premiers essais. Une vérification ultérieure de la fiche sur ordinateur a révélé une collision entre le nom du produit et la variable WordPress `name` : le thème 0.4.1 affecte désormais explicitement les arguments du modèle. Deux reproductions SSR et une vérification visuelle confirment le titre rétabli. Commerce 0.2.1 retire aussi la promesse de réponse sous 24 heures de l’introduction FAQ par défaut. L’éditeur d’administration et le nouveau rendu sur téléphone restent à contrôler visuellement.

La base technique suit les points d’intégration documentés par WordPress : [structure d’un thème](https://developer.wordpress.org/themes/core-concepts/theme-structure/) et [chargement des styles et scripts](https://developer.wordpress.org/themes/core-concepts/including-assets/). Le test local utilise [WordPress Playground](https://developer.wordpress.org/playground/handbook/guides/programmatic-playground-cli/).


## Contact et préparation à l’ouverture

Le thème 0.3.0 dirige les liens Contact vers la page publiée contenant `[belmains_contact]`, reliée à Belmains CRM 0.2.0. Le formulaire fonctionne sans JavaScript : ses demandes apparaissent dans **Service client**, sans e-mail automatique. Une référence saisie par le visiteur reste une indication à vérifier, jamais une autorisation d’accès à une commande. Le formulaire conserve les champs à corriger pendant dix minutes au maximum et protège contre les doublons et les envois abusifs. Exclure Contact du cache pleine page.

Le script explicite `tools/prepare-launch.php`, exécuté avec WP-CLI après activation du CRM, crée la page Contact et quatre brouillons français : mentions légales, CGV, confidentialité, livraison/retours. Il conserve les pages existantes, prépare les couleurs et le logo des e-mails, désactive l’indexation de la préproduction et masque uniquement la page d’exemple WordPress inchangée. Il ne configure ni taxe, ni paiement, ni adresse d’expéditeur et ne modifie pas le stock. Les brouillons ne sont pas publiés ni présentés comme des documents finalisés.

Les informations du vendeur, le régime fiscal, les moyens de paiement, les conditions de livraison et les coordonnées de retour doivent être confirmés avant finalisation. Références de préparation : [CGV — Service Public](https://entreprendre.service-public.fr/vosdroits/F33527), [information sur les formulaires — CNIL](https://www.cnil.fr/fr/exemples-de-formulaire-de-collecte-de-donnees-caractere-personnel) et [conservation des données — CNIL](https://www.cnil.fr/fr/passer-laction/les-durees-de-conservation-des-donnees).

Les e-mails WooCommerce utilisent la présentation Belmains. Leur génération est vérifiée sur une copie isolée sans envoi ; sur Local, les messages sont capturés dans Mailpit. Une réception réelle n’est pas confirmée : elle nécessite l’adresse professionnelle, un transport d’e-mail configuré et un essai de réception. Voir le [guide WooCommerce sur les e-mails](https://woocommerce.com/document/email-faq/).

Le [workflow de validation et d’archives](DEPLOYMENT.md) automatise les contrôles de syntaxe et les ZIP dans GitHub. Le déploiement vers WordPress attend encore le choix de l’hébergement ; aucune clé ni donnée commerciale n’est incluse dans les paquets.

Validation de cette étape : 74 contrôles Contact et 41 contrôles de confidentialité réussis sur la base isolée, puis essai HTTP du formulaire jusqu’au ticket privé, répétition sans doublon et correction des champs. Le rendu de la confirmation de commande a été vérifié pour le logo, les couleurs et les montants sans envoi. La boutique locale conserve 500 gants, zéro commande et zéro ticket de test ; les quatre pages commerciales restent en brouillon. Le contrôle visuel de la nouvelle page Contact sur mobile reste à effectuer, le navigateur étant indisponible dans la session de préparation du 8 octobre 2026.
