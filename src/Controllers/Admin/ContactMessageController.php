<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\View;
use App\Models\ContactMessage;

class ContactMessageController extends BaseAdminController
{
    public function index(array $params): void
    {
        Auth::require();
        View::render('admin/contact-messages/list.twig', [
            'messages' => ContactMessage::all(),
        ]);
    }

    public function show(array $params): void
    {
        Auth::require();
        $message = ContactMessage::find((int)$params['id']);

        if (!$message) {
            $this->notFound();
            return;
        }

        if (!$message['read_at']) {
            ContactMessage::markAsRead($message['id']);
        }

        View::render('admin/contact-messages/show.twig', [
            'message' => $message,
        ]);
    }

    public function delete(array $params): void
    {
        Auth::require();
        ContactMessage::delete((int)$params['id']);
        View::flash('success', 'Message supprimé.');
        $this->redirect('/admin/contact-messages');
    }
}
