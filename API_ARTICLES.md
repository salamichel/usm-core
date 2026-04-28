# API de création d'articles

## Endpoint

**Méthode :** `POST`
**URL :** `/api/articles`
**Content-Type :** `application/json`

## Validation

L'API valide automatiquement les champs suivants :
- `canalblog_id` (requis) : Identifiant unique de l'article sur CanalBlog
- `title` (requis) : Titre de l'article
- `content` (requis) : Contenu HTML complet de l'article
- `published_at` (requis) : Date/heure de publication (format ISO 8601)

## Structure de la requête

```json
{
  "canalblog_id": "35054696",
  "title": "Tournoi d'Halloween du club le 31/10/2025",
  "slug": "2025/11/tournoi-d-halloween",
  "url": "https://usmvolley.canalblog.com/2025/11/...",
  "published_at": "2025-11-01T10:53:00+01:00",
  "tags": ["club", "animation", "tournoi club"],
  "cover_image": "https://image.canalblog.com/...",
  "content": "<p>Contenu HTML de l'article...</p>"
}
```

## Champs acceptés

| Champ | Type | Requis | Description |
|---|---|---|---|
| `canalblog_id` | string | ✓ | Identifiant unique CanalBlog |
| `title` | string | ✓ | Titre de l'article |
| `slug` | string | - | Slug pour l'URL (auto-généré si absent) |
| `url` | string | - | URL originale sur CanalBlog |
| `published_at` | string | ✓ | Date ISO 8601 |
| `tags` | array | - | Tags/catégories (ignorés actuellement) |
| `cover_image` | string | - | URL de l'image de couverture |
| `content` | string | ✓ | Contenu HTML du post |

## Réponses

### 201 Created — Article créé
```json
{
  "message": "Article created successfully",
  "id": 123
}
```

### 200 OK — Article déjà existant
```json
{
  "message": "Article already exists",
  "id": 456
}
```

### 400 Bad Request — Validation échouée
```json
{
  "error": "Validation failed",
  "errors": {
    "title": "title is required",
    "content": "content is required"
  }
}
```

### 405 Method Not Allowed
```json
{
  "error": "Method not allowed"
}
```

### 500 Server Error
```json
{
  "error": "Server error: ..."
}
```

## Fonctionnalités

✅ **Validation des données** — Les 4 champs requis sont validés avant traitement

✅ **Dédoublonnage** — Vérifie que l'article n'existe pas déjà via `canalblog_id`

✅ **Téléchargement d'images** — Récupère automatiquement l'image de couverture depuis l'URL fournie

✅ **Stockage des images** — Enregistre l'image dans `/public/assets/uploads/`

✅ **Publication immédiate** — Les articles sont créés en tant que posts publiés

✅ **Pas de CSRF** — L'endpoint API n'exige pas de token CSRF

## Exemple avec cURL

```bash
curl -X POST http://localhost:8080/api/articles \
  -H "Content-Type: application/json" \
  -d '{
    "canalblog_id": "35054696",
    "title": "Mon article",
    "content": "<p>Contenu...</p>",
    "published_at": "2025-11-01T10:53:00+01:00",
    "cover_image": "https://example.com/image.jpg"
  }'
```

## Notes de développement

- La migration `006_article_api.sql` ajoute la colonne `canalblog_id` avec contrainte d'unicité
- Les images sont téléchargées et sauvegardées avec un nom unique (`api-post-{id}-{timestamp}.ext`)
- Si le téléchargement de l'image échoue, l'article est créé sans photo de couverture
- Les tags ne sont pas sauvegardés actuellement (peuvent être implémentés ultérieurement)
- L'URL originale est stockée via le champ `url` dans le payload (non sauvegardée en base, informatif uniquement)
