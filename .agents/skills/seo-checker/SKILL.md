---
name: seo-checker
description: Aide à l'analyse et à la validation SEO des templates Twig
---

# Compétence : Vérificateur SEO (seo-checker)

Cette compétence permet de s'assurer que les pages publiques du projet USM Volley respectent les bonnes pratiques en matière de SEO et d'accessibilité.

## Commande d'Analyse Automatique
Vous pouvez exécuter le script via Docker :

* **Si vos conteneurs sont déjà lancés (`docker compose up`) :**
  ```bash
  docker compose exec app php .agents/skills/seo-checker/scripts/check_templates.php
  ```

* **Si vos conteneurs ne sont pas lancés :**
  ```bash
  docker compose run --rm app php .agents/skills/seo-checker/scripts/check_templates.php
  ```

---

## Règles SEO du Projet

### 1. Balise de Titre (`<title>`)
Chaque page doit comporter une balise `<title>` dynamique et descriptive.
* Dans `base.twig`, utilisez un bloc ou une variable :
```twig
<title>{% block title %}{{ metadata.title|default(site_config.club_name) }}{% endblock %}</title>
```

### 2. Description Meta (`<meta name="description">`)
Une description unique par page publique :
```twig
<meta name="description" content="{% block description %}{{ metadata.description|default(site_config.club_tagline) }}{% endblock %}">
```

### 3. Structure des En-têtes (`<h1>` - `<h6>`)
* **Un seul `<h1>` par page** : Il doit représenter le sujet principal de la page.
* **Pas de saut de niveau** : Ne pas passer d'un `<h1>` à un `<h3>` sans un `<h2>` intermédiaire.
* **Ne pas utiliser de balises de titre pour le style** : Si vous voulez simplement du texte grand, utilisez des classes CSS (`text-lg`, `font-bold`), pas un `<h3>`.

### 4. HTML Sémantique
Utilisez les balises sémantiques appropriées à la place de `<div>` génériques :
* `<header>` pour l'en-tête du site ou d'une section.
* `<nav>` pour les menus de navigation (fil d'Ariane inclus).
* `<main>` pour le contenu principal.
* `<article>` pour les publications de blog ou fiches indépendantes.
* `<footer>` pour le pied de page.
* `<section>` pour découper les grands blocs thématiques.

### 5. Images et Attributs `alt`
Toutes les balises `<img>` doivent comporter un attribut `alt` :
* Si l'image est décorative : `alt=""`.
* Si l'image apporte une information : fournir une description textuelle concise.
* Exemple Twig :
```twig
<img src="{{ asset('uploads/' ~ photo.filename) }}" alt="{{ photo.caption|default('Photo de ' ~ equipe.nom) }}">
```
