# Belmains — travail et publication

Travailler en français avec Norman et préserver les changements existants.

Le site public est WordPress chez Infomaniak : https://belmains.com. Les sources déployées sont les trois dossiers `wordpress/belmains`, `wordpress/belmains-commerce` et `wordpress/belmains-crm`. Le dossier `site/` est une ancienne vitrine statique.

La branche de publication est `codex/version-3-bordeaux`. Un push sur cette branche lance la validation et la publication automatique. Pour livrer une modification demandée par Norman, vérifier le code, committer, pousser sur cette branche puis contrôler la validation GitHub et le site réel. Une simple modification locale ne publie rien.

Lire `wordpress/AUTOMATIC-DEPLOYMENT.md` avant de modifier le mécanisme de déploiement. Les copies de confiance du récepteur sont fixées hors du Web ; elles ne sont pas mises à jour par les ZIP du site. Un changement direct en ligne bloque les publications suivantes tant qu'il n'a pas été réconcilié.

Ne pas réinstaller WordPress ni importer une ancienne base. Préserver contenus, brouillons, commandes, stocks, médias et réglages. Les achats, paiements, notifications commerciales et expéditions réels restent fermés jusqu'à une instruction explicite d'ouverture. Ne pas lancer les tests d'intégration sur la production ; utiliser uniquement une base isolée `bcrm_validation_`. Les tests de déploiement utilisent une boutique factice et ne contactent pas la production.

Ne pas committer de secrets, sessions, exports SQL ou sauvegardes privées. Les caractéristiques confirmées sont 5 modes, 3 niveaux de chaleur, 42 °C maximum et séances de 15 minutes (pas une durée de recharge). Ne pas inventer de promesse médicale ni modifier les prix ou la fiscalité sans demande.
