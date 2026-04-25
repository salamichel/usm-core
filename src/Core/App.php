<?php
declare(strict_types=1);

namespace App\Core;

use App\Controllers\HomeController;
use App\Controllers\BlogController;
use App\Controllers\EquipesController;
use App\Controllers\PageController;
use App\Controllers\Admin\AuthController;
use App\Controllers\Admin\DashboardController;
use App\Controllers\Admin\DocumentController;
use App\Controllers\Admin\EquipeConfigController;
use App\Controllers\Admin\MenuController;
use App\Controllers\Admin\PageAdminController;
use App\Controllers\Admin\PostController;
use App\Controllers\Admin\SaisonController;

class App
{
    private Router $router;

    public function __construct()
    {
        $this->router = new Router();
        $this->registerRoutes();
    }

    public function run(): void
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $uri    = $_SERVER['REQUEST_URI'];
        $this->router->dispatch($method, $uri);
    }

    private function registerRoutes(): void
    {
        $r = $this->router;

        // ── Public ────────────────────────────────────────────────────────────
        $r->get('/',              [HomeController::class, 'index']);
        $r->get('/blog',          [BlogController::class, 'list']);
        $r->get('/blog/{slug}',   [BlogController::class, 'show']);
        $r->get('/p/{slug}',      [PageController::class, 'show']);
        $r->get('/documents',     [PageController::class, 'documents']);
        $r->get('/equipes',       [EquipesController::class, 'index']);
        $r->get('/equipes/{id}',  [EquipesController::class, 'show']);

        // ── Admin auth ────────────────────────────────────────────────────────
        $r->get('/admin/login',   [AuthController::class, 'showLogin']);
        $r->post('/admin/login',  [AuthController::class, 'handleLogin']);
        $r->get('/admin/logout',  [AuthController::class, 'logout']);

        // ── Admin dashboard ───────────────────────────────────────────────────
        $r->get('/admin',         [DashboardController::class, 'index']);

        // ── Admin posts ───────────────────────────────────────────────────────
        $r->get('/admin/posts',             [PostController::class, 'index']);
        $r->get('/admin/posts/create',      [PostController::class, 'create']);
        $r->post('/admin/posts/create',     [PostController::class, 'store']);
        $r->get('/admin/posts/{id}/edit',   [PostController::class, 'edit']);
        $r->post('/admin/posts/{id}/edit',  [PostController::class, 'update']);
        $r->post('/admin/posts/{id}/delete',[PostController::class, 'delete']);

        // ── Admin pages ───────────────────────────────────────────────────────
        $r->get('/admin/pages',             [PageAdminController::class, 'index']);
        $r->get('/admin/pages/create',      [PageAdminController::class, 'create']);
        $r->post('/admin/pages/create',     [PageAdminController::class, 'store']);
        $r->get('/admin/pages/{id}/edit',   [PageAdminController::class, 'edit']);
        $r->post('/admin/pages/{id}/edit',  [PageAdminController::class, 'update']);
        $r->post('/admin/pages/{id}/delete',[PageAdminController::class, 'delete']);

        // ── Admin menu ────────────────────────────────────────────────────────
        $r->get('/admin/menu',             [MenuController::class, 'index']);
        $r->get('/admin/menu/create',      [MenuController::class, 'create']);
        $r->post('/admin/menu/create',     [MenuController::class, 'store']);
        $r->get('/admin/menu/{id}/edit',   [MenuController::class, 'edit']);
        $r->post('/admin/menu/{id}/edit',  [MenuController::class, 'update']);
        $r->post('/admin/menu/{id}/delete',[MenuController::class, 'delete']);

        // ── Admin documents ───────────────────────────────────────────────────
        $r->get('/admin/documents',             [DocumentController::class, 'index']);
        $r->get('/admin/documents/create',      [DocumentController::class, 'create']);
        $r->post('/admin/documents/create',     [DocumentController::class, 'store']);
        $r->get('/admin/documents/{id}/edit',   [DocumentController::class, 'edit']);
        $r->post('/admin/documents/{id}/edit',  [DocumentController::class, 'update']);
        $r->post('/admin/documents/{id}/delete',[DocumentController::class, 'delete']);

        // ── Admin saisons & joueurs ───────────────────────────────────────────
        $r->get('/admin/saisons',                [SaisonController::class, 'index']);
        $r->get('/admin/saisons/create',         [SaisonController::class, 'create']);
        $r->post('/admin/saisons/create',        [SaisonController::class, 'store']);
        $r->get('/admin/saisons/joueurs',        [SaisonController::class, 'joueurs']);
        $r->post('/admin/saisons/{id}/delete',   [SaisonController::class, 'delete']);
        $r->post('/admin/saisons/{id}/activate', [SaisonController::class, 'activate']);
        $r->post('/admin/saisons/{id}/flash',    [SaisonController::class, 'flash']);
        $r->get('/admin/saisons/{id}/snapshots', [SaisonController::class, 'snapshots']);

        // ── Admin équipes config ──────────────────────────────────────────────
        $r->get('/admin/equipes-config',        [EquipeConfigController::class, 'index']);
        $r->get('/admin/equipes-config/create', [EquipeConfigController::class, 'create']);
        $r->post('/admin/equipes-config/create',[EquipeConfigController::class, 'store']);
        $r->get('/admin/equipes-config/{id}/edit',   [EquipeConfigController::class, 'edit']);
        $r->post('/admin/equipes-config/{id}/edit',  [EquipeConfigController::class, 'update']);
        $r->post('/admin/equipes-config/{id}/delete',[EquipeConfigController::class, 'delete']);
        $r->get('/admin/equipes-config/{id}/saisons/{sid}/photos',
            [EquipeConfigController::class, 'saisonPhotos']);
        $r->post('/admin/equipes-config/{id}/saisons/{sid}/photos/upload',
            [EquipeConfigController::class, 'uploadSaisonPhoto']);
        $r->post('/admin/equipes-config/{id}/saisons/{sid}/photos/{pid}/delete-xhr',
            [EquipeConfigController::class, 'deleteSaisonPhotoXhr']);
        $r->get('/admin/equipes-config/{id}/saisons/{sid}/joueurs',
            [EquipeConfigController::class, 'saisonJoueurs']);
        $r->post('/admin/equipes-config/{id}/saisons/{sid}/joueurs/add',
            [EquipeConfigController::class, 'addJoueur']);
        $r->post('/admin/equipes-config/{id}/saisons/{sid}/joueurs/{jid}/remove',
            [EquipeConfigController::class, 'removeJoueur']);

        // ── Admin photos (posts) ──────────────────────────────────────────────
        $r->post('/admin/posts/{id}/photos/upload',           [PostController::class, 'uploadPhoto']);
        $r->post('/admin/posts/{id}/photos/{pid}/delete',     [PostController::class, 'deletePhoto']);
        $r->post('/admin/posts/{id}/photos/{pid}/delete-xhr', [PostController::class, 'deletePhotoXhr']);

        // ── Admin photos (pages) ──────────────────────────────────────────────
        $r->post('/admin/pages/{id}/photos/upload',           [PageAdminController::class, 'uploadPhoto']);
        $r->post('/admin/pages/{id}/photos/{pid}/delete',     [PageAdminController::class, 'deletePhoto']);
        $r->post('/admin/pages/{id}/photos/{pid}/delete-xhr', [PageAdminController::class, 'deletePhotoXhr']);
        $r->post('/admin/pages/{id}/photos/{pid}/delete', [PageAdminController::class, 'deletePhoto']);
    }
}
