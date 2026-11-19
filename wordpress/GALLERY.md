# Galerie Belmains

Le modèle `page-galerie.php` présente trois vidéos du dossier Drive fourni par Norman, avec des lecteurs natifs sans lecture automatique. Les MP4 sont adaptés au web en 720 × 1280, avec audio conservé et affiches WebP. Leur durée n'est pas modifiée. Les légendes décrivent les vidéos sans ajouter de promesse produit.

Les liens du menu ordinateur, du menu mobile et des pieds de page utilisent uniquement la page publiée enregistrée dans `belmains_gallery_page_id`. Le thème ne crée pas de page ni de contournement du contrôle d'accès.

Les vidéos du dossier `assets/gallery/` sont sélectionnées dans les séries AD3, AD2 et AD6 du lot `BATCH 1 | 11/26` : respectivement découverte, quotidien et rituel. Les originaux Drive restent inchangés ; aucun droit de partage Drive n'est modifié.

## Publication

1. Pousser les sources sur `codex/version-3-bordeaux`, puis attendre la validation isolée et le déploiement automatique du thème 0.4.3.
2. Vérifier le code et les manifestes déployés. Exécuter l'outil manuel `wordpress/tools/publish-gallery.php` depuis la copie de confiance `~/belmains-deployment/source`, via WP-CLI et avec `BELMAINS_GALLERY_PUBLISH=1` et `BELMAINS_GALLERY_REVISION` égal au SHA complet réellement déployé.
3. Cet outil crée le brouillon, sauvegarde le garde et la référence hors du Web, installe l'exception précise dans le garde autoritaire, ne met à jour que son empreinte de référence et publie la galerie en dernier. Les restrictions commerciales restent actives.
4. Vérifier les liens ordinateur/mobile, la lecture des trois vidéos, l'absence de lecture automatique, les réponses publiques GET/HEAD et le maintien du blocage des achats et de l'API anonyme.

La validation CI utilise des tables `bcrm_validation_` dans une base éphémère, avec réseau applicatif externe, e-mails et cron bloqués. Elle vérifie les vrais contrôles WordPress du garde et conserve un aperçu HTML pour la revue visuelle. Aucun test d'intégration n'est exécuté sur la boutique réelle.
