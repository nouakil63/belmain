# Changer de produit sur le site Belmains

Le site garde toujours la même structure et le même design.
Seuls les **textes** et les **photos** changent, et ils vivent dans un seul fichier par produit.

```
index.html            ← la page (ne se modifie plus)
moteur.js             ← injecte la fiche produit dans la page (ne se modifie pas)
produit.js            ← UNE ligne : le produit actif
produits/
  gant-massage.js     ← fiche produit : tous les textes + chemins des photos
  autre-produit.js    ← une fiche par produit
images/
  gant-massage/       ← les photos de ce produit
  autre-produit/
editeur.html          ← éditeur visuel (formulaire + aperçu en direct)
```

## Méthode rapide : l'éditeur visuel

1. Ouvrir `editeur.html` dans le navigateur (double-clic suffit, pas besoin de serveur).
2. Cliquer **Nouveau produit (copie)** : on part d'une copie du produit en ligne, il n'y a plus qu'à changer ce qui diffère.
3. Modifier les textes et choisir les photos, section par section. L'aperçu à droite se met à jour en direct.
4. Cliquer **Télécharger le pack**. On obtient `belmains-<nom>.zip`.
5. Décompresser le zip **à la racine du site** (à côté de `index.html`) et accepter de remplacer `produit.js`.
6. Publier le site. Le nouveau produit est en ligne.

Le brouillon est conservé automatiquement dans le navigateur : on peut fermer l'onglet et reprendre plus tard.
Pour retoucher un produit existant : **Ouvrir un fichier produit…** puis choisir `produits/<nom>.js`.

## Méthode manuelle (sans éditeur)

- Changer de produit : ouvrir `produit.js` et écrire le nom du fichier voulu, par exemple `var PRODUIT_ACTIF = "creme-mains";`
- Créer un produit : copier `produits/gant-massage.js` en `produits/creme-mains.js`, modifier les textes, poser les photos dans `images/creme-mains/` et renseigner leur chemin dans le champ `"src"`.
- Prévisualiser un produit sans l'activer : `index.html?produit=creme-mains`

## Mise en forme dans les textes

| Dans le texte | À l'écran |
|---|---|
| `*mot*` | mot en italique rouge (dans les titres) |
| `**mot**` | mot en gras |
| retour à la ligne (grand titre) | une ligne du titre |
| ligne vide (onglets, réponses FAQ) | nouveau paragraphe |

Les espaces insécables français (avant `?`, `!`, `:`, dans `8 000`) sont ajoutés automatiquement.

## Photos

- Un champ `"src"` vide affiche l'emplacement « Photo à fournir » avec la note indiquée.
- Formats conseillés : grande photo du haut 4:5 (ex. 1200 × 1500), packshot carré (ex. 1200 × 1200), vignettes carrées, séquence 360° : 12 photos carrées numérotées dans l'ordre (`360-01.jpg` … `360-12.jpg`).
- Le champ `"alt"` décrit la photo pour Google et l'accessibilité. Il est à remplir.

## Ce qui est généré automatiquement

À partir de la fiche produit, `moteur.js` remplit aussi le titre de l'onglet, la description Google, les balises de partage et les données structurées Produit et FAQ (JSON-LD). Le bloc commenté « SEO » en haut de `index.html` n'est plus qu'un exemple.

## Avis clients

Un avis laissé vide affiche l'étiquette « avis réel à insérer ». Ne jamais publier un avis inventé.
