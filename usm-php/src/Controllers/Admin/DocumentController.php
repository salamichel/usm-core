<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\View;
use App\Models\Document;

class DocumentController
{
    public function index(array $params): void
    {
        Auth::require();
        View::render('admin/documents/list.twig', ['documents' => Document::all()]);
    }

    public function create(array $params): void
    {
        Auth::require();
        View::render('admin/documents/form.twig', ['document' => null, 'action' => BASE_URL . '/admin/documents/create']);
    }

    public function store(array $params): void
    {
        Auth::require();
        $data = $this->formData();
        if (empty($data['title'])) {
            View::render('admin/documents/form.twig', ['document' => $data, 'action' => BASE_URL . '/admin/documents/create', 'error' => 'Le titre est obligatoire.']);
            return;
        }
        if (empty($_FILES['file']['name'])) {
            View::render('admin/documents/form.twig', ['document' => $data, 'action' => BASE_URL . '/admin/documents/create', 'error' => 'Un fichier est requis.']);
            return;
        }
        try {
            $filename = Document::uploadFile($_FILES['file']);
        } catch (\RuntimeException $e) {
            View::render('admin/documents/form.twig', ['document' => $data, 'action' => BASE_URL . '/admin/documents/create', 'error' => $e->getMessage()]);
            return;
        }
        $data['filename'] = $filename;
        Document::create($data);
        View::flash('success', 'Document ajouté.');
        header('Location: ' . BASE_URL . '/admin/documents');
        exit;
    }

    public function edit(array $params): void
    {
        Auth::require();
        $document = Document::find((int)$params['id']);
        if (!$document) { $this->notFound(); return; }
        View::render('admin/documents/form.twig', ['document' => $document, 'action' => BASE_URL . '/admin/documents/' . $document['id'] . '/edit']);
    }

    public function update(array $params): void
    {
        Auth::require();
        $id       = (int)$params['id'];
        $document = Document::find($id);
        if (!$document) { $this->notFound(); return; }
        $data = $this->formData();
        if (empty($data['title'])) {
            View::render('admin/documents/form.twig', ['document' => array_merge($document, $data), 'action' => BASE_URL . '/admin/documents/' . $id . '/edit', 'error' => 'Le titre est obligatoire.']);
            return;
        }
        // Replace file if a new one is uploaded
        if (!empty($_FILES['file']['name'])) {
            try {
                $filename = Document::uploadFile($_FILES['file']);
                Document::updateFilename($id, $filename);
                // Remove old file
                $old = UPLOAD_DIR . '/' . $document['filename'];
                if (file_exists($old)) unlink($old);
            } catch (\RuntimeException $e) {
                View::render('admin/documents/form.twig', ['document' => array_merge($document, $data), 'action' => BASE_URL . '/admin/documents/' . $id . '/edit', 'error' => $e->getMessage()]);
                return;
            }
        }
        Document::update($id, $data);
        View::flash('success', 'Document mis à jour.');
        header('Location: ' . BASE_URL . '/admin/documents');
        exit;
    }

    public function delete(array $params): void
    {
        Auth::require();
        Document::delete((int)$params['id']);
        View::flash('success', 'Document supprimé.');
        header('Location: ' . BASE_URL . '/admin/documents');
        exit;
    }

    private function formData(): array
    {
        return [
            'title'     => trim($_POST['title'] ?? ''),
            'doc_type'  => trim($_POST['doc_type'] ?? '') ?: null,
            'is_public' => isset($_POST['is_public']) ? 1 : 0,
        ];
    }

    private function notFound(): void
    {
        http_response_code(404);
        View::render('404.twig');
    }
}
