# Système de Tags pour les Articles

## 🎯 Vue d'ensemble

Système complet de tags/catégories pour les articles du blog avec :
- Stockage en base de données (normalisé avec slugs)
- Gestion automatique lors de l'import CanalBlog
- Interface d'administration pour gérer les tags
- Filtrage front-end par tag

---

## 📊 Structure de la base de données

### Table `tags`
```sql
id        INT PRIMARY KEY AUTO_INCREMENT
name      VARCHAR(100) UNIQUE      -- Nom du tag (ex: "Tournoi")
slug      VARCHAR(100) UNIQUE      -- Slug normalisé (ex: "tournoi")
created_at TIMESTAMP
```

### Table `post_tags` (relation many-to-many)
```sql
post_id   INT -> posts.id (ON DELETE CASCADE)
tag_id    INT -> tags.id (ON DELETE CASCADE)
PRIMARY KEY (post_id, tag_id)
```

**Avantages** :
- ✅ Un article peut avoir plusieurs tags
- ✅ Un tag peut être assigné à plusieurs articles
- ✅ Suppression d'articles nettoie automatiquement les relations
- ✅ Requêtes optimisées avec index

---

## 🔧 Modèle Tag (`src/Models/Tag.php`)

### Méthodes principales

#### Recherche
```php
Tag::find(int $id)                    // Par ID
Tag::findBySlug(string $slug)         // Par slug (pour URLs)
Tag::findByPost(int $postId)          // Tous les tags d'un article
```

#### CRUD
```php
Tag::create(array $data)              // Créer un tag
Tag::update(int $id, array $data)     // Mettre à jour
Tag::delete(int $id)                  // Supprimer
Tag::all()                            // Tous les tags
```

#### Gestion des articles
```php
Tag::findOrCreateByName(string $name) // Créer ou récupérer (idempotent)
Tag::attachToPost(int $postId, int $tagId)    // Assigner tag à article
Tag::detachFromPost(int $postId, int $tagId)  // Retirer tag d'article
Tag::setPostTags(int $postId, array $tagIds)  // Remplacer tous les tags
```

#### Statistiques
```php
Tag::getPostCount(int $tagId)         // Nombre d'articles avec ce tag
```

---

## 📥 API (Import CanalBlog)

### Payload reçu
```json
{
  "canalblog_id": "35054696",
  "title": "Tournoi d'Halloween",
  "content": "...",
  "published_at": "2025-11-01T10:53:00+01:00",
  "tags": ["club", "animation", "tournoi club"],
  "cover_image": "https://..."
}
```

### Traitement
```php
// ArticleApiController::create()
$postId = Post::create($postData);

// Création/attachement automatique des tags
foreach ($data['tags'] as $tagName) {
    $tagId = Tag::findOrCreateByName($tagName);  // ← Création auto
    Tag::attachToPost($postId, $tagId);
}
```

**Caractéristiques** :
- ✅ Tags créés automatiquement s'ils n'existent pas
- ✅ Slugs normalisés avec SlugManager
- ✅ Doublons évités (find first, then create)
- ✅ Idempotent (importer 2x = même résultat)

---

## 🛠️ Administration

### Formulaire d'édition (`admin/posts/form.twig`)

```twig
<div>
  <label class="block font-black mb-3">Tags</label>
  <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
    {% for tag in all_tags %}
    <label class="flex items-center gap-2">
      <input type="checkbox" name="tags[]" value="{{ tag.id }}"
             {% if tags|map(attribute='id')|index(tag.id) is not null %}checked{% endif %}>
      <span>{{ tag.name }}</span>
    </label>
    {% endfor %}
  </div>
</div>
```

### Workflow
1. Affichage de tous les tags disponibles
2. Sélection via checkboxes (multi-select)
3. Soumission du formulaire → `saveTags()` dans PostController
4. `Tag::setPostTags()` remplace les anciens tags

