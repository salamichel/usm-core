<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\View;
use App\Models\MotsClef;
use App\Services\Validator;

class MotsClefController extends AdminCrudController
{
    public function __construct()
    {
        parent::__construct();
        $this->entityType = 'mot_cle';
        $this->itemName = 'motCle';
        $this->itemsName = 'mots-cles';
        $this->templates = [
            'list' => 'admin/mots-cles/list.twig',
            'form' => 'admin/mots-cles/form.twig',
        ];
    }

    protected function getModel(): string
    {
        return MotsClef::class;
    }

    protected function getEntity(int $id): ?array
    {
        return MotsClef::find($id);
    }

    protected function getAllEntities(): array
    {
        return MotsClef::all();
    }

    protected function createEntity(array $data): int
    {
        return MotsClef::create($data);
    }

    protected function updateEntity(int $id, array $data): void
    {
        MotsClef::update($id, $data);
    }

    protected function deleteEntity(int $id): void
    {
        MotsClef::delete($id);
    }

    protected function getFormData(): array
    {
        $categorie = trim($_POST['categorie'] ?? '');
        $nouvelleCategorie = trim($_POST['nouvelle_categorie'] ?? '');
        if ($nouvelleCategorie !== '') {
            $categorie = $nouvelleCategorie;
        }

        return [
            'Catégorie' => $categorie,
            'Mot'       => trim($_POST['mot'] ?? ''),
        ];
    }

    protected function validateData(array $data, ?array $existingEntity = null): ?string
    {
        $validator = Validator::make($data)
            ->required('Catégorie', 'La catégorie est obligatoire.')
            ->required('Mot', 'Le mot-clé est obligatoire.');

        return $validator->fails() ? $validator->firstError() : null;
    }

    protected function getCreateData(): array
    {
        return [
            'motCle'     => null,
            'categories' => MotsClef::getCategories(),
            'action'     => BASE_URL . '/admin/mots-cles/create',
        ];
    }

    protected function getEditData(array $entity): array
    {
        return [
            'motCle'     => $entity,
            'categories' => MotsClef::getCategories(),
            'action'     => BASE_URL . '/admin/mots-cles/' . $entity['id'] . '/edit',
        ];
    }

    protected function getRedirectUrl(int $id, bool $isEdit = true): string
    {
        return BASE_URL . '/admin/mots-cles';
    }

    /**
     * Liste des mots-clés (paginée et filtrable par catégorie).
     */
    public function index(array $params): void
    {
        $page = (int)($_GET['page'] ?? 1);
        if ($page < 1) {
            $page = 1;
        }

        $category = $_GET['category'] ?? null;
        if ($category === '') {
            $category = null;
        }

        $perPage = 30;
        $total = MotsClef::count($category);
        $pagesCount = (int)ceil($total / $perPage);

        if ($page > $pagesCount && $pagesCount > 0) {
            $page = $pagesCount;
        }

        $motsCles = MotsClef::allPaginated($page, $perPage, $category);
        $categories = MotsClef::getCategories();

        View::render('admin/mots-cles/list.twig', [
            'motsCles'     => $motsCles,
            'categories'   => $categories,
            'selectedCat'  => $category,
            'currentPage'  => $page,
            'pagesCount'   => $pagesCount,
            'total'        => $total,
        ]);
    }

    /**
     * Suppression d'un mot-clé.
     */
    public function delete(array $params): void
    {
        $this->requirePost('/admin/mots-cles');

        $id = (int)$params['id'];
        $motCle = MotsClef::find($id);

        if (!$motCle) {
            $this->notFound('error.twig', ['error' => 'Mot-clé non trouvé.']);
            return;
        }

        MotsClef::delete($id);
        View::flash('success', 'Mot-clé supprimé avec succès.');
        $this->redirect('/admin/mots-cles');
    }
}
