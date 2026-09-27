# Belmains — boutique WordPress, CRM et builder

Dépôt de la boutique **Belmains** (gant de massage des mains).

| Dossier | Rôle |
|---|---|
| `wp-content/plugins/belmains-crm/` | Plugin CRM : tableau de bord, expéditions Easyship, mouvements de stock, retours SAV |
| `wp-content/themes/belmains/` | Thème : page d'accueil animée + **builder** (Apparence → Personnaliser → ★ Belmains Builder) |
| `index.html`, `direction-b.html` | Propositions graphiques d'origine (v1 et direction B), conservées pour référence |
| `docker-compose.yml`, `docker/init.sh` | Environnement local complet en une commande |

## Choix d'architecture (à lire avant de discuter)

**Le CRM ne réinvente pas la gestion de commandes : il s'appuie sur WooCommerce.**
Commandes, paiements (Stripe/PayPal), clients, codes promo, remboursements, TVA, e-mails transactionnels et
gestion de stock de base sont fournis par WooCommerce, maintenus par des centaines de développeurs et
compatibles avec tous les prestataires de paiement. Réécrire cela = des mois de travail et des risques
(conformité paiement, RGPD, factures). Le plugin `belmains-crm` ajoute uniquement ce qui manque à Belmains :

- **Tableau de bord** unique (menu « Belmains » en haut du menu admin) : CA jour/7 j/30 j, commandes à expédier,
  colis en transit, incidents, retours ouverts, stock bas, dernières commandes avec suivi.
- **Expéditions** : statuts de commande « Expédiée », « Livrée », « Retour en cours » ; colonne « Suivi » dans la liste
  des commandes ; metabox Expédition (transporteur, n° de suivi, URL) ; page « Expéditions » avec filtres
  (à expédier / en transit / incidents / livrées) et actions groupées ; historique horodaté par commande ;
  e-mails « colis en route » / « colis livré » au client ; suivi visible dans « Mon compte ».
- **Easyship** : client API (création d'expédition, achat d'étiquette, lecture du suivi), webhook entrant qui met
  à jour le suivi et bascule automatiquement les statuts de commande, synchronisation de secours 2×/jour (cron).
- **Stocks** : journal de tous les mouvements (commande, annulation/remboursement, réception fournisseur,
  inventaire, casse, SAV) avec avant/après, motif, commande liée et auteur ; ajustement manuel en 3 clics ;
  alerte de stock bas sur le tableau de bord.
- **Retours SAV** : le client demande un retour depuis « Mon compte → Retours » (fenêtre de 30 jours
  paramétrable) ; workflow Demandée → Acceptée/Refusée → Colis en retour → Reçue → Remboursée/Échangée/Clôturée ;
  e-mails automatiques à chaque étape ; remboursement WooCommerce en un clic (via la passerelle si elle le
  permet, sinon enregistré et marqué « à virer manuellement ») avec remise en stock.

## Le builder

Objectif : changer produit, textes, photos, couleurs, polices et ordre des sections **sans toucher au code**,
avec aperçu en direct. Il est construit sur le Customizer WordPress (natif, sans dépendance, avec brouillon /
planification / publication).

