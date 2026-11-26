# Belmains CRM

Belmains CRM ajoute un espace de pilotage privé dans WordPress pour suivre la boutique WooCommerce, son audience consentie, ses clients et ses expéditions. Le module fonctionne sans abonnement CRM, sans bibliothèque distante et sans extension de suivi payante. Seule exception : la publicité Meta, confiée à l’extension officielle Meta for WooCommerce, que le CRM ne laisse fonctionner qu’après l’accord du visiteur. L’hébergement, les paiements, la publicité et les prestations Iziship conservent leurs propres coûts.

Version 0.5.1 — WordPress 6.3 minimum, PHP 8.0 minimum. WooCommerce est nécessaire pour les commandes, clients, stocks et expéditions. Le module utilise les interfaces WooCommerce et déclare sa compatibilité avec le stockage des commandes HPOS.

## Installation et premier accès

1. Sauvegarder les fichiers et la base de données du WordPress cible.
2. Installer et activer WooCommerce, puis vérifier la devise, le fuseau horaire, les taxes et les produits de la boutique.
3. Copier le dossier `belmains-crm/` dans `wp-content/plugins/`, ou importer son archive dans **Extensions → Ajouter une extension → Téléverser une extension**. Activer **Belmains CRM**.
4. Ouvrir **Belmains CRM** dans le menu d’administration avec un compte administrateur ou gestionnaire de boutique.
5. Choisir une période et consulter **Connexions**. Les préférences sont modifiables par un administrateur : mesure d’audience, conservation, seuil de stock bas et délai de livraison de référence.

L’activation crée les tables propres au CRM et programme la purge de l’audience. Elle ne crée aucun produit, client, commande, témoignage ou chiffre de démonstration. Sans données réelles, les états vides, les zéros et les valeurs indisponibles sont normaux.

Le développement se teste sur une copie locale. La boutique hébergée utilise `https://belmains.com/` ; son ouverture commerciale reste une étape séparée de la mise à jour du CRM. Le thème Belmains 0.3.0 et le plugin séparé Belmains Commerce raccordent la fiche produit au panier et à la commande WooCommerce. Le CRM exploite ces commandes ; il ne configure pas le paiement. Les moyens de paiement réels, Iziship et la fiscalité restent à finaliser avant l’ouverture des ventes.

## Les huit rubriques

| Rubrique | Ce qu’elle permet de suivre ou de faire |
|---|---|
| **Vue d’ensemble** | Commandes, chiffre d’affaires net, remboursements, panier moyen, unités vendues, clients récurrents, marge selon les coûts connus ; courbes quotidiennes ; visiteurs, sessions, sources, appareils et étapes d’achat mesurées. |
| **Commandes** | Recherche, filtres et pagination ; détail des articles et coordonnées ; coûts historiques ; notes internes ; suivi du colis ; accès à la commande WooCommerce ; export CSV de la période. |
| **Clients** | Clients inscrits et invités, e-mail et téléphone, commandes et dépenses sur la période, première et dernière commande de cette période, segment et export CSV. |
| **Expéditions** | Plusieurs colis et retours par commande ; transporteurs, liens officiels, dates, relais confirmé, historique, filtres, alertes de délai, CSV, message à copier et bon de préparation imprimable. |
| **Stocks** | Produits et variations, références, disponibilité, quantité lorsqu’elle est gérée, stock bas et lien vers la fiche WooCommerce pour modifier le stock. |
| **Marketing** | Dépenses publicitaires saisies manuellement, sources et campagnes attribuées aux commandes, chiffre d’affaires associé et ROAS lorsqu’il est calculable. |
| **Service client** | Demandes du formulaire Contact et saisies internes, e-mail, référence, priorité, état ouvert/en attente/résolu et notes de traitement. |
| **Connexions** | État de WooCommerce, préparation Iziship, dernière réception de suivi observée et préférences du CRM. |

