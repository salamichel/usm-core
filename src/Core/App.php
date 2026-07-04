<?php
declare(strict_types=1);

namespace App\Core;

use App\Controllers\HomeController;
use App\Controllers\BlogController;
use App\Controllers\ContactController;
use App\Controllers\EquipesController;
use App\Controllers\PageController;
use App\Controllers\SitemapController;
use App\Controllers\JoueurController;
use App\Controllers\AgendaController;
use App\Controllers\Member\AuthController as joueurAuthController;
use App\Controllers\Member\DashboardController as joueurDashboardController;
use App\Controllers\Member\ParticipationController;
use App\Controllers\Member\ProfileController;
use App\Controllers\Member\CaptainController;
use App\Controllers\Api\ArticleApiController;
use App\Controllers\Admin\AuthController;
use App\Controllers\Admin\CategorieEquipeController;
use App\Controllers\Admin\TagController;
use App\Controllers\Admin\DashboardController;
use App\Controllers\Admin\EquipeConfigController;
use App\Controllers\Admin\HomeBlockController;
use App\Controllers\Admin\MenuController;
use App\Controllers\Admin\PageAdminController;
use App\Controllers\Admin\PostController;
use App\Controllers\Admin\SaisonController;
use App\Controllers\Admin\SiteConfigController;
use App\Controllers\Admin\ContactAdminController;
use App\Controllers\Admin\StatsController;
use App\Controllers\Admin\LocationController;
use App\Controllers\Admin\ContactMessageController;
use App\Controllers\Admin\MediaUploadController;
use App\Controllers\Admin\PhotoAdminController;
use App\Controllers\Admin\MotsClefController;
use App\Controllers\Admin\ManifestationGeneratorController;
use App\Controllers\Admin\ManifestationController;

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

        // Validate CSRF token on POST requests (except login)
        if ($method === 'POST' && !$this->isLoginRoute($uri)) {
            $token = $_POST['_csrf_token'] ?? null;
            if (!$token) {
                $ct = $_SERVER['CONTENT_TYPE'] ?? '';
                if (str_contains($ct, 'application/json')) {
                    $body  = json_decode(file_get_contents('php://input'), true);
                    $token = $body['_csrf_token'] ?? null;
                }
            }
            if (!CsrfToken::validate($token)) {
                http_response_code(403);
                View::render('error.twig', ['error' => 'Token CSRF invalide.']);
                return;
            }
        }

        $this->router->dispatch($method, $uri);
    }

    private function isLoginRoute(string $uri): bool
    {
        $path = parse_url($uri, PHP_URL_PATH);
        return str_ends_with($path, '/admin/login') || str_starts_with($path, '/api/');
    }

    private function registerRoutes(): void
    {
        $r = $this->router;

        // ── SEO ───────────────────────────────────────────────────────────────
        $r->get('/robots.txt',   [SitemapController::class, 'robots']);
        $r->get('/sitemap.xml',  [SitemapController::class, 'sitemap']);

        // ── Public ────────────────────────────────────────────────────────────
        $r->get('/',              [HomeController::class, 'index']);
        $r->get('/blog',          [BlogController::class, 'list']);
        $r->get('/blog/tag/{tag}', [BlogController::class, 'list']);
        $r->get('/blog/{slug+}',  [BlogController::class, 'show']);
        $r->get('/p/{slug}',      [PageController::class, 'show']);
        $r->get('/equipes',                    [EquipesController::class, 'index']);
        $r->get('/equipes/{slug}',             [EquipesController::class, 'category']);
        $r->get('/equipes/{categorie}/{slug}', [EquipesController::class, 'show']);
        $r->post('/equipes/{categorie}/{slug}/contact-capitaine', [EquipesController::class, 'contactCaptain']);
        $r->get('/agenda',                     [AgendaController::class, 'index']);
        $r->get('/agenda/{id}',   [AgendaController::class, 'show']);
        $r->get('/contact',       [ContactController::class, 'show']);
        $r->post('/contact',      [ContactController::class, 'submit']);


        // Espace Adhérent Public
        $r->get('/member/login', [joueurAuthController::class, 'loginForm']);
        $r->post('/member/login', [joueurAuthController::class, 'login']);
        $r->post('/member/logout', [joueurAuthController::class, 'logout']);

        $r->get('/member/dashboard', [joueurDashboardController::class, 'index']);        
        $r->get('/member/profile', [ProfileController::class, 'show']);
        $r->post('/member/profile', [ProfileController::class, 'update']);
        $r->post('/joueurs/delete/{id}', [JoueurController::class, 'delete']);        
        $r->get('/public/participation/update', [ParticipationController::class, 'publicUpdate']);

        // Espace Capitaine
        $r->get('/member/captain', [CaptainController::class, 'index']);
        $r->get('/member/captain/matches/create', [CaptainController::class, 'createMatchForm']);
        $r->post('/member/captain/matches/create', [CaptainController::class, 'storeMatch']);
        $r->get('/member/captain/matches/{id}/edit', [CaptainController::class, 'editMatchForm']);
        $r->post('/member/captain/matches/{id}/edit', [CaptainController::class, 'updateMatch']);
        $r->get('/member/captain/matches/{id}/select-players', [CaptainController::class, 'selectPlayersForm']);
        $r->post('/member/captain/matches/{id}/select-players', [CaptainController::class, 'updateSelectedPlayers']);
        $r->post('/member/captain/matches/{id}/remind', [CaptainController::class, 'remindNoResponse']);

        // ── API ───────────────────────────────────────────────────────────────
        $r->post('/api/member/participations/upsert', [ParticipationController::class, 'apiUpsert']);
        $r->post('/api/captain/participation/update', [CaptainController::class, 'apiUpdatePlayerParticipation']);
        $r->options('/api/articles', [ArticleApiController::class, 'create']);
        $r->post('/api/articles',   [ArticleApiController::class, 'create']);

        // ── Admin auth ────────────────────────────────────────────────────────
        $r->get('/admin/login',   [AuthController::class, 'showLogin']);
        $r->post('/admin/login',  [AuthController::class, 'handleLogin']);
        $r->get('/admin/logout',  [AuthController::class, 'logout']);

        // ── Admin dashboard ───────────────────────────────────────────────────
        $r->get('/admin',         [DashboardController::class, 'index']);

        // ── Admin tags ─────────────────────────────────────────────────────
        $r->get('/admin/tags',             [TagController::class, 'index']);
        $r->get('/admin/tags/create',      [TagController::class, 'create']);
        $r->post('/admin/tags/create',     [TagController::class, 'store']);
        $r->get('/admin/tags/{id}/edit',   [TagController::class, 'edit']);
        $r->post('/admin/tags/{id}/edit',  [TagController::class, 'update']);
        $r->post('/admin/tags/{id}/delete',[TagController::class, 'delete']);

        // ── Admin posts ───────────────────────────────────────────────────────
        $r->get('/admin/posts',             [PostController::class, 'index']);
        $r->get('/admin/posts/create',      [PostController::class, 'create']);
        $r->post('/admin/posts/create',     [PostController::class, 'store']);
        $r->get('/admin/posts/{id}/edit',   [PostController::class, 'edit']);
        $r->post('/admin/posts/{id}/edit',  [PostController::class, 'update']);
        $r->post('/admin/posts/{id}/delete',         [PostController::class, 'delete']);
        $r->post('/admin/posts/{id}/toggle-slider', [PostController::class, 'toggleSlider']);

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

        // ── Admin saisons & joueurs ───────────────────────────────────────────
        $r->get('/admin/saisons',                [SaisonController::class, 'index']);
        $r->get('/admin/saisons/create',         [SaisonController::class, 'create']);
        $r->post('/admin/saisons/create',        [SaisonController::class, 'store']);
        $r->get('/admin/saisons/joueurs',        [SaisonController::class, 'joueurs']);
        $r->get('/admin/saisons/{id}/edit',      [SaisonController::class, 'edit']);
        $r->post('/admin/saisons/{id}/edit',     [SaisonController::class, 'update']);
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
        $r->post('/admin/equipes-config/{id}/saisons/{sid}/joueurs/{jid}/toggle-captain',
            [EquipeConfigController::class, 'toggleCaptain']);

        // ── Admin catégories d'équipes ────────────────────────────────────────
        $r->get('/admin/categories-equipes',              [CategorieEquipeController::class, 'index']);
        $r->get('/admin/categories-equipes/create',       [CategorieEquipeController::class, 'create']);
        $r->post('/admin/categories-equipes/create',      [CategorieEquipeController::class, 'store']);
        $r->get('/admin/categories-equipes/{id}/edit',    [CategorieEquipeController::class, 'edit']);
        $r->post('/admin/categories-equipes/{id}/edit',   [CategorieEquipeController::class, 'update']);
        $r->post('/admin/categories-equipes/{id}/delete', [CategorieEquipeController::class, 'delete']);

        // ── Admin stats ───────────────────────────────────────────────────────
        $r->get('/admin/stats',        [StatsController::class, 'index']);

        // ── Admin site config (footer, contact, réseaux) ──────────────────────
        $r->get('/admin/site-config',  [SiteConfigController::class, 'edit']);
        $r->post('/admin/site-config', [SiteConfigController::class, 'update']);

        // ── Admin contacts ────────────────────────────────────────────────────
        $r->get('/admin/contacts',                   [ContactAdminController::class, 'index']);
        $r->get('/admin/contacts/{id}',              [ContactAdminController::class, 'show']);
        $r->post('/admin/contacts/{id}/reply',       [ContactAdminController::class, 'reply']);
        $r->post('/admin/contacts/{id}/status',      [ContactAdminController::class, 'updateStatus']);
        $r->post('/admin/contacts/{id}/delete',      [ContactAdminController::class, 'delete']);
        $r->post('/admin/contacts/bulk-action',      [ContactAdminController::class, 'bulkAction']);

        // ── Admin locations ──────────────────────────────────────────────
        $r->get('/admin/locations',             [LocationController::class, 'index']);
        $r->get('/admin/locations/create',      [LocationController::class, 'create']);
        $r->post('/admin/locations/create',     [LocationController::class, 'store']);
        $r->get('/admin/locations/{id}/edit',   [LocationController::class, 'edit']);
        $r->post('/admin/locations/{id}/edit',  [LocationController::class, 'update']);
        $r->post('/admin/locations/{id}/delete',[LocationController::class, 'delete']);

        // ── Admin Mots-clés (Base Externe) ────────────────────────────────────
        $r->get('/admin/mots-cles',             [MotsClefController::class, 'index']);
        $r->get('/admin/mots-cles/create',      [MotsClefController::class, 'create']);
        $r->post('/admin/mots-cles/create',     [MotsClefController::class, 'store']);
        $r->get('/admin/mots-cles/{id}/edit',   [MotsClefController::class, 'edit']);
        $r->post('/admin/mots-cles/{id}/edit',  [MotsClefController::class, 'update']);
        $r->post('/admin/mots-cles/{id}/delete',[MotsClefController::class, 'delete']);

        // ── Admin Générateur de manifestations ────────────────────────────────
        $r->get('/admin/manifestations/generator',  [ManifestationGeneratorController::class, 'showForm']);
        $r->post('/admin/manifestations/generator', [ManifestationGeneratorController::class, 'generate']);

        // ── Admin Manifestations (CRUD) ───────────────────────────────────────
        $r->get('/admin/manifestations',             [ManifestationController::class, 'index']);
        $r->get('/admin/manifestations/create',      [ManifestationController::class, 'create']);
        $r->post('/admin/manifestations/create',     [ManifestationController::class, 'store']);
        $r->get('/admin/manifestations/{id}/edit',   [ManifestationController::class, 'edit']);
        $r->post('/admin/manifestations/{id}/edit',  [ManifestationController::class, 'update']);
        $r->post('/admin/manifestations/{id}/delete',[ManifestationController::class, 'delete']);

        // ── Admin media upload (WYSIWYG editor) ───────────────────────────────
        $r->post('/admin/media/upload', [MediaUploadController::class, 'upload']);

        // ── Admin contact messages ────────────────────────────────────────────
        $r->get('/admin/contact-messages',         [ContactMessageController::class, 'index']);
        $r->get('/admin/contact-messages/{id}',    [ContactMessageController::class, 'show']);
        $r->post('/admin/contact-messages/{id}/delete', [ContactMessageController::class, 'delete']);

        // ── Admin home blocks ─────────────────────────────────────────────────
        $r->get('/admin/home-blocks',                 [HomeBlockController::class, 'index']);
        $r->get('/admin/home-blocks/create',          [HomeBlockController::class, 'create']);
        $r->post('/admin/home-blocks/create',         [HomeBlockController::class, 'store']);
        $r->post('/admin/home-blocks/upload',         [HomeBlockController::class, 'uploadImage']);
        $r->get('/admin/home-blocks/{id}/edit',       [HomeBlockController::class, 'edit']);
        $r->post('/admin/home-blocks/{id}/edit',      [HomeBlockController::class, 'update']);
        $r->post('/admin/home-blocks/{id}/delete',    [HomeBlockController::class, 'delete']);
        $r->post('/admin/home-blocks/{id}/move-up',   [HomeBlockController::class, 'moveUp']);
        $r->post('/admin/home-blocks/{id}/move-down', [HomeBlockController::class, 'moveDown']);

        // ── Admin photos (partagé) ───────────────────────────────────────────
        $r->post('/admin/photos/reorder', [PhotoAdminController::class, 'reorder']);

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
