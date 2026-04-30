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
use App\Services\AgendaService;
use App\Services\SeoService;
use App\Services\StructuredDataService;
use App\ValueObjects\PageMetadata;

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

        // SEO metadata
        $breadcrumbs = [
            ['name' => 'Accueil', 'url' => SeoService::absoluteUrl('/')],
            ['name' => 'Équipes', 'url' => SeoService::absoluteUrl('/equipes')],
        ];
        $jsonLd = [
            StructuredDataService::sportsClub(),
        ];
        $breadcrumbSchema = StructuredDataService::breadcrumbs($breadcrumbs);
        if ($breadcrumbSchema) {
            $jsonLd[] = $breadcrumbSchema;
        }

        $meta = new PageMetadata(
            title: SeoService::title('Équipes'),
            description: SeoService::description(
                null,
                null,
                'Découvrez les équipes du club USM Volley-Ball : compositions, joueurs, matchs et entraînements.'
            ),
            canonical: SeoService::absoluteUrl('/equipes'),
            ogType: 'website',
            jsonLd: $jsonLd,
            breadcrumbs: $breadcrumbs,
        );

        View::render('equipes/index.twig', [
            'meta'   => $meta,
            'grouped' => $result,
            'saison' => $saison,
        ]);
    }

    public function show(array $params): void
    {
        $equipe = EquipeConfig::findBySlug($params['slug']);
        if (!$equipe) {
            $this->notFound();
            return;
        }

        $saison  = Saison::getActive();
        $es      = $saison ? EquipeSaison::findBySaisonAndEquipe($saison['id'], $equipe['id']) : null;
        $allPhotos  = $es ? Photo::forEntity('equipe_saison', $es['id']) : [];
        $cover   = $es ? Photo::getEntityCover('equipe_saison', $es['id']) : null;
        $photos  = $cover ? array_filter($allPhotos, fn($p) => $p['id'] !== $cover['id']) : $allPhotos;
        $joueurs = $es ? EquipeSaisonJoueur::findByEquipeSaison($es['id']) : [];

        // Mini agenda: upcoming matches for this team
        $miniAgendaEvents = [];
        if (!empty($equipe['slug_colonne'])) {
            $miniAgendaEvents = AgendaService::getUpcomingMatchesForTeam(
                $equipe['slug_colonne'],
                MINI_AGENDA_LIMIT
            );
        }

        // Autres équipes de la même catégorie
        $otherEquipes = [];
        if ($saison) {
            $allByCategory = EquipeConfig::groupedByCategorie();
            $categoryEquipes = $allByCategory[$equipe['categorie']] ?? [];
            foreach ($categoryEquipes as $eq) {
                if ($eq['id'] === $equipe['id']) continue;
                $esSaison = EquipeSaison::findBySaisonAndEquipe($saison['id'], $eq['id']);
                if (!$esSaison || EquipeSaisonJoueur::countByEquipeSaison($esSaison['id']) === 0) continue;
                $eq['cover'] = Photo::getEntityCover('equipe_saison', $esSaison['id']);
                $otherEquipes[] = $eq;
            }
        }

        // SEO metadata
        $ogImage = $es ? SeoService::pickOgImage(null, $photos) : null;
        $url = SeoService::absoluteUrl('/equipes/' . $equipe['slug']);
        $breadcrumbs = [
            ['name' => 'Accueil', 'url' => SeoService::absoluteUrl('/')],
            ['name' => 'Équipes', 'url' => SeoService::absoluteUrl('/equipes')],
            ['name' => $equipe['libelle'], 'url' => $url],
        ];
        $jsonLd = [
            StructuredDataService::sportsTeam($equipe, $ogImage, $url),
            StructuredDataService::sportsClub(),
        ];
        $breadcrumbSchema = StructuredDataService::breadcrumbs($breadcrumbs);
        if ($breadcrumbSchema) {
            $jsonLd[] = $breadcrumbSchema;
        }

        $meta = new PageMetadata(
            title: SeoService::title($equipe['libelle']),
            description: SeoService::description(
                null,
                null,
                'Équipe ' . $equipe['libelle'] . ' du club USM Volley-Ball : joueurs, matchs et entraînements.'
            ),
            canonical: $url,
            ogImage: $ogImage,
            ogType: 'website',
            jsonLd: $jsonLd,
            breadcrumbs: $breadcrumbs,
        );

        View::render('equipes/detail.twig', [
            'meta'              => $meta,
            'equipe'            => $equipe,
            'cover'             => $cover,
            'photos'            => $photos,
            'joueurs'           => $joueurs,
            'saison'            => $saison,
            'otherEquipes'      => $otherEquipes,
            'mini_agenda_events' => $miniAgendaEvents,
        ]);
    }
}
