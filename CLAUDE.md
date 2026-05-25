# CLAUDE.md — USM Volley (PHP)

## Contexte du projet

Site public + back-office pour l'**Union Sportive Miosienne Volley-Ball**.
Stack : PHP 8.2 · Twig · MySQL 8 · TailwindCSS (CDN) · Docker Compose.

Hébergement cible : **InfinityFree** (shared hosting, pas de SSH, pas de
Composer en prod). Toutes les dépendances (`vendor/`) sont versionnées.

---

## Lancer le projet en dev

```bash
docker compose up --build
```

| Service | URL |
|---|---|
| Site PHP | http://localhost:8080 |
| phpMyAdmin (base locale) | http://localhost:8081 |
| MySQL local | localhost:3306 |
| MySQL externe simulée | localhost:3307 |

Au démarrage, deux services `migrate` et `migrate_external` appliquent
automatiquement tous les fichiers SQL dans l'ordre puis s'arrêtent.

---

## Architecture des fichiers

```
/
├── public/
│   ├── index.php          ← point d'entrée unique
│   └── assets/uploads/    ← photos uploadées (gitkeep)
├── src/
│   ├── Core/
│   │   ├── App.php            ← enregistrement de toutes les routes
│   │   ├── Database.php       ← singleton PDO (base locale usm_volley)
│   │   ├── ExternalDatabase.php ← singleton PDO (base externe USM)
│   │   ├── Router.php         ← dispatch HTTP, params {id} → tableau
│   │   ├── View.php           ← render Twig + flash + globals
│   │   └── Auth.php           ← session admin
│   ├── Models/
│   │   ├── Post.php, PageStatique.php, Document.php, MenuItem.php, Photo.php
│   │   ├── Saison.php         ← saisons locales (activate = transaction)
│   │   ├── JoueurSnapshot.php ← flash depuis base externe + rebuild équipes
│   │   ├── EquipeConfig.php   ← config permanente des équipes
│   │   ├── EquipeSaison.php   ← liaison équipe × saison (findOrCreate)
│   │   ├── EquipeSaisonJoueur.php ← membres d'une équipe pour une saison
│   │   ├── Contact.php        ← messages de contact (create, find, all, updateStatus, delete)
│   │   └── ContactReply.php   ← réponses aux contacts
│   ├── Controllers/
│   │   ├── AgendaController.php      ← /agenda (crosstable) + /agenda/{id} (détail)
│   │   ├── HomeController.php, BlogController.php, PageController.php
│   │   ├── ContactController.php       ← formulaire public /contact
│   │   ├── EquipesController.php  ← /equipes + /equipes/{id}
│   │   └── Admin/
│   │       ├── AuthController.php, DashboardController.php
│   │       ├── PostController.php, PageAdminController.php
│   │       ├── MenuController.php, DocumentController.php
│   │       ├── SaisonController.php       ← admin saisons + flash
│   │       ├── EquipeConfigController.php ← admin équipes + photos + joueurs
│   │       └── ContactAdminController.php ← gestion messages de contact
│   ├── Helpers/
│   │   └── ParticipationStatus.php  ← parsing centralisé des statuts de participation
│   └── Services/
│       ├── AgendaService.php  ← crosstable, stats participation, manifestations
│       ├── BrevoService.php   ← emails via API Brevo
│       ├── Validator.php      ← validation centralisée
│       ├── Logger.php         ← logging multi-canal
│       ├── SlugManager.php    ← génération slugs
│       └── Pagination.php     ← pagination
├── config/config.php      ← constantes DB_*, EXT_DB_*, BASE_URL, etc.
├── database/
│   ├── schema.sql, seed.sql, add_photos.sql  ← base locale initiale
│   ├── external_schema.sql, external_seed.sql ← base externe simulée
│   └── migrations/
│       ├── 001_saisons.sql          ← saisons + joueur_snapshots
│       └── 002_equipes_config.sql   ← equipes_config + equipe_saison
│                                       + equipe_saison_joueur + ALTER photos
└── templates/front001/
    ├── base.twig, home.twig, 404.twig, _gallery.twig
    ├── blog/, pages/, documents/
    ├── agenda/
    │   ├── index.twig         ← tableau croisé joueurs × manifestations
    │   ├── detail.twig        ← détail manifestation + participation
    │   ├── filters.twig       ← formulaire filtres (équipe, type, lieu, etc.)
    │   └── _macros.twig       ← macros réutilisables (filtres, stats, cellules)
    ├── equipes/
    │   ├── index.twig   ← cards groupées par catégorie (saison active)
    │   └── detail.twig  ← galerie + liste joueurs
    └── admin/
        ├── layout.twig, login.twig, dashboard.twig
        ├── _photo_dropzone.twig   ← Dropzone réutilisable (posts/pages)
        ├── posts/, pages/, menu/, documents/
        ├── saisons/
        │   ├── list.twig, create.twig, snapshots.twig, joueurs.twig
        ├── equipes-config/
        │   ├── list.twig, form.twig
        │   ├── saison_photos.twig   ← Dropzone dédié équipe×saison
        │   └── saison_joueurs.twig  ← ajout/retrait joueurs post-flash
        └── contacts/
            ├── list.twig   ← liste messages avec filtres + actions de masse
            └── detail.twig ← détail + historique + formulaire réponse
    └── contact/
        └── form.twig   ← formulaire public de contact
```