Les notes et demandes SAV sont internes : aucun e-mail n’est envoyé par leur création ou leur modification. Les réglages de stock restent ceux de WooCommerce ; le CRM n’introduit pas un stock parallèle.

## Lire les indicateurs correctement

- **Période :** 30 derniers jours par défaut, jusqu’à 366 jours inclus. Les bornes de calcul utilisent le fuseau horaire WordPress.
- **Ventes :** commandes payées ou ayant un paiement/remboursement enregistré, regroupées par date de création de la commande. Le chiffre d’affaires exclut les taxes et déduit les remboursements connus, même lorsqu’ils ont été enregistrés après la période choisie. Ce n’est pas un relevé des encaissements bancaires de la période.
- **Remboursements :** les remboursements partiels et complets sont pris en compte. Un remboursement global sans détail produit ne permet pas de répartir les quantités et montants remboursés entre les articles ; un avertissement le précise. Changer seulement le statut en « Remboursée » ne renseigne pas un montant réel de remboursement.
- **Devises :** les agrégats commerciaux, clients et marketing utilisent uniquement la devise de la boutique. Les commandes dans d’autres devises sont signalées et exclues des sommes ; leur détail reste consultable dans les commandes.
- **Clients :** rapprochement par e-mail de commande, y compris pour les invités. Les achats, dépenses et dates sont limités à la période. « Récurrent » signifie plusieurs commandes payées dans cette période ; ce n’est pas une valeur client à vie.
- **Marge :** saisir, dans chaque commande, les quatre coûts historiques hors taxes : produits, expédition, emballage et frais de paiement. Un champ vide signifie « inconnu » ; zéro signifie « aucun coût ». La marge reste indisponible si un coût manque. Elle déduit ces coûts du chiffre d’affaires net, hors publicité et charges fixes. Après un retour, ajuster les coûts aux montants réellement supportés.
- **Stocks :** une quantité vide peut correspondre à un produit dont le stock n’est pas géré. Plusieurs variations peuvent partager le stock d’un parent : ces quantités ne s’additionnent pas.

Les analyses agrégées et recherches étendues sont limitées à 5 000 commandes. Au-delà, un avertissement indique que les résultats sont partiels et invite à réduire la période ; la marge complète n’est pas annoncée. La liste simple des commandes reste paginée. Le résumé commercial peut être conservé en cache pendant 60 secondes ; les modifications de commandes invalident ce cache.

Les exports de commandes et clients portent sur toute la période, indépendamment de la recherche ou du filtre de statut affichés. L’export des colis applique au contraire les filtres visibles (recherche, transporteur, état, sens et priorité). Ils sont limités à 5 000 lignes. Le SAV affiche les 200 demandes les plus récemment mises à jour ; les compteurs couvrent toutes les demandes. Le marketing limite le détail visible à 5 000 dépenses/campagnes et le signale ; son total de dépenses inclut toutes les saisies correspondantes.

## Audience et consentement

Tant que l’extension Meta n’est pas connectée, le bandeau ne porte que sur la mesure d’audience. La mesure locale commence après **Accepter**. **Refuser** laisse la navigation possible sans comptage. Le bouton **Mes choix de mesure d’audience** permet de revenir sur ce choix. Les visites de l’administration et des gestionnaires sont exclues du script de mesure.

Les identifiants d’audience sont pseudonymes. La table d’événements ne conserve pas les e-mails clients, les adresses IP, les paramètres complets des URL ou les agents utilisateurs. Les commandes commerciales restent naturellement dans WooCommerce avec les informations nécessaires à leur traitement.

La préférence est mémorisée jusqu’à 180 jours, l’identifiant visiteur jusqu’à 30 jours et la session pendant 30 minutes d’inactivité. Les événements sont conservés 90 jours par défaut, réglables entre 30 et 365 jours, puis supprimés par la planification WordPress. Désactiver la mesure arrête les nouvelles collectes ; cela ne supprime pas immédiatement l’historique conservé.

