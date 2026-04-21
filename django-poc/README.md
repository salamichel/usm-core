# USM Volley — POC Django 5.2

Prototype de validation de l'étude comparative Next.js → Django 5.2 (voir
`/root/.claude/plans/tu-peux-etudier-pour-giggly-micali.md`).

## Ce que le POC démontre

1. **Schéma Prisma → Django models** : `User`, `Saison`, `Adhesion`, `EquipeGroupe`,
   `Document`, `Post`, `Event`, `Photo` — tous les enums traduits en `TextChoices`.
2. **Admin gratuit** : `/admin/` fournit CRUD + filtres + recherche + autocomplete
   pour tous les modèles. C'est ce qui divise l'effort Phase 6 par 3 à 5.
3. **django-allauth** : auth email/mot de passe sur `/accounts/login/` et
   `/accounts/signup/`.
4. **Formulaire dynamique `/adhesion/`** : affichage conditionnel du tarif et des
   champs *Souhait d'équipe* / *Coupes* selon la catégorie (sans React).
5. **Règles métier** : validation `<15 ans` + `Sans Compétition` bloqué, Coupe Heitz
   réservée aux féminines, Coupe Aïco aux masculins.

## Lancer le POC

### Option A — SQLite local (le plus rapide)

```bash
cd django-poc
python3 -m venv venv && source venv/bin/activate
pip install -r requirements.txt
python manage.py migrate
python manage.py createsuperuser
python manage.py runserver
```

Puis :
- http://localhost:8000/ — home
- http://localhost:8000/admin/ — admin (login superuser)
- http://localhost:8000/adhesion/ — formulaire dynamique

Avant de tester `/adhesion/`, créer au moins une `Saison` avec `is_active=True`
dans l'admin.

### Option B — Docker (PostgreSQL 16)

```bash
cd django-poc
docker compose up --build
```

## Structure

```
django-poc/
├── usm_volley/          # settings.py, urls.py, wsgi.py
├── members/             # User (AbstractUser), Role, Gender
├── seasons/             # Saison
├── teams/               # EquipeGroupe, CategorieAdhesion
├── adhesions/           # Adhesion + /adhesion/ form + HTMX
├── content/             # Document, Post, Event, Photo
├── templates/           # base.html, home, adhesion form
├── requirements.txt
├── Dockerfile
└── docker-compose.yml
```

## Limites du POC

- Pas de webhook HelloAsso (stub à implémenter — équivalent JS/Python).
- Pas d'intégration Brevo (backend email console pour l'instant).
- Tailwind via CDN pour simplicité — prod passerait par `django-tailwind`.
- CKEditor pour Post non installé (simple `TextField` pour l'instant).