### PostController surcharges
```php
public function edit()          // Charge tags pour l'article
public function create()        // Charge tous les tags disponibles
public function store()         // Sauvegarde tags après création
public function update()        // Sauvegarde tags après mise à jour
private function saveTags()     // Écrit les associations post_tags
```

---

## 🌐 Frontend (Blog Public)

### Page de liste (`blog/list.twig`)

#### Filtre par tag
```twig
<div class="mb-8 p-4 bg-gray-50 border-2 border-gray-200 rounded">
  <div class="text-xs font-black text-gray-600 mb-3">Filtrer par tag</div>
  <div class="flex flex-wrap gap-2">
    {% for tag in all_tags %}
    {% set count = tag_counts[tag.id] ?? 0 %}
    {% if count > 0 or selected_tag and selected_tag.id == tag.id %}
      <a href="{{ url('blog/tag/' ~ tag.slug) }}"
         class="px-3 py-2 {% if selected_tag.id == tag.id %}bg-blue-600 text-white{% else %}bg-white text-gray-700{% endif %}">
        {{ tag.name }} ({{ count }})
      </a>
    {% endif %}
    {% endfor %}
  </div>
</div>
```

**Fonctionnement** :
- ✅ Affiche tous les tags avec article count
- ✅ Masque les tags sans articles
- ✅ Affiche le tag sélectionné en bleu
- ✅ Lien de désélection (× ou retour à `/blog`)

#### Tag counts
```php
// BlogController::list()
$tagCounts = [];
foreach ($allTags as $tag) {
    $tagCounts[$tag['id']] = Tag::getPostCount($tag['id']);
}
```

### Page de détail (`blog/detail.twig`)

```twig
{% if tags|length %}
<div class="mt-8 pt-8 border-t-2 border-gray-200">
  <div class="text-sm font-black text-gray-600 mb-3">Tags</div>
  <div class="flex flex-wrap gap-2">
    {% for tag in tags %}
    <a href="{{ url('blog/tag/' ~ tag.slug) }}"
       class="px-3 py-2 bg-gray-100 hover:bg-blue-100 rounded">
      {{ tag.name }}
    </a>
    {% endfor %}
  </div>
</div>
{% endif %}
```

**Affichage** :
- ✅ Sous le contenu, avant la galerie
- ✅ Tags cliquables pour filtrer
- ✅ Style cohérent avec site

### Route de filtrage

```php
// src/Core/App.php
$r->get('/blog/tag/{tag}', [BlogController::class, 'list']);
```

```php
// BlogController::list()
$tagSlug = $params['tag'] ?? null;
if ($tagSlug) {
    $selectedTag = Tag::findBySlug($tagSlug);
    if ($selectedTag) {
        $posts = $this->filterPostsByTag($posts, $selectedTag['id']);
    }
}
```

---

## 🔄 Workflows complets

### 1️⃣ Importer un article CanalBlog avec tags

```
API reçoit :
  "tags": ["club", "animation", "tournoi club"]
         ↓
ArticleApiController::create()
         ↓
Post créé avec canalblog_id
         ↓
Pour chaque tag :
  - Tag::findOrCreateByName("club")
    → Crée tag si absent, retourne ID
  - Tag::attachToPost(postId, tagId)
    → Insert dans post_tags
         ↓
Article visible avec tags
```

### 2️⃣ Éditer un article en admin

```
Formulaire chargé :
  - all_tags = tous les tags
  - tags = tags de cet article
         ↓
Admin sélectionne des checkboxes
         ↓
Submit → PostController::update()
         ↓
Post::update() met à jour article
Tag::setPostTags() remplace tous les tags
         ↓
Article mis à jour
```

### 3️⃣ Filtrer blog par tag en front

```
Visiteur clique sur tag "Tournoi"
         ↓
URL : /blog/tag/tournoi
         ↓
BlogController::list(['tag' => 'tournoi'])
         ↓
Tag::findBySlug('tournoi')
         ↓
filterPostsByTag() récupère post_ids
         ↓
Affiche seulement articles avec ce tag
```

