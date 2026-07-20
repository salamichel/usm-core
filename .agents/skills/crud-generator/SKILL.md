---
name: crud-generator
description: Aide à la génération de nouveaux contrôleurs CRUD admin, de modèles PDO et de templates Twig avec support de la suppression en masse
---

# Compétence : Générateur de CRUD (crud-generator)

Cette compétence permet de générer rapidement la structure complète (Modèle, Contrôleur Admin, Vues Twig, et Routes) d'une nouvelle entité administrable dans le projet USM Volley avec gestion de la suppression unitaire et groupée (en masse).

## Commande de Génération Automatique
Vous pouvez exécuter le script via Docker :

* **Si vos conteneurs sont déjà lancés (`docker compose up`) :**
  ```bash
  docker compose exec app php .agents/skills/crud-generator/scripts/generate.php EntityName db_table_name field1:type field2:type ...
  ```

* **Si vos conteneurs ne sont pas lancés :**
  ```bash
  docker compose run --rm app php .agents/skills/crud-generator/scripts/generate.php EntityName db_table_name field1:type field2:type ...
  ```

**Exemple :**
```bash
docker compose exec app php .agents/skills/crud-generator/scripts/generate.php Sponsor sponsors nom:string description:text logo:string ordre:int
```

---

## Guide d'implémentation manuelle d'un CRUD

