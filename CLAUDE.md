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
│   └── Services/
│       ├── AgendaService.php  ← lit Manifestation via ExternalDatabase
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

## AgendaService

Lit la table `Manifestation` de la base externe via `ExternalDatabase::get()`.
`getUpcomingMatches(int $limit)` et `getUpcomingTrainings(int $limit)` renvoient
un tableau d'événements normalisés :

```
title, date_display (fr : "Ven 24 Avr"), time_display (HH:MM),
location, comment, status, is_soon (bool : dans les 3 jours)
```

En cas d'échec de connexion à la base externe, retourne `[]` silencieusement.

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
- `005_joueur_snapshots_nlicence.sql` — ajout colonne n_licence
- `006_article_api.sql` — table pour intégration API article
- `007_tags.sql` — table tags pour catégorisation articles
- `008_contacts.sql` — messages de contact + réponses (Brevo)