La conversion correspond aux visiteurs mesurés dans la période ayant aussi un achat payé tracé dans cette période, divisés par les visiteurs mesurés. L’achat peut être enregistré jusqu’à sept jours après la création d’une commande consentie. La navigation refusée, les bloqueurs, plusieurs appareils et les paiements plus tardifs limitent la couverture. Les étapes du parcours sont des nombres d’événements, pas des personnes uniques à chaque étape. Les commandes et ajouts au panier de la maquette sont exclus des achats et ajouts WooCommerce.

## Publicité Meta (Facebook et Instagram)

La publicité Meta repose sur l’extension officielle **Meta for WooCommerce** (éditeur : Meta, validée ici en version 3.7.9). Elle fournit le pixel, l’API Conversions (envois depuis le serveur) et la synchronisation du catalogue. Belmains CRM n’envoie rien lui-même à Meta : il décide, visite par visite, si l’extension a le droit de fonctionner.

**Pourquoi ce verrou.** Laissée seule, l’extension charge le script de Meta et envoie un événement serveur dès la première page, avant toute réponse au bandeau. Constaté sur l’installation isolée : pour un visiteur sans accord, un `PageView` part du serveur avec son adresse IP et son navigateur. Le CRM l’en empêche.

**Fonctionnement.** La décision est prise sur le serveur à chaque requête, par les filtres officiels `facebook_for_woocommerce_integration_pixel_enabled` et `facebook_signals_held`. Sans accord publicitaire, l’extension ne construit pas son suivi : aucun script, aucun cookie Meta, aucun appel à Meta ni à un CDN, aucun événement serveur. L’accord est le cookie `bcrm_ads=yes`, posé par le bandeau ; toute autre valeur vaut refus. L’extension reste aussi fermée pour un gestionnaire connecté et tant que la vitrine d’avant ouverture est en place, même si un accord existe.

**Consentement.** La publicité est une finalité distincte de la mesure d’audience. Dès que l’extension est connectée, le bandeau propose **Tout accepter**, **Tout refuser** et **Personnaliser** (deux cases non cochées). Le refus est aussi simple que l’accord, le choix est conservé six mois et reste modifiable par le lien **Gérer mes choix de confidentialité**. Un visiteur qui avait déjà répondu pour l’audience est interrogé à nouveau pour cette nouvelle finalité. Avec un signal de refus du navigateur (Global Privacy Control, Do Not Track), le bandeau ne s’ouvre pas seul et rien n’est chargé.

Comme la décision est prise sur le serveur, la page est **rechargée une fois** juste après l’accord : c’est la page rechargée qui porte le pixel. Le formulaire de commande n’est jamais rechargé (la saisie serait perdue) ; l’accord y prend effet à la page suivante. Le retrait révoque le pixel immédiatement, supprime les cookies `_fbp` et `_fbc`, et les pages suivantes sont servies sans l’extension.

**Événements, après accord uniquement** : `PageView`, `ViewContent`, `AddToCart`, `InitiateCheckout` et `Purchase`. Chacun part du navigateur et du serveur avec le même identifiant, pour que Meta ne le compte qu’une fois ; l’ajout au panier part du serveur seulement, la boutique redirigeant aussitôt vers le panier. Deux réglages propres à Belmains complètent l’extension :

- La page d’accueil est la fiche produit de la boutique, mais l’extension ne reconnaît que le gabarit produit de WooCommerce. Le CRM lui fait déclarer `ViewContent` pour le produit affiché sur l’accueil.
- D’origine, l’extension déclare l’achat dès la création de la commande, paiement encore en attente : un paiement refusé ou abandonné compte alors comme une vente (constaté sur l’installation isolée). Le CRM retire ces deux déclenchements anticipés ; l’achat est déclaré depuis la page de confirmation seulement. Le filtre `bcrm_meta_purchase_on_confirmation_only` (renvoyer `false`) rétablit le comportement d’origine.

