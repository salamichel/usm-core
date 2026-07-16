# CLAUDE.md — USM Volley (PHP)

## Contexte du projet

Site public + back-office pour l'**Unions Salles Mios Volley-Ball**.
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
│   ├── .htaccess          ← configuration Apache et redirections URL
│   ├── manifest.json      ← manifeste PWA (configuration de l'application)
│   ├── service-worker.js  ← service worker pour le support PWA et cache hors-ligne
│   └── assets/
│       ├── content-styles.css ← styles typographiques de l'éditeur WYSIWYG
│       ├── front001/, front002/, front003/ ← ressources statiques (CSS, JS, images, polices)
│       ├── icons/         ← icônes de raccourci et de PWA
│       └── uploads/       ← photos et documents importés/téléversés (gitkeep)
├── src/
│   ├── Core/
│   │   ├── App.php            ← configuration globale et enregistrement de toutes les routes
│   │   ├── AbstractDatabase.php ← classe abstraite parente des singletons PDO (connexion, configuration)
│   │   ├── Database.php       ← singleton PDO pour la base de données locale (usm_volley)
│   │   ├── ExternalDatabase.php ← singleton PDO pour la base externe (simulée/réelle)
│   │   ├── Router.php         ← dispatching des requêtes HTTP et extraction des paramètres
│   │   ├── View.php           ← wrapper de rendu Twig, lazy-loading des variables globales et gestion du flash
│   │   ├── Auth.php           ← gestion de la session et des accès de l'administrateur
│   │   ├── CsrfToken.php      ← génération et validation des jetons de sécurité CSRF
│   │   └── NotFoundHandler.php ← trait fournissant des helpers pour les erreurs 404
│   ├── Models/
│   │   ├── Post.php           ← articles de blog (slugs, slider, tag, etc.)
│   │   ├── PageStatique.php   ← pages statiques du CMS
│   │   ├── MenuItem.php       ← éléments de navigation dynamique du menu
│   │   ├── Photo.php          ← gestion des images, variantes et couverture des entités
│   │   ├── Saison.php         ← saisons sportives locales (activation de saison via transaction)
│   │   ├── JoueurSnapshot.php ← copie figée des joueurs récupérés lors du flash de la saison active
│   │   ├── EquipeConfig.php   ← configuration persistante des équipes (sexe, niveau, filet, lien FFVB)
│   │   ├── EquipeSaison.php   ← liaison d'une équipe à une saison spécifique
│   │   ├── EquipeSaisonJoueur.php ← composition d'une équipe (joueurs et statut capitaine)
│   │   ├── Contact.php        ← messages généraux et status (ancien modèle)
│   │   ├── ContactMessage.php ← soumissions de messages du formulaire de contact public
│   │   ├── ContactReply.php   ← historique des réponses envoyées aux contacts par l'admin
│   │   ├── CategorieEquipe.php ← catégories d'équipes (ex. Jeunes, Seniors) pour regroupement
│   │   ├── HomeBlock.php      ← blocs de contenu administrables sur la page d'accueil
│   │   ├── Location.php       ← lieux physiques des entraînements et des matchs
│   │   ├── SiteConfig.php     ← configuration globale du club injectée dans Twig
│   │   ├── Tag.php            ← étiquettes associées aux articles de blog
│   │   ├── Joueur.php         ← modèle représentant les joueurs dans la base externe
│   │   ├── Participation.php  ← présences/absences stockées dans la base externe
│   │   └── EntityType.php     ← énumération des types d'entités liés à des photos
│   ├── Controllers/
│   │   ├── HomeController.php   ← page d'accueil publique (blocs, posts épinglés)
│   │   ├── BlogController.php   ← affichage de la liste des articles et détails (pagination, tags)
│   │   ├── PageController.php   ← affichage des pages statiques avec compilation Twig
│   │   ├── ContactController.php ← formulaire de contact public (soumission et envoi Brevo)
│   │   ├── EquipesController.php ← liste des équipes par catégorie et détail d'une équipe
│   │   ├── AgendaController.php ← planning et grille globale de présence
│   │   ├── JoueurController.php ← fiche d'information d'un joueur
│   │   ├── SitemapController.php ← rendu du sitemap.xml et robots.txt
│   │   ├── Admin/
│   │   │   ├── BaseAdminController.php ← contrôleur parent pour la vérification de session admin
│   │   │   ├── AdminCrudController.php ← classe de base abstraite pour tous les CRUD admin (photos comprises)
│   │   │   ├── AuthController.php      ← connexion et déconnexion de l'administration
│   │   │   ├── DashboardController.php ← statistiques globales et messages non lus
│   │   │   ├── PostController.php, PageAdminController.php ← édition des articles/pages
│   │   │   ├── MenuController.php      ← réorganisation et édition des items de menu
│   │   │   ├── SaisonController.php    ← création de saisons, activation et processus de flash
│   │   │   ├── EquipeConfigController.php ← édition des équipes, assignation de joueurs & photos
│   │   │   ├── ContactAdminController.php ← liste des messages, historique et réponses
│   │   │   ├── ContactMessageController.php ← suppression et archivage fin des messages
│   │   │   ├── CategorieEquipeController.php, TagController.php, HomeBlockController.php ← gestion CRUD
│   │   │   ├── LocationController.php  ← édition et géolocalisation Google Maps des gymnases
│   │   │   ├── MediaUploadController.php ← gestionnaire d'upload asynchrone pour l'éditeur WYSIWYG
│   │   │   ├── PhotoAdminController.php ← contrôleur de suppression/réorganisation AJAX d'images
│   │   │   └── SiteConfigController.php ← configuration des thèmes et des données du club
│   │   ├── Api/
│   │   │   └── ArticleApiController.php ← endpoint POST de synchronisation d'articles externes
│   │   └── Member/
│   │       ├── AuthController.php      ← connexion/déconnexion des adhérents
│   │       ├── DashboardController.php ← page d'accueil de l'espace adhérent (KPIs, planning)
│   │       ├── ParticipationController.php ← sauvegarde AJAX/synchrone des présences aux manifestations
│   │       ├── ProfileController.php   ← modification des infos de profil et mot de passe adhérent
│   │       └── CaptainController.php   ← tableau de bord capitaine (grille de présence, popover AJAX, convocations)
│   ├── Helpers/
│   │   ├── ParticipationStatus.php ← helper de formatage et de styles de badges de participation
│   │   └── HtmlHelper.php          ← fonctions utilitaires pour la génération de code HTML
│   ├── ValueObjects/
│   │   └── PageMetadata.php       ← objet valeur encapsulant les métadonnées SEO d'une page
│   └── Services/
│       ├── AgendaService.php       ← coordination de l'affichage de l'agenda croisé public/capitaine
│       ├── Agenda/
│       │   ├── EventNormalizer.php     ← normalisation des dates et types d'événements
│       │   ├── EventRepository.php     ← requêtes SQL brutes optimisées pour l'agenda externe
│       │   └── ParticipationStatsService.php ← calcul des effectifs et alertes de sous-effectif
│       ├── MemberDashboardService.php  ← génération des statistiques et KPIs de l'espace membre
│       ├── BrevoService.php        ← wrapper API Brevo pour notifications admin et réponses email
│       ├── ContentRenderer.php     ← compilation dynamique à la volée des blocs Twig du CMS
│       ├── ExternalImageDownloader.php ← téléchargement et mise en cache des images distantes
│       ├── ImageResizer.php, ImageVariant.php ← redimensionnement et gestion des formats WebP/variants
│       ├── Logger.php              ← logger multi-canal (app, audit, errors)
│       ├── Pagination.php          ← service de calcul des offsets pour les listes paginées
│       ├── SeoService.php, SitemapService.php, StructuredDataService.php ← structures SEO et microdonnées
│       ├── SlugManager.php         ← utilitaire de slugification et de garantie d'unicité en BDD
│       ├── UploadPathManager.php   ← gestionnaire des répertoires physiques des uploads
│       └── Validator.php           ← bibliothèque de validation de formulaires chainable
├── config/
│   └── config.php         ← configuration de base, constantes DB_*, EXT_DB_*, etc.
├── database/
│   ├── schema.sql, seed.sql, add_photos.sql  ← base locale initiale
│   ├── external_schema.sql, external_seed.sql ← base externe simulée
│   └── migrations/        ← scripts SQL idempotents exécutés au démarrage
└── templates/front002/    ← thèmes de l'application (front002 actif par défaut)
    ├── base.twig          ← layout parent global (header, footer, PWA)
    ├── home.twig          ← structure de la page d'accueil (slider, blocs, actus)
    ├── 404.twig, error.twig ← pages d'erreurs HTTP et applicatives
    ├── contact.twig       ← formulaire de contact public
    ├── _flash.twig        ← rendu des notifications de session
    ├── _footer.twig       ← pied de page avec config dynamique
    ├── _gallery.twig      ← composant carrousel/galerie photos
    ├── _header.twig       ← en-tête principal et menu de navigation
    ├── _mobile_menu.twig  ← menu mobile hors-écran (drawer)
    ├── _pwa_tags.twig     ← métadonnées et balises d'intégration PWA
    ├── _member_bottom_bar.twig ← barre de navigation basse pour mobile (connecté)
    ├── _visitor_bottom_bar.twig ← barre de navigation basse pour mobile (visiteur)
    ├── blog/
    │   ├── list.twig      ← liste paginée des articles de blog
    │   └── detail.twig    ← fiche complète d'un article de blog
    ├── pages/
    │   └── detail.twig    ← affichage des pages statiques du CMS
    ├── joueurs/
    │   └── index.twig     ← affichage d'informations de joueur
    ├── auth/
    │   └── login.twig     ← formulaire de connexion pour l'espace membre
    ├── member/
    │   ├── dashboard.twig ← tableau de bord adhérent avec planning et KPIs de participation
    │   ├── profile.twig   ← mise à jour du profil adhérent
    │   └── captain/
    │       ├── dashboard.twig ← tableau de bord capitaine (grille interactive, actions AJAX)
    │       ├── create_match.twig ← formulaire de création de manifestation
    │       ├── edit_match.twig   ← formulaire d'édition de manifestation
    │       └── select_players.twig ← sélection manuelle de l'effectif convoqué
    ├── agenda/
    │   ├── index.twig     ← grille croisée de présences aux manifestations
    │   ├── detail.twig    ← détails d'un événement et statistiques
    │   ├── filters.twig   ← widget de filtrage des événements et des joueurs
    │   ├── cards.twig     ← vue alternative en liste d'événements
    │   ├── _event_card.twig ← carte individuelle d'événement
    │   ├── _player_modal.twig ← popup de détails de joueur
    │   └── _macros.twig   ← macros Twig réutilisables
    └── admin/
        ├── layout.twig, login.twig, dashboard.twig
        ├── _photo_dropzone.twig   ← zone de glisser-déposer pour photos d'entité
        ├── _confirm_dialog.twig   ← boîte de dialogue générique de confirmation
        ├── _macros.twig           ← composants Twig utilitaires d'administration
        ├── posts/, pages/, menu/, seasons/, site-config/
        ├── categories-equipes/, home-blocks/, locations/, tags/
        ├── equipes-config/
        │   ├── list.twig, form.twig
        │   ├── saison_photos.twig ← gestion des photos associées à l'équipe pour la saison
        │   └── saison_joueurs.twig ← ajustements post-flash de la liste des joueurs
        └── contacts/
            ├── list.twig   ← liste paginée avec filtres et actions de masse
            └── detail.twig ← détail d'un message, historique et formulaire de réponse
```

---

## Deux bases de données

### Base locale (`usm_volley`)

Gérée par `Database::get()`. Contient tout ce que le site gère lui-même :
`posts`, `pages`, `menu_items`, `photos`, `saisons`, `joueur_snapshots`,
`equipes_config`, `equipe_saison`, `equipe_saison_joueur`, `categories_equipes`,
`home_blocks`, `locations`, `site_config`, `tags`, `contact_messages`, `contact_replies`.

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

Le pattern dépend de la complexité du contrôleur :

#### CRUD standard — étendre `AdminCrudController`

Pour la majorité des fonctionnalités CRUD admin (création, édition, suppression avec photos optionnelles) :

1. Créer `src/Controllers/Admin/MonController.php` qui étend `AdminCrudController`
2. Implémenter les méthodes abstraites et les hooks nécessaires
3. Ne **pas** appeler `Auth::require()` — c'est automatique via `BaseAdminController`
4. Ajouter l'`use` et les routes dans `App.php`

Voir la règle 7 de `AGENTS.md` pour un exemple complet.

#### Contrôleur spécialisé — étendre `BaseAdminController`

Pour les contrôleurs avec une logique métier spécifique qui ne s'adapte pas au CRUD standard :

1. Créer `src/Controllers/Admin/MonController.php` qui étend `BaseAdminController`
2. Ne **pas** appeler `Auth::require()` dans les méthodes — le constructeur de `BaseAdminController` le fait automatiquement
3. Utiliser `$this->findOr404(MonModel::class, $id)` pour récupérer les entités
4. Utiliser `$this->redirect('/admin/mon-chemin')` pour les redirections post-action
5. Ajouter l'`use` et les routes dans `App.php`

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

### Charte Graphique & Structure de Page (Thème front002)

Afin de maintenir une cohérence visuelle sur l'ensemble du site avec le thème `front002` (qui s'aligne sur le design de l'Espace Capitaine), toutes les pages de contenu principales (blog, agenda, contact, équipes, profil membre, pages CMS) doivent respecter la structure HTML et les classes Tailwind suivantes :

1. **Wrapper de Page** :
   Le bloc de contenu principal `{% block content %}` doit être enveloppé dans un conteneur avec un fond gris clair et un espacement de bas de page :
   ```html
   <div class="min-h-screen bg-slate-50/50 pb-24 md:pb-12">
     ...
   </div>
   ```

2. **En-tête Sombre (Bandeau)** :
   Placé directement au début du wrapper, il contient le fil d'Ariane et le titre principal.
   ```html
   <div class="bg-slate-900 text-white pt-8 pb-20 md:pt-12">
     <div class="max-w-6xl mx-auto px-4 sm:px-6"> <!-- max-w-4xl pour les pages d'article/CMS étroites -->
       <nav aria-label="Fil d'Ariane" class="text-sm text-slate-400 mb-6">
         <a href="{{ url('') }}" class="hover:text-white transition-colors">Accueil</a>
         <span class="mx-2 text-slate-600">/</span>
         <span class="text-slate-200">Titre Parent</span>
       </nav>
       <div>
         <span class="inline-block text-xs uppercase tracking-wider text-[var(--primary)] font-bold mb-2">Surtitre</span>
         <h1 class="font-serif text-3xl md:text-5xl font-extrabold tracking-tight text-white mt-1">Titre de la Page</h1>
       </div>
     </div>
   </div>
   ```

3. **Conteneur Flottant avec Cartes Blanches** :
   Le contenu réel de la page doit être décalé vers le haut pour chevaucher l'en-tête sombre grâce à la classe `-mt-10`. Le contenu doit être placé dans des cartes blanches arrondies et ombrées.
   ```html
   <div class="max-w-6xl mx-auto px-4 sm:px-6 -mt-10"> <!-- max-w-4xl pour les pages étroites -->
     <div class="bg-white rounded-3xl p-6 md:p-8 border border-slate-100 shadow-sm space-y-8">
       <!-- Contenu de la page -->
     </div>
   </div>
   ```

4. **Boutons & Éléments de Formulaire** :
   - **Bords arrondis** : Utiliser impérativement `rounded-xl` (ou `rounded-3xl` pour les grandes cartes) au lieu des valeurs par défaut ou `rounded-lg`.
   - **Champs de saisie** : `class="w-full px-3.5 py-3 border border-slate-205 rounded-xl text-slate-800 focus:border-[var(--primary)] focus:outline-none focus:ring-2 focus:ring-[var(--primary)]/20 transition-all text-sm"`
   - **Bouton principal (CTA)** : Utiliser les classes standards en y ajoutant `rounded-xl font-bold` (ou `btn-primary py-3.5 rounded-xl font-bold`).
   - **Focus** : Toujours utiliser le halo doux `focus:ring-2 focus:ring-[var(--primary)]/20` avec une bordure de couleur primaire.

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
  - Filtres supportés : `team`, `lieu`, `type`, `manifestation`, `this_week`, `hide_empty_players`
  
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
| `lieu` | string | Filtre par Lieu |
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
$brevo->sendEmail($email, $name, $subject, $htmlContent, $textContent, $cc);
$brevo->sendContactNotification($contact); // notif admin
$brevo->sendReplyToVisitor($email, $name, $replyText); // réponse visiteur
$brevo->sendPlayerSelectionNotification($player, $event); // convocation match
$brevo->sendPlayerDeselectionNotification($player, $event); // désélection match
$brevo->sendTrainingOverlapNotification($player, $training, $match, $cc); // retrait entraînement avec CC capitaine
$brevo->sendMatchModificationNotification($player, $oldEvent, $newEvent); // modification date/lieu match
```

**Configuration** : Variables d'env `BREVO_API_KEY`, `BREVO_FROM_EMAIL`, `BREVO_FROM_NAME`, `BREVO_REDIRECT_EMAIL`.

**Templates Twig** : Tous les e-mails sont externalisés en templates Twig dans [templates/front002/emails/](file:///c:/wamp64/www/usm-core/templates/front002/emails/) :
- `selection.twig` (convocation)
- `deselection.twig` (désélection)
- `match_cancellation.twig` (annulation)
- `match_modification.twig` (modification date/lieu)
- `match_reminder.twig` (rappel de réponse)
- `training_overlap.twig` (chevauchement entraînement)
- `captain_message.twig` (message de contact au capitaine)
- `contact_notification.twig` (notification de contact admin)
- `visitor_reply.twig` (réponse de l'admin à un visiteur)

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

### Twig dans les pages CMS & Configuration Globale

Les pages et articles supportent la compilation Twig pour référencer la configuration du site.
Cela permet aux éditeurs de construire du contenu dynamique sans code :

```twig
Email: {{ site_config.email }}
Téléphone: {{ site_config.phone|default('Non renseigné') }}
Adresse: {{ site_config.address }}
© {{ site_config.club_name }}
```

> [!NOTE]
> **Variable Globale Twig vs Variable Locale** :
> - Sur le site public (front-end), la configuration du site est injectée globalement dans tous les templates Twig sous la variable `site_config` (ex: `{{ site_config.club_name }}`).
> - Dans le template d'administration `admin/site-config/edit.twig`, les configurations de la table `site_config` sont passées localement par le contrôleur sous le nom de variable `config` (ex: `{{ config.club_name }}`).

**Champs disponibles** : `club_name`, `club_tagline`, `address`, `email`, `phone`,
`facebook_url`, `instagram_url`, `legal_text`, `home_slider_posts_count`,
`home_latest_posts_count`, et les clés de configuration des bandeaux mobiles (`visitor_bottom_*`, `member_bottom_*`).

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

## Espace Adhérent / Membre

L'espace adhérent permet aux joueurs du club de se connecter pour gérer leurs participations aux manifestations (matchs, entraînements) et modifier leur profil.

### Authentification & Session

Gérée par `Member\AuthController`. Un joueur se connecte avec son identifiant et son mot de passe.
La session de l'adhérent stocke ses informations d'identification de joueur.

### Tableau de bord & Participations

- **Dashboard** (`/member/dashboard`) : Affiche le résumé des matchs et entraînements à venir, ainsi que l'état de ses réponses.
- **Mise à jour des participations** (`/member/participations/update`) : Formulaire pour indiquer sa disponibilité sur chaque manifestation. Supporte l'enregistrement en base externe via `Participation::upsert()`.
- **API** : Une route d'API `POST /api/member/participations/upsert` permet des mises à jour rapides et asynchrones des participations de l'adhérent.

### Profil Adhérent

Accessible via `/member/profile` pour permettre à l'adhérent de mettre à jour son mot de passe ou ses coordonnées.

### Fonctionnement du Dashboard Dynamique

Le tableau de bord de l'adhérent est entièrement dynamique et s'appuie sur les composants suivants :
1. **Service de Données** : `App\Services\MemberDashboardService` fournit :
   - `getKPIs($userId)` : Événements de la semaine, de la semaine prochaine, et actions requises (participations manquantes ou indécises).
   - `getImminentEvents($userId, $limit)` : Prochains événements avec statut de participation et vérification de chevauchements via `flagOverlappingSelected`.
   - `getSeasonStats($userId)` : Taux de présence aux matchs et entraînements, et classement des lieux visités.
2. **Contrôleur** : `App\Controllers\Member\DashboardController` orchestre la récupération de ces données et les transmet au template.
3. **Vue Twig** : `templates/front002/member/dashboard.twig` affiche graphiquement les KPIs, la liste interactive des événements et les graphiques d'assiduité.
4. **Interactivité** : Gestion du changement asynchrone des statuts de présence en AJAX via l'API `POST /api/member/participations/upsert`.


---

## Espace Capitaine

L'espace capitaine permet aux capitaines d'équipes de piloter les convocations et de visualiser en un coup d'œil les disponibilités de leurs joueurs.

### Fonctionnalités Clés
- **Tableau de Bord Capitaine** (`/member/captain/dashboard`) :
  - **Grille de Présence** : Tableau croisé des joueurs de son effectif avec les 8 prochains événements (matchs et entraînements), avec positionnement collant (sticky) de l'en-tête et du nom des joueurs.
  - **Modification Directe par Clic** : Un clic sur une cellule du tableau ouvre un menu popover flottant (AJAX) permettant d'ajuster immédiatement le statut de présence ou de sélection du joueur (convoqué, dispo, si besoin, absent, etc.).
  - **Coloration par Statut** : Les en-têtes et les colonnes entières sont teintés en jaune pour les manifestations "Provisoires" et en rouge pour les manifestations "Annulées". Les convocations (★) sont automatiquement masquées si un match est annulé.
  - **Indicateurs de Performance (KPIs)** : Taux de réponse, disponibilité moyenne par match, assiduité aux entraînements, et alertes de sous-effectif (matchs avec moins de 6 convoqués).
- **Administration depuis l'Agenda** (`/agenda/{id}`) : Si l'utilisateur connecté est le capitaine de l'équipe du match, des raccourcis "Gérer les convocations" et "Modifier le match" sont affichés en haut de la fiche de l'événement.

### API Capitaine
- **Mise à jour d'une participation** : `POST /api/captain/participation/update`
  - Body JSON : `{ joueur_id, manifestation_id, status }`
  - Gère la désélection concurrentielle (`removeConcurrentParticipations`) et recalcule dynamiquement toute la grille et les métriques de l'équipe pour les renvoyer au format JSON.

### Architecture des Présences (ParticipationStatus)
Pour immuniser le code source contre le changement des libellés de la base de données (ex. renommer "Présent" en "Présent(e)"), une couche d'abstraction a été mise en place :
1. **Helper `ParticipationStatus`** : Convertit un libellé textuel brut (`Oui`, `Présent(e)`, `Joker`) en une **catégorie abstraite standardisée** en anglais (`present`, `available`, `available_if_needed`, `selected`, `absent`, `unavailable`, `unknown`).
2. **`EventNormalizer`** : Calcule les totaux et trie les joueurs dans des tableaux (ex. `$manifestationStats['available']`) en se basant **uniquement** sur ces catégories, jamais sur les chaînes brutes.
3. **Twig & JS** : Les vues Twig (ex. `_event_card.twig`, `detail.twig`) et le code JavaScript (`agenda-cards.js`) s'appuient sur les clés standardisées (ex. `m.user_status_category == 'available'`, `data-status-key="available"`). Les seules chaînes "en dur" conservées sont celles envoyées via les requêtes HTTP `POST` car elles doivent correspondre exactement aux données enregistrées en base.

---

## API Articles d'importation

Le site propose un endpoint API pour la création ou la mise à jour d'articles depuis un système externe.

- **Route** : `POST /api/articles` (géré par `ArticleApiController`)
- **Authentification** : Requiert un token ou une clé API d'autorisation dans les headers (selon la configuration).
- **Fonctionnement** : Reçoit un payload JSON avec le titre, contenu, catégorie, tag, et images de l'article pour les insérer directement dans la base locale `usm_volley`.

---

## Gestion des Images & Variantes

Pour optimiser les temps de chargement du site public, un système de génération de variantes d'images est intégré.

- **Services** : `ImageResizer` et `ImageVariant`
- **Fonctionnement** : Lors de l'upload d'une photo via Dropzone ou l'éditeur, des formats réduits ou optimisés (ex. formats miniatures, formats de liste, formats WebP, etc.) sont automatiquement générés et référencés.
- **Nettoyage/Migration** : Des scripts dans `scripts/` (ex. `generate_variants.php`) permettent de recalculer l'ensemble des variantes à la volée.

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
| `THEME` | `front002` | Dossier sous `templates/` |
| `APP_DEBUG` | `true` | Désactive cache Twig, affiche PDOException |
| `ADMIN_EMAIL` | — | Email admin |
| `ADMIN_PASSWORD_HASH` | — | `password_hash(..., PASSWORD_BCRYPT)` |
| `BREVO_API_KEY` | — | Clé API Brevo pour envoyer les emails |
| `BREVO_FROM_EMAIL` | `noreply@usm-volley.fr` | Email "De" pour les notifications |
| `BREVO_FROM_NAME` | `USM Volley` | Nom "De" pour les notifications |
| `CRON_SECURITY_TOKEN` | `usm_cron_token_fallback_2026` | Token de sécurité pour le déclenchement des tâches automatisées (cron/GitLab) |


---

## Migrations

Les fichiers SQL sont **idempotents** (`IF NOT EXISTS`, `INSERT IGNORE`,
`ON DUPLICATE KEY UPDATE`). Pour ajouter une migration :

1. Créer `database/migrations/NNN_description.sql`
2. Ajouter la ligne dans le service `migrate` du `docker-compose.yml`

Ordre d'application au démarrage :
```
schema.sql → seed.sql → add_photos.sql
→ 001_saisons.sql → 002_equipes_config.sql → ... → 024_add_ffvb_link_to_equipes_config.sql
```

**Migrations récentes** :
- `003_home_blocks.sql` — blocs accueil (contenu + position)
- `004_site_config.sql` — config du site (nom, email, phone, réseaux)
- `005_blog_slug_cleanup.sql` — nettoyage des slugs des articles de blog
- `005_joueur_snapshots_nlicence.sql` — ajout colonne nlicence
- `006_article_api.sql` — table pour intégration API article
- `007_tags.sql` — table tags pour catégorisation articles
- `008_contacts.sql` — messages de contact + réponses (Brevo)
- `008_locations.sql` — gestion des lieux de manifestations
- `009_contact_messages.sql` — table pour messages du formulaire de contact
- `010_categories_equipes.sql` — catégories des équipes (ex. séniors, jeunes)
- `010_contact_phone.sql` — ajout du numéro de téléphone aux contacts
- `011_equipes_config_description.sql` — ajout d'une description longue pour les équipes
- `011_theme_config.sql` — configuration des thèmes d'affichage
- `012_categories_equipes_fields.sql` — champs de description pour les catégories
- `013_equipes_config_description_courte.sql` — description courte pour les équipes
- `014_equipes_type_hauteur.sql` — type et hauteur du filet d'équipe
- `015_site_config_front003.sql` — configuration spécifique du site pour le thème 3
- `016_site_config_theme.sql` — liaison configuration / thème
- `017_photos_has_variants.sql` — statut des variantes d'images générées
- `018_post_slider_pin.sql` — possibilité d'épingler un article au slider
- `019_equipe_config_autoincrement.sql` — correction auto_increment sur equipes_config
- `020_home_block_photos.sql` — photos dédiées aux blocs d'accueil
- `021_page_add_category.sql` — catégorisation des pages statiques
- `022_saison_datedebut_datefin.sql` — ajout de date_debut et date_fin aux saisons
- `023_add_captain_to_equipe_saison_joueur.sql` — ajout du flag is_captain pour désigner le capitaine d'équipe
- `024_add_ffvb_link_to_equipes_config.sql` — ajout du lien FFVB aux équipes
- `031_member_email_preferences.sql` — préférences individuelles d'abonnements d'emails
- `032_equipe_config_training_filter.sql` — filtre / association des entraînements par équipe

