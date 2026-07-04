<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\View;
use App\Models\MotsClef;
use App\Services\Validator;

class MotsClefController extends BaseAdminController
{
    /**
     * Liste des mots-clés (paginée et filtrable par catégorie).
     */
    public function index(array $params): void
    {
        Auth::require();

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
     * Formulaire d'ajout d'un mot-clé.
     */
    public function create(array $params): void
    {
        Auth::require();
        $categories = MotsClef::getCategories();

        View::render('admin/mots-cles/form.twig', [
            'motCle'     => null,
            'categories' => $categories,
            'action'     => BASE_URL . '/admin/mots-cles/create',
        ]);
    }

    /**
     * Enregistrement du mot-clé.
     */
    public function store(array $params): void
    {
        Auth::require();

        $categorie = trim($_POST['categorie'] ?? '');
        // Gérer le cas où l'utilisateur écrit une nouvelle catégorie
        $nouvelleCategorie = trim($_POST['nouvelle_categorie'] ?? '');
        if ($nouvelleCategorie !== '') {
            $categorie = $nouvelleCategorie;
        }

        $mot = trim($_POST['mot'] ?? '');

        $data = [
            'Catégorie' => $categorie,
            'Mot'       => $mot
        ];

        $validator = Validator::make($data)
            ->required('Catégorie', 'La catégorie est obligatoire.')
            ->required('Mot', 'Le mot-clé est obligatoire.');

        if ($validator->fails()) {
            View::render('admin/mots-cles/form.twig', [
                'motCle'     => $data,
                'categories' => MotsClef::getCategories(),
                'action'     => BASE_URL . '/admin/mots-cles/create',
                'error'      => $validator->firstError(),
            ]);
            return;
        }

        MotsClef::create($data);
        View::flash('success', 'Mot-clé créé avec succès.');
        $this->redirect('/admin/mots-cles');
    }

    /**
     * Formulaire d'édition d'un mot-clé.
     */
    public function edit(array $params): void
    {
        Auth::require();
        $id = (int)$params['id'];
        $motCle = MotsClef::find($id);

        if (!$motCle) {
            $this->notFound('error.twig', ['error' => 'Mot-clé non trouvé.']);
            return;
        }

        $categories = MotsClef::getCategories();

        View::render('admin/mots-cles/form.twig', [
            'motCle'     => $motCle,
            'categories' => $categories,
            'action'     => BASE_URL . '/admin/mots-cles/' . $id . '/edit',
        ]);
    }

    /**
     * Mise à jour du mot-clé.
     */
    public function update(array $params): void
    {
        Auth::require();
        $id = (int)$params['id'];
        $motCle = MotsClef::find($id);

        if (!$motCle) {
            $this->notFound('error.twig', ['error' => 'Mot-clé non trouvé.']);
            return;
        }

        $categorie = trim($_POST['categorie'] ?? '');
        $nouvelleCategorie = trim($_POST['nouvelle_categorie'] ?? '');
        if ($nouvelleCategorie !== '') {
            $categorie = $nouvelleCategorie;
        }

        $mot = trim($_POST['mot'] ?? '');

        $data = [
            'Catégorie' => $categorie,
            'Mot'       => $mot
        ];

        $validator = Validator::make($data)
            ->required('Catégorie', 'La catégorie est obligatoire.')
            ->required('Mot', 'Le mot-clé est obligatoire.');

        if ($validator->fails()) {
            View::render('admin/mots-cles/form.twig', [
                'motCle'     => array_merge($motCle, $data),
                'categories' => MotsClef::getCategories(),
                'action'     => BASE_URL . '/admin/mots-cles/' . $id . '/edit',
                'error'      => $validator->firstError(),
            ]);
            return;
        }

        MotsClef::update($id, $data);
        View::flash('success', 'Mot-clé mis à jour avec succès.');
        $this->redirect('/admin/mots-cles');
    }

    /**
     * Suppression d'un mot-clé.
     */
    public function delete(array $params): void
    {
        Auth::require();
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
