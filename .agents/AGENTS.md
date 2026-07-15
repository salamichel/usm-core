# Instructions pour les Agents de Codage (USM Volley)

Ce fichier définit les règles, les patterns de conception et les contraintes à respecter lors du développement sur ce projet.

## Technologies & Environnement
- **Stack** : PHP 8.2 · Twig · MySQL 8 · TailwindCSS (CDN) · Docker Compose.
- **Hébergement** : InfinityFree (mutualisé, pas de SSH/Composer en production). Toutes les dépendances dans `vendor/` sont versionnées.
- **Bases de données** :
  - Base locale (`usm_volley`) : Gérée par `Database::get()`. Contient les tables gérées directement par le site.
  - Base externe USM (`if0_34936599_usm` en prod, `db_external` en dev) : Gérée par `ExternalDatabase::get()`. Contient les joueurs et l'agenda des manifestations.

## Règles & Raccourcis de Codage

### 1. Pas d'ORM (PDO pur)
- Utiliser uniquement `Database::get()` ou `ExternalDatabase::get()` avec `PDO::FETCH_ASSOC`.
- Les méthodes des modèles doivent être statiques.
- Exemple :
  ```php
  public static function find(int $id): ?array
  {
      $stmt = Database::get()->prepare("SELECT * FROM table WHERE id = ? LIMIT 1");
      $stmt->execute([$id]);
      return $stmt->fetch() ?: null;
  }
  ```

### 2. Routage
- Les routes sont définies dans `src/Core/App.php` via la méthode `registerRoutes()`.
- Exemple : `$r->get('/ma-route/{id}', [MonController::class, 'maMethode']);`
- **Attention** : Mettre les routes statiques avant les routes paramétrées pour éviter les conflits de capture.

### 3. Contrôleurs Administration
- **Ne PAS appeler `Auth::require()` dans les méthodes** — la vérification est centralisée dans le constructeur de `BaseAdminController`. Tout contrôleur qui étend `BaseAdminController` est automatiquement protégé.
- Utiliser `View::flash('success', '...')` suivi d'une redirection via `$this->redirect('/admin/...')` pour les messages flash de confirmation.
- Injecter le token CSRF dans tous les formulaires POST via `<input type="hidden" name="_csrf_token" value="{{ csrf_token }}">`.

### 4. Sécurité & Données
- **Valider toutes les données** via `App\Services\Validator` (API chaînable) — **ne pas valider manuellement** avec `if ($x === '')`.
- Générer et garantir l'unicité des slugs avec `App\Services\SlugManager`.
- Logger les événements via `App\Services\Logger` (canaux : `app()`, `audit()`, `errors()`).

### 5. Charte Graphique (Thème front002 / TailwindCSS)
Pour conserver l'uniformité visuelle du site, chaque page principale doit utiliser la structure suivante :
1. **Conteneur Principal** : `<div class="min-h-screen bg-slate-50/50 pb-24 md:pb-12">`
2. **Bandeau d'en-tête Sombre** :
   ```html
   <div class="bg-slate-900 text-white pt-8 pb-20 md:pt-12">
     <div class="max-w-6xl mx-auto px-4 sm:px-6">
       <!-- Fil d'Ariane & Titre -->
     </div>
   </div>
   ```
3. **Conteneur de Contenu Flottant** (chevauchant le bandeau via `-mt-10`) :
   ```html
   <div class="max-w-6xl mx-auto px-4 sm:px-6 -mt-10">
     <div class="bg-white rounded-3xl p-6 md:p-8 border border-slate-100 shadow-sm space-y-8">
       <!-- Contenu de la page -->
     </div>
   </div>
   ```
4. **Formulaires & Boutons** :
   - Coins arrondis : utiliser `rounded-xl` (champs de saisie, boutons) ou `rounded-3xl` (grandes cartes).
   - Boutons principaux : utiliser les classes de bouton standard avec `rounded-xl font-bold` (ex. `btn-primary py-3.5`).
   - Focus : toujours utiliser le halo doux `focus:ring-2 focus:ring-[var(--primary)]/20` avec contour de couleur primaire.

### 6. Base de données & Migrations
- Les migrations se trouvent sous `database/migrations/` et doivent être strictement idempotentes (`IF NOT EXISTS`, `INSERT IGNORE`, `ON DUPLICATE KEY UPDATE`).
- Déclarer chaque nouvelle migration dans le service `migrate` de `docker-compose.yml`.

### 7. Contrôleurs CRUD Admin — `AdminCrudController`
- Pour tout nouveau CRUD admin simple, étendre `AdminCrudController` plutôt que `BaseAdminController`.
- Implémenter les méthodes abstraites : `getEntity()`, `getAllEntities()`, `createEntity()`, `updateEntity()`, `deleteEntity()`, `getFormData()`, `validateData()`.
- Utiliser les hooks optionnels pour les comportements spécifiques : `afterStore()`, `afterUpdate()`, `getIndexData()`, `getEditData()`, `getCreateData()`, `getRedirectUrl()`.
- Exemple minimal :
  ```php
  class MonController extends AdminCrudController
  {
      public function __construct()
      {
          $this->itemName  = 'item';
          $this->itemsName = 'items';
      }

      protected function getEntity(int $id): ?array { return MonModel::find($id); }
      protected function getAllEntities(): array     { return MonModel::all(); }
      protected function createEntity(array $d): int { return MonModel::create($d); }
      protected function updateEntity(int $id, array $d): void { MonModel::update($id, $d); }
      protected function deleteEntity(int $id): void { MonModel::delete($id); }
      protected function getFormData(): array { return ['champ' => $_POST['champ'] ?? '']; }
      protected function validateData(array $d, ?array $entity = null): ?string
      {
          $v = Validator::make($d)->required('champ', 'Le champ est obligatoire.');
          return $v->fails() ? $v->firstError() : null;
      }
  }
  ```

### 8. Recherche d'entité ou 404 — `findOr404()`
- Dans les contrôleurs admin, utiliser `$this->findOr404(ModelClass::class, $id)` au lieu du pattern `find() + if (!$entity) { $this->notFound(); return; }`.
- Exemple :
  ```php
  // ❌ Avant :
  $entity = Model::find($id);
  if (!$entity) { $this->notFound(); return; }

  // ✅ Après :
  $entity = $this->findOr404(Model::class, $id);
  ```

### 9. Macros Twig Admin — `admin/_macros.twig`
- Importer systématiquement les macros dans tout template de formulaire admin :
  ```twig
  {% import 'admin/_macros.twig' as m %}
  ```
- Utiliser `{{ m.form_error(error ?? null) }}` à la place des blocs `{% if error %}...{% endif %}` manuels.
- Utiliser `{{ m.btn_submit('Enregistrer') }}` à la place du `<button type="submit" class="border-4 border-black ...">` copié-collé.