---

## Deux bases de données

### Base locale (`usm_volley`)

Gérée par `Database::get()`. Contient tout ce que le site gère lui-même :
`posts`, `pages`, `menu_items`, `documents`, `photos`, `saisons`,
`joueur_snapshots`, `equipes_config`, `equipe_saison`, `equipe_saison_joueur`.

### Base externe USM

Gérée par `ExternalDatabase::get()`. En production c'est la base InfinityFree
du club (`if0_34936599_usm`). En dev elle est simulée par le service Docker
`db_external` (port 3307) avec des données de test.

Contient : `Joueurs` (avec flags d'équipes) et `Manifestation` (agenda).

Variables d'environnement : `EXT_DB_HOST`, `EXT_DB_NAME`, `EXT_DB_USER`,
`EXT_DB_PASS`.

---

## Patterns à respecter

### Ajouter un modèle

Utiliser `Database::get()` (PDO, `FETCH_ASSOC`). Pas d'ORM.
Les méthodes sont toutes `static`. Exemple minimal :

```php
public static function find(int $id): ?array
{
    $stmt = Database::get()->prepare("SELECT * FROM table WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}
```

### Ajouter une route

Dans `src/Core/App.php`, méthode `registerRoutes()` :

```php
$r->get('/ma-route/{id}', [MonController::class, 'maMethode']);
```

Les segments `{id}` deviennent des clés dans le tableau `$params` reçu par
le contrôleur. Mettre les routes statiques (`/create`, `/joueurs`) **avant**
les routes paramétrées (`/{id}`) pour éviter les conflits.

### Ajouter un contrôleur admin

1. Créer `src/Controllers/Admin/MonController.php`
2. Appeler `Auth::require()` en tête de chaque action
3. Utiliser `View::flash('success', '...')` + `header('Location: ...')` pour les redirections
4. Ajouter l'`use` et les routes dans `App.php`

### Photos (upload Dropzone)

Pour les entités `post` et `page`, réutiliser `admin/_photo_dropzone.twig`
(passe `entity_type`, `entity_id`, `upload_url`, `photos`).

Pour les équipes×saison, le template dédié `saison_photos.twig` inline
le Dropzone avec des URLs explicites (entity_type = `equipe_saison`).

```php
Photo::forEntity('equipe_saison', $es['id'])  // lecture
Photo::create('equipe_saison', $es['id'], $filename) // écriture
```

### Éditeur WYSIWYG — Jodit (posts & pages)

L'éditeur Jodit est utilisé pour les posts et pages dans les trois thèmes
(`front001`, `front002`, `front003`). Il supporte :

- **Drag & drop d'images** directement dans l'éditeur → upload automatique → insertion `<img>` inline
- **Drag & drop de fichiers** (PDF, DOC/DOCX, XLS/XLSX) → upload → insertion d'un lien `<a>`
- **Bouton "insertFile"** dans la toolbar (icône document) → sélecteur de fichiers PDF/Word/Excel

L'upload passe par `POST /admin/media/upload` → `MediaUploadController::upload()`.
Les fichiers sont stockés dans `public/assets/uploads/editor_image/YYYY/MM/` ou
`editor_file/YYYY/MM/` selon le type.

Le token CSRF est injecté automatiquement via `uploader.data` (lu depuis l'input
`[name="_csrf_token"]` du formulaire). Ne pas supprimer cet input du formulaire.

Formats acceptés : JPG, PNG, WebP, GIF (images) · PDF, DOC, DOCX, XLS, XLSX (fichiers).
Taille max : 10 Mo.

### Twig

- `url('chemin')` → `BASE_URL/chemin`
- `asset('uploads/fichier.jpg')` → `BASE_URL/assets/uploads/fichier.jpg`
- `{{ var|date_fr }}` → date au format `d/m/Y`
- Flash message : disponible via la globale `flash` (type + message)
- `APP_DEBUG = true` → pas de cache Twig

---

## Saisons & Flash joueurs

Workflow admin :

1. **Créer une saison** — `/admin/saisons/create`
2. **Activer la saison** — met `is_active = 1`, remet les autres à 0 (transaction)
3. **Flasher** — `POST /admin/saisons/{id}/flash`
   - Lit tous les `Joueurs` de la base externe
   - Upsert dans `joueur_snapshots` (idempotent via `ON DUPLICATE KEY UPDATE`)
   - Pour chaque `equipes_config` active : `DELETE` + re-`INSERT` dans
     `equipe_saison_joueur` selon les flags booléens du snapshot
   - **Re-flasher réinitialise les ajustements manuels des équipes**

Ajustements post-flash : `/admin/equipes-config/{id}/saisons/{sid}/joueurs`
(ajouter / retirer un joueur sans re-flasher).

---

## Page front-end /equipes

- Seules les équipes ayant **au moins un membre** dans `equipe_saison_joueur`
  pour la saison active sont affichées.
- La photo de couverture = `Photo::forEntity('equipe_saison', $es['id'])[0]`.
- La page détail liste les joueurs via `EquipeSaisonJoueur::findByEquipeSaison()`,
  triés `nom ASC`.

---

## Agenda (Manifestations)

### Vue d'ensemble

Affiche les événements (matchs et entraînements) avec participation des joueurs en temps réel.
Lit la table `Manifestation` et `Participation` de la base externe via `ExternalDatabase::get()`.

Routes :
- `GET /agenda` — tableau croisé (joueurs × événements) avec filtres
- `GET /agenda/{id}` — détail d'un événement + stats de participation

### AgendaService

Service métier pour la gestion de l'agenda :

**Méthodes principales** :
- `getCrossTable(array $filters = [])` — crosstable joueurs × manifestations
  - Retourne : `joueurs`, `manifestations`, `cross` (matrix participation)
  - Filtres supportés : `team`, `location`, `type`, `manifestation`, `this_week`, `hide_empty_players`
  
- `getParticipationStats(int $manifestationId)` — stats participation pour UN événement
  - Retourne : counts par catégorie + `enough_players` (bool : >= 6 joueurs dispo)
  
- `getParticipationStatsBatch(array $manifestationIds)` — stats en batch (évite N+1)
  
- `getFilterOptions()` — options pour les dropdowns filtres
  - Types (Match, Entraînement, etc.)
  - Locations (Lieu)
  - ManifestationNames (segment 3 de ManifestationTypée)
  - Teams (depuis Mots_clef où Catégorie='EquipeParEquipe')

**Événements rapides** :
- `getUpcomingMatches(int $limit = 5)` — matchs à venir
- `getUpcomingTrainings(int $limit = 5)` — entraînements à venir

### ParticipationStatus (Helper)

Centralise le parsing des statuts de participation. Élimine duplication across 3 services methods.

**Catégories** :
- `present` — "Présent", "Oui"
- `available` — "Disponible", "Joker", "Disponible si nécessaire"
- `unavailable` — "Indisponible"
- `absent` — "Absent", "Non"
- `selected` — "Sélectionné(e)"
- `unknown` — "Ne sait pas encore", "?"
- `no_response` — vide

**API** :
```php
use App\Helpers\ParticipationStatus;

$status = new ParticipationStatus($dbValue);
$status->getCategory();        // → 'present', 'available', etc.
$status->isPresent();          // → bool
$status->getCompanionCount();  // → int (0-4 pour accompagnants)
$status->getIcon();            // → '✓', '◐', '✗', etc.
$status->getBackgroundColor(); // → 'bg-green-100', etc.
```

### Règle métier : "Équipe OK"

Un événement a **assez de joueurs** si :
```
(présents + disponibles + sélectionnés) >= 6
```

Affichage : "✓ Équipe OK" (vert) ou "⚠️ Sous-effectif" (rouge)

**Important** : Ce statut s'affiche **uniquement pour les matchs**, pas les entraînements.

### Filtres agenda

Implémentés via `extractFilters()` en AgendaController.

| Filtre | Type | Effet |
|--------|------|-------|
| `team` | string | Restreint joueurs à une équipe |
| `type` | string | Filtre événements par type (Match, Entraînement, etc.) |
| `location` | string | Filtre par Lieu |
| `manifestation` | string | Filtre par nom d'événement (segment 3) |
| `this_week` | bool | Seulement événements cette semaine (Lun-Dim) |
| `hide_empty_players` | bool | Masque joueurs sans participation |

Les filtres "Tous" sont ignorés et supprimés du tableau retourné.

### Templates et Macros

**_macros.twig** fournit 5 macros réutilisables :
- `render_filter_dropdown()` — sélecteur filtre
- `render_filter_checkbox()` — case à cocher filtre
- `render_participation_cell()` — badge statut participation (icon + label + couleur)
- `render_stats_row()` — ligne table stats (icône, label, count)
- `render_participation_badge()` — badge viabilité événement

---

## Services métier

### Validator

Validation centralisée avec API chaînable :

```php
use App\Services\Validator;

$v = Validator::make($_POST)
    ->required('title', 'Le titre est obligatoire.')
    ->minLength('title', 3)
    ->email('email')
    ->unique('email', fn($val) => !UserExists($val));

if ($v->fails()) {
    // $v->errors() → tableau des erreurs
    // $v->firstError() → première erreur
    return;
}
$data = $v->getCleanData(['title', 'email']);
```

### Logger

Logging multi-canal (app, audit, errors) :

```php
use App\Services\Logger;

Logger::app()->info('Action effectuée', ['user_id' => 123]);
Logger::audit()->warning('Modification sensible', ['entity' => 'post', 'id' => 45]);
Logger::errors()->error('Erreur BDD', ['query' => '...']);
```

Logs écrits dans `/logs/app.log`, `/logs/audit.log`, `/logs/errors.log`.

### SlugManager

Génération et unicité des slugs :

```php
use App\Services\SlugManager;

$slug = SlugManager::generate('Mon Article');      // → 'mon-article'
$unique = SlugManager::makeUnique($slug, 'posts'); // → 'mon-article-2' si existe
```

Utilisé par Post et PageStatique (évite duplication).

### BrevoService

Intégration avec l'API Brevo pour l'envoi d'emails :

```php
use App\Services\BrevoService;

$brevo = new BrevoService();
$brevo->sendEmail($email, $name, $subject, $htmlContent, $textContent);
$brevo->sendContactNotification($contact); // notif admin
$brevo->sendReplyToVisitor($email, $name, $replyText); // réponse visiteur
```

**Configuration** : Variables d'env `BREVO_API_KEY`, `BREVO_FROM_EMAIL`, `BREVO_FROM_NAME`.

Templates HTML stylisés en néo-brutalisme (même design que le site).
Signature inclut les infos du club depuis `site_config`.

---

## Formulaire de contact & gestion des messages

### Public : `/contact`

- Formulaire de contact avec validation (nom, email, sujet, message)
- Préservation des données en cas d'erreur
- Notification auto à l'admin via Brevo (email stylisé)

### Admin : `/admin/contacts`

Tableau de bord complet pour gérer les messages :

- **Filtrage** : Nouveaux / Répondus / Archivés / Tous
- **Sélection en masse** : checkboxes + "Sélectionner tous"
- **Actions groupées** : Archiver ou Supprimer plusieurs messages
- **Détail message** : `/admin/contacts/{id}`
  - Historique des réponses
  - Formulaire de réponse (envoie email au visiteur via Brevo)
  - Changement de statut individuel
  - Suppression individuelle

**Models** :
- `Contact` : messages entrants (create, find, all, updateStatus, delete, countByStatus)
- `ContactReply` : historique des réponses (create, findByContact, getLatest)

**Métriques au dashboard** :
- Nombre de messages neufs (badge rouge dans le menu si > 0)
- Nombre de messages répondus
- Total de messages

---

## Contrôleurs CRUD admin

### Base class : AdminCrudController

Pour simplifier les CRUD post/page/etc., étendre `AdminCrudController` :

```php
use App\Controllers\Admin\AdminCrudController;
use App\Models\MyEntity;

class MyController extends AdminCrudController
{
    public function __construct()
    {
        $this->entityType = 'my_entity';
        $this->itemName = 'item';
        $this->itemsName = 'items';
    }

    protected function getEntity(int $id): ?array
    {
        return MyEntity::find($id);
    }

    protected function createEntity(array $data): int
    {
        return MyEntity::create($data);
    }

    protected function getFormData(): array
    {
        return ['field' => $_POST['field'] ?? ''];
    }
    // ... autres abstract methods
}
```

Fournit : `index()`, `create()`, `store()`, `edit()`, `update()`, `delete()`,
`uploadPhoto()`, `deletePhoto()`, `deletePhotoXhr()`.

### 404 handler

Dans les contrôleurs publics, utiliser le trait `NotFoundHandler` :

```php
use App\Core\NotFoundHandler;

class BlogController
{
    use NotFoundHandler;

    public function show(array $params): void
    {
        $post = Post::findBySlug($params['slug']);
        if (!$post) {
            $this->notFound();
            return;
        }
        // ...
    }
}
```

---

## Sécurité

### CSRF tokens

Tous les formulaires POST admin incluent automatiquement le token :
```twig
<form method="POST" action="...">
  <input type="hidden" name="_csrf_token" value="{{ csrf_token }}">
  <!-- ... champs ... -->
</form>
```

Validé automatiquement dans `App.php` (excepté `/admin/login`).

### Photo cover

Accès sûr à la photo de couverture d'une entité :

```php
$cover = Photo::getEntityCover('equipe_saison', $es['id']);
// Remplace : $cover = Photo::forEntity(...)[0] ?? null;
```

### Twig dans les pages CMS

Les pages et articles supportent la compilation Twig pour référencer la configuration du site.
Cela permet aux éditeurs de construire du contenu dynamique sans code :

```twig
Email: {{ site_config.email }}
Téléphone: {{ site_config.phone|default('Non renseigné') }}
Adresse: {{ site_config.address }}
© {{ site_config.club_name }}
```

**Champs disponibles** : `club_name`, `club_tagline`, `address`, `email`, `phone`,
`facebook_url`, `instagram_url`, `legal_text`, `home_slider_posts_count`,
`home_latest_posts_count`.

**Fonctionnement** :
- À chaque rendu d'une page ou article, le contenu est compilé comme template Twig
- Le contexte fourni est limité à `site_config` uniquement (pas d'accès à auth, globals, etc.)
- Les erreurs Twig sont loggées ; en debug le message s'affiche, en production le contenu brut s'affiche

**Usage** :
- Pages mentions légales : insérer email/adresse qui se mettront à jour automatiquement
- Articles de référence : ajouter dynamiquement le nom du club
- Conditions : `{% if site_config.facebook_url %}Suivez-nous{% endif %}`
- Filtres : `{{ site_config.legal_text|truncate(200) }}`

---

## Variables d'environnement

| Variable | Défaut dev | Description |
|---|---|---|
| `DB_HOST` | `db` | MySQL local (Docker) |
| `DB_NAME` | `usm_volley` | Base locale |
| `DB_USER` / `DB_PASS` | `usm` / `usm_password` | Credentials locaux |
| `EXT_DB_HOST` | `db_external` | MySQL externe simulé |
| `EXT_DB_NAME` | `usm_external` | Base externe |
| `EXT_DB_USER` / `EXT_DB_PASS` | `usm_ext` / `usm_ext_password` | Credentials externes |
| `BASE_URL` | auto-détecté | URL publique sans slash final |
| `THEME` | `front001` | Dossier sous `templates/` |
| `APP_DEBUG` | `true` | Désactive cache Twig, affiche PDOException |
| `ADMIN_EMAIL` | — | Email admin |
| `ADMIN_PASSWORD_HASH` | — | `password_hash(..., PASSWORD_BCRYPT)` |
| `BREVO_API_KEY` | — | Clé API Brevo pour envoyer les emails |
| `BREVO_FROM_EMAIL` | `noreply@usm-volley.fr` | Email "De" pour les notifications |
| `BREVO_FROM_NAME` | `USM Volley` | Nom "De" pour les notifications |

---

## Migrations

Les fichiers SQL sont **idempotents** (`IF NOT EXISTS`, `INSERT IGNORE`,
`ON DUPLICATE KEY UPDATE`). Pour ajouter une migration :

1. Créer `database/migrations/NNN_description.sql`
2. Ajouter la ligne dans le service `migrate` du `docker-compose.yml`

Ordre d'application au démarrage :
```
schema.sql → seed.sql → add_photos.sql
→ 001_saisons.sql → 002_equipes_config.sql → ... → 008_contacts.sql
```

**Migrations récentes** :
- `003_home_blocks.sql` — blocs accueil (contenu + position)
- `004_site_config.sql` — config du site (nom, email, phone, réseaux)
- `005_joueur_snapshots_nlicence.sql` — ajout colonne nlicence
- `006_article_api.sql` — table pour intégration API article
- `007_tags.sql` — table tags pour catégorisation articles
- `008_contacts.sql` — messages de contact + réponses (Brevo)
