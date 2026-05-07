<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\View;
use App\Models\CategorieEquipe;

class CategorieEquipeController
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
        header('Location: ' . BASE_URL . '/admin/categories-equipes/' . $id . '/edit');
        exit;
    }

    public function edit(array $params): void
    {
        Auth::require();
        $categorie = CategorieEquipe::find((int)$params['id']);
        if (!$categorie) {
            http_response_code(404);
            View::render('error.twig', ['error' => 'Catégorie introuvable.']);
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
            http_response_code(404);
            View::render('error.twig', ['error' => 'Catégorie introuvable.']);
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
        header('Location: ' . BASE_URL . '/admin/categories-equipes/' . $id . '/edit');
        exit;
    }

    public function delete(array $params): void
    {
        Auth::require();
        CategorieEquipe::delete((int)$params['id']);
        View::flash('success', 'Catégorie supprimée.');
        header('Location: ' . BASE_URL . '/admin/categories-equipes');
        exit;
    }

    private function formData(): array
    {
        return [
            'nom'         => trim($_POST['nom'] ?? ''),
            'description' => $_POST['description'] ?? null,
            'ordre'       => (int)($_POST['ordre'] ?? 0),
        ];
    }
}
