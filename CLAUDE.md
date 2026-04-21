# USM Volley — POC Django 5.2

POC (Proof of Concept) Django 5.2 pour la plateforme USM Volley — gestion d'adhésions et paiements HelloAsso.

## Stack

- **Framework**: Django 5.2 + Python 3.11
- **Database**: SQLite (dev) / PostgreSQL (production)
- **Auth**: Custom User model (email-based)
- **Paiements**: HelloAsso v5 API + OAuth2
- **Emails**: Brevo (Sendinblue) API
- **Tests**: Django test framework

## Infrastructure

```bash
# Virtualenv
python -m venv venv
source venv/bin/activate

# Install deps
pip install -r requirements.txt

# Migrations
python manage.py migrate

# Dev server
python manage.py runserver

# Tests
python manage.py test adhesions

# Create superuser
python manage.py createsuperuser
```

### Docker

```bash
docker compose up -d
docker compose logs -f app
docker compose down
```

## Architecture Modèles

### Authentification
- **User** (custom) — email-based, roles (BUREAU, ADHERENT)
- **Saison** — cycles annuels (ex: 2024-2025)

### Adhésions
- **Adhesion** — lien User/Saison, tarification, statut paiement
  - `statut_paiement` : EN_ATTENTE | VALIDE | REMBOURSE
  - `preferences` : JSON (indisponibilités, souhaits équipe, coupes)
  - `membre_famille` : support adhésions pour famille (FK optional)
  
- **TarifAdhesion** — tarification administrable (saison × catégorie)

- **Coupe** — listes coupes (Heitz féminin, Aïco masculin, etc.)

### HelloAsso
Champs sur Adhesion :
- `helloasso_checkout_intent_id` — ID intent créé lors du paiement
- `helloasso_payment_id` — ID unique du paiement (webhook)
- `helloasso_order_id` — ID commande HelloAsso
- `helloasso_payer_email` — email du payeur
- `helloasso_payment_receipt_url` — URL attestation HelloAsso
- `helloasso_webhook_id` — idempotence webhook
- `helloasso_metadata` — JSON metadata
- `last_webhook_at` — audit trail

## Routes & Vues

### Public
- `GET /` — Accueil
- `GET/POST /adhesion/` — Formulaire adhésion (login required)

### Adhérent
- `GET /mon-compte/adhesions/` — Mes adhésions
- `POST /adhesion/<id>/payer/` — Initier paiement HelloAsso
- `GET /adhesion/<id>/paiement-retour/` — Retour après paiement
- `GET /adhesion/<id>/paiement-erreur/` — Erreur paiement

### Admin
- `/admin/adhesions/adhesion/` — Gestion adhésions
  - Colonnes : bénéficiaire, saison, montant, statut, lien commande
  - Fieldset HelloAsso : IDs, attestation, metadata, webhook trace
  - Liens cliquables vers HelloAsso (attestation)

### API
- `POST /api/webhooks/helloasso/` — Webhook paiement HelloAsso

## Flux Paiement

1. **Création adhésion** → `adhesions/views.py:adhesion_view()`
   - Form validation + tarification
   - Création Adhesion(statut=EN_ATTENTE)
   - Redirection `/mon-compte/adhesions/`

2. **Initiation paiement** → `adhesions/views_payment.py:adhesion_payer()`
   - OAuth2 token fetch (HelloAsso)
   - Create checkout intent → `helloasso_checkout_intent_id`
   - Redirect user → HelloAsso payment page

3. **Webhook HelloAsso** → `adhesions/views_webhook.py:webhook_helloasso()`
   - Vérification signature HMAC SHA256
   - Résolution adhésion (4 stratégies) :
     1. `metadata.adhesion_id` (plusieurs locations)
     2. `helloasso_order_id` (data.order.id)
     3. `helloasso_checkout_intent_id`
     4. Email + montant (EN_ATTENTE) — refuse si ambigu
   - Update `statut_paiement` (VALIDE ou REMBOURSE)
   - Idempotence : `helloasso_webhook_id` unique
   - Email confirmation (Brevo)

4. **Retour utilisateur** → `adhesions/views_payment.py:adhesion_paiement_retour()`
   - Capture querystring (checkoutIntentId, orderId)
   - Affiche page "Merci" (webhook peut ne pas être arrivé)

## Intégrations

### HelloAsso OAuth2

**Settings** :
```python
HELLOASSO_API_HOST = "https://api.helloasso-sandbox.com/v5"  # ou prod
HELLOASSO_CLIENT_ID = "..."
HELLOASSO_CLIENT_SECRET = "..."
HELLOASSO_WEBHOOK_SECRET = "..."
HELLOASSO_ORGANIZATION_SLUG = "usm-volley"
PUBLIC_BASE_URL = "https://example.com"  # HTTPS pour BackUrl
```

**Wrapper** : `adhesions/services/helloasso_client.py`
- `_fetch_token()` → Authlib OAuth2Session
- `create_checkout_intent(amount, payer_email, metadata)` → HelloAssoCheckoutIntent

**Dépendances** : `Authlib>=1.3`, `requests>=2.31`

### Brevo Emails

**Settings** :
```python
BREVO_API_KEY = "..."
BREVO_TEMPLATE_ADHESION_CREATED = 123  # ID template Brevo
BREVO_TEMPLATE_PAYMENT_CONFIRMED = 456
```

**Wrapper** : `adhesions/services/brevo_client.py`
- `send_adhesion_created(email, first_name, last_name, amount, category, season)`
- `send_payment_confirmed(email, first_name, last_name, amount, payment_id)`

