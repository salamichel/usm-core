<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Database;
use App\Core\ExternalDatabase;
use App\Core\View;
use App\Models\Saison;

class DashboardController
{
    public function index(array $params): void
    {
        Auth::require();
        $db = Database::get();

        // ── Stats CMS ──────────────────────────────────────────────────────────
        $stats = [
            'posts'       => (int)$db->query("SELECT COUNT(*) FROM posts")->fetchColumn(),
            'pages'       => (int)$db->query("SELECT COUNT(*) FROM pages")->fetchColumn(),
            'menu'        => (int)$db->query("SELECT COUNT(*) FROM menu_items")->fetchColumn(),
            'home_blocks' => (int)$db->query("SELECT COUNT(*) FROM home_blocks")->fetchColumn(),
        ];

        // ── Stats club (base locale) ───────────────────────────────────────────
        $saisonActive = Saison::getActive();

        $snapshotsStmt = $db->prepare(
            "SELECT COUNT(*) FROM joueur_snapshots" .
            ($saisonActive ? " WHERE saison_id = ?" : "")
        );
        $saisonActive
            ? $snapshotsStmt->execute([$saisonActive['id']])
            : $snapshotsStmt->execute([]);

        $clubStats = [
            'saison_active'  => $saisonActive,
            'saisons'        => (int)$db->query("SELECT COUNT(*) FROM saisons")->fetchColumn(),
            'equipes'        => (int)$db->query("SELECT COUNT(*) FROM equipes_config WHERE is_active = 1")->fetchColumn(),
            'snapshots'      => (int)$snapshotsStmt->fetchColumn(),
        ];

        // ── Stats base externe (silencieux si indisponible) ────────────────────
        $extStats = ['joueurs' => null, 'matchs' => null, 'entrainements' => null];
        try {
            $extDb = ExternalDatabase::get();

            $extStats['joueurs'] = (int)$extDb->query("SELECT COUNT(*) FROM Joueurs")->fetchColumn();

            $stmtM = $extDb->prepare(
                "SELECT COUNT(*) FROM Manifestation
                 WHERE `ManifestationTypée` LIKE ? AND `Date` >= CURDATE()"
            );
            $stmtM->execute(['% - Match - %']);
            $extStats['matchs'] = (int)$stmtM->fetchColumn();

            $stmtE = $extDb->prepare(
                "SELECT COUNT(*) FROM Manifestation
                 WHERE `ManifestationTypée` LIKE ? AND `Date` >= CURDATE()"
            );
            $stmtE->execute(['% - Entra%']);
            $extStats['entrainements'] = (int)$stmtE->fetchColumn();
        } catch (\Throwable) {
            // base externe indisponible — valeurs null affichées "—" dans le template
        }

        View::render('admin/dashboard.twig', [
            'stats'      => $stats,
            'clubStats'  => $clubStats,
            'extStats'   => $extStats,
        ]);
    }
}
