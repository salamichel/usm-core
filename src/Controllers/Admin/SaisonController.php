<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\View;
use App\Models\JoueurSnapshot;
use App\Models\Saison;
use App\Models\EquipeConfig;
use App\Models\MemberEmailPreference;
use App\Models\MotsClef;
use App\Services\Validator;

class SaisonController extends AdminCrudController
{
    public function __construct()
    {
        parent::__construct();
        $this->entityType = 'saison';
        $this->itemName = 'saison';
        $this->itemsName = 'saisons';
        $this->templates = [
            'list'   => 'admin/saisons/list.twig',
            'create' => 'admin/saisons/create.twig',
            'edit'   => 'admin/saisons/edit.twig',
        ];
    }

    protected function getModel(): string
    {
        return Saison::class;
    }

    protected function getEntity(int $id): ?array
    {
        return Saison::find($id);
    }

    protected function getAllEntities(): array
    {
        return Saison::all();
    }

    protected function createEntity(array $data): int
    {
        return Saison::create($data);
    }

    protected function updateEntity(int $id, array $data): void
    {
        Saison::update($id, $data);
    }

    protected function deleteEntity(int $id): void
    {
        Saison::delete($id);
    }

    protected function getFormData(): array
    {
        return [
            'libelle'    => trim($_POST['libelle'] ?? ''),
            'date_debut' => trim($_POST['date_debut'] ?? ''),
            'date_fin'   => trim($_POST['date_fin'] ?? ''),
        ];
    }

    protected function validateData(array $data, ?array $existingEntity = null): ?string
    {
        $v = Validator::make($data)
            ->required('libelle', 'Le libellé est obligatoire.')
            ->required('date_debut', 'La date de début est obligatoire.')
            ->required('date_fin', 'La date de fin est obligatoire.');
        return $v->fails() ? $v->firstError() : null;
    }

    protected function getIndexData(array $entities): array
    {
        foreach ($entities as &$s) {
            $s['snapshot_count'] = Saison::snapshotCount($s['id']);
        }
        return [
            'saisons' => $entities,
        ];
    }

    public function update(array $params): void
    {
        $id     = (int)$params['id'];
        $saison = $this->findOr404(Saison::class, $id);

        $data = $this->getFormData();
        $error = $this->validateData($data, $saison);
        if ($error !== null) {
            View::render($this->getFormTemplate(true), [
                'saison' => array_merge($saison, $data),
                'error'  => $error,
            ]);
            return;
        }

        try {
            Saison::update($id, $data);
            View::flash('success', "Saison « {$data['libelle']} » mise à jour.");
            $this->redirect('/admin/saisons');
        } catch (\PDOException $e) {
            $error = $e->getCode() === '23000' || str_contains($e->getMessage(), '1062')
                ? "Le libellé « {$data['libelle']} » existe déjà pour une autre saison."
                : "Erreur lors de la mise à jour : " . $e->getMessage();

            View::render($this->getFormTemplate(true), [
                'saison' => array_merge($saison, $data),
                'error'  => $error,
            ]);
        }
    }

    public function joueurs(array $params): void
    {
        try {
            $joueurs = JoueurSnapshot::getExternalJoueurs();
            $error   = null;
        } catch (\Throwable $e) {
            $joueurs = [];
            $error   = 'Impossible de se connecter à la base externe : ' . $e->getMessage();
        }

        // Récupération des catégories d'équipes pour l'affichage
        $categories = EquipeConfig::getEquipesSlug();

        // Récupération des saisons pour le formulaire de flashage
        $seasons = Saison::all();
        $saisonActive = Saison::getActive();

        View::render('admin/saisons/joueurs.twig', [
            'joueurs'       => $joueurs,
            'error'         => $error,
            'categories'    => $categories,
            'seasons'       => $seasons,
            'saison_active' => $saisonActive,
        ]);
    }

    public function flashSelect(array $params): void
    {
        $saisonId = (int)($_POST['saison_id'] ?? 0);
        $s        = Saison::find($saisonId);
        if (!$s) {
            View::flash('error', 'Saison introuvable.');
            $this->redirect('/admin/saisons/joueurs');
            return;
        }
        try {
            $count = JoueurSnapshot::flashForSaison($saisonId);
            View::flash('success', "{$count} joueurs enregistrés pour la saison « {$s['libelle']} ».");
        } catch (\Throwable $e) {
            View::flash('error', 'Erreur lors du flash : ' . $e->getMessage());
        }
        $this->redirect('/admin/saisons/' . $saisonId . '/snapshots');
    }

    public function flash(array $params): void
    {
        $id = (int)$params['id'];
        $s  = $this->findOr404(Saison::class, $id);
        try {
            $count = JoueurSnapshot::flashForSaison($id);
            View::flash('success', "{$count} joueurs enregistrés pour la saison « {$s['libelle']} ».");
        } catch (\Throwable $e) {
            View::flash('error', 'Erreur lors du flash : ' . $e->getMessage());
        }
        $this->redirect('/admin/saisons/' . $id . '/snapshots');
    }

    public function snapshots(array $params): void
    {
        $id     = (int)$params['id'];
        $saison = $this->findOr404(Saison::class, $id);
        $snapshots = JoueurSnapshot::findBySaison($id);

        // Récupération des catégories d'équipes pour l'affichage
        $categories = EquipeConfig::getEquipesSlug();

        View::render('admin/saisons/snapshots.twig', [
            'saison'     => $saison,
            'snapshots'  => $snapshots,
            'categories' => $categories,
        ]);
    }

    public function sendWeeklyReminder(): void
    {
        try {
            $sentCount = \App\Services\Agenda\EventNotificationService::sendWeeklyNotifications();
            View::flash('success', "Le rappel hebdomadaire de présence a été envoyé avec succès à {$sentCount} joueur(s).");
        } catch (\Throwable $e) {
            View::flash('error', "Une erreur est survenue lors de l'envoi des rappels : " . $e->getMessage());
        }
        $this->redirect('/admin/saisons');
    }

    public function purge(array $params): void
    {
        $id = (int)$params['id'];
        $s  = $this->findOr404(Saison::class, $id);
        
        $db = \App\Core\Database::get();
        $db->beginTransaction();
        try {
            // 1. Supprimer les adhésions aux équipes
            $db->prepare("
                DELETE FROM equipe_saison_joueur 
                WHERE equipe_saison_id IN (
                    SELECT id FROM equipe_saison WHERE saison_id = ?
                )
            ")->execute([$id]);

            // 2. Supprimer les snapshots des joueurs
            $db->prepare("DELETE FROM joueur_snapshots WHERE saison_id = ?")->execute([$id]);

            // 3. Supprimer les préférences d'emails
            $db->prepare("DELETE FROM member_email_preferences WHERE saison_id = ?")->execute([$id]);

            $db->commit();
            View::flash('success', "Les joueurs et abonnements de la saison « {$s['libelle']} » ont été purgés avec succès.");
        } catch (\Throwable $e) {
            $db->rollBack();
            View::flash('error', "Erreur lors de la purge : " . $e->getMessage());
        }
        
        $this->redirect('/admin/saisons');
    }

    /**
     * Retourne les préférences d'emails d'un joueur pour une saison en JSON.
     * Route: GET /admin/saisons/{id}/joueurs/{pid}/email-preferences
     */
    public function getEmailPreferences(array $params): void
    {
        header('Content-Type: application/json');

        $saisonId = (int)($params['id'] ?? 0);
        $playerId = (int)($params['pid'] ?? 0);

        if ($saisonId <= 0 || $playerId <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Paramètres invalides.']);
            exit;
        }

        $saison = Saison::find($saisonId);
        if (!$saison) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Saison introuvable.']);
            exit;
        }

        $trainingTypes = MotsClef::getTrainingTypes();
        $dbPrefs = MemberEmailPreference::getPreferences($playerId, $saisonId);

        $preferences = [
            [
                'key'           => 'match',
                'label'         => 'Avis de création de match',
                'is_subscribed' => isset($dbPrefs['match']) ? ((int)$dbPrefs['match'] === 1) : true,
            ],
            [
                'key'           => 'weekly_presence',
                'label'         => 'Rappel hebdomadaire de présence',
                'is_subscribed' => isset($dbPrefs['weekly_presence']) ? ((int)$dbPrefs['weekly_presence'] === 1) : true,
            ],
            [
                'key'           => 'club_life',
                'label'         => 'Vie du club & Tournois',
                'is_subscribed' => isset($dbPrefs['club_life']) ? ((int)$dbPrefs['club_life'] === 1) : true,
            ],
        ];

        foreach ($trainingTypes as $type) {
            $cleanLabel = trim(str_replace(['Présences - ', 'Disponibilités - '], '', $type));
            $preferences[] = [
                'key'           => $type,
                'label'         => 'Entraînement : ' . $cleanLabel,
                'is_subscribed' => isset($dbPrefs[$type]) ? ((int)$dbPrefs[$type] === 1) : true,
            ];
        }

        echo json_encode([
            'success'     => true,
            'preferences' => $preferences,
        ]);
        exit;
    }

    /**
     * Met à jour les préférences d'emails d'un joueur pour une saison.
     * Route: POST /admin/saisons/{id}/joueurs/{pid}/email-preferences
     */
    public function updateEmailPreferences(array $params): void
    {
        header('Content-Type: application/json');

        $saisonId = (int)($params['id'] ?? $_POST['saison_id'] ?? 0);
        $playerId = (int)($params['pid'] ?? $_POST['player_id'] ?? 0);

        if ($saisonId <= 0 || $playerId <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Paramètres invalides.']);
            exit;
        }

        $saison = Saison::find($saisonId);
        if (!$saison) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Saison introuvable.']);
            exit;
        }

        $postedPrefs   = $_POST['preferences'] ?? [];
        $trainingTypes = MotsClef::getTrainingTypes();
        $allKeys       = array_merge(['match', 'weekly_presence', 'club_life'], $trainingTypes);

        try {
            foreach ($allKeys as $key) {
                $isSubscribed = isset($postedPrefs[$key]) && (string)$postedPrefs[$key] === '1';
                MemberEmailPreference::setPreference($playerId, $saisonId, $key, $isSubscribed);
            }

            echo json_encode([
                'success' => true,
                'message' => 'Préférences enregistrées avec succès.',
            ]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error'   => 'Erreur lors de l’enregistrement : ' . $e->getMessage(),
            ]);
        }
        exit;
    }
}

