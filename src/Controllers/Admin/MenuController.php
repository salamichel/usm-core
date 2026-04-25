<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\View;
use App\Models\MenuItem;
use App\Models\PageStatique;

class MenuController
{
    public function index(array $params): void
    {
        Auth::require();
        View::render('admin/menu/list.twig', [
            'items' => MenuItem::getTree(),
        ]);
    }

    public function create(array $params): void
    {
        Auth::require();
        View::render('admin/menu/form.twig', [
            'item'   => null,
            'action' => BASE_URL . '/admin/menu/create',
            'roots'  => MenuItem::roots(),
            'pages'  => PageStatique::allPublished(),
        ]);
    }

    public function store(array $params): void
    {
        Auth::require();
        $data = $this->formData();
        if (empty($data['label'])) {
            View::render('admin/menu/form.twig', ['item' => $data, 'action' => BASE_URL . '/admin/menu/create', 'roots' => MenuItem::roots(), 'pages' => PageStatique::allPublished(), 'error' => 'Le libellé est obligatoire.']);
            return;
        }
        MenuItem::create($data);
        View::flash('success', 'Élément de menu créé.');
        header('Location: ' . BASE_URL . '/admin/menu');
        exit;
    }

    public function edit(array $params): void
    {
        Auth::require();
        $item = MenuItem::find((int)$params['id']);
        if (!$item) { $this->notFound(); return; }
        View::render('admin/menu/form.twig', [
            'item'   => $item,
            'action' => BASE_URL . '/admin/menu/' . $item['id'] . '/edit',
            'roots'  => MenuItem::roots(),
            'pages'  => PageStatique::allPublished(),
        ]);
    }

    public function update(array $params): void
    {
        Auth::require();
        $id   = (int)$params['id'];
        $item = MenuItem::find($id);
        if (!$item) { $this->notFound(); return; }
        $data = $this->formData();
        if (empty($data['label'])) {
            View::render('admin/menu/form.twig', ['item' => array_merge($item, $data), 'action' => BASE_URL . '/admin/menu/' . $id . '/edit', 'roots' => MenuItem::roots(), 'pages' => PageStatique::allPublished(), 'error' => 'Le libellé est obligatoire.']);
            return;
        }
        // Prevent circular parent
        if ((int)($data['parent_id'] ?? 0) === $id) {
            $data['parent_id'] = null;
        }
        MenuItem::update($id, $data);
        View::flash('success', 'Élément de menu mis à jour.');
        header('Location: ' . BASE_URL . '/admin/menu');
        exit;
    }

    public function delete(array $params): void
    {
        Auth::require();
        MenuItem::delete((int)$params['id']);
        View::flash('success', 'Élément de menu supprimé.');
        header('Location: ' . BASE_URL . '/admin/menu');
        exit;
    }

    private function formData(): array
    {
        return [
            'label'     => trim($_POST['label'] ?? ''),
            'link_type' => $_POST['link_type'] ?? 'none',
            'target'    => trim($_POST['target'] ?? '') ?: null,
            'parent_id' => (int)($_POST['parent_id'] ?? 0) ?: null,
            'position'  => (int)($_POST['position'] ?? 0),
        ];
    }

    private function notFound(): void
    {
        http_response_code(404);
        View::render('404.twig');
    }
}
