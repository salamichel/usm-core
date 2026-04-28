<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\View;
use App\Models\Contact;
use App\Models\ContactReply;
use App\Services\BrevoService;
use App\Services\Validator;

class ContactAdminController
{
    public function index(array $params): void
    {
        Auth::require();

        $status = $_GET['status'] ?? 'new';
        if (!in_array($status, ['new', 'replied', 'archived', 'all'])) {
            $status = 'new';
        }

        $contacts = Contact::all($status);

        View::render('admin/contacts/list.twig', [
            'contacts'    => $contacts,
            'currentStatus' => $status,
            'newCount'    => Contact::countByStatus('new'),
            'repliedCount' => Contact::countByStatus('replied'),
            'archivedCount' => Contact::countByStatus('archived'),
        ]);
    }

    public function show(array $params): void
    {
        Auth::require();

        $contact = Contact::find((int)$params['id']);
        if (!$contact) {
            http_response_code(404);
            View::render('404.twig');
            return;
        }

        $replies = ContactReply::findByContact((int)$params['id']);

        View::render('admin/contacts/detail.twig', [
            'contact' => $contact,
            'replies' => $replies,
        ]);
    }

    public function reply(array $params): void
    {
        Auth::require();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL . '/admin/contacts/' . $params['id']);
            exit;
        }

        $contact = Contact::find((int)$params['id']);
        if (!$contact) {
            http_response_code(404);
            View::render('404.twig');
            return;
        }

        $v = Validator::make($_POST)
            ->required('reply', 'La réponse est obligatoire.')
            ->minLength('reply', 5);

        if ($v->fails()) {
            View::flash('error', 'Erreur : ' . $v->firstError());
            header('Location: ' . BASE_URL . '/admin/contacts/' . $params['id']);
            exit;
        }

        $replyText = trim($_POST['reply']);

        try {
            $fromEmail = \App\Models\SiteConfig::get('email') ?: ADMIN_EMAIL;
            ContactReply::create((int)$params['id'], $fromEmail, $replyText);
            Contact::updateStatus((int)$params['id'], 'replied');

            try {
                $brevo = new BrevoService();
                $brevo->sendReplyToVisitor($contact['email'], $contact['name'], $replyText, $fromEmail);
            } catch (\Exception $e) {
                \App\Services\Logger::errors()->error('Failed to send reply email', ['error' => $e->getMessage()]);
            }

            View::flash('success', 'Réponse envoyée avec succès.');
            header('Location: ' . BASE_URL . '/admin/contacts/' . $params['id']);
            exit;
        } catch (\Exception $e) {
            View::flash('error', 'Une erreur est survenue.');
            header('Location: ' . BASE_URL . '/admin/contacts/' . $params['id']);
            exit;
        }
    }

    public function updateStatus(array $params): void
    {
        Auth::require();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL . '/admin/contacts');
            exit;
        }

        $contact = Contact::find((int)$params['id']);
        if (!$contact) {
            http_response_code(404);
            return;
        }

        $status = $_POST['status'] ?? 'new';
        if (!in_array($status, ['new', 'replied', 'archived'])) {
            $status = 'new';
        }

        Contact::updateStatus((int)$params['id'], $status);
        View::flash('success', 'Statut mis à jour.');
        header('Location: ' . BASE_URL . '/admin/contacts/' . $params['id']);
        exit;
    }

    public function delete(array $params): void
    {
        Auth::require();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL . '/admin/contacts');
            exit;
        }

        Contact::delete((int)$params['id']);
        View::flash('success', 'Message supprimé.');
        header('Location: ' . BASE_URL . '/admin/contacts');
        exit;
    }

    public function bulkAction(array $params): void
    {
        Auth::require();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL . '/admin/contacts');
            exit;
        }

        $action = $_POST['action'] ?? '';
        $contactIds = $_POST['contact_ids'] ?? [];

        if (!in_array($action, ['archive', 'delete']) || empty($contactIds)) {
            View::flash('error', 'Action invalide ou aucun message sélectionné.');
            header('Location: ' . BASE_URL . '/admin/contacts');
            exit;
        }

        $contactIds = array_filter(array_map(function($id) {
            $id = (int)$id;
            return $id > 0 ? $id : null;
        }, $contactIds));

        if (empty($contactIds)) {
            View::flash('error', 'IDs de messages invalides.');
            header('Location: ' . BASE_URL . '/admin/contacts');
            exit;
        }

        try {
            $count = 0;
            foreach ($contactIds as $id) {
                if ($action === 'archive') {
                    Contact::updateStatus($id, 'archived');
                } else {
                    Contact::delete($id);
                }
                $count++;
            }

            $actionLabel = $action === 'archive' ? 'archivé' : 'supprimé';
            View::flash('success', "$count message" . ($count > 1 ? 's' : '') . " $actionLabel" . ($count > 1 ? 's' : '') . '.');
            header('Location: ' . BASE_URL . '/admin/contacts');
            exit;
        } catch (\Exception $e) {
            View::flash('error', 'Une erreur est survenue lors du traitement.');
            header('Location: ' . BASE_URL . '/admin/contacts');
            exit;
        }
    }
}
