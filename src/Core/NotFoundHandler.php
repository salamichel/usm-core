<?php
declare(strict_types=1);

namespace App\Core;

trait NotFoundHandler
{
    protected function notFound(): void
    {
        http_response_code(404);
        View::render('404.twig');
    }
}
