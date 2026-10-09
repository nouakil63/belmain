# Site Belmains retenu

La version 3 est désormais le seul site de présentation, servi directement à la racine sans iframe ni sélecteur de maquettes.

- Adresse : https://belmain.vercel.app/
- Les anciennes adresses `?version=1`, `?version=2` et `?version=3` affichent le même site retenu.
- Les anciennes adresses `/maquettes/…` redirigent vers la racine.
- Les fichiers des versions 1 et 2 sont retirés ; leur historique reste accessible dans Git.
- Site statique, sans compilation. Le panier et les formulaires sont encore ceux de la démonstration.
- Vercel : répertoire racine `site`, sortie `.`, aucun framework.
- La préparation WordPress est séparée dans `wordpress/` à la racine du dépôt.

La configuration locale `.vercel` est exclue du dépôt. Les choix visuels sont détaillés dans `LIRE-MOI.md`.
