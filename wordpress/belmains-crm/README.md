# Belmains CRM

Belmains CRM ajoute un espace de pilotage privé dans WordPress pour suivre la boutique WooCommerce, son audience consentie, ses clients et ses expéditions. Le module fonctionne sans abonnement CRM, sans bibliothèque distante et sans extension de suivi payante. L’hébergement, les paiements, la publicité et les prestations Iziship conservent leurs propres coûts.

Version 0.2.0 — WordPress 6.3 minimum, PHP 8.0 minimum. WooCommerce est nécessaire pour les commandes, clients, stocks et expéditions. Le module utilise les interfaces WooCommerce et déclare sa compatibilité avec le stockage des commandes HPOS.

## Installation et premier accès

1. Sauvegarder les fichiers et la base de données du WordPress cible.
2. Installer et activer WooCommerce, puis vérifier la devise, le fuseau horaire, les taxes et les produits de la boutique.
3. Copier le dossier `belmains-crm/` dans `wp-content/plugins/`, ou importer son archive dans **Extensions → Ajouter une extension → Téléverser une extension**. Activer **Belmains CRM**.
4. Ouvrir **Belmains CRM** dans le menu d’administration avec un compte administrateur ou gestionnaire de boutique.
5. Choisir une période et consulter **Connexions**. Les préférences sont modifiables par un administrateur : mesure d’audience, conservation, seuil de stock bas et délai de livraison de référence.

L’activation crée les tables propres au CRM et programme la purge de l’audience. Elle ne crée aucun produit, client, commande, témoignage ou chiffre de démonstration. Sans données réelles, les états vides, les zéros et les valeurs indisponibles sont normaux.

Le site de préparation actuel est `http://belmains.local/`. Le thème Belmains 0.3.0 et le plugin séparé Belmains Commerce raccordent la fiche produit au panier et à la commande WooCommerce. Le CRM exploite ces commandes ; il ne configure pas le paiement. Les moyens de paiement réels, Iziship et la fiscalité restent à finaliser avant l’ouverture des ventes.

## Les huit rubriques

| Rubrique | Ce qu’elle permet de suivre ou de faire |
|---|---|
| **Vue d’ensemble** | Commandes, chiffre d’affaires net, remboursements, panier moyen, unités vendues, clients récurrents, marge selon les coûts connus ; courbes quotidiennes ; visiteurs, sessions, sources, appareils et étapes d’achat mesurées. |
| **Commandes** | Recherche, filtres et pagination ; détail des articles et coordonnées ; coûts historiques ; notes internes ; suivi du colis ; accès à la commande WooCommerce ; export CSV de la période. |
| **Clients** | Clients inscrits et invités, e-mail et téléphone, commandes et dépenses sur la période, première et dernière commande de cette période, segment et export CSV. |
| **Expéditions** | Numéro et lien de suivi, transporteur, dates et état du colis ; compteurs d’expéditions, livraisons, incidents et retours ; repère de dépassement du délai interne. |
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

Les exports portent sur toute la période, indépendamment de la recherche ou du filtre de statut affichés. Ils sont limités à 5 000 lignes. Le SAV affiche les 200 demandes les plus récemment mises à jour ; les compteurs couvrent toutes les demandes. Le marketing limite le détail visible à 5 000 dépenses/campagnes et le signale ; son total de dépenses inclut toutes les saisies correspondantes.

## Audience et consentement

La mesure locale commence après **Accepter**. **Refuser** laisse la navigation possible sans comptage. Le bouton **Mes choix de mesure d’audience** permet de revenir sur ce choix. Les visites de l’administration et des gestionnaires sont exclues du script de mesure.

Les identifiants d’audience sont pseudonymes. La table d’événements ne conserve pas les e-mails clients, les adresses IP, les paramètres complets des URL ou les agents utilisateurs. Les commandes commerciales restent naturellement dans WooCommerce avec les informations nécessaires à leur traitement.