**Données transmises à Meta, après accord.** Pages vues, produit consulté, panier, commande (produits, montant TTC, devise, numéro de commande), adresse IP, navigateur et identifiants publicitaires Meta. Si la correspondance avancée automatique est activée sur le pixel dans le Gestionnaire d’événements, l’extension joint à l’achat les coordonnées de facturation hachées (e-mail, nom, téléphone, ville, code postal, pays). L’identifiant de produit est l’identifiant WooCommerce du produit : laisser l’extension créer et alimenter le catalogue plutôt que le saisir à la main.

**Mise en service, dans cet ordre.**

1. Publier cette version du CRM. Sans l’extension, elle ne change rien pour les visiteurs.
2. Mettre à jour la politique de confidentialité : un texte est proposé dans **Réglages → Confidentialité → guide de politique**.
3. Installer et activer **Meta for WooCommerce** depuis **Extensions → Ajouter**. La publication automatique ne l’installe pas et ne la met pas à jour.
4. La connecter dans **Marketing → Facebook** avec un compte administrateur de l’entreprise Meta : choisir la page, le compte publicitaire, le pixel et le catalogue. Aucun identifiant n’est à saisir dans le CRM.
5. Contrôler dans **Belmains CRM → Connexions** : l’état passe à « En attente de l’ouverture », puis « Actif après accord » une fois la vitrine ouverte. Le code de vérification du domaine, si Meta Business le demande, se colle au même endroit.

Ne jamais connecter l’extension sur un site où ce verrou n’est pas publié : elle mesurerait tous les visiteurs sans leur accord. Les gestionnaires connectés ne sont jamais mesurés : pour contrôler les événements, utiliser une fenêtre privée non connectée, puis l’outil de test d’événements de Meta.

**Limites à connaître.** Les refus et les bloqueurs réduisent le nombre d’événements : les chiffres de Meta resteront inférieurs aux commandes réelles de WooCommerce. Un client qui paie mais ne revient pas sur la page de confirmation n’est pas déclaré comme achat. Avec un cache de pages, une page gardée en cache est servie sans l’extension, y compris à un visiteur qui a accepté ; le CRM marque les pages servies avec l’extension comme privées (`Cache-Control: private`, `DONOTCACHEPAGE`) pour qu’elles ne soient jamais servies à quelqu’un d’autre. Le verrou s’appuie sur des filtres et des points d’accroche de l’extension : relancer `tests/meta-integration.php` sur l’installation isolée avant d’accepter une mise à jour de celle-ci.

Validation : `tests/meta-integration.php` (62 contrôles sur la base isolée avec l’extension officielle active, 17 sans elle) et `tests/meta-consent-ui.cjs` (26 contrôles DOM). Aucun de ces essais ne contacte Meta : les appels sont interceptés et comptés.

## Marketing manuel

Dans **Marketing**, ajouter les dépenses publicitaires hors taxes, leur date, source et campagne. Pour corriger une saisie, supprimer la ligne concernée puis ajouter la bonne valeur. Aucun compte publicitaire n’est interrogé et aucun budget publicitaire n’est modifié.

La source et la campagne sont enregistrées sur la commande à partir de la navigation consentie. Utiliser des noms cohérents dans les liens, par exemple `utm_source=google&utm_campaign=belmains_rentree` ; préférer lettres, chiffres, tirets, points et traits de soulignement. Reprendre ces noms dans la dépense. Les noms de campagne distinguent majuscules et minuscules.

Le ROAS observé est le chiffre d’affaires attribué hors taxes et après remboursements divisé par les dépenses renseignées. Il reste indisponible sans attribution ou dépense. Le ROAS global est également masqué lorsque des commandes attribuées proviennent de sources/campagnes sans coût correspondant : les ventes directes ou organiques ne doivent pas gonfler le rendement publicitaire. Le ROAS par campagne reste consultable lorsqu’elle dispose de données suffisantes. Ce ratio ne mesure ni le bénéfice, ni un ROI complet, ni l’ensemble des ventes influencées par une publicité.

Cette version n’envoie pas de relances de panier abandonné, ne synchronise pas les statistiques des régies et ne suit pas les ouvertures d’e-mails.

