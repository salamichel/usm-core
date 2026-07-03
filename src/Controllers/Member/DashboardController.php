<?php

namespace App\Controllers\Member;

use App\Core\View;

class DashboardController
{
    /**
     * Affiche le tableau de bord de l'adhérent.
     * Route: GET /member/dashboard
     */
    public function index(): void
    {
        // Vérification d'accès spécifique aux adhérents (différent de l'admin CMS)
        if (!isset($_SESSION['LogIn']) || $_SESSION['LogIn'] !== true) {
            View::flash('error', 'Veuillez vous connecter pour accéder à l\'espace adhérent.');
            $redirect = $_SERVER['REQUEST_URI'] ?? '/member/dashboard';
            header('Location: /member/login?redirect=' . urlencode($redirect));
            exit;
        }

        $userId = (int) $_SESSION['LogInId'];

        // Rafraîchir le statut de capitaine
        $saisonActive = \App\Models\Saison::getActive();
        $captainedTeams = $saisonActive ? \App\Models\EquipeSaisonJoueur::findCaptainedTeams($userId, $saisonActive['id']) : [];
        $_SESSION['IsCaptainSaison'] = !empty($captainedTeams);

        $kpis = \App\Services\MemberDashboardService::getKPIs($userId);
        $imminentEvents = \App\Services\MemberDashboardService::getImminentEvents($userId, 100);
        \App\Services\AgendaService::flagOverlappingSelected($imminentEvents, $userId);
        $stats = \App\Services\MemberDashboardService::getSeasonStats($userId);

        View::render('member/dashboard.twig', [
            'is_capitaine' => $_SESSION['IsCaptainSaison'] ?? false,
            'is_admin_web' => $_SESSION['AdminWeb'] ?? false,
            'kpis' => $kpis,
            'imminent_events' => $imminentEvents,
            'stats' => $stats,
        ]);
    }
}