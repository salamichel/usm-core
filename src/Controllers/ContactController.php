<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Models\Location;

class ContactController
{
    public function index(array $params): void
    {
        View::render('contact.twig', [
            'locations' => Location::all(),
        ]);
    }
}
