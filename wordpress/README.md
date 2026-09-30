# Migration WordPress — première étape

Le thème `belmains/` reprend le design retenu, sa palette bordeaux, ses textes, la vidéo du gant en rotation et les cinq visuels français. Les maquettes 01 et 02 et leur sélecteur sont retirés du site de présentation.

## État du livrable

- Thème classique installable, version 0.1.0 ; WordPress 6.3 minimum, PHP 8.0 minimum.
- Accueil rendu par WordPress, sans iframe ni constructeur de pages.
- Images, vidéo, polices, styles et scripts livrés avec le thème ; leurs adresses s’adaptent au domaine d’installation.
- Galerie, zoom, navigation mobile et animations repris du site validé.
- Cinq exemples d’avis pour la maquette ; une mention unique « Avis de démonstration » en bas de page identifie ces textes, prénoms et notes fictifs.
- Pages WordPress ordinaires éditables dans l’administration, avec le même univers graphique.
- Aucun produit ni aucune commande créés automatiquement. Aucun changement des réglages du site à l’activation.

Le panier, le suivi et les formulaires de l’accueil restent des démonstrations. Ce livrable prépare l’intégration visuelle ; il n’ouvre pas encore les ventes. L’accueil utilise un modèle PHP dédié dans `template-parts/landing.php`, et non des blocs éditables. Une notice d’administration rappelle le travail de raccordement restant.

## Installation, une fois l’hébergement choisi

1. Installer WordPress sur l’hébergement de préparation et activer HTTPS.
2. Régler le site en français et choisir « Demander aux moteurs de recherche de ne pas indexer ce site » pendant la préparation.
3. Dans **Apparence → Thèmes → Ajouter → Téléverser un thème**, importer `belmains-wordpress-theme-0.1.0.zip`, puis activer **Belmains**.
4. Ouvrir l’accueil et contrôler la vidéo, les cinq visuels, le zoom et l’affichage mobile. Le thème fournit directement l’accueil, sans import de page requis.
5. Poursuivre le raccordement de la boutique avant toute mise en vente.

## Étape suivante : boutique

WooCommerce sera raccordé après l’installation : produit, stock, offres, panier et commande, livraison, paiements, e-mails et suivi. Les offres validées sont de 89,99 € au lieu de 109,99 € pour un gant, et de 149,99 € au lieu de 179,99 € pour deux. La remise duo devra être calculée côté serveur et rester cohérente avec le stock et les taxes ; les calculs JavaScript de démonstration ne constituent pas une tarification de commande.

La mention de livraison validée est « Livraison Offerte — Livraison à domicile ou en point relais sous 48h/72h ». Le transporteur et la sélection du point relais restent à connecter. Les coordonnées du vendeur, les textes de vente et de retour, les dimensions du produit et les avis réels restent à renseigner. Les indicateurs de satisfaction déjà présents dans la maquette restent à justifier avant l’ouverture publique.

Le domaine et l’hébergeur seront choisis plus tard, à la demande du client. Aucun achat, changement DNS ou déploiement WordPress distant n’a été effectué.

## Développement et vérification

Le fichier `blueprint.json` sert uniquement au WordPress de test local. Il active le thème, désactive l’indexation et crée une page de validation. Ne pas l’exécuter sur un site existant.

Validation réalisée sur WordPress 7.1.2 et PHP 8.3.33 : accueil et page éditable rendus sans erreur PHP, 27 ressources du thème accessibles, six vues de galerie, zoom et fermeture par Échap, rendu mobile à 390 px sans débordement horizontal. Les scripts de la galerie ne sont chargés que sur l’accueil. Aucun paiement n’a été testé, le commerce n’étant pas encore raccordé.

La base technique suit les points d’intégration documentés par WordPress : [structure d’un thème](https://developer.wordpress.org/themes/core-concepts/theme-structure/) et [chargement des styles et scripts](https://developer.wordpress.org/themes/core-concepts/including-assets/). Le test local utilise [WordPress Playground](https://developer.wordpress.org/playground/handbook/guides/programmatic-playground-cli/).
