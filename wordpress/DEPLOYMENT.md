# Préparer les mises à jour WordPress

GitHub vérifie le code et prépare les trois archives installables de Belmains. **Ce workflow ne déploie pas les mises à jour sur WordPress.** Une copie est installée chez Infomaniak à `https://preparation.belmains.com/` depuis le 9 octobre 2026 ; le déploiement automatique reste à raccorder.

## Ce qui est automatisé

Le workflow [wordpress-checks.yml](../.github/workflows/wordpress-checks.yml) s'exécute à chaque push ou pull request touchant `wordpress/` ou le workflow, et peut être lancé manuellement depuis GitHub Actions. Il utilise un runner GitHub standard Ubuntu 24.04 et les outils PHP, Node.js et Python déjà présents, sans abonnement à une extension WordPress ni installation de dépendances de projet.

Il contrôle la syntaxe de tous les fichiers PHP et JavaScript sous `wordpress/`, puis construit les archives. Ce contrôle de syntaxe ne remplace pas un essai du panier, de la commande, des paiements et des e-mails sur une copie de préparation. Les tests d'intégration du dossier `tests/` ne sont pas exécutés par ce workflow : ils exigent leur base WordPress locale isolée.

Le jeton du workflow possède seulement le droit de lire le dépôt. Aucun secret de boutique ni accès d'hébergement n'est utilisé. Les deux actions officielles sont fixées à leur SHA complet. Le workflow n'utilise pas `pull_request_target`, ne publie aucune release et n'installe rien sur une boutique.

Les archives et leurs empreintes SHA-256 sont disponibles dans l'artefact de l'exécution pendant **7 jours**. Le nombre de minutes et le stockage dépendent du forfait et des quotas du compte GitHub ; vérifier les limites du compte avant une utilisation intensive. Aucun budget payant n'est activé par ces fichiers.

## Produire les mêmes archives sur l'ordinateur

Prérequis : Python 3.12 ou ultérieur. Depuis la racine du dépôt :

```text
python wordpress/tools/package-release.py --output-dir ../belmains-release
```

La commande lit les versions directement dans les en-têtes WordPress et produit :

- `belmains-wordpress-theme-VERSION.zip`, avec le dossier racine `belmains/` ;
- `belmains-commerce-VERSION.zip`, avec le dossier racine `belmains-commerce/` ;
- `belmains-crm-VERSION.zip`, avec le dossier racine `belmains-crm/` ;
- `SHA256SUMS`, pour vérifier l'intégrité des trois ZIP.

Les dates et permissions de chaque entrée sont fixées, les fichiers sont triés et les fins de ligne des textes sont normalisées. Deux constructions des mêmes sources avec la même version de Python/zlib produisent les mêmes empreintes. Le script vérifie la structure et l'intégrité de chaque archive avant de la déposer dans le dossier demandé. Ce dossier doit se trouver hors de `wordpress/` ; une archive portant déjà le même nom y est remplacée, les autres fichiers sont conservés.

Seuls les trois composants, leurs modèles PHP, notices et ressources publiques sont admis. Une structure inattendue, un lien symbolique, un fichier caché, un nom réservé aux sauvegardes ou à la configuration, ou un format non prévu fait échouer la création. Les outils, tests, exports de base, médias téléchargés par les clients, journaux, clés et fichiers `.env` ne font pas partie des paquets. Les images et la vidéo livrées avec le thème sont incluses ; le dossier WordPress `uploads/` ne l'est jamais. Ces règles de fichiers ne détectent pas un secret collé à l'intérieur d'un fichier PHP autorisé : la revue des modifications doit toujours vérifier l'absence de secrets.

Avant une mise à jour, modifier l'en-tête `Version` du composant concerné et sa constante de version lorsqu'il en utilise une. Les noms des ZIP suivront ces valeurs, sans changement du script.

## Première mise en ligne

