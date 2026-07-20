<?php
declare(strict_types=1);

if ($argc < 3) {
    echo "Usage: php generate.php EntityName db_table_name [field1:type] [field2:type] ...\n";
    echo "Types supportés : string, text, int, bool, date\n";
    echo "Exemple : php generate.php Sponsor sponsors nom:string description:text logo:string ordre:int\n";
    exit(1);
}

$entityName = $argv[1];
$tableName = $argv[2];
$rawFields = array_slice($argv, 3);

$fields = [];
foreach ($rawFields as $raw) {
    if (strpos($raw, ':') === false) {
        continue;
    }
    [$name, $type] = explode(':', $raw, 2);
    $fields[$name] = $type;
}

// Détermination des dossiers cibles
$workspaceRoot = dirname(__DIR__, 4);
$modelPath = "$workspaceRoot/src/Models/$entityName.php";
$controllerPath = "$workspaceRoot/src/Controllers/Admin/{$entityName}Controller.php";
$viewsDir = "$workspaceRoot/templates/front002/admin/$tableName";
$listPath = "$viewsDir/list.twig";
$formPath = "$viewsDir/form.twig";

echo "Génération du CRUD pour l'entité : $entityName (table : $tableName)...\n";

// --- 1. Génération du Modèle PHP ---
$modelFieldsBinding = "";
$modelFieldsAssignments = [];
$modelFieldsUpdates = [];
foreach ($fields as $name => $type) {
    if ($type === 'int') {
        $modelFieldsBinding .= "            '$name' => (int)(\$data['$name'] ?? 0),\n";
    } elseif ($type === 'bool') {
        $modelFieldsBinding .= "            '$name' => (int)!\nempty(\$data['$name']),\n";
    } else {
        $modelFieldsBinding .= "            '$name' => \$data['$name'] ?? null,\n";
    }
    $modelFieldsAssignments[] = ":$name";
    $modelFieldsUpdates[] = "$name = :$name";
}

$modelContent = <<<PHP
<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class $entityName
{
    public static function all(): array
    {
        \$stmt = Database::get()->query("SELECT * FROM `$tableName` ORDER BY id DESC");
        return \$stmt->fetchAll(\\PDO::FETCH_ASSOC);
    }

    public static function find(int \$id): ?array
    {
        \$stmt = Database::get()->prepare("SELECT * FROM `$tableName` WHERE id = ? LIMIT 1");
        \$stmt->execute([\$id]);
        return \$stmt->fetch(\\PDO::FETCH_ASSOC) ?: null;
    }

    public static function create(array \$data): int
    {
        \$fields = array_keys(\$data);
        \$cols = implode(', ', array_map(fn(\$f) => "`\$f`", \$fields));
        \$placeholders = implode(', ', array_map(fn(\$f) => ":\$f", \$fields));

        \$stmt = Database::get()->prepare("INSERT INTO `$tableName` (\$cols) VALUES (\$placeholders)");
        \$stmt->execute(\$data);

        return (int)Database::get()->lastInsertId();
    }

    public static function update(int \$id, array \$data): void
    {
        \$sets = [];
        foreach (array_keys(\$data) as \$field) {
            \$sets[] = "`\$field` = :\$field";
        }
        \$setString = implode(', ', \$sets);

        \$stmt = Database::get()->prepare("UPDATE `$tableName` SET \$setString WHERE id = :_id_placeholder_");
        \$data['_id_placeholder_'] = \$id;
        \$stmt->execute(\$data);
    }

    public static function delete(int \$id): void
    {
        \$stmt = Database::get()->prepare("DELETE FROM `$tableName` WHERE id = ?");
        \$stmt->execute([\$id]);
    }

    public static function deleteBulk(array \$ids): int
    {
        \$cleanIds = array_values(array_filter(array_map('intval', \$ids), fn(int \$id) => \$id > 0));
        if (empty(\$cleanIds)) {
            return 0;
        }
        \$placeholders = implode(',', array_fill(0, count(\$cleanIds), '?'));
        \$stmt = Database::get()->prepare("DELETE FROM `$tableName` WHERE id IN (\$placeholders)");
        \$stmt->execute(\$cleanIds);
        return \$stmt->rowCount();
    }
}
PHP;

// --- 2. Génération du Contrôleur PHP ---
$controllerFieldsArr = [];
foreach ($fields as $name => $type) {
    if ($type === 'int') {
        $controllerFieldsArr[] = "            '$name' => (int)(\$_POST['$name'] ?? 0),";
    } elseif ($type === 'bool') {
        $controllerFieldsArr[] = "            '$name' => !empty(\$_POST['$name']) ? 1 : 0,";
    } else {
        $controllerFieldsArr[] = "            '$name' => trim(\$_POST['$name'] ?? ''),";
    }
}
$controllerFieldsStr = implode("\n", $controllerFieldsArr);

