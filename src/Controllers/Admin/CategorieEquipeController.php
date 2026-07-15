<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\View;
use App\Helpers\HtmlHelper;
use App\Models\CategorieEquipe;

class CategorieEquipeController extends AdminCrudController
{
    public function __construct()
    {
        parent::__construct();
        $this->entityType = 'categorie_equipe';
        $this->itemName = 'categorie';
        $this->itemsName = 'categories-equipes';
        $this->templates = [
            'list' => 'admin/categories-equipes/list.twig',
            'form' => 'admin/categories-equipes/form.twig',
        ];
    }

    protected function getModel(): string
    {
        return CategorieEquipe::class;
    }

    protected function getEntity(int $id): ?array
    {
        return CategorieEquipe::find($id);
    }

    protected function getAllEntities(): array
    {
        return CategorieEquipe::all();
    }

    protected function createEntity(array $data): int
    {
        return CategorieEquipe::create($data);
    }

    protected function updateEntity(int $id, array $data): void
    {
        CategorieEquipe::update($id, $data);
    }

    protected function deleteEntity(int $id): void
    {
        CategorieEquipe::delete($id);
    }

    protected function getFormData(): array
    {
        return [
            'nom'         => trim($_POST['nom'] ?? ''),
            'description' => HtmlHelper::nullIfEmptyHtml($_POST['description'] ?? null),
            'ordre'       => (int)($_POST['ordre'] ?? 0),
        ];
    }

    protected function validateData(array $data, ?array $existingEntity = null): ?string
    {
        if (empty($data['nom'])) {
            return 'Le nom de la catégorie est obligatoire.';
        }
        return null;
    }

    protected function getIndexData(array $entities): array
    {
        return [
            'categories' => $entities,
        ];
    }
}
