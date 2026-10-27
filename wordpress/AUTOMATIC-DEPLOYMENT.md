# Publication automatique Belmains

La branche de publication est `codex/version-3-bordeaux` du dépôt public `nouakil63/belmain`. Une modification locale n'est publiée qu'après commit et push sur cette branche, puis réussite du workflow `WordPress — validation et archives`.

## Chaîne de publication

1. GitHub vérifie PHP et JavaScript, exécute les tests isolés du déploiement et construit les trois ZIP.
2. Un second job, autorisé à écrire uniquement pour publier la livraison, dépose les trois archives et leurs SHA-256 dans une préversion `belmains-deploy-SHA_COMPLET`. Les PR ne publient rien.
3. Le workflow de notification, situé sur la branche GitHub par défaut, appelle l'entrée fixe `https://belmains.com/belmains-deploy-check.php` après la validation de la branche de publication. Il n'utilise ni secret ni code de la révision déclenchante.
4. Le récepteur utilise un programme PHP de confiance installé hors du Web, dans `~/belmains-deployment`. Il vérifie indépendamment la branche, le SHA, la réussite du workflow et les empreintes des archives. Il ignore tous les paramètres reçus.
5. Il compare le code en ligne à son état enregistré, sauvegarde les trois composants hors du Web, prépare les nouveaux fichiers et remplace uniquement le thème Belmains et les extensions Belmains Commerce et CRM.
6. L'accueil doit répondre 200, l'API anonyme doit conserver la réponse 503 de préparation et le CSS public doit correspondre aux octets déployés. Un échec entraîne le retour au code précédent, sans restauration de base.

Une tâche native Infomaniak, `Belmains - publication GitHub`, vérifie aussi toutes les quinze minutes en secours. Les e-mails de cette tâche sont désactivés. La notification GitHub est le déclenchement principal.

## Périmètre préservé

Les publications n'écrivent ni dans la base WordPress/WooCommerce, ni dans `uploads/`, `wp-config.php`, les autres extensions ou les MU plugins. L'empreinte du garde `belmains-staging-guard.php` doit rester identique. Les achats, paiements, notifications commerciales et expéditions restent fermés jusqu'à une demande explicite d'ouverture.

Un changement direct des fichiers en ligne bloque la publication suivante : comparer et réintégrer ce changement dans Git avant de réconcilier l'état. Ne pas remplacer aveuglément l'état enregistré. Les changements de version du CRM susceptibles de migrer son schéma doivent être validés dans un environnement isolé avant publication.

## Exploitation

- Programme fixé sur le serveur : `~/belmains-deployment/deploy-runtime.php`. Il ne se met pas à jour à partir du code candidat. Modifier ce programme ou le pont HTTP exige une installation contrôlée depuis une révision relue.
- État et journal de récupération : `current.json`, `pending.json` ; journal d'exécution : `run.log`, tous hors du Web.
- Sauvegardes de code conservées dans `backups/`. Surveiller leur volume et les archiver avant tout nettoyage.
- Les secrets et sessions ne sont enregistrés ni dans Git ni dans les archives.
- Pour suspendre une publication, désactiver le workflow de notification et la tâche native Infomaniak. Une révision ne passant pas les contrôles n'est pas déployée.
- Pour revenir à du code antérieur, publier un commit de retour sur la branche. Ne jamais restaurer automatiquement une ancienne base de production.

Le dossier `site/` est une ancienne vitrine statique. La production est WordPress ; une modification visuelle destinée au site doit être appliquée aux sources `wordpress/belmains/` ou aux extensions concernées. Les textes enregistrés avec l'éditeur WordPress restent dans sa base et ne sont pas remplacés par les archives.
