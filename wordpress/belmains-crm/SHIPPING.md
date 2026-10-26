# Logistique Belmains 0.3.0

## Périmètre

Le module enregistre les colis et retours d’une commande WooCommerce. Il ne réserve pas de transport, n’achète pas d’affranchissement, ne choisit pas de relais au paiement et n’interroge pas les API des transporteurs. Les numéros, états, dates et relais proviennent d’un gestionnaire autorisé ou d’un fournisseur raccordé. Aucune donnée de démonstration n’est installée.

Les états sont `pending`, `label_created` (numéro reçu, sans preuve d’expédition), `shipped`, `in_transit`, `out_for_delivery`, `relay`, `delivered`, `exception`, `returned`, `cancelled`. Plusieurs colis livrés seulement en partie produisent le résumé « Partiellement livré ». Le statut commercial WooCommerce, le paiement et le stock ne sont jamais modifiés par ce module.

Chronopost, Colissimo et La Poste utilisent [l’outil officiel de suivi La Poste](https://www.laposte.fr/conseils-pratiques/suivre-une-lettre-un-colissimo-un-envoi-express). Les autres transporteurs acceptent une URL HTTPS publique fournie. Le système ouvre ces liens ; il ne copie pas leurs données.

## Compatibilité avec les commandes existantes

Une lecture ne déclenche aucune migration de base. Avant la première modification, le dossier reprend le suivi historique `_bcrm_tracking_*`, `tracking_number` et les éléments existants de `_wc_shipment_tracking_items`. Les anciennes métadonnées ne sont pas supprimées. Après création du dossier, les colis sont conservés dans `_bcrm_shipping_v2` avec une révision et un historique.

Une écriture REST WooCommerce sur `tracking_number` continue d’être observée. Un numéro nouveau ajoute un colis ; une répétition ne crée pas de doublon et ne fait pas reculer un état reçu. Pour corriger une faute dans un numéro existant, modifier ce colis dans le CRM : écrire un autre numéro dans le champ historique le traite comme un autre colis. Les changements futurs de `_wc_shipment_tracking_items` après création du dossier ne constituent pas un flux d’événements ; ils nécessitent un adaptateur explicite.

## Contrat facultatif pour un adaptateur

Ce format appartient à Belmains. **Il ne décrit pas une API native Iziship confirmée.** L’équipe du logisticien doit confirmer ses champs et installer ou convenir de l’adaptation. La connexion existante par numéro reste utilisable sans cet enrichissement.

Un service autorisé utilise l’API REST standard WooCommerce sur HTTPS : `PUT /wp-json/wc/v3/orders/{id}`. Il transmet une liste dans `meta_data` :

```json
{
  "meta_data": [{
    "key": "_belmains_shipping_events",
    "value": [{
      "event_id": "logisticien-evenement-unique",
      "parcel_id": "logisticien-colis-stable",
      "occurred_at": "2026-10-14T12:00:00Z",
      "status": "in_transit",
      "number": "NUMERO_REEL_FOURNI_PAR_LE_TRANSPORTEUR",
      "carrier_code": "colissimo",
      "direction": "outbound"
    }]
  }]
}
```

Ne pas envoyer cet exemple à une commande réelle. Utiliser un environnement d’essai et les véritables identifiants fournis par la source.

- `event_id` : stable, unique pour cette commande, 1 à 100 caractères parmi lettres ASCII, chiffres, point, tiret, deux-points et soulignement.
- `parcel_id` : stable, même alphabet, 1 à 80 caractères. Réutiliser l’identifiant d’un colis déjà connu ; ne pas réimporter son numéro sous un autre identifiant.
- `occurred_at` : date ISO complète avec secondes et fuseau (`Z` ou `+02:00`). Une date à plus de cinq minutes dans le futur est refusée.
- `status` : l’un des états ci-dessus. Un numéro est requis sauf pour `pending` ou `cancelled`.
- `direction` : `outbound` ou `return` ; préciser `return` pour un retour.
- `carrier_code` : `chronopost`, `colissimo`, `laposte`, `mondial_relay`, `dpd`, `dhl`, `ups`, `gls`, `other`. `carrier` précise le nom si `other`.
- Champs facultatifs : `url` (HTTPS public), `shipped_at`, `delivered_at`, `expected_at` (dates `AAAA-MM-JJ`), `relay_name`, `relay_address`. Les dates effectives d’expédition et livraison ne peuvent pas être futures. Un événement `shipped` ou `delivered` renseigne sa date depuis `occurred_at` si elle n’est pas fournie.

Les lots contiennent 1 à 50 événements et sont validés entièrement avant modification. Rejouer exactement le même événement est sans effet. Un identifiant réutilisé avec un contenu différent est rejeté. Les événements reçus dans le désordre restent dans l’historique, mais un événement antérieur ne remplace pas l’état le plus récent. Un colis archivé doit être restauré avant import. Limites par commande : 50 colis, archives comprises, et 1 000 événements. Aucune suppression silencieuse d’historique.

**Contrôler le résultat après chaque écriture.** Le hook fonctionne après l’enregistrement WooCommerce : HTTP 200 de WooCommerce ne prouve donc pas que le lot a été accepté. Relire la commande et vérifier l’absence de `_bcrm_shipping_import_error`, les identifiants dans `_bcrm_shipping_v2.events` et l’état du colis. Les erreurs apparaissent aussi dans le CRM. Une erreur temporaire de concurrence impose un nouvel essai avec les mêmes identifiants. La boîte d’entrée `_belmains_shipping_events` est effacée après traitement ; l’historique est la référence.

Les écritures REST directes dans `_bcrm_shipping_v2` et `_bcrm_shipping_import_error` sont refusées, y compris par identifiant de métadonnée. Les clés WooCommerce sont réservées à un utilisateur autorisé, conservées hors dépôt et hors notes de commande. La réception d’une écriture WooCommerce ne certifie pas à elle seule l’identité d’Iziship.

## Interface privée et protection des données

Les routes `GET parcels`, `POST orders/{id}/parcels`, `GET shipping-export` et `GET orders/{id}/shipping-history` appartiennent à `belmains-crm/v1` et exigent les droits WooCommerce/administration. Les écritures manuelles transmettent `{revision, parcel}` ; une révision périmée répond HTTP 409. Une exclusion mutuelle par commande protège aussi les imports concurrents.

Les exports CSV suivent les filtres et sont limités à 5 000 lignes complètes. Les analyses parcourent au maximum 5 000 commandes créées sur la période ; un avertissement signale une vue partielle. Les valeurs pouvant être interprétées comme formules de tableur sont neutralisées. Les notes et étiquettes ne figurent pas dans ce CSV. L’export JSON du dossier complet est privé et peut contenir ces informations internes : le conserver dans un espace autorisé.

Les vues clients et les e-mails WooCommerce existants montrent seulement les colis actifs avec numéro, état, dates et relais, sans note interne, URL d’étiquette ou diagnostic. Les modifications n’envoient aucun message automatiquement. Sur le site catalogue actuel, le garde-fou de préparation continue d’empêcher les envois de mails et les ventes.

## Validation et installation

Tests d’intégration : charger WordPress dans une base isolée `bcrm_validation_`, environnement `local`, puis `tests/crm-integration.php`, `tests/tracking-form-integration.php` et `tests/shipping-integration.php`. Les tests de logistique refusent tout autre environnement et bloquent les e-mails. La suite utilise de vraies commandes WooCommerce temporaires et les supprime. Les tests DOM de l’interface utilisent des données synthétiques et aucune requête réseau.

Avant mise à jour, sauvegarder le dossier du plugin courant. Installer uniquement le ZIP CRM 0.3.0, conserver son activation et contrôler les pages privées, permissions REST, version des assets et état de la boutique. Aucune création de commande réelle ou d’expédition n’est requise pour ce contrôle. Conserver la copie locale et la sauvegarde avant mise à jour.

Retour arrière : réinstaller le ZIP sauvegardé du CRM. Aucune migration de schéma n’est introduite dans 0.3.0. Les anciennes métadonnées restent présentes. Si des colis ont été saisis depuis la mise à jour, exporter leurs dossiers avant retour arrière : l’ancienne version ne sait pas afficher le nouveau dossier multi-colis. Ne pas supprimer `_bcrm_shipping_v2`, faute de quoi l’historique serait perdu. Réinstaller 0.3.0 le rend à nouveau visible.

## Raccordements restant à valider avec Iziship

Confirmer le domaine final, les références produit et le stock, les champs des transporteurs, le retour des numéros et événements, le cycle des étiquettes et retours et, si souhaité, le choix d’un relais. Faire un essai convenu avec leur équipe qui ne déclenche aucune expédition physique. La création d’étiquettes, le suivi automatique et les notifications ne doivent pas être annoncés opérationnels avant ce contrôle.