## Expéditions et raccordement Iziship

**Expéditions** gère jusqu’à 50 colis par commande, envoi ou retour, chacun avec son transporteur, son numéro, son état et ses dates. Les liens Chronopost, Colissimo et La Poste utilisent l’outil officiel La Poste. Mondial Relay, DPD, DHL, UPS, GLS et les autres transporteurs peuvent recevoir un lien HTTPS fourni par le logisticien. Un lien de suivi ouvre le site du transporteur ; il n’importe pas ses événements.

Le détail de commande permet d’ajouter ou modifier un colis, l’archiver et le restaurer, conserver un lien privé vers son étiquette, renseigner le relais confirmé et une note interne. Le message client est préparé à copier, sans envoi automatique. Le bon de préparation imprimable n’est pas une étiquette d’affranchissement. Les adresses de retour Chronopost et Colissimo confirmées par Iziship sont présentées selon le transporteur ; elles ne changent pas l’origine espagnole des expéditions.

Les compteurs couvrent les colis actifs de la période de création des commandes, avant les filtres. La liste place en premier les erreurs d’import, incidents, délais dépassés et numéros manquants. Un retard utilise la date prévue renseignée ou, à défaut, la date d’expédition plus le délai interne en jours calendaires. Ce repère ne prouve pas un incident. Le statut commercial de la commande reste distinct et visible.

La remontée existante de **`tracking_number`** via l’API WooCommerce reste reconnue. Un nouveau numéro est conservé comme colis supplémentaire ; un numéro répété ne recrée pas le colis. La présence du numéro ne prouve pas l’expédition. « Terminée » dans WooCommerce ne prouve pas la livraison. Les états de transport sont renseignés par l’équipe ou importés au moyen du contrat facultatif décrit dans [SHIPPING.md](SHIPPING.md).

