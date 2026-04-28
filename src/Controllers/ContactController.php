<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Models\Location;
use App\Models\ContactMessage;
use App\Services\Validator;

class ContactController
{
    public function index(array $params): void
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

        $validator = Validator::make($_POST)
            ->required('name', 'Le nom est obligatoire.')
            ->minLength('name', 2)
            ->required('email', 'L\'adresse email est obligatoire.')
            ->email('email')
            ->required('subject', 'Le sujet est obligatoire.')
            ->minLength('subject', 3)
            ->required('message', 'Le message est obligatoire.')
            ->minLength('message', 10);

        if ($validator->fails()) {
            View::render('contact.twig', [
                'locations' => Location::all(),
                'error'     => $validator->firstError(),
                'form'      => [
                    'name'    => trim($_POST['name'] ?? ''),
                    'email'   => trim($_POST['email'] ?? ''),
                    'phone'   => trim($_POST['phone'] ?? ''),
                    'subject' => trim($_POST['subject'] ?? ''),
                    'message' => trim($_POST['message'] ?? ''),
                ],
            ]);
            return;
        }

        $data = $validator->getCleanData(['name', 'email', 'phone', 'subject', 'message']);
        ContactMessage::create($data);

        View::flash('success', 'Merci ! Votre message a été reçu. Nous vous répondrons au plus tôt.');
        header('Location: ' . BASE_URL . '/contact');
        exit;
    }
}
