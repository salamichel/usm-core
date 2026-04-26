<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\NotFoundHandler;
use App\Core\View;
use App\Models\EquipeConfig;
use App\Models\EquipeSaison;
use App\Models\EquipeSaisonJoueur;
use App\Models\Photo;
use App\Models\Saison;

class EquipesController
{
    use NotFoundHandler;
    public function index(array $params): void
    {
        $saison  = Saison::getActive();
        $grouped = EquipeConfig::groupedByCategorie();
        $result  = [];

        foreach ($grouped as $cat => $equipes) {
            foreach ($equipes as $eq) {
                if (!$saison) continue;
                $es = EquipeSaison::findBySaisonAndEquipe($saison['id'], $eq['id']);
                if (!$es || EquipeSaisonJoueur::countByEquipeSaison($es['id']) === 0) continue;
                $eq['cover'] = Photo::getEntityCover('equipe_saison', $es['id']);
                $result[$cat][] = $eq;
            }
        }

        View::render('equipes/index.twig', ['grouped' => $result, 'saison' => $saison]);
    }

    public function show(array $params): void
    {
        $equipe = EquipeConfig::find((int)$params['id']);
        if (!$equipe || !$equipe['is_active']) {
            $this->notFound();
            return;
        }

        $saison  = Saison::getActive();
        $es      = $saison ? EquipeSaison::findBySaisonAndEquipe($saison['id'], $equipe['id']) : null;
        $photos  = $es ? Photo::forEntity('equipe_saison', $es['id']) : [];
        $joueurs = $es ? EquipeSaisonJoueur::findByEquipeSaison($es['id']) : [];

        View::render('equipes/detail.twig', [
            'equipe'  => $equipe,
            'photos'  => $photos,
            'joueurs' => $joueurs,
            'saison'  => $saison,
        ]);
    }
}
