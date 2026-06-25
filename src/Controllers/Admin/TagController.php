<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\View;
use App\Models\Tag;

class TagController extends BaseAdminController
{
    public function index(array $params): void
    {
        Auth::require();
        $tags = Tag::all();
        foreach ($tags as &$tag) {
            $tag['post_count'] = Tag::getPostCount($tag['id']);
        }
        View::render('admin/tags/list.twig', ['tags' => $tags]);
    }

    public function create(array $params): void
    {
        Auth::require();
        View::render('admin/tags/form.twig', [
            'tag'    => null,
            'action' => BASE_URL . '/admin/tags/create',
        ]);
    }

    public function store(array $params): void
    {
        Auth::require();
        $name = trim($_POST['name'] ?? '');
        $slug = trim($_POST['slug'] ?? '');

        if (empty($name)) {
            View::render('admin/tags/form.twig', [
                'tag'    => ['name' => $name, 'slug' => $slug],
                'action' => BASE_URL . '/admin/tags/create',
                'error'  => 'Le nom du tag est obligatoire.',
            ]);
            return;
        }

        $id = Tag::create(['name' => $name, 'slug' => $slug]);
        View::flash('success', 'Tag créé avec succès.');
        $this->redirect('/admin/tags/' . $id . '/edit');
    }

    public function edit(array $params): void
    {
        Auth::require();
        $tag = Tag::find((int)$params['id']);
        if (!$tag) {
            $this->notFound('error.twig', ['error' => 'Tag non trouvé']);
            return;
        }
        $tag['post_count'] = Tag::getPostCount($tag['id']);
        View::render('admin/tags/form.twig', [
            'tag'    => $tag,
            'action' => BASE_URL . '/admin/tags/' . $tag['id'] . '/edit',
        ]);
    }

    public function update(array $params): void
    {
        Auth::require();
        $id  = (int)$params['id'];
        $tag = Tag::find($id);
        if (!$tag) {
            $this->notFound('error.twig', ['error' => 'Tag non trouvé']);
            return;
        }

        $name = trim($_POST['name'] ?? '');
        $slug = trim($_POST['slug'] ?? '');

        if (empty($name)) {
            $tag['post_count'] = Tag::getPostCount($id);
            View::render('admin/tags/form.twig', [
                'tag'    => array_merge($tag, ['name' => $name, 'slug' => $slug]),
                'action' => BASE_URL . '/admin/tags/' . $id . '/edit',
                'error'  => 'Le nom du tag est obligatoire.',
            ]);
            return;
        }

        Tag::update($id, ['name' => $name, 'slug' => $slug]);
        View::flash('success', 'Tag mis à jour avec succès.');
        $this->redirect('/admin/tags');
    }

    public function delete(array $params): void
    {
        Auth::require();
        $id  = (int)$params['id'];
        $tag = Tag::find($id);
        if (!$tag) {
            $this->notFound('error.twig', ['error' => 'Tag non trouvé']);
            return;
        }

        Tag::delete($id);
        View::flash('success', 'Tag supprimé avec succès.');
        $this->redirect('/admin/tags');
    }
}
