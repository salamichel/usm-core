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
- Toujours commencer les actions d'administration par `Auth::require()`.
- Utiliser `View::flash('success', '...')` suivi d'une redirection HTTP via `header('Location: ...')` pour les messages flash de confirmation.
- Injecter le token CSRF dans tous les formulaires POST via `<input type="hidden" name="_csrf_token" value="{{ csrf_token }}">`.

### 4. Sécurité & Données
- Valider les données via `App\Services\Validator` (API chaînable).
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
