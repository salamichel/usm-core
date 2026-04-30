# Améliorations Admin & Frontend pour API CanalBlog

## 🎯 Vue d'ensemble

Mise à jour complète de l'administration et du frontend pour gérer les articles importés via l'API CanalBlog. Identification visuelle claire entre articles manuels et importés, avec gestion cohérente des slugs.

---

## 📊 Colonne `canalblog_id`

### Base de données
- **Migration** : `006_article_api.sql`
- **Colonne** : `VARCHAR(255) DEFAULT NULL UNIQUE`
- **Index** : `idx_posts_canalblog_id` pour recherches rapides
- **Contrainte** : Unicité pour éviter les doublons

### Modèle Post
- ✅ `findByCanalblogId(string $id)` — Cherche par ID CanalBlog
- ✅ `create()` accepte le paramètre `canalblog_id`
- ✅ Les articles manuels ont `canalblog_id = NULL`

---

## 🛠️ Interface d'administration

### Liste des articles (`admin/posts/list.twig`)

#### Badge "API"
- **Couleur** : Bleu (`bg-blue-100`, `text-blue-800`)
- **Position** : Titre (à côté du titre de l'article)
- **Affichage** : Uniquement si `post.canalblog_id` est présent
- **Responsive** : Mobile et desktop

```twig
{% if post.canalblog_id %}
  <span class="bg-blue-100 text-blue-800 border border-blue-400 px-2 py-0.5 text-xs font-bold rounded">API</span>
{% endif %}
```

#### Exemple
```
✓ "Mon Article sur le Tournoi"   [API]   [Publié]   27/04
  slug: mon-article-sur-le-tournoi
```

### Édition d'article (`admin/posts/form.twig`)

#### Info box "Importé via API"
- **Condition** : Visible uniquement pour articles importés (`post.canalblog_id` présent)
- **Contenu** : 
  - Texte : "Importé via API CanalBlog"
  - ID CanalBlog : `post.canalblog_id` en monospace
- **Style** : Boîte bleue discrète avec bordure bleu clair

```twig
{% if post and post.canalblog_id %}
<div class="bg-blue-50 border-2 border-blue-300 p-3 rounded">
  <div class="text-xs text-blue-600 font-bold mb-1">Importé via API CanalBlog</div>
  <div class="font-mono text-sm text-blue-900">ID: {{ post.canalblog_id }}</div>
</div>
{% endif %}
```

#### Reste du formulaire
- Les champs sont éditables normalement
- Le `canalblog_id` n'est pas modifiable (lecture seule)
- Slug auto-généré mais éditable

---

## 🌐 Frontend (Page Publique)

### Liste des actualités (`templates/front001/blog/list.twig`)

#### Badge "CanalBlog"
- **Couleur** : Bleu discret (`bg-blue-100`, `text-blue-700`)
- **Position** : Sous le titre, à côté de la date
- **Taille** : `text-xs` (petit et discret)
- **Texte** : "CanalBlog"

```twig
{% if post.canalblog_id %}
  <span class="text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded font-semibold">CanalBlog</span>
{% endif %}
```

#### Exemple
```
Tournoi d'Halloween du club 📝
27/04/2025  [CanalBlog]

Notre club a organisé un tournoi...
```

### Détail d'article (`templates/front001/blog/detail.twig`)

#### Badge "Importé de CanalBlog"
- **Position** : Sous le titre, à côté de la date
- **Texte** : "Importé de CanalBlog" (plus descriptif)
- **Couleur** : Bleu `bg-blue-100`, `text-blue-700`
- **Taille** : `text-xs`

```twig
{% if post.canalblog_id %}
  <span class="text-xs bg-blue-100 text-blue-700 px-2 py-1 rounded font-semibold">Importé de CanalBlog</span>
{% endif %}
```

#### Exemple
```
Tournoi d'Halloween du club
27/04/2025  [Importé de CanalBlog]

<contenu HTML de l'article>
<images de l'article>
```

---

## 🔤 Gestion cohérente des slugs

### Problème identifié
Les slugs CanalBlog reçus via l'API ont un format différent :
- Format CanalBlog : `2025/11/tournoi-d-halloween-du-club-le-31/10/2025.html`
- Contiennent des slashes, des points, potentiellement des accents

### Solution implémentée

#### Normalisation dans l'API
```php
// ArticleApiController
$slugInput = trim((string)($data['slug'] ?? $title));
$slug = SlugManager::generate($slugInput);  // ← Normalisation
```

#### SlugManager amélioré
1. **Conversion en minuscules** : `tournoi` (cohérent)
2. **Suppression des accents** : `tournoi-d-halloween` au lieu de perdre des caractères
3. **Nettoyage des caractères** : Suppression des slashes, points, etc.
4. **Normalisation des espaces** : `mot mot` → `mot-mot`
5. **Consolidation** : `mot--mot` → `mot-mot`
6. **Trimming** : Suppression des tirets au début/fin

#### Fonction de suppression des accents
```php
private static function removeAccents(string $text): string
{
    // Utilise transliterator si disponible (méthode correcte)
    if (function_exists('transliterator_transliterate')) {
        return transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $text) ?: $text;
    }
    
    // Sinon, substitution manuelle des accents français
    return strtr($text, [
        'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'à' => 'a', 'â' => 'a', 'ä' => 'a',
        'ù' => 'u', 'û' => 'u', 'ü' => 'u',
        'ç' => 'c', 'ñ' => 'n',
        // ... et versions majuscules
    ]);
}
```

### Cohérence garantie
- ✅ Articles manuels : Passent par `PostController::getFormData()` → `SlugManager::generate()`
- ✅ Articles API : Passent par `ArticleApiController::create()` → `SlugManager::generate()`
- ✅ Tous les slugs normalisés identiquement
- ✅ Accents français (`é`, `è`, `à`, etc.) gérés correctement

### Exemples de normalisation
| Input CanalBlog | Slug normalisé |
|---|---|
| `2025/11/tournoi-d-halloween-du-club-le-31/10/2025.html` | `202511tournoi-d-halloween-du-club-le-311022025` |
| `Tournoi d'Halloween du club` | `tournoi-dhalloween-du-club` |
| `Article sur l'équipe junior de 2025` | `article-sur-lequipe-junior-de-2025` |

---

## 🎨 Design & Cohérence

### Palette de couleurs
- **API/CanalBlog** : Bleu (`#dbeafe` / `#1e40af`)
  - `bg-blue-100` pour fond
  - `text-blue-700` ou `text-blue-800` pour texte
  - `border-blue-400` pour bordures

### Typographie
- **Badges** : `text-xs`, `font-semibold` ou `font-bold`
- **Info box admin** : `font-mono` pour l'ID CanalBlog
- **Cohérent** : Même style sur mobile et desktop

### UX
- Pas de dégradation de la lecture
- Badges subtils, informatifs
- Distinction claire sans bruit visuel
- Responsive par défaut

---

## ✅ Checklist d'implémentation

Admin :
- ✅ Badge API dans liste
- ✅ Info box dans formulaire
- ✅ Mobile et desktop

Frontend :
- ✅ Badge dans liste blog
- ✅ Badge dans détail article
- ✅ Textes descriptifs

Slugs :
- ✅ Normalisation API
- ✅ Gestion des accents
- ✅ SlugManager robuste
- ✅ Cohérence admin ↔ API

---

## 📝 Notes de développement

### Évolutions futures possibles
1. Lien vers l'article original sur CanalBlog (ajouter `url` à `posts`)
2. Import d'autres metadonnées (tags → catégories)
3. Marquage des articles mis à jour depuis CanalBlog
4. Interface de re-sync manuel

### Maintenance
- La colonne `canalblog_id` est immutable après création
- Les articles peuvent être édités manuellement (slug, contenu, etc.)
- Suppression d'articles importés possible (cascade DELETE)
- SlugManager est centralisé, toute amélioration s'applique à tous les articles
