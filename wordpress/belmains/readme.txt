Belmains — thème WordPress 0.5.0

Installer le ZIP dans Apparence > Thèmes > Ajouter > Téléverser un thème.
Activer sur un WordPress de préparation. L’accueil s’affiche automatiquement.

Ce premier livrable migre le design, les ressources locales et les interactions.
La vidéo et les cinq visuels sont inclus. Aucun constructeur de pages requis.
Les pages WordPress ordinaires utilisent leur contenu éditable dans WordPress.
L’accueil conserve le design validé et se modifie dans « Ma boutique » grâce à
Belmains Commerce 0.2.0 : textes, médias, couleurs, sections, prix et offres.
Les brouillons sont enregistrés dans WordPress. L’aperçu privé ne modifie pas
la boutique publique ; la publication est une action volontaire.

Le panier, les offres et le stock sont gérés par WooCommerce et Belmains Commerce.
Le suivi utilise les commandes WooCommerce et leurs informations d’expédition.
Le formulaire de contact est relié au SAV par Belmains CRM : les demandes sont
enregistrées sans envoi automatique d’e-mail. Le thème ne crée aucun produit,
compte, commande ou réglage WooCommerce à son activation.
Les paiements, la fiscalité, l’envoi externe des e-mails et Iziship restent à
configurer. Les avis de démonstration sont réservés à la préproduction.

Ne pas confondre installation du thème et ouverture des ventes.
Une notice dans l’administration rappelle l’état de la migration.

Galerie vidéo — version 0.4.3
Le modèle « Galerie Belmains » présente trois clips locaux avec leurs affiches.
Les lecteurs natifs ne se lancent pas automatiquement et démarrer une vidéo
met les autres en pause. Les fichiers sont dans assets/gallery/.
La page est créée séparément dans WordPress ; l’option belmains_gallery_page_id
la désigne. Les liens de menu apparaissent seulement pour une page publiée,
sans mot de passe et utilisant page-galerie.php. Le thème ne modifie aucun
contrôle d’accès : l’autorisation publique reste gérée par le garde du site.

Commande sur une seule page (0.5.0)
Un panier rempli n'a plus de page à lui : l'ajout au panier et le lien « Panier » mènent à la page de commande, où la quantité se modifie et l'article se retire dans le récapitulatif (inc/checkout.php, assets/checkout.js). La page « Panier » ne sert plus qu'au panier vide. Le balisage de la commande WooCommerce est conservé tel quel, la mise en page est faite en CSS : les extensions de paiement n'y voient aucune différence.