La première migration est une opération distincte des mises à jour de code. Elle doit transférer l'état existant de la boutique, y compris sa base et ses médias, depuis une sauvegarde de préparation contrôlée. Ne pas recréer le produit ni réinitialiser son stock avec le script de provisionnement lors de cette migration.

**Périmètre de la base locale :** la boutique et l'installation de validation partagent la même base MySQL, avec deux préfixes distincts. Sélectionner explicitement toutes les tables du préfixe commercial `wp_` et exclure `bcrm_validation_` lors de l'export. Un export complet de la base emporterait aussi les données de test. Contrôler la liste des tables dans l'export et inclure celles des extensions nouvellement installées ; le nombre constaté le 8 octobre 2026 est de 57 tables commerciales, dont trois tables Revolut. Ce nombre doit être recalculé si les extensions évoluent. Les anciennes sauvegardes globales ne sont pas des paquets de migration prêts à importer.

Conserver les sauvegardes SQL et la configuration locale dans un emplacement privé hors du dépôt et du répertoire Web. La copie de `wp-config.php` sert au retour arrière local : ne pas la transférer telle quelle vers l'hébergement. Recréer la configuration de destination et préserver les protections de préparation (paiements désactivés, mode test, site non ouvert à la vente) avant sa première requête HTTP. Exclure les caches, journaux et anciennes sauvegardes de l'archive de transfert. Vérifier l'intégrité de l'archive ne remplace pas un essai de restauration.

1. Choisir l'hébergement et le domaine, obtenir les accès à son panneau de gestion et à WordPress, activer HTTPS.
2. Créer une copie de préparation non indexée. Installer les versions compatibles de WordPress, PHP et WooCommerce ; migrer la base et les médias avec l'outil fourni par l'hébergeur ou une méthode adaptée à ses accès. WP-CLI n'est pas supposé disponible.
3. Adapter les URL avec un outil WordPress qui préserve les données sérialisées, puis contrôler les liens, les images et le panier. Ne pas effectuer un remplacement brut dans un export SQL.
4. Configurer les paiements en mode test, la fiscalité confirmée, les e-mails et Iziship. Garder les vraies clés privées hors de GitHub et des ZIP.
5. Tester sur la copie une commande, une annulation et un remboursement avec remise en stock, le suivi invité, les notifications et l'affichage mobile. Vérifier ensuite les réglages de vente réels avant d'ouvrir le site.

## Raccorder les futures mises à jour automatiques

### État de la copie Infomaniak au 9 octobre 2026

La migration a été effectuée via WebFTP et phpMyAdmin authentifiés depuis le Manager. Le site utilise WordPress 7.1.3 et les tables `wp_`. Les tables `wp_1527127_` de l’installation vierge sont conservées, avec une sauvegarde SQL et une copie de sa configuration dans un emplacement local privé. La sauvegarde de départ contient 57 tables ; l’import prend les 55 tables hors utilisateurs, puis conserve les deux tables du nouvel administrateur Infomaniak sous le préfixe de destination. Les mots de passe et clés de configuration ne sont pas réécrits à partir de l’installation locale. L’adresse administrative est `contact@belmains.com`.

Les URL ont été adaptées avec `wp search-replace --export --precise`, avec extensions chargées pour conserver les objets sérialisés. Les GUID et les comptes locaux ont été exclus du remplacement. L’import ne contient aucune suppression de table. Les fichiers `wp-content/` sont transférés sans caches ni journaux ; l’archive est conservée hors de la racine Web.

`tools/staging-guard.php` est installé comme `wp-content/mu-plugins/belmains-staging-guard.php` **sur cette copie uniquement**. Il bloque les visiteurs publics, l’API REST non administrative, les paiements et les e-mails ; il impose noindex et empêche le lanceur asynchrone Action Scheduler. La configuration conserve `WCPAY_DEV_MODE=true`, `WP_ENVIRONMENT_TYPE=staging`, `DISABLE_WP_CRON=true` et `WP_CACHE=false`. Ce fichier reste actif même si un réglage de visibilité est changé dans l’administration. Sa suppression relève de la validation d’ouverture, avec remise en service explicite des connecteurs et tâches planifiées. Il n’est pas inclus dans les trois ZIP de mise à jour.

