<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Models\Contact;
use App\Models\Location;
use App\Services\BrevoService;
use App\Services\Validator;

class ContactController
{
    public function show(array $params): void
    {
        View::render('contact.twig', [
            'locations' => Location::all(),
        ]);
    }

    public function submit(array $params): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL . '/contact');
            exit;
        }

        $v = Validator::make($_POST)
            ->required('lastname', 'Le nom est obligatoire.')
            ->minLength('lastname', 2)
            ->required('firstname', 'Le prénom est obligatoire.')
            ->minLength('firstname', 2)
            ->required('email', 'L\'email est obligatoire.')
            ->email('email')
            ->required('phone', 'Le téléphone est obligatoire.')
            ->minLength('phone', 6)
            ->required('subject', 'Le sujet est obligatoire.')
            ->required('message', 'Le message est obligatoire.')
            ->minLength('message', 10);

        if ($v->fails()) {
            View::render('contact.twig', [
                'locations' => Location::all(),
                'error' => $v->firstError(),
                'form_data' => [
                    'lastname' => $_POST['lastname'] ?? '',
                    'firstname' => $_POST['firstname'] ?? '',
                    'email' => $_POST['email'] ?? '',
                    'phone' => $_POST['phone'] ?? '',
                    'subject' => $_POST['subject'] ?? '',
                    'message' => $_POST['message'] ?? '',
                ],
            ]);
            return;
        }

        $clean = $v->getCleanData(['lastname', 'firstname', 'email', 'phone', 'subject', 'message']);

        $data = [
            'name' => trim($clean['firstname'] . ' ' . $clean['lastname']),
            'email' => $clean['email'],
            'phone' => $clean['phone'],
            'subject' => $clean['subject'],
            'message' => $clean['message'],
        ];

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
            View::render('contact.twig', [
                'locations' => Location::all(),
                'error' => 'Une erreur est survenue. Veuillez réessayer.',
                'form_data' => [
                    'lastname' => $_POST['lastname'] ?? '',
                    'firstname' => $_POST['firstname'] ?? '',
                    'email' => $_POST['email'] ?? '',
                    'phone' => $_POST['phone'] ?? '',
                    'subject' => $_POST['subject'] ?? '',
                    'message' => $_POST['message'] ?? '',
                ],
            ]);
        }
    }
}
