<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Models\Contact;
use App\Services\BrevoService;
use App\Services\Validator;

class ContactController
{
    public function show(array $params): void
    {
        View::render('contact/form.twig');
    }

    public function submit(array $params): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL . '/contact');
            exit;
        }

        $v = Validator::make($_POST)
            ->required('name', 'Le nom est obligatoire.')
            ->minLength('name', 2)
            ->required('email', 'L\'email est obligatoire.')
            ->email('email')
            ->required('subject', 'Le sujet est obligatoire.')
            ->minLength('subject', 5)
            ->required('message', 'Le message est obligatoire.')
            ->minLength('message', 10);

        if ($v->fails()) {
            View::flash('error', 'Erreur : ' . $v->firstError());
            header('Location: ' . BASE_URL . '/contact');
            exit;
        }

        $data = $v->getCleanData(['name', 'email', 'subject', 'message']);

        try {
            $contactId = Contact::create($data);

            $contact = Contact::find($contactId);
            if ($contact) {
                try {
                    $brevo = new BrevoService();
                    $brevo->sendContactNotification($contact);
                } catch (\Exception $e) {
                    \App\Services\Logger::errors()->error('Failed to send contact notification', ['error' => $e->getMessage()]);
                }
            }

            View::flash('success', 'Merci ! Nous avons bien reçu votre message et vous répondrons rapidement.');
            header('Location: ' . BASE_URL . '/contact');
            exit;
        } catch (\Exception $e) {
            View::flash('error', 'Une erreur est survenue. Veuillez réessayer.');
            header('Location: ' . BASE_URL . '/contact');
            exit;
        }
    }
}