Le bloc `WPSuperCache` de `.htaccess` a été retiré sur la préparation : il servait encore la page vierge en cache aux visiteurs déconnectés, en contournant PHP malgré `WP_CACHE=false`. Les règles WordPress, la compression et la protection Git sont conservées. Le contrôle après déconnexion confirme désormais la page « Boutique en cours de préparation ». Le cache pleine page restera désactivé jusqu’à la définition de ses exclusions (panier, commande, compte, contact et administration). Transférer les fichiers de configuration validés par le téléversement WebFTP ; la saisie du contenu PHP dans son éditeur a produit une erreur de syntaxe, corrigée par l’envoi du fichier exact.

Pour revenir à l’installation vierge, restaurer sa configuration privée (ancien préfixe de tables) et retirer la protection de préparation uniquement si nécessaire. Aucun retour de base ne doit viser des commandes reçues après l’ouverture. Ne pas transférer les tables locales de validation ni publier les archives SQL.

La cible sera uniquement le code du thème et des deux plugins Belmains. Pour automatiser le transfert, il faudra connaître le domaine HTTPS, le mécanisme offert par l'hébergeur (déploiement Git, SFTP/SSH ou autre), le chemin exact de WordPress et la manière de sauvegarder/restaurer le site.

Le futur déploiement partira d'une branche de publication identifiée et d'une vérification réussie. Il utilisera un accès limité aux trois répertoires concernés, stocké dans les secrets de l'environnement GitHub approprié, et une connexion dont l'identité du serveur est vérifiée. Ne pas désactiver la vérification de l'hôte SSH pour simplifier la connexion.

Le transfert ne doit jamais écraser `wp-config.php`, la base, `wp-content/uploads/`, WooCommerce, les autres extensions ni les données créées par les clients. Ne pas synchroniser tout `wp-content/` avec suppression des fichiers absents. Les changements de schéma ou de données doivent être traités séparément et testés avant publication.

Tant que ces éléments ne sont pas connus, télécharger les trois ZIP validés et utiliser le téléversement natif de WordPress pour remplacer la version déjà installée, après sauvegarde. Ne pas désinstaller un plugin pour le mettre à jour.

## Sauvegarde et retour à la version précédente

### Vitrine sans vente — préparation du 9 octobre 2026

`belmains.com` et `www.belmains.com` sont maintenant rattachés au site Infomaniak existant comme alias, avec mise à jour DNS et certificat Let's Encrypt. Le domaine principal du site et les options WordPress restent `preparation.belmains.com` tant que la publication ci-dessous n'est pas terminée. L'enregistrement A de `belmains.com` répond avec l'IP Infomaniak ; le MX répond toujours chez Infomaniak. HTTPS présente actuellement la page de préparation, sans avertissement de certificat.

Le thème 0.4.2 ajoute un affichage de vitrine compatible avec `tools/public-catalog-guard.php`. Ce garde est destiné à **remplacer**, sous le même nom distant `wp-content/mu-plugins/belmains-staging-guard.php`, le garde de préparation. Ne pas installer les deux simultanément. Il autorise seulement l'accueil publié pour les visiteurs : panier, compte, formulaires et brouillons restent fermés. Les achats sont bloqués par les contrôles WooCommerce et Store API, pas seulement par le bouton. Les e-mails, l'audience, l'indexation et le lanceur asynchrone restent désactivés. Les avis de démonstration et les conditions de livraison/retour non finalisées ne sont pas présentés comme disponibles à l'achat. Le contact de cette vitrine passe par `mailto:contact@belmains.com`.

