# Publication automatique Belmains

La branche de publication est `codex/version-3-bordeaux` du dépôt public `nouakil63/belmain`. Une modification locale n'est publiée qu'après commit et push sur cette branche, puis réussite du workflow `WordPress — validation et archives`.

## Chaîne de publication

1. GitHub vérifie PHP et JavaScript, exécute les tests isolés du déploiement et construit les trois ZIP.
2. Un second job, autorisé à écrire uniquement pour publier la livraison, dépose les trois archives et leurs SHA-256 dans une préversion `belmains-deploy-SHA_COMPLET`. Les PR ne publient rien.
3. Le workflow de notification, situé sur la branche GitHub par défaut, appelle l'entrée fixe `https://belmains.com/belmains-deploy-check.php` après la validation de la branche de publication. Il n'utilise ni secret ni code de la révision déclenchante.
4. Le récepteur utilise un programme PHP de confiance installé hors du Web, dans `~/belmains-deployment`. Il vérifie indépendamment la branche, le SHA, la réussite du workflow et les empreintes des archives. Il ignore tous les paramètres reçus.
5. Il compare le code en ligne à son état enregistré, sauvegarde les trois composants hors du Web, prépare les nouveaux fichiers et remplace uniquement le thème Belmains et les extensions Belmains Commerce et CRM.
6. L'accueil doit répondre 200 et le CSS public doit correspondre aux octets déployés. L'API anonyme doit répondre selon l'état enregistré de la boutique : 503 de préparation tant que la vitrine est fermée, réponse normale une fois les ventes ouvertes. Un échec entraîne le retour au code précédent, sans restauration de base.

Une tâche native Infomaniak, `Belmains - publication GitHub`, vérifie aussi toutes les quinze minutes en secours. Les e-mails de cette tâche sont désactivés. La notification GitHub est le déclenchement principal.

## Périmètre préservé

Les publications n'écrivent ni dans la base WordPress/WooCommerce, ni dans `uploads/`, `wp-config.php`, les autres extensions ou les MU plugins. L'empreinte du garde `belmains-staging-guard.php` doit rester identique, que la boutique soit fermée ou ouverte. Les achats, paiements, notifications commerciales et expéditions restent fermés jusqu'à une demande explicite d'ouverture, exécutée avec l'outil décrit plus bas.

Un changement direct des fichiers en ligne bloque la publication suivante : comparer et réintégrer ce changement dans Git avant de réconcilier l'état. Ne pas remplacer aveuglément l'état enregistré. Les changements de version du CRM susceptibles de migrer son schéma doivent être validés dans un environnement isolé avant publication.

## Ouverture des ventes

L'ouverture ne se fait jamais en supprimant le garde à la main : le récepteur exige ce fichier, compare son empreinte à l'état enregistré et, tant qu'il attend une boutique fermée, annulerait chaque publication suivante. Elle passe par l'outil `wordpress/tools/open-shop.php`, lancé en ligne de commande depuis la copie relue `~/belmains-deployment/source/wordpress/tools`, avec `php open-shop.php` ou `wp eval-file open-shop.php`.

| Commande | Effet |
|---|---|
| `BELMAINS_SHOP=check` | Rapport en lecture seule. Ne modifie rien. Code de sortie 1 s'il reste un point bloquant. |
| `BELMAINS_SHOP=open BELMAINS_SHOP_REVISION=SHA_COMPLET` | Ouvre les ventes. |
| `BELMAINS_SHOP=close BELMAINS_SHOP_REVISION=SHA_COMPLET` | Remet la vitrine d'avant ouverture. |

Le rapport contrôle le récepteur (révision, dérive, copie relue), les moyens de paiement activés, la TVA, la livraison, le produit et son stock, les quatre pages légales, les textes de l'éditeur qui deviendront publics, le mode « bientôt disponible » de WooCommerce, le référencement, les e-mails, la publicité Meta, une éventuelle balise Google hors bandeau et les tâches de fond en attente. Un point bloquant empêche l'ouverture ; rien n'est alors modifié. Sont bloquants : aucun moyen de paiement, TVA non calculée, aucune livraison, produit absent ou épuisé, page légale absente, presque vide ou encore à l'état de modèle (mention « Document de travail » ou champ entre crochets), politique de confidentialité sans mention de Meta alors que le pixel est connecté, compte Google Ads relié.

Options de l'ouverture : `BELMAINS_SHOP_ENABLE_GATEWAYS=id,id` active les moyens de paiement nommés ; `BELMAINS_SHOP_INDEX=1` rend le site indexable ; `BELMAINS_SHOP_ALLOW=taxes,google` accepte un point bloquant nommé après une décision explicite.