Le contrat d’événements Belmains est préparé mais **aucune compatibilité native Iziship n’est annoncée sans essai**. Il faut convenir avec leur équipe des données réellement envoyées, du transporteur et de la correspondance des références. Le [guide WooCommerce d’Iziship](https://wiki.iziship.co/article/01-Votre-boutique-en-ligne/02-WooCommerce/01-guide-connexion) reste la référence pour leurs accès. Une clé dédiée lecture/écriture, transmise par un canal sécurisé, permet leur raccordement ; aucune clé n’est créée ni transmise par le CRM.

Les états reçus sont horodatés, dédoublonnés et conservés dans l’historique. Un événement ancien ne remplace pas un état plus récent. Les sauvegardes concurrentes sont refusées pour éviter une perte de données. L’interface montre les 100 derniers événements reçus et exporte l’historique privé complet, jusqu’à 1 000 événements par commande. Les erreurs d’import apparaissent sur la commande et dans le filtre « Import à vérifier ».

Le suivi de tous les colis actifs est ajouté aux vues de commande autorisées et aux e-mails WooCommerce existants. Notes internes, étiquettes et diagnostics techniques sont exclus des informations client. Aucune saisie de suivi ne déclenche un e-mail, un remboursement, une commande d’expédition, un achat d’étiquette, un changement de stock ou de paiement. L’interrogation automatique des transporteurs et le sélecteur de relais au paiement restent à raccorder au logisticien.

## Données privées et maintenance

L’administration et les exports nécessitent les droits de gestion de WooCommerce ou d’administration du site. Les préférences générales sont réservées aux administrateurs. Les demandes du navigateur utilisent l’authentification WordPress et son jeton de session ; les réponses privées ne sont pas mises en cache publiquement. Le seul point de collecte public accepte des événements d’audience bornés, après consentement, sans donner accès aux rapports ni aux clients.

Sauvegarder la base avec les commandes et les tables du CRM : événements, demandes SAV et dépenses marketing. Les exports CSV contiennent des données clients et doivent rester dans les espaces autorisés. Les règles de conservation des commandes WooCommerce, du SAV et des dépenses restent à définir pour la boutique ; le réglage de rétention du CRM concerne seulement l’audience.

Désactiver **Belmains CRM** retire son interface, arrête son script de mesure et sa purge planifiée, sans effacer ses données ni les commandes. Réactiver le module rétablit la programmation. Ne pas utiliser le site commercial pour créer de faux visiteurs, commandes ou clients destinés à remplir les graphiques ; les essais doivent rester identifiés et séparés des données d’exploitation.

Pour poursuivre le développement, travailler dans ce dossier, conserver les appels WooCommerce CRUD pour les commandes et valider les changements sur une copie du site. Contrôler notamment les permissions, remboursements, consentements, coûts inconnus et états de suivi avant diffusion. Le contrat des interfaces figure dans `../crm-contract.md`.


## Rétractation en ligne

Depuis le 19 juin 2026, une boutique qui vend en ligne à des consommateurs doit leur permettre de se rétracter par une fonction en ligne. Le module la fournit avec le code court `[belmains_retractation]` :

- le lien **« Renoncer au contrat ici »** apparaît dans les deux pieds de page du thème, dans les e-mails de commande adressés au client et sur ses pages de commande, dès que la page est publiée et que les ventes sont ouvertes ;
- le client indique son nom, son e-mail et son numéro de commande, puis clique sur **« Confirmer la rétractation »** ;
- la déclaration est enregistrée dans le SAV en priorité haute ; un accusé de réception reprenant sa date, son heure et son contenu part aussitôt à l’adresse indiquée, et la boutique est prévenue par e-mail ;
- si l’accusé de réception n’a pas pu partir, le client le lit à l’écran et la demande passe en priorité urgente : il faut alors le lui adresser à la main.

Toute déclaration est enregistrée, que la commande soit retrouvée ou non : la commande n’est rattachée que si son numéro **et** l’e-mail de facturation correspondent, et rien n’indique au visiteur si une commande existe. Les délais légaux (retour, remboursement sous quatorze jours) courent à partir de cette déclaration : traiter ces demandes sans attendre.

La page est créée en brouillon par `wordpress/tools/publish-legal.php` et publiée par l’ouverture des ventes. Avant l’ouverture, ni lien ni formulaire ne sont proposés.

## Formulaire de contact et données du SAV

Créer une page publiée contenant `[belmains_contact]`, puis enregistrer son ID dans `belmains_contact_page_id`. Le script de préparation `../tools/prepare-launch.php` le fait explicitement ; rien n’est créé à l’activation du plugin. Les liens du thème pointent vers cette page. Le formulaire utilise WordPress, sans service externe ni abonnement.

Le nom, l’e-mail, le sujet, le message et l’éventuelle référence saisie sont enregistrés dans un ticket ouvert de priorité normale. Aucun rattachement à une commande n’est réalisé automatiquement. Les champs sont validés côté serveur ; le ticket est accessible seulement aux gestionnaires autorisés. Les réponses au client restent manuelles et aucun e-mail n’est déclenché par une soumission ou une note du SAV.

L’antipourriel utilise un champ piège, un contrôle de formulaire/origine, des quotas temporaires par IP hachée et e-mail haché, et un jeton contre les doubles envois. L’IP brute n’est pas conservée. Les valeurs à corriger restent au maximum dix minutes dans un état temporaire privé associé au navigateur. Aucun nom, e-mail ou message ne passe dans l’URL de retour. La page interdit le cache via WordPress ; l’exclure également dans le cache de l’hébergeur.

Dans **Outils → Exporter les données** et **Effacer les données**, le CRM ajoute les tickets associés à l’e-mail vérifié selon la procédure WordPress. L’export est paginé ; l’effacement anonymise le sujet, le message, l’e-mail et la référence de commande du ticket, tout en conservant ses dates et son état. Les commandes et la conservation commerciale restent gérées séparément par WooCommerce. Aucun effacement n’est lancé automatiquement par le module ; la durée de conservation du SAV doit être définie avant l’ouverture.