### 1. La table de base de données
Créer une migration SQL dans `database/migrations/` (ex. `025_create_sponsors_table.sql`) :
```sql
CREATE TABLE IF NOT EXISTS `sponsors` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `nom` VARCHAR(255) NOT NULL,
  `description` TEXT NULL,
  `logo` VARCHAR(255) NULL,
  `ordre` INT DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 2. Le Modèle PDO (`src/Models/Sponsor.php`)
Le modèle doit utiliser uniquement du PDO pur (via `Database::get()`) et des méthodes statiques, incluant `deleteBulk` :
```php
<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class Sponsor
{
    public static function all(): array
    {
        $stmt = Database::get()->query("SELECT * FROM sponsors ORDER BY ordre ASC, nom ASC");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::get()->prepare("SELECT * FROM sponsors WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    public static function create(array $data): int
    {
        $stmt = Database::get()->prepare("
            INSERT INTO sponsors (nom, description, logo, ordre) 
            VALUES (:nom, :description, :logo, :ordre)
        ");
        $stmt->execute([
            'nom' => $data['nom'] ?? '',
            'description' => $data['description'] ?? null,
            'logo' => $data['logo'] ?? null,
            'ordre' => (int)($data['ordre'] ?? 0),
        ]);
        return (int)Database::get()->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $stmt = Database::get()->prepare("
            UPDATE sponsors 
            SET nom = :nom, description = :description, logo = :logo, ordre = :ordre 
            WHERE id = :id
        ");
        $stmt->execute([
            'id' => $id,
            'nom' => $data['nom'],
            'description' => $data['description'] ?? null,
            'logo' => $data['logo'] ?? null,
            'ordre' => (int)($data['ordre'] ?? 0),
        ]);
    }

    public static function delete(int $id): void
    {
        $stmt = Database::get()->prepare("DELETE FROM sponsors WHERE id = ?");
        $stmt->execute([$id]);
    }

    public static function deleteBulk(array $ids): int
    {
        $cleanIds = array_values(array_filter(array_map('intval', $ids), fn(int $id) => $id > 0));
        if (empty($cleanIds)) {
            return 0;
        }
        $placeholders = implode(',', array_fill(0, count($cleanIds), '?'));
        $stmt = Database::get()->prepare("DELETE FROM sponsors WHERE id IN ($placeholders)");
        $stmt->execute($cleanIds);
        return $stmt->rowCount();
    }
}
```

### 3. Le Contrôleur Admin (`src/Controllers/Admin/SponsorController.php`)
Il hérite de `BaseAdminController` et propose les actions d'édition, suppression unitaire et suppression groupée (`deleteBulk`) :
```php
<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\View;
use App\Models\Sponsor;

class SponsorController extends BaseAdminController
{
    public function index(array $params): void
    {
        Auth::require();
        View::render('admin/sponsors/list.twig', [
            'sponsors' => Sponsor::all(),
        ]);
    }

    public function create(array $params): void
    {
        Auth::require();
        View::render('admin/sponsors/form.twig', [
            'sponsor' => null,
            'action' => BASE_URL . '/admin/sponsors/create',
        ]);
    }

    public function store(array $params): void
    {
        Auth::require();
        $data = $this->formData();
        if ($data['nom'] === '') {
            View::render('admin/sponsors/form.twig', [
                'sponsor' => $data,
                'action' => BASE_URL . '/admin/sponsors/create',
                'error' => 'Le nom du sponsor est obligatoire.',
            ]);
            return;
        }
        $id = Sponsor::create($data);
        View::flash('success', 'Sponsor créé avec succès.');
        $this->redirect('/admin/sponsors/' . $id . '/edit');
    }

    public function edit(array $params): void
    {
        Auth::require();
        $sponsor = Sponsor::find((int)$params['id']);
        if (!$sponsor) {
            $this->notFound('error.twig', ['error' => 'Sponsor introuvable.']);
            return;
        }
        View::render('admin/sponsors/form.twig', [
            'sponsor' => $sponsor,
            'action' => BASE_URL . '/admin/sponsors/' . $sponsor['id'] . '/edit',
        ]);
    }

    public function update(array $params): void
    {
        Auth::require();
        $id = (int)$params['id'];
        $sponsor = Sponsor::find($id);
        if (!$sponsor) {
            $this->notFound('error.twig', ['error' => 'Sponsor introuvable.']);
            return;
        }
        $data = $this->formData();
        if ($data['nom'] === '') {
            View::render('admin/sponsors/form.twig', [
                'sponsor' => array_merge($sponsor, $data),
                'action' => BASE_URL . '/admin/sponsors/' . $id . '/edit',
                'error' => 'Le nom du sponsor est obligatoire.',
            ]);
            return;
        }
        Sponsor::update($id, $data);
        View::flash('success', 'Sponsor mis à jour.');
        $this->redirect('/admin/sponsors');
    }

    public function delete(array $params): void
    {
        Auth::require();
        $this->requirePost('/admin/sponsors');
        Sponsor::delete((int)$params['id']);
        View::flash('success', 'Sponsor supprimé.');
        $this->redirect('/admin/sponsors');
    }

    public function deleteBulk(array $params): void
    {
        Auth::require();
        $this->requirePost('/admin/sponsors');
        $rawIds = $_POST['ids'] ?? [];
        if (!is_array($rawIds) || empty($rawIds)) {
            View::flash('error', 'Aucun élément sélectionné pour la suppression.');
            $this->redirect('/admin/sponsors');
            return;
        }
        $deleted = Sponsor::deleteBulk($rawIds);
        if ($deleted > 0) {
            View::flash('success', sprintf('%d élément(s) supprimé(s) avec succès.', $deleted));
        } else {
            View::flash('error', 'Aucun élément n\'a pu être supprimé.');
        }
        $this->redirect('/admin/sponsors');
    }

    private function formData(): array
    {
        return [
            'nom' => trim($_POST['nom'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'logo' => trim($_POST['logo'] ?? ''),
            'ordre' => (int)($_POST['ordre'] ?? 0),
        ];
    }
}
```

### 4. Les Vues Twig (`templates/front002/admin/sponsors/list.twig`)
Propose la sélection unitaire et globale avec confirmation JS avant suppression :
```twig
{% extends "admin/layout.twig" %}

{% block content %}
<div class="flex justify-between items-center mb-6">
    <h1 class="text-2xl font-bold text-slate-800">Gestion des Sponsors</h1>
    <a href="{{ url('admin/sponsors/create') }}" class="px-4 py-2 bg-indigo-600 text-white rounded-xl font-bold hover:bg-indigo-700 transition-all text-sm">
        Ajouter un Sponsor
    </a>
</div>

{% if sponsors|length %}
<form id="bulk-delete-form" method="POST" action="{{ url('admin/sponsors/delete-bulk') }}">
    <input type="hidden" name="_csrf_token" value="{{ csrf_token }}">

    <div class="bg-slate-50 border border-slate-200 rounded-xl p-3 mb-4 flex items-center justify-between">
        <label class="flex items-center space-x-2 text-sm font-semibold cursor-pointer">
            <input type="checkbox" id="select-all" class="w-4 h-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
            <span>Tout sélectionner</span>
        </label>
        <button type="submit" id="btn-bulk-delete" disabled class="px-3 py-1.5 bg-rose-600 text-white rounded-lg text-xs font-bold hover:bg-rose-700 disabled:opacity-50 disabled:cursor-not-allowed transition">
            🗑️ Supprimer la sélection (<span id="bulk-count">0</span>)
        </button>
    </div>

    <div class="bg-white rounded-3xl border border-slate-100 shadow-sm overflow-hidden">
        <table class="w-full text-left border-collapse text-sm">
            <thead>
                <tr class="bg-slate-50 border-b border-slate-100 text-slate-600 font-semibold">
                    <th class="p-4 w-10 text-center"></th>
                    <th class="p-4">Nom</th>
                    <th class="p-4">Ordre</th>
                    <th class="p-4 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-slate-700">
                {% for sponsor in sponsors %}
                    <tr>
                        <td class="p-4 text-center">
                            <input type="checkbox" name="ids[]" value="{{ sponsor.id }}" class="item-checkbox w-4 h-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                        </td>
                        <td class="p-4 font-medium">{{ sponsor.nom }}</td>
                        <td class="p-4">{{ sponsor.ordre }}</td>
                        <td class="p-4 text-right space-x-2">
                            <a href="{{ url('admin/sponsors/' ~ sponsor.id ~ '/edit') }}" class="text-indigo-600 hover:text-indigo-900 font-semibold">Modifier</a>
                            <a href="{{ url('admin/sponsors/' ~ sponsor.id ~ '/delete') }}" onclick="return confirm('Supprimer ce sponsor ?')" class="text-rose-600 hover:text-rose-900 font-semibold">Supprimer</a>
                        </td>
                    </tr>
                {% endfor %}
            </tbody>
        </table>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const selectAll = document.getElementById('select-all');
    const checkboxes = document.querySelectorAll('.item-checkbox');
    const btnBulk = document.getElementById('btn-bulk-delete');
    const countSpan = document.getElementById('bulk-count');
    const form = document.getElementById('bulk-delete-form');

    function update() {
        const checked = document.querySelectorAll('.item-checkbox:checked').length;
        if (countSpan) countSpan.textContent = checked;
        if (btnBulk) btnBulk.disabled = (checked === 0);
        if (selectAll) selectAll.checked = (checkboxes.length > 0 && checked === checkboxes.length);
    }

    if (selectAll) {
        selectAll.addEventListener('change', function() {
            checkboxes.forEach(cb => cb.checked = selectAll.checked);
            update();
        });
    }

    checkboxes.forEach(cb => cb.addEventListener('change', update));

    if (form) {
        form.addEventListener('submit', function(e) {
            const checked = document.querySelectorAll('.item-checkbox:checked').length;
            if (checked === 0) {
                e.preventDefault();
                return;
            }
            if (!confirm('Voulez-vous vraiment supprimer les ' + checked + ' élément(s) sélectionné(s) ?')) {
                e.preventDefault();
            }
        });
    }
    update();
});
</script>

{% else %}
    <div class="bg-white rounded-3xl p-8 border border-slate-100 text-center text-slate-400">
        Aucun sponsor enregistré.
    </div>
{% endif %}
{% endblock %}
```

### 5. Déclaration dans `src/Core/App.php`
Ajouter les routes dans `registerRoutes()` de `App.php` (**note : placer `delete-bulk` avant `{id}/delete`**) :
```php
// Sponsors CRUD Admin
$r->get('/admin/sponsors', [SponsorController::class, 'index']);
$r->get('/admin/sponsors/create', [SponsorController::class, 'create']);
$r->post('/admin/sponsors/create', [SponsorController::class, 'store']);
$r->post('/admin/sponsors/delete-bulk', [SponsorController::class, 'deleteBulk']);
$r->get('/admin/sponsors/{id}/edit', [SponsorController::class, 'edit']);
$r->post('/admin/sponsors/{id}/edit', [SponsorController::class, 'update']);
$r->get('/admin/sponsors/{id}/delete', [SponsorController::class, 'delete']);
```

