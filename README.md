# USM Volley — Plateforme de gestion des adhésions

Application Django 5.2 pour la gestion des adhésions et paiements du club USM Volley.

## Stack

- **Framework** : Django 5.2 + Python 3.12
- **Base de données** : SQLite (dev) / PostgreSQL 16 (production)
- **Auth** : django-allauth (email/mot de passe)
- **Paiements** : HelloAsso v5 API + OAuth2
- **Emails** : Brevo (Sendinblue) API
- **Front** : django-tailwind + django-htmx + CKEditor 5

## Lancer en local

```bash
python -m venv venv
source venv/bin/activate
pip install -r requirements.txt

cp .env.example .env  # puis remplir les variables

python manage.py migrate
python manage.py createsuperuser
python manage.py runserver
```

Accès :
- http://localhost:8000/ — accueil
- http://localhost:8000/admin/ — interface admin
- http://localhost:8000/adhesion/ — formulaire d'adhésion (connexion requise)

> Avant de tester `/adhesion/`, créer au moins une `Saison` avec `is_active=True` dans l'admin.

## Docker

```bash
docker compose up -d
docker compose logs -f app
docker compose down
```

## Variables d'environnement

```bash
# .env
DEBUG=False
SECRET_KEY=...
ALLOWED_HOSTS=example.com,www.example.com
DATABASE_URL=postgres://user:pass@host:5432/db

# HelloAsso
HELLOASSO_API_HOST=https://api.helloasso.com/v5
HELLOASSO_CLIENT_ID=...
HELLOASSO_CLIENT_SECRET=...
HELLOASSO_WEBHOOK_SECRET=...
HELLOASSO_ORGANIZATION_SLUG=usm-volley
PUBLIC_BASE_URL=https://example.com

# Brevo
BREVO_API_KEY=...
BREVO_TEMPLATE_ADHESION_CREATED=123
BREVO_TEMPLATE_PAYMENT_CONFIRMED=456
```

## Structure du projet

```
usm-core/
├── usm_volley/          # settings, urls, wsgi
├── adhesions/           # adhésions, paiements, webhook HelloAsso
│   ├── services/
│   │   ├── helloasso_client.py   # OAuth2 + checkout intent
│   │   └── brevo_client.py       # envoi emails
│   ├── views.py                  # formulaire adhésion
│   ├── views_payment.py          # flux paiement HelloAsso
│   ├── views_webhook.py          # réception webhook
│   ├── models.py
│   └── admin.py
├── compte/              # espace adhérent (/mon-compte/)
├── content/             # blog et pages statiques
├── members/             # modèle User personnalisé
├── seasons/             # modèle Saison
├── teams/               # EquipeGroupe, catégories
├── templates/
├── Dockerfile
└── docker-compose.yml
```

## Flux paiement HelloAsso

1. **Création adhésion** — formulaire `/adhesion/` → `Adhesion` créée en statut `EN_ATTENTE`
2. **Initiation paiement** — `/adhesion/<id>/payer/` → OAuth2 token + checkout intent → redirection HelloAsso
3. **Webhook** — `POST /api/webhooks/helloasso/` → vérification HMAC SHA256 → mise à jour statut → email Brevo
4. **Retour utilisateur** — `/adhesion/<id>/paiement-retour/` → page de confirmation

## Tests

```bash
python manage.py test adhesions
```

## Déploiement

```bash
python manage.py migrate --noinput
python manage.py collectstatic --noinput
```