Avant remplacement, un export privé complet de la base distante a été conservé hors dépôt et hors racine Web (70 tables, y compris les tables de l'installation vierge et la clé Iziship existante). Un ZIP limité aux cinq fichiers de thème modifiés et au garde, ainsi qu'un ZIP inverse, sont préparés hors dépôt. **Ces fichiers ne sont pas encore déployés : le contrôle de sécurité du navigateur a bloqué l'ouverture de la session WebFTP.** Ne pas contourner ce blocage par un autre canal de session.

Pour terminer après rétablissement autorisé de WebFTP :

1. Déposer l'archive limitée dans le dossier privé `backups`, puis extraire ses six fichiers dans le répertoire WordPress existant. Vérifier le rendu et le bouton désactivé. Ne pas transférer une ancienne base ni écraser les connecteurs.
2. Passer les options scalaires `home` et `siteurl` de `https://preparation.belmains.com` à `https://belmains.com`. Les médias existants sur le sous-domaine peuvent rester accessibles ; tout remplacement ultérieur de contenu sérialisé doit utiliser un outil compatible WordPress.
3. Vérifier les accès HTTPS déconnectés, les images, les liens de contact, le rendu mobile, l'absence d'avis de démonstration et l'impossibilité de commander. Vérifier l'administration et conserver stock/commandes inchangés. Informer Iziship du changement d'URL de connexion, sans recréer la clé.
4. Présenter le domaine à Revolut seulement une fois la vitrine effectivement visible. La publication ne confirme ni l'acceptation du dossier Merchant API ni l'ouverture des paiements. L'ouverture commerciale nécessite toujours la validation des retours, de la fiscalité, des e-mails et des paiements.

`tests/catalog-integration.php` vérifie les refus d'achat/checkout classique et Store API, le maintien de l'API administrative, le blocage REST public, les e-mails, les avis et l'indexation, uniquement sur les tables locales `bcrm_validation_`. Aucun produit ni commande n'est créé par ces contrôles.

Avant chaque publication, conserver la dernière version fonctionnelle des trois ZIP, leur commit et une sauvegarde datée de la base, de `uploads/` et de la configuration du serveur dans un espace privé. Utiliser la sauvegarde de l'hébergeur ou son panneau d'administration ; conserver au moins une copie hors du serveur et vérifier qu'elle est restaurable.

1. Sur une copie de préparation, restaurer une sauvegarde et contrôler les commandes, les stocks, les médias et les accès. Ne pas envoyer d'e-mails ni appeler les paiements ou Iziship réels pendant cet essai.
2. Publier le code validé, puis vérifier immédiatement l'accueil, l'ajout au panier, le passage de commande, le suivi et l'accès privé au CRM.
3. Si une régression provient du code, remettre les ZIP de la dernière version fonctionnelle avec le panneau WordPress ou le gestionnaire de fichiers de l'hébergeur. Conserver les commandes et stocks actuels.
4. Si une restauration de base devient nécessaire, suspendre les nouvelles ventes et inventorier les commandes et remboursements arrivés depuis la sauvegarde. Ne jamais restaurer automatiquement une ancienne base par-dessus des commandes récentes. Préparer leur récupération avant la restauration et réconcilier les paiements avec le prestataire.

## Références officielles

- [Image du runner Ubuntu 24.04](https://github.com/actions/runner-images/blob/main/images/ubuntu/Ubuntu2404-Readme.md) : outils disponibles dans l'environnement du workflow.
- [Sécurisation des workflows GitHub](https://docs.github.com/en/actions/reference/security/secure-use) : permissions minimales et actions fixées par SHA.
- [Artifacts GitHub](https://github.com/actions/upload-artifact) : conservation et récupération des fichiers de construction.
- [Checkout v7.0.1](https://github.com/actions/checkout/releases/tag/v7.0.1) et [upload-artifact v7.0.2](https://github.com/actions/upload-artifact/releases/tag/v7.0.2) : versions retenues et vérifiées le 8 octobre 2026.
