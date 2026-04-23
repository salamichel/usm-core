<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Database;
use App\Core\View;

class DashboardController
{
    public function index(array $params): void
    {
        Auth::require();
        $db    = Database::get();
        $stats = [
            'posts'     => (int)$db->query("SELECT COUNT(*) FROM posts")->fetchColumn(),
            'pages'     => (int)$db->query("SELECT COUNT(*) FROM pages")->fetchColumn(),
            'menu'      => (int)$db->query("SELECT COUNT(*) FROM menu_items")->fetchColumn(),
            'documents' => (int)$db->query("SELECT COUNT(*) FROM documents")->fetchColumn(),
        ];
        View::render('admin/dashboard.twig', ['stats' => $stats]);
    }
}
