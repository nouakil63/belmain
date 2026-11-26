# TVA Belmains — taux standards de référence

`vat-eu27-standard.csv` contient les 27 taux normaux généraux de l'Union européenne, vérifiés le 27 octobre 2026 dans la [table officielle Your Europe](https://europa.eu/youreurope/business/finance-and-tax/vat/vat-rules-rates/index_en.htm) dont la date de vérification affichée est le 13 juillet 2026. Le code WooCommerce de la Grèce est GR.

Ces lignes ne décrivent pas toutes les exceptions régionales : les taux du Portugal continental diffèrent des Açores et de Madère ; certaines îles grecques ont un régime particulier ; plusieurs territoires sont hors du champ TVA UE. Voir le [périmètre territorial de la Commission](https://taxation-customs.ec.europa.eu/taxation/vat/vat-directive/how-does-vat-work/territorial-scope_en).

Les destinations actuellement autorisées par la boutique sont limitées à la France. L'import ne modifie ni ces pays ni les zones de livraison. Ne pas élargir les destinations avant validation des exceptions et du régime fiscal par le comptable. Le numéro de TVA britannique et une mention d'IOSS ne constituent pas une validation des immatriculations ou du régime applicable au stock en Espagne.

Commerce 0.2.2 garde les prix TTC constants (89,99 € pour un gant, 149,99 € pour deux) avec le [filtre officiel WooCommerce](https://woocommerce.com/document/keep-tax-inclusive-prices-across-locations/). La base de boutique, les prix, le stock et les commandes ne sont pas modifiés par l'import. Les taxes se calculent sur l'adresse de livraison et les montants restent affichés TTC. Le champ Shipping=1 est sans effet sur les frais de livraison actuellement gratuits ; une future livraison payante doit être validée séparément.

Le script `wordpress/tools/configure-vat.php` est un outil manuel WP-CLI avec sauvegarde privée et contrôles ; les ZIP du déploiement n'activent ni n'importent de taxes. Les tests `tax-integration.php`, `commerce-integration.php` et `vat-config-integration.php` ne doivent tourner que sur les tables isolées `bcrm_validation_`, jamais sur la boutique réelle.

## Application contrôlée

Après publication et vérification de Commerce 0.2.2, l'outil manuel s'exécute depuis la console officielle avec `BELMAINS_VAT_APPLY=1` et `wp eval-file wordpress/tools/configure-vat.php`, en ciblant le dossier WordPress réel. Il exige la vitrine protégée, les destinations France et la classe standard existante du produit ; il sauvegarde les taux et réglages dans `~/belmains-deployment/vat-backups/` avant écriture. Les lignes déjà identiques sont réutilisées et toute règle existante en conflit bloque l'import. Un échec supprime uniquement les lignes créées par cette tentative et restaure ses réglages précédents.

Validation du 27 octobre 2026 sur les tables locales isolées : 483 contrôles de TVA, 57 contrôles Commerce et 55 contrôles d'import/configuration, avec réseau externe, e-mails et cron bloqués. Ces suites ne s'exécutent jamais sur la boutique réelle. La vérification après application sur le serveur se limite aux réglages, aux recherches de taux et aux prix/stock/protections existants ; aucune commande n'est créée.