L'ouverture prend les deux verrous du récepteur, sauvegarde le garde, l'état, le moteur de confiance et les réglages dans `opening-backups/` hors du Web, puis :

1. remplace le moteur de confiance `~/belmains-deployment/deploy-runtime.php` par celui de la révision relue, qui sait reconnaître une boutique ouverte ;
2. remplace le garde par `tools/open-guard.php`, qui ne restreint rien, et enregistre dans `current.json` sa nouvelle empreinte et `"mode":"open"` ;
3. désactive le mode « bientôt disponible », rattache les pages CGV et confidentialité à la commande et au bandeau, publie les pages légales en brouillon et active les moyens de paiement nommés ;
4. vérifie que le récepteur accepte le nouvel état, puis attend que l'API publique réponde normalement (le serveur garde le PHP compilé quelques secondes).

Un échec en cours de route rétablit le garde, l'état, le moteur et les réglages ; ce qui n'a pas pu être rétabli est nommé. La fermeture remet `tools/public-catalog-guard.php` et l'état de préparation ; les pages publiées et les réglages restent en place, le garde les masque.

L'outil refuse tout autre site que `https://belmains.com`, sauf la copie isolée `bcrm_validation_`. Il ne passe aucune commande, n'encaisse rien et ne modifie ni prix ni fiscalité : la première commande réelle et son remboursement restent un essai manuel, à faire juste après l'ouverture. `tests/open-shop-integration.php` rejoue l'ensemble sur la copie isolée, avec un état de déploiement jetable.

## Exploitation

- Programme fixé sur le serveur : `~/belmains-deployment/deploy-runtime.php`. Il ne se met pas à jour à partir du code candidat. Modifier ce programme ou le pont HTTP exige une installation contrôlée depuis une révision relue.
- État et journal de récupération : `current.json`, `pending.json` ; journal d'exécution : `run.log`, tous hors du Web.
- Sauvegardes de code conservées dans `backups/`. Surveiller leur volume et les archiver avant tout nettoyage.
- Les secrets et sessions ne sont enregistrés ni dans Git ni dans les archives.
- Pour suspendre une publication, désactiver le workflow de notification et la tâche native Infomaniak. Une révision ne passant pas les contrôles n'est pas déployée.
- Pour revenir à du code antérieur, publier un commit de retour sur la branche. Ne jamais restaurer automatiquement une ancienne base de production.

Le dossier `site/` est une ancienne vitrine statique. La production est WordPress ; une modification visuelle destinée au site doit être appliquée aux sources `wordpress/belmains/` ou aux extensions concernées. Les textes enregistrés avec l'éditeur WordPress restent dans sa base et ne sont pas remplacés par les archives.

## Galerie publique pendant la préparation

La galerie est une exception étroite dans le garde autoritaire `tools/public-catalog-guard.php` : seule la page configurée par `belmains_gallery_page_id`, publiée avec le template `page-galerie.php`, sans mot de passe ni aperçu, est accessible en GET/HEAD. Les flux, intégrations embarquées, autres pages, écritures REST, achats, paiements, e-mails et indexation restent protégés. Le thème ne retire ni ne contourne le garde.

Le garde n'est toujours pas installé par les trois ZIP. Après publication du thème et vérification de la révision déployée, une installation contrôlée passe par le checkout relu `~/belmains-deployment/source/wordpress/tools/publish-gallery.php`, exécuté avec WP-CLI et les deux paramètres explicites `BELMAINS_GALLERY_PUBLISH=1` et `BELMAINS_GALLERY_REVISION=SHA_COMPLET`.

Cet outil vérifie la révision, l'empreinte du garde et les trois manifestes avant tout changement. Il prend les verrous du récepteur dans le même ordre, refuse une récupération en attente, sauvegarde le garde, l'état et la page/option dans `gallery-backups/` hors du Web, puis crée la nouvelle page en brouillon. Il installe atomiquement le garde vérifié, met à jour uniquement `current.json.guard_sha256` en conservant la révision et tous les manifestes, et publie la page en dernier. Une page existante étrangère au mécanisme ne peut pas être écrasée. En cas d'échec, le garde, l'état, l'option et le statut antérieur sont restaurés ; une nouvelle page passe à la corbeille sans suppression définitive.

Ne jamais réinitialiser toute la référence ou ignorer une différence pour faire accepter ce changement. Le programme de déploiement de confiance reste inchangé et continue d'exiger l'égalité stricte avec la nouvelle empreinte approuvée. Les tests `gallery-access-integration.php` s'exécutent uniquement sur l'installation locale `bcrm_validation_`, réseau externe et cron bloqués ; les contrôles publics finaux se limitent à GET/HEAD et aux lectures des protections.
