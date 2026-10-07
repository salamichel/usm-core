<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\View;
use App\Models\OpponentTeam;
use App\Services\Validator;

class OpponentTeamController extends AdminCrudController
{
    public function __construct()
    {
        parent::__construct();
        $this->entityType = 'opponent_teams';
        $this->itemName   = 'équipe adverse';
        $this->itemsName  = 'opponent-teams';
    }

    protected function getModel(): string
    {
        return OpponentTeam::class;
    }

    protected function getEntity(int $id): ?array
    {
        return OpponentTeam::find($id);
    }

    protected function getAllEntities(): array
    {
        return OpponentTeam::all();
    }

    protected function createEntity(array $data): int
    {
        return OpponentTeam::create($data);
    }

    protected function updateEntity(int $id, array $data): void
    {
        OpponentTeam::update($id, $data);
    }

    protected function deleteEntity(int $id): void
    {
        OpponentTeam::delete($id);
    }

    protected function getFormData(): array
    {
        return [
            'name'         => trim($_POST['name'] ?? ''),
            'club'         => trim($_POST['club'] ?? ''),
            'city'         => trim($_POST['city'] ?? ''),
            'is_active'    => isset($_POST['is_active']) ? 1 : 0,
            'needs_review' => isset($_POST['needs_review']) ? 1 : 0,
        ];
    }

    protected function validateData(array $data, ?array $existingEntity = null): ?string
    {
        $v = Validator::make($data)
            ->required('name', 'Le nom de l\'équipe est obligatoire.');

        if ($v->fails()) {
            return $v->firstError();
        }

        // Vérifier l'unicité du nom
        $existing = OpponentTeam::findByName($data['name']);
        if ($existing) {
            if (!$existingEntity || (int)$existing['id'] !== (int)$existingEntity['id']) {
                return 'Une équipe avec ce nom existe déjà dans le référentiel.';
            }
        }

        return null;
    }

    protected function getIndexData(array $entities): array
    {
        $pendingCount = count(array_filter($entities, fn($e) => !empty($e['needs_review'])));
        return [
            'teams'        => $entities,
            'pendingCount' => $pendingCount,
        ];
    }

    protected function getCreateData(): array
    {
        return [
            'team'   => [
                'name'         => '',
                'club'         => '',
                'city'         => '',
                'is_active'    => 1,
                'needs_review' => 0,
            ],
            'action' => BASE_URL . '/admin/opponent-teams/create',
        ];
    }

    protected function getEditData(array $entity): array
    {
        return [
            'team'         => $entity,
            'allTeams'     => OpponentTeam::all(),
            'action'       => BASE_URL . '/admin/opponent-teams/' . $entity['id'] . '/edit',
        ];
    }

    /**
     * Valide une équipe soumise par un capitaine (retire le drapeau needs_review).
     */
    public function validate(array $params): void
    {
        $this->requirePost('/admin/opponent-teams');
        $id = (int)$params['id'];
        OpponentTeam::validate($id);
        View::flash('success', 'Équipe validée avec succès.');
        header('Location: ' . BASE_URL . '/admin/opponent-teams');
        exit;
    }

    /**
     * Fusionne cette équipe dans une autre équipe existante.
     */
    public function merge(array $params): void
    {
        $this->requirePost('/admin/opponent-teams');
        $fromId = (int)$params['id'];
        $toId   = (int)($_POST['target_team_id'] ?? 0);

        if ($toId <= 0 || $toId === $fromId) {
            View::flash('error', 'Veuillez sélectionner une équipe cible valide distincte.');
            header('Location: ' . BASE_URL . '/admin/opponent-teams/' . $fromId . '/edit');
            exit;
        }

        $source = OpponentTeam::find($fromId);
        $target = OpponentTeam::find($toId);

        if (!$source || !$target) {
            View::flash('error', 'Équipe introuvable.');
            header('Location: ' . BASE_URL . '/admin/opponent-teams');
            exit;
        }

        OpponentTeam::merge($fromId, $toId);
        View::flash('success', "L'équipe '{$source['name']}' a été fusionnée dans '{$target['name']}'.");
        header('Location: ' . BASE_URL . '/admin/opponent-teams');
        exit;
    }
}
