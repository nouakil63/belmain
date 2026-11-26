# Validation de la logistique

Les essais doivent rester sur la base isolée `bcrm_validation_`, avec `WP_ENVIRONMENT_TYPE=local`. Ne jamais charger ces suites sur la boutique commerciale. Bloquer les e-mails avec `pre_wp_mail` et les requêtes externes (`WP_HTTP_BLOCK_EXTERNAL=true`), et désactiver le cron de cette copie.

Charger WordPress, puis successivement :

1. `crm-integration.php` : socle CRM et données de validation.
2. `tracking-form-integration.php` : accès au suivi client.
3. `shipping-integration.php` : colis, retours, historique, conflits, permissions et adaptateur. Les commandes créées par cette suite sont supprimées dans un bloc `finally`.

La troisième suite écrit `shipping-ui-fixture.json` dans le répertoire parent du WordPress de validation. Cette fixture contient uniquement les commandes artificielles de la suite. Elle ne doit pas être copiée depuis un export client réel.

Pour l’interface, utiliser Node avec `jsdom` 30 installé dans un environnement de test, puis :

```sh
node wordpress/tests/shipping-ui.cjs /chemin/vers/shipping-ui-fixture.json
```

Si les dépendances sont dans un répertoire de test séparé, renseigner `NODE_PATH` vers son `node_modules`. Les requêtes, téléchargements, presse-papiers et fenêtres d’impression sont simulés. Le test vérifie les interactions DOM, les formulaires, l’export filtré, le refus d’une sauvegarde périmée et l’absence de fuite des notes internes dans le message client. Il ne constitue pas une validation visuelle dans un navigateur ou sur un téléphone physique.

Résultat validé pour 0.3.0 : 55 contrôles CRM, 11 contrôles de suivi client, 68 contrôles logistiques et 21 contrôles DOM. L’installation en production a ensuite été contrôlée par des lectures authentifiées : correspondance des 16 fichiers, assets, réglages préservés, routes privées et maintien du mode catalogue. Aucun essai de production ne crée de paiement, de commande ou d’expédition.