La préférence est mémorisée jusqu’à 180 jours, l’identifiant visiteur jusqu’à 30 jours et la session pendant 30 minutes d’inactivité. Les événements sont conservés 90 jours par défaut, réglables entre 30 et 365 jours, puis supprimés par la planification WordPress. Désactiver la mesure arrête les nouvelles collectes ; cela ne supprime pas immédiatement l’historique conservé.

La conversion correspond aux visiteurs mesurés dans la période ayant aussi un achat payé tracé dans cette période, divisés par les visiteurs mesurés. L’achat peut être enregistré jusqu’à sept jours après la création d’une commande consentie. La navigation refusée, les bloqueurs, plusieurs appareils et les paiements plus tardifs limitent la couverture. Les étapes du parcours sont des nombres d’événements, pas des personnes uniques à chaque étape. Les commandes et ajouts au panier de la maquette sont exclus des achats et ajouts WooCommerce.

## Marketing manuel

Dans **Marketing**, ajouter les dépenses publicitaires hors taxes, leur date, source et campagne. Pour corriger une saisie, supprimer la ligne concernée puis ajouter la bonne valeur. Aucun compte publicitaire n’est interrogé et aucun budget publicitaire n’est modifié.

La source et la campagne sont enregistrées sur la commande à partir de la navigation consentie. Utiliser des noms cohérents dans les liens, par exemple `utm_source=google&utm_campaign=belmains_rentree` ; préférer lettres, chiffres, tirets, points et traits de soulignement. Reprendre ces noms dans la dépense. Les noms de campagne distinguent majuscules et minuscules.

Le ROAS observé est le chiffre d’affaires attribué hors taxes et après remboursements divisé par les dépenses renseignées. Il reste indisponible sans attribution ou dépense. Le ROAS global est également masqué lorsque des commandes attribuées proviennent de sources/campagnes sans coût correspondant : les ventes directes ou organiques ne doivent pas gonfler le rendement publicitaire. Le ROAS par campagne reste consultable lorsqu’elle dispose de données suffisantes. Ce ratio ne mesure ni le bénéfice, ni un ROI complet, ni l’ensemble des ventes influencées par une publicité.

Cette version n’envoie pas de relances de panier abandonné, ne synchronise pas les statistiques des régies et ne suit pas les ouvertures d’e-mails.

## Iziship : compte actif, connexion à réaliser

Le compte Iziship est actif, mais la boutique locale en HTTP n’est pas joignable depuis Iziship. La présence du module ne signifie donc pas que la connexion fonctionne. Il faut d’abord une boutique WordPress accessible publiquement en HTTPS.

