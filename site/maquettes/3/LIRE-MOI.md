# Belmains — maquette HTML

Reconstitution de la page d’accueil du thème fourni `belmains-theme-v0.7.zip`.

## Ouvrir la maquette

Ouvrir `index.html` dans un navigateur. Toutes les images, les polices et les bibliothèques sont locales. Aucun serveur, compte Shopify ou compte WordPress n’est nécessaire.

Le fichier `Belmains-maquette.html`, livré à côté de cette archive, rassemble le même site dans un seul HTML. Il peut être envoyé seul et consulté hors connexion.

## Contenu repris

Les 16 sections de la page d’accueil suivent l’ordre du thème Shopify, avec ses couleurs, ses typographies Playfair Display / Inter, son logo, ses cinq photos et ses textes. Les styles des sections éditoriales sont convertis directement depuis les fichiers Liquid et leurs réglages JSON. Les vagues, bandeaux, carrousels, promesses, témoignages et FAQ sont conservés.

Les menus Shopify n’étaient pas exportés : les liens Accueil, Contact et Suivre ma commande ont été reconstitués. Le logo du pied de page et les pictogrammes de réassurance qui pointaient vers des fichiers Shopify absents ont été remplacés par le logo Belmains inclus et des pictogrammes SVG locaux.

Aucun produit n’était sélectionné dans l’export. La fiche produit reprend ses blocs de texte et ses réglages ; sa galerie utilise les sept vues du gant déjà fournies pendant le projet. Le prix reste « Prix à renseigner ».

## Interactions

- Menu mobile avec fermeture par Échap et navigation au clavier.
- Galerie : miniatures, flèches, clavier et glissement tactile.
- Quantité, ajout au panier, modification de quantité et suppression.
- Panier et commande simulés : aucun paiement.
- Formulaires de contact et de suivi en démonstration : aucune donnée transmise ni conservée.
- FAQ et accordéons de la fiche produit.
- Carrousels de présentation, de témoignages et de photos.
- Diaporama automatique avec pause, arrêt hors écran et respect de la préférence système de mouvement réduit.
- Révélations discrètes au défilement ; bouton de pause des animations dans le pied de page.

## Fichiers pour l’intégration

- `index.html` : structure et contenu de la page.
- `base.css` : typographie et éléments communs.
- `sections.css` : styles convertis du thème d’origine.
- `maquette.css` : adaptation statique de la fiche produit, des fenêtres, du panier et du responsive.
- `interactions.js` : comportements locaux, indépendants de Shopify.
- `assets/` : logo, photos, vues du produit, polices et GSAP.

Les identifiants de sections et attributs `data-source-section` permettent de retrouver la correspondance avec le thème d’origine. Ils ne créent aucune dépendance à Shopify. Ce dossier est une maquette statique, pas un thème WordPress installable : l’intégration WordPress/WooCommerce viendra ensuite.

## Contenus à compléter pour la boutique

Le thème conserve des avis explicitement en attente, un chiffre de satisfaction et des textes techniques/commerciaux fournis dans l’archive. Ils ont été repris pour la présentation ; renseigner les vrais avis et le prix, puis valider les caractéristiques, les conditions commerciales et les coordonnées du vendeur avant la mise en production. Les pages de contact, suivi, mentions légales et guide des tailles sont des aperçus.

La maquette porte `noindex,nofollow`. Elle ne contient ni suivi publicitaire ni envoi à des services Shopify.

## Contrôles réalisés

Chrome : 1440, 1024, 768, 390 et 320 pixels. Galerie, navigation clavier, panier, contact, suivi, FAQ, carrousels, menu mobile et pause des animations vérifiés. Aucun débordement horizontal, image manquante ou erreur JavaScript observé. Version autonome également vérifiée sans réseau.

## Finition visuelle

La version affinée conserve intégralement le HTML et les textes de la première maquette. Seule la présentation CSS évolue : titre d’accueil mieux proportionné, bouton rouge plus lisible, espacements et ombres harmonisés, galerie fixe pendant la lecture sur grand écran, réassurance regroupée et meilleure disposition des cartes sur mobile. La version initiale est conservée dans `Belmains-maquette-originale.html`, à côté des livrables.
