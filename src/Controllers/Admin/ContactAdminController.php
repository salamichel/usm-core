<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\View;
use App\Models\Contact;
use App\Models\ContactReply;
use App\Services\BrevoService;
use App\Services\Logger;
use App\Services\Validator;

class ContactAdminController extends BaseAdminController
{
    /** Statuts valides pour un contact. */
    private const VALID_STATUSES = ['new', 'replied', 'archived'];

    public function index(array $params): void
    {
        Auth::require();

        $status = $_GET['status'] ?? 'new';
        if (!in_array($status, [...self::VALID_STATUSES, 'all'])) {
            $status = 'new';
        }

        $contacts = Contact::all($status);

        View::render('admin/contacts/list.twig', [
            'contacts'      => $contacts,
            'currentStatus' => $status,
            'newCount'      => Contact::countByStatus('new'),
            'repliedCount'  => Contact::countByStatus('replied'),
            'archivedCount' => Contact::countByStatus('archived'),
        ]);
    }

    public function show(array $params): void
    {
        Auth::require();

        $contact = Contact::find((int)$params['id']);
        if (!$contact) {
            $this->notFound();
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
        $this->requirePost('/admin/contacts/' . $params['id']);

        $contact = Contact::find((int)$params['id']);
        if (!$contact) {
            $this->notFound();
            return;
        }

        $v = Validator::make($_POST)
            ->required('reply', 'La réponse est obligatoire.')
            ->minLength('reply', 5);

        if ($v->fails()) {
            View::flash('error', 'Erreur : ' . $v->firstError());
            $this->redirect('/admin/contacts/' . $params['id']);
            return;
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
                Logger::errors()->error('Failed to send reply email', ['error' => $e->getMessage()]);
            }

            View::flash('success', 'Réponse envoyée avec succès.');
        } catch (\Exception $e) {
            View::flash('error', 'Une erreur est survenue.');
        }

        $this->redirect('/admin/contacts/' . $params['id']);
    }

    public function updateStatus(array $params): void
    {
        Auth::require();
        $this->requirePost('/admin/contacts');

        $contact = Contact::find((int)$params['id']);
        if (!$contact) {
            $this->notFound();
            return;
        }

        $status = $_POST['status'] ?? 'new';
        if (!in_array($status, self::VALID_STATUSES)) {
            $status = 'new';
        }

        Contact::updateStatus((int)$params['id'], $status);
        View::flash('success', 'Statut mis à jour.');
        $this->redirect('/admin/contacts/' . $params['id']);
    }

    public function delete(array $params): void
    {
        Auth::require();
        $this->requirePost('/admin/contacts');

        Contact::delete((int)$params['id']);
        View::flash('success', 'Message supprimé.');
        $this->redirect('/admin/contacts');
    }

    public function bulkAction(array $params): void
    {
        Auth::require();
        $this->requirePost('/admin/contacts');

        $action     = $_POST['action'] ?? '';
        $contactIds = $_POST['contact_ids'] ?? [];

        if (!in_array($action, ['archive', 'delete']) || empty($contactIds)) {
            View::flash('error', 'Action invalide ou aucun message sélectionné.');
            $this->redirect('/admin/contacts');
            return;
        }

        $contactIds = array_values(array_filter(array_map(fn($id) => ($id = (int)$id) > 0 ? $id : null, $contactIds)));

        if (empty($contactIds)) {
            View::flash('error', 'IDs de messages invalides.');
            $this->redirect('/admin/contacts');
            return;
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

            $label = $action === 'archive' ? 'archivé' : 'supprimé';
            View::flash('success', "$count message" . ($count > 1 ? 's' : '') . " $label" . ($count > 1 ? 's' : '') . '.');
        } catch (\Exception $e) {
            View::flash('error', 'Une erreur est survenue lors du traitement.');
        }

        $this->redirect('/admin/contacts');
    }
}