$controllerContent = <<<PHP
<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\View;
use App\Models\\$entityName;

class {$entityName}Controller extends BaseAdminController
{
    public function index(array \$params): void
    {
        Auth::require();
        View::render('admin/$tableName/list.twig', [
            '$tableName' => $entityName::all(),
        ]);
    }

    public function create(array \$params): void
    {
        Auth::require();
        View::render('admin/$tableName/form.twig', [
            'item'   => null,
            'action' => BASE_URL . '/admin/$tableName/create',
        ]);
    }

    public function store(array \$params): void
    {
        Auth::require();
        \$data = \$this->formData();
        
        // TODO: Ajouter des validations personnalisées ici
        
        \$id = $entityName::create(\$data);
        View::flash('success', '$entityName créé avec succès.');
        \$this->redirect('/admin/$tableName/' . \$id . '/edit');
    }

    public function edit(array \$params): void
    {
        Auth::require();
        \$id = (int)\$params['id'];
        \$item = $entityName::find(\$id);
        if (!\$item) {
            \$this->notFound('error.twig', ['error' => 'Entité introuvable.']);
            return;
        }
        View::render('admin/$tableName/form.twig', [
            'item'   => \$item,
            'action' => BASE_URL . '/admin/$tableName/' . \$item['id'] . '/edit',
        ]);
    }

    public function update(array \$params): void
    {
        Auth::require();
        \$id = (int)\$params['id'];
        \$item = $entityName::find(\$id);
        if (!\$item) {
            \$this->notFound('error.twig', ['error' => 'Entité introuvable.']);
            return;
        }
        \$data = \$this->formData();

        $entityName::update(\$id, \$data);
        View::flash('success', '$entityName mis à jour avec succès.');
        \$this->redirect('/admin/$tableName');
    }

    public function delete(array \$params): void
    {
        Auth::require();
        \$this->requirePost('/admin/$tableName');
        \$id = (int)\$params['id'];
        $entityName::delete(\$id);
        View::flash('success', '$entityName supprimé avec succès.');
        \$this->redirect('/admin/$tableName');
    }

    public function deleteBulk(array \$params): void
    {
        Auth::require();
        \$this->requirePost('/admin/$tableName');
        \$rawIds = \$_POST['ids'] ?? [];
        if (!is_array(\$rawIds) || empty(\$rawIds)) {
            View::flash('error', 'Aucun élément sélectionné pour la suppression.');
            \$this->redirect('/admin/$tableName');
            return;
        }
        \$deleted = $entityName::deleteBulk(\$rawIds);
        if (\$deleted > 0) {
            View::flash('success', sprintf('%d $entityName(s) supprimé(s) avec succès.', \$deleted));
        } else {
            View::flash('error', 'Aucun élément n\'a pu être supprimé.');
        }
        \$this->redirect('/admin/$tableName');
    }

    private function formData(): array
    {
        return [
$controllerFieldsStr
        ];
    }
}
PHP;

// --- 3. Génération des Vues Twig ---
$listTableHeaders = "";
$listTableCells = "";
foreach (array_keys($fields) as $fname) {
    $listTableHeaders .= "                <th class=\"p-4\">" . ucfirst($fname) . "</th>\n";
    $listTableCells .= "                    <td class=\"p-4\">{{ row.$fname }}</td>\n";
}

$listTwigContent = <<<TWIG
{% extends "admin/layout.twig" %}

{% block content %}
<div class="flex justify-between items-center mb-6">
    <h1 class="text-2xl font-bold text-slate-800">Gestion de $entityName</h1>
    <a href="{{ url('admin/$tableName/create') }}" class="px-4 py-2 bg-indigo-600 text-white rounded-xl font-bold hover:bg-indigo-700 transition-all text-sm">
        Ajouter un(e) $entityName
    </a>
</div>

{% if $tableName|length %}
<form id="bulk-delete-form" method="POST" action="{{ url('admin/$tableName/delete-bulk') }}">
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
                    <th class="p-4 w-16">ID</th>
$listTableHeaders                <th class="p-4 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-slate-700">
                {% for row in $tableName %}
                    <tr>
                        <td class="p-4 text-center">
                            <input type="checkbox" name="ids[]" value="{{ row.id }}" class="item-checkbox w-4 h-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                        </td>
                        <td class="p-4 font-mono text-slate-400">#{{ row.id }}</td>
$listTableCells                    <td class="p-4 text-right space-x-2">
                            <a href="{{ url('admin/$tableName/' ~ row.id ~ '/edit') }}" class="text-indigo-600 hover:text-indigo-900 font-semibold">Modifier</a>
                            <a href="{{ url('admin/$tableName/' ~ row.id ~ '/delete') }}" onclick="return confirm('Confirmer la suppression ?')" class="text-rose-600 hover:text-rose-900 font-semibold">Supprimer</a>
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
        Aucun enregistrement trouvé.
    </div>
{% endif %}
{% endblock %}
TWIG;