Le raccordement préparé utilise la remontée du numéro de suivi dans la métadonnée WooCommerce **`tracking_number`**, sans achat d’une extension de suivi. Le [guide WooCommerce d’Iziship](https://wiki.iziship.co/article/01-Votre-boutique-en-ligne/02-WooCommerce/01-guide-connexion) sert de référence pour la configuration avec leur équipe.

Une fois le domaine prêt :

1. Vérifier que l’adresse HTTPS de la boutique et son API WooCommerce sont accessibles à Iziship.
2. Créer dans **WooCommerce → Réglages → Avancé → API REST** une clé dédiée à Iziship, en lecture/écriture, pour un utilisateur autorisé.
3. Transmettre ces accès par le canal sécurisé convenu avec Iziship et demander la remontée dans `tracking_number`. Ne pas mettre les clés dans GitHub, les notes de commande ou un export.
4. Vérifier une expédition réelle ou un essai convenu avec Iziship : numéro reçu sur la bonne commande, visibilité dans le CRM et lien de suivi correct.

Aucune clé n’est créée ni transmise automatiquement par ce plugin. **Connexions** indique les écritures de suivi reçues via l’API WooCommerce ; cette observation seule ne prouve pas l’identité du service émetteur.

**Expédié et livré sont deux états distincts.** Iziship peut marquer la commande WooCommerce « Terminée » lors de l’expédition. Le CRM ne transforme pas cet état en livraison confirmée. Les états « En transit », « En point relais », « Livré », « Incident » et « Retourné », ainsi que leurs dates, se renseignent manuellement tant qu’une source d’événements transporteur n’est pas raccordée. Le délai de référence est un repère interne, pas une confirmation transporteur.

Si le transporteur ou le lien ne sont pas fournis, les compléter dans le détail de commande ; aucun lien n’est inventé. Le CRM présente un suivi principal par commande. Pour plusieurs colis, consulter également le détail WooCommerce. Le suivi est ajouté aux vues de commande autorisées et aux e-mails WooCommerce existants ; une modification manuelle du suivi n’envoie pas à elle seule un nouvel e-mail.

## Données privées et maintenance

L’administration et les exports nécessitent les droits de gestion de WooCommerce ou d’administration du site. Les préférences générales sont réservées aux administrateurs. Les demandes du navigateur utilisent l’authentification WordPress et son jeton de session ; les réponses privées ne sont pas mises en cache publiquement. Le seul point de collecte public accepte des événements d’audience bornés, après consentement, sans donner accès aux rapports ni aux clients.

Sauvegarder la base avec les commandes et les tables du CRM : événements, demandes SAV et dépenses marketing. Les exports CSV contiennent des données clients et doivent rester dans les espaces autorisés. Les règles de conservation des commandes WooCommerce, du SAV et des dépenses restent à définir pour la boutique ; le réglage de rétention du CRM concerne seulement l’audience.

Désactiver **Belmains CRM** retire son interface, arrête son script de mesure et sa purge planifiée, sans effacer ses données ni les commandes. Réactiver le module rétablit la programmation. Ne pas utiliser le site commercial pour créer de faux visiteurs, commandes ou clients destinés à remplir les graphiques ; les essais doivent rester identifiés et séparés des données d’exploitation.

Pour poursuivre le développement, travailler dans ce dossier, conserver les appels WooCommerce CRUD pour les commandes et valider les changements sur une copie du site. Contrôler notamment les permissions, remboursements, consentements, coûts inconnus et états de suivi avant diffusion. Le contrat des interfaces figure dans `../crm-contract.md`.


## Formulaire de contact et données du SAV

Créer une page publiée contenant `[belmains_contact]`, puis enregistrer son ID dans `belmains_contact_page_id`. Le script de préparation `../tools/prepare-launch.php` le fait explicitement ; rien n’est créé à l’activation du plugin. Les liens du thème pointent vers cette page. Le formulaire utilise WordPress, sans service externe ni abonnement.

Le nom, l’e-mail, le sujet, le message et l’éventuelle référence saisie sont enregistrés dans un ticket ouvert de priorité normale. Aucun rattachement à une commande n’est réalisé automatiquement. Les champs sont validés côté serveur ; le ticket est accessible seulement aux gestionnaires autorisés. Les réponses au client restent manuelles et aucun e-mail n’est déclenché par une soumission ou une note du SAV.

L’antipourriel utilise un champ piège, un contrôle de formulaire/origine, des quotas temporaires par IP hachée et e-mail haché, et un jeton contre les doubles envois. L’IP brute n’est pas conservée. Les valeurs à corriger restent au maximum dix minutes dans un état temporaire privé associé au navigateur. Aucun nom, e-mail ou message ne passe dans l’URL de retour. La page interdit le cache via WordPress ; l’exclure également dans le cache de l’hébergeur.

Dans **Outils → Exporter les données** et **Effacer les données**, le CRM ajoute les tickets associés à l’e-mail vérifié selon la procédure WordPress. L’export est paginé ; l’effacement anonymise le sujet, le message, l’e-mail et la référence de commande du ticket, tout en conservant ses dates et son état. Les commandes et la conservation commerciale restent gérées séparément par WooCommerce. Aucun effacement n’est lancé automatiquement par le module ; la durée de conservation du SAV doit être définie avant l’ouverture.
