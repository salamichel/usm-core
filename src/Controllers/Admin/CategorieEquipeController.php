<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\View;
use App\Helpers\HtmlHelper;
use App\Models\CategorieEquipe;

class CategorieEquipeController extends BaseAdminController
{
    public function index(array $params): void
    {
        Auth::require();
        View::render('admin/categories-equipes/list.twig', [
            'categories' => CategorieEquipe::all(),
        ]);
    }

    public function create(array $params): void
    {
        Auth::require();
        View::render('admin/categories-equipes/form.twig', [
            'categorie' => null,
            'action'    => BASE_URL . '/admin/categories-equipes/create',
        ]);
    }

    public function store(array $params): void
    {
        Auth::require();
        $data = $this->formData();
        if ($data['nom'] === '') {
            View::render('admin/categories-equipes/form.twig', [
                'categorie' => $data,
                'action'    => BASE_URL . '/admin/categories-equipes/create',
                'error'     => 'Le nom de la catégorie est obligatoire.',
            ]);
            return;
        }
        $id = CategorieEquipe::create($data);
        View::flash('success', "Catégorie « {$data['nom']} » créée.");
        $this->redirect('/admin/categories-equipes/' . $id . '/edit');
    }

    public function edit(array $params): void
    {
        Auth::require();
        $categorie = CategorieEquipe::find((int)$params['id']);
        if (!$categorie) {
            $this->notFound('error.twig', ['error' => 'Catégorie introuvable.']);
            return;
        }
        View::render('admin/categories-equipes/form.twig', [
            'categorie' => $categorie,
            'action'    => BASE_URL . '/admin/categories-equipes/' . $categorie['id'] . '/edit',
        ]);
    }

    public function update(array $params): void
    {
        Auth::require();
        $id        = (int)$params['id'];
        $categorie = CategorieEquipe::find($id);
        if (!$categorie) {
            $this->notFound('error.twig', ['error' => 'Catégorie introuvable.']);
            return;
        }
        $data = $this->formData();
        if ($data['nom'] === '') {
            View::render('admin/categories-equipes/form.twig', [
                'categorie' => array_merge($categorie, $data),
                'action'    => BASE_URL . '/admin/categories-equipes/' . $id . '/edit',
                'error'     => 'Le nom de la catégorie est obligatoire.',
            ]);
            return;
        }
        CategorieEquipe::update($id, $data);
        View::flash('success', "Catégorie « {$data['nom']} » mise à jour.");
        $this->redirect('/admin/categories-equipes/' . $id . '/edit');
    }

    public function delete(array $params): void
    {
        Auth::require();
        CategorieEquipe::delete((int)$params['id']);
        View::flash('success', 'Catégorie supprimée.');
        $this->redirect('/admin/categories-equipes');
    }

    private function formData(): array
    {
        return [
            'nom'         => trim($_POST['nom'] ?? ''),
            'description' => HtmlHelper::nullIfEmptyHtml($_POST['description'] ?? null),
            'ordre'       => (int)($_POST['ordre'] ?? 0),
        ];
    }
}