- **Produit** : un menu déroulant choisit le produit WooCommerce vendu. Nom, prix, prix barré, % de remise,
  photos et bouton « Ajouter au panier » suivent ce produit. Les lots 2 et 3 sont eux-mêmes des produits
  WooCommerce (l'économie en % est calculée automatiquement).
- **Textes** : chaque texte de la page est un champ. `*mot*` = accent (italique rouge), `**mot**` = gras.
- **Sections** : glisser-déposer pour réordonner, case à cocher pour masquer.
- **Couleurs** (11 teintes de la charte), **typographies** (5 serifs / 5 sans-serif Google Fonts), arrondis.
- **Photos** : chaque emplacement « photo à fournir » de la maquette est un champ image, y compris les 12 vues 360°.
- **SEO** : titre, meta description, Open Graph, JSON-LD Produit + FAQ générés depuis les réglages.

La source de vérité des champs est `wp-content/themes/belmains/inc/fields.php`. Ajouter un champ = une ligne.
Les gabarits de sections sont dans `template-parts/landing/` : pour intégrer la **v3** de la maquette, on
remplace le HTML de chaque section en gardant les appels `bm_text()` / `bm_img()`, sans toucher au builder.

Ce que ce builder n'est **pas** : un éditeur libre type Elementor où l'on peut casser la mise en page. C'est
un choix : pour une marque mono-produit, un builder contraint est plus rapide, plus sûr et garde les animations.
Si un jour il faut des pages libres (blog, landing secondaires), l'éditeur de blocs WordPress reste disponible
sur les pages classiques.

## Démarrer en local

```bash
docker compose up -d
docker compose run --rm cli        # installe WP (fr), WooCommerce, active plugin + thème, crée 3 produits démo
```

- Site : http://localhost:8080 — admin / admin
- CRM : http://localhost:8080/wp-admin/admin.php?page=belmains
- Builder : http://localhost:8080/wp-admin/customize.php?autofocus[panel]=bm_builder

Sur un hébergement classique : copier les deux dossiers dans `wp-content/`, activer WooCommerce, le plugin
« Belmains CRM » et le thème « Belmains ». Les réglages Easyship sont dans Belmains → Réglages.

## Easyship — mise en service

1. Easyship → Connect → API : créer un jeton (sandbox `sand_…` pour tester, prod `prod_…`) et le coller dans
   Belmains → Réglages. Renseigner l'adresse d'expédition et le colis par défaut.
2. Easyship → Connect → Webhooks : ajouter l'URL affichée dans les réglages
   (`/wp-json/belmains/v1/easyship/webhook`) et y coller la clé secrète générée par le plugin.
3. Deux modes de travail possibles :
   - **Depuis WordPress** : bouton « Créer sur Easyship » sur la commande (ou automatique dès qu'une commande
     est payée, option dans les réglages), puis « Acheter l'étiquette ».
   - **Depuis Easyship** : installer aussi le plugin officiel « Easyship WooCommerce Shipping Rates »
     (tarifs au checkout + synchronisation des commandes vers le tableau de bord Easyship), acheter les étiquettes
     chez Easyship ; le webhook ramène le suivi dans WordPress.
4. Sans Easyship (Colissimo en direct, etc.) : saisir transporteur + n° de suivi dans la metabox ; l'URL de suivi
   est déduite pour Colissimo, Chronopost, Mondial Relay, UPS, DHL.

## À vérifier avant la mise en production

- **API Easyship** : le client suit la doc publique 2023-01 (`POST /shipments`, `POST /labels`,
  `GET /shipments/{id}`). Les noms de champs de la réponse et le mode de signature du webhook
  (`X-Easyship-Signature`, HMAC-SHA256 du corps) doivent être confirmés sur developers.easyship.com avec un
  jeton sandbox — je n'ai pas pu appeler l'API depuis cet environnement. Le payload est filtrable
  (`bm_easyship_shipment_payload`) sans modifier le plugin.
- **Code douanier (HS)** et pays d'origine des articles pour les envois hors UE (réglages).
- **Passerelle de paiement** : Stripe/PayPal gèrent les remboursements automatiques ; le virement (BACS) non.
- **E-mails** : configurer un envoi SMTP (WP Mail SMTP, Brevo…) sinon les notifications partent en spam.
- **Avis clients** : les 3 emplacements restent vides tant qu'aucun avis réel n'est saisi (pas de faux avis).
- **Photos** : tous les visuels sont des emplacements « photo à fournir » tant qu'aucune image n'est chargée.

## Ce qui a été testé

Sur un WordPress 6.8 + WooCommerce 9.8 local (base SQLite) : activation plugin/thème sans erreur, rendu de la
page d'accueil (10 sections, lots, bouton panier → checkout), toutes les pages admin du CRM, le Customizer,
puis un scénario complet : commande payée → stock décrémenté et journalisé → webhooks Easyship (étiquette,
transit avec signature HMAC, livraison) → commande passée « Expédiée » puis « Livrée » → demande de retour →
acceptation (commande « Retour en cours ») → réception → remboursement WooCommerce + remise en stock →
commande « Terminée ». Non testé faute d'accès réseau : appels réels à l'API Easyship, envoi d'e-mails.