**Dépendances** : `sib-api-v3-sdk>=7.6.0`

## Sécurité Webhook

```python
@require_http_methods(["POST"])
@csrf_exempt  # Webhook externe
def webhook_helloasso(request):
    # 1. Vérifier signature HMAC SHA256
    signature = request.headers.get("X-HelloAsso-Signature")
    if not verify_signature(payload_raw, signature):
        return JsonResponse({"error": "Invalid signature"}, status=401)
    
    # 2. Check idempotence via helloasso_webhook_id (UNIQUE)
    if Adhesion.objects.filter(helloasso_webhook_id=webhook_id).exists():
        return JsonResponse({"ok": True}, status=200)  # déjà traité
    
    # 3. Résoudre l'adhésion (4 stratégies)
    adhesion = _resolve_adhesion(payload)
    
    # 4. Update status + sauvegarde
    adhesion.statut_paiement = new_status
    adhesion.save()
    
    # 5. Return 200 OK
    return JsonResponse({"ok": True}, status=200)
```

**Signature** :
- HelloAsso envoie : `X-HelloAsso-Signature: sha256=<hex>`
- On vérifie : `hmac.compare_digest(signature, expected)`
- Debug mode : si `DEBUG=True` et pas de signature → accepté (sandbox)

## Admin Customization

**AdhesionAdmin** :
- `list_display` : bénéficiaire, user, saison, catégorie, montant, statut, commande, date
- `search_fields` : email, helloasso_payment_id, helloasso_order_id
- `list_filter` : statut_paiement, categorie, saison
- Fieldset HelloAsso : IDs (readonly), lien attestation cliquable, metadata, last_webhook_at
- Fieldset Préférences : formatage JSON, éditable brut

**Liens cliquables** :
- `helloasso_receipt_link` → attestation HelloAsso (depuis `paymentReceiptUrl`)
- `helloasso_order_link` → même URL, affichage order_id

## Tests

```bash
python manage.py test adhesions.tests.HelloAssoWebhookTests
python manage.py test adhesions.tests.AdhesionPaymentViewTests
```

**Coverage** :
- Webhook signature valid/invalid
- Webhook idempotence (duplicate)
- Adhesion resolution (4 strategies)
- Status transitions (EN_ATTENTE → VALIDE)
- Payer redirect (unauthenticated → 403)
- Payment already done → redirect home

## Règles Métiers

### Statuts Paiement
- **EN_ATTENTE** : adhésion créée, en attente paiement
- **VALIDE** : paiement reçu, confirmé (webhook state=authorized)
- **REMBOURSE** : remboursement reçu (webhook state=refunded)

### Protection Overwrites
- Si adhésion existante pour (User, Saison) et statut=VALIDE/REMBOURSE
  → refuser nouvelle adhesion (msg erreur → redirect home)
- Permet update si statut=EN_ATTENTE

### Tarification
Fallback PRICING (si pas TarifAdhesion en DB) :
- SANS_COMPETITION: 60€
- COMPETITION_VOLLEY: 100€
- COMPETLIB: 100€
- COMPETITION_DEP: 150€

## Déploiement

### Environnement
```bash
# .env
DEBUG=False
SECRET_KEY=...
ALLOWED_HOSTS=example.com,www.example.com
DATABASE_URL=postgres://user:pass@host:5432/db
HELLOASSO_API_HOST=https://api.helloasso.com/v5
HELLOASSO_CLIENT_ID=...
HELLOASSO_CLIENT_SECRET=...
HELLOASSO_WEBHOOK_SECRET=...
PUBLIC_BASE_URL=https://example.com
BREVO_API_KEY=...
```

### Docker
```dockerfile
FROM python:3.11
WORKDIR /app
COPY requirements.txt .
RUN pip install -r requirements.txt
COPY . .
CMD ["gunicorn", "usm_volley.wsgi"]
```

### Migration DB
```bash
python manage.py migrate --noinput
python manage.py collectstatic --noinput
```

## Prochaines Étapes

- [ ] Pages statiques CMS (PageStatique model + admin)
- [ ] Menu dynamique (MenuItem model + context processor)
- [ ] Blog avec CKEditor5
- [ ] Espace adhérent complet (mon-compte/documents, etc.)
- [ ] Back-office admin (KPIs, import CSV)
- [ ] Rate limiting sur webhook
- [ ] Audit logging (historique modifications Adhesion)
- [ ] Cache redis (menu, tarifs)

## Fichiers Clés

- `usm_volley/settings.py` — config HelloAsso, Brevo, DB
- `adhesions/models.py` — Adhesion + HelloAsso fields
- `adhesions/views.py` — formulaire adhésion
- `adhesions/views_payment.py` — paiement HelloAsso
- `adhesions/views_webhook.py` — webhook + résolution
- `adhesions/admin.py` — interface admin
- `adhesions/services/helloasso_client.py` — OAuth2 + checkout
- `adhesions/services/brevo_client.py` — emails
- `templates/compte/adhesions.html` — liste adhésions front-end
- `templates/adhesion/form.html` — formulaire adhésion

## Notes

- Webhook CSRF exempt car source externe
- HMAC timing-attack safe (`hmac.compare_digest`)
- Idempotence garantie par contrainte UNIQUE sur `helloasso_webhook_id`
- Retry logic : 202 Accepted on error → HelloAsso retry
- Email errors logged but don't block the flow
- Debug mode bypass signature en sandbox (SKIP_SIGNATURE_IN_DEBUG)

---

**Status**: Infrastructure ✅ | Authentification ✅ | Adhésion ✅ | Paiements ✅ | CMS ⏳