---

## 📝 Exemples de données

### Tags (table)
```
id | name         | slug
1  | Club         | club
2  | Animation    | animation
3  | Tournoi Club | tournoi-club
4  | Junior       | junior
5  | Senior       | senior
```

### Post-Tags (relations)
```
post_id | tag_id
5       | 1      (Article 5 = Club)
5       | 2      (Article 5 = Animation)
5       | 3      (Article 5 = Tournoi Club)
6       | 1      (Article 6 = Club)
6       | 4      (Article 6 = Junior)
```

### Tags par article
- Article 5 : `[Club, Animation, Tournoi Club]`
- Article 6 : `[Club, Junior]`
- Affichage : `club (2)`, `animation (1)`, `tournoi-club (1)`, `junior (1)`, `senior (0)`

---

## 🎨 Design & UX

### Admin
- ✅ Checkboxes en grille 2x3
- ✅ Tous les tags visibles
- ✅ Sélection claire (checked/unchecked)

### Frontend
- **Liste blog** :
  - Barre grise avec tags disponibles
  - Counts affichés
  - Tag sélectionné souligné en bleu
  - Clic = filtre immédiat

- **Détail article** :
  - Tags en bas du contenu
  - Style "boutons" cliquables
  - Hover effect discret

### Couleurs
- Tag inactif : `bg-white`, `text-gray-700`, `border-gray-300`
- Tag sélectionné : `bg-blue-600`, `text-white`
- Tag hover : `border-blue-400`, `text-blue-600`

---

## ⚡ Performance

### Requêtes optimisées
```sql
-- Récupérer tous les tags d'un article (1 query)
SELECT t.* FROM tags t
INNER JOIN post_tags pt ON t.id = pt.tag_id
WHERE pt.post_id = ?

-- Compter articles par tag (optimisé)
SELECT COUNT(*) FROM post_tags WHERE tag_id = ?

-- Index sur post_tags pour lectures rapides
KEY idx_tag_id (tag_id)
KEY idx_post_id (post_id)   -- PRIMARY KEY
```

### Cache potentiel
- Tag counts pourraient être cachés (rarement changent)
- Slugs sont uniques (fast lookups)
- Pas de N+1 queries (batch loads dans templates)

---

## 🚀 Fonctionnalités futures possibles

1. **Multi-tag filtering** : `/blog?tags=club,tournoi` (AND/OR)
2. **Tag management page** : Admin CRUD dédié aux tags
3. **Tag cloud** : Affichage de tous les tags avec tailles proportionnelles
4. **Related articles** : "Articles similaires" basé sur tags communs
5. **Tag descriptions** : Ajouter `description` colonne pour SEO
6. **Tag RSS feeds** : `/blog/tag/{tag}/feed.xml`

---

## ✅ Checklist de test

- [ ] Tags créés depuis CanalBlog importés correctement
- [ ] Tags affichés en admin (forme+liste)
- [ ] Tags modifiables sur articles existants
- [ ] Filtre fonctionne (`/blog/tag/{slug}`)
- [ ] Counts exacts
- [ ] Tags cliquables sur détail article
- [ ] Slugs normalisés (accents, espaces)
- [ ] Suppression article = nettoyage post_tags
- [ ] Suppression tag = retrait de tous les articles

---

## 📂 Fichiers modifiés

```
✓ database/migrations/007_tags.sql
✓ src/Models/Tag.php (NEW)
✓ src/Controllers/Admin/PostController.php
✓ src/Controllers/Api/ArticleApiController.php
✓ src/Controllers/BlogController.php
✓ src/Core/App.php
✓ templates/front001/admin/posts/form.twig
✓ templates/front001/admin/posts/list.twig
✓ templates/front001/blog/list.twig
✓ templates/front001/blog/detail.twig
```