$formFieldsHtml = "";
foreach ($fields as $fname => $ftype) {
    $flabel = ucfirst($fname);
    if ($ftype === 'text') {
        $formFieldsHtml .= <<<HTML
    <div class="space-y-2">
        <label for="$fname" class="block text-sm font-semibold text-slate-700">$flabel</label>
        <textarea id="$fname" name="$fname" rows="4" class="w-full px-3.5 py-3 border border-slate-200 rounded-xl text-slate-800 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 transition-all text-sm">{{ item.$fname }}</textarea>
    </div>

HTML;
    } elseif ($ftype === 'bool') {
        $formFieldsHtml .= <<<HTML
    <div class="flex items-center space-x-3 py-2">
        <input type="checkbox" id="$fname" name="$fname" value="1" {% if item.$fname %}checked{% endif %} class="w-4 h-4 text-indigo-600 border-slate-350 rounded focus:ring-indigo-500">
        <label for="$fname" class="text-sm font-semibold text-slate-700">$flabel</label>
    </div>

HTML;
    } elseif ($ftype === 'int') {
        $formFieldsHtml .= <<<HTML
    <div class="space-y-2">
        <label for="$fname" class="block text-sm font-semibold text-slate-700">$flabel</label>
        <input type="number" id="$fname" name="$fname" value="{{ item.$fname|default(0) }}" class="w-full px-3.5 py-3 border border-slate-200 rounded-xl text-slate-800 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 transition-all text-sm">
    </div>

HTML;
    } else {
        $formFieldsHtml .= <<<HTML
    <div class="space-y-2">
        <label for="$fname" class="block text-sm font-semibold text-slate-700">$flabel</label>
        <input type="text" id="$fname" name="$fname" value="{{ item.$fname }}" class="w-full px-3.5 py-3 border border-slate-200 rounded-xl text-slate-800 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 transition-all text-sm">
    </div>

HTML;
    }
}

$formTwigContent = <<<TWIG
{% extends "admin/layout.twig" %}

{% block content %}
<div class="mb-6">
    <a href="{{ url('admin/$tableName') }}" class="text-sm text-slate-500 hover:text-slate-800 transition-colors">← Retour à la liste</a>
    <h1 class="text-2xl font-bold text-slate-800 mt-2">
        {% if item %}Modifier l'entité #{{ item.id }}{% else %}Ajouter un(e) $entityName{% endif %}
    </h1>
</div>

{% if error %}
    <div class="bg-rose-50 border border-rose-100 text-rose-800 rounded-xl p-4 mb-6 text-sm">
        {{ error }}
    </div>
{% endif %}

<div class="bg-white rounded-3xl p-6 md:p-8 border border-slate-100 shadow-sm">
    <form method="POST" action="{{ action }}" class="space-y-6">
        <input type="hidden" name="_csrf_token" value="{{ csrf_token }}">

$formFieldsHtml
        <div class="pt-4">
            <button type="submit" class="px-6 py-3 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl font-bold transition-all text-sm">
                Enregistrer
            </button>
        </div>
    </form>
</div>
{% endblock %}
TWIG;

// Écriture des fichiers
if (!is_dir($viewsDir)) {
    mkdir($viewsDir, 0755, true);
}

file_put_contents($modelPath, $modelContent);
file_put_contents($controllerPath, $controllerContent);
file_put_contents($listPath, $listTwigContent);
file_put_contents($formPath, $formTwigContent);

echo "\nFichiers générés avec succès :\n";
echo "1. Modèle : src/Models/$entityName.php\n";
echo "2. Contrôleur : src/Controllers/Admin/{$entityName}Controller.php\n";
echo "3. Vue liste : templates/front002/admin/$tableName/list.twig\n";
echo "4. Vue formulaire : templates/front002/admin/$tableName/form.twig\n";
echo "\nÉtape suivante : Ajoutez ces routes dans src/Core/App.php (registerRoutes) :\n\n";
echo "\$r->get('/admin/$tableName', [{$entityName}Controller::class, 'index']);\n";
echo "\$r->get('/admin/$tableName/create', [{$entityName}Controller::class, 'create']);\n";
echo "\$r->post('/admin/$tableName/create', [{$entityName}Controller::class, 'store']);\n";
echo "\$r->post('/admin/$tableName/delete-bulk', [{$entityName}Controller::class, 'deleteBulk']);\n";
echo "\$r->get('/admin/$tableName/{id}/edit', [{$entityName}Controller::class, 'edit']);\n";
echo "\$r->post('/admin/$tableName/{id}/edit', [{$entityName}Controller::class, 'update']);\n";
echo "\$r->post('/admin/$tableName/{id}/delete', [{$entityName}Controller::class, 'delete']);\n";
echo "\nN'oubliez pas d'importer la classe : use App\\Controllers\\Admin\\{$entityName}Controller;\n";
TWIG;
