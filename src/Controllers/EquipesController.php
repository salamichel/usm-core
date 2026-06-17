<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\NotFoundHandler;
use App\Core\View;
use App\Models\CategorieEquipe;
use App\Models\EquipeConfig;
use App\Models\EquipeSaison;
use App\Models\EquipeSaisonJoueur;
use App\Models\Photo;
use App\Models\Saison;
use App\Services\AgendaService;
use App\Services\SeoService;
use App\Services\SlugManager;
use App\Services\StructuredDataService;
use App\ValueObjects\PageMetadata;

class EquipesController
{
    use NotFoundHandler;
    public function index(array $params): void
    {
        $saisonId = isset($_GET['saison']) ? (int)$_GET['saison'] : null;
        $saison = null;

        if ($saisonId) {
            $saison = Saison::find($saisonId);
        }

        if (!$saison) {
            $saison = Saison::getActive();
        }

        $allSaisons = Saison::all();
        $categories = CategorieEquipe::all();
        $result = [];

        foreach ($categories as $cat) {
            $visibleTeams = [];
            $cover = null;

            foreach (EquipeConfig::findByCategory($cat['nom']) as $eq) {
                if (!$saison) {
                    continue;
                }
                $es = EquipeSaison::findBySaisonAndEquipe($saison['id'], $eq['id']);
                if (!$es || EquipeSaisonJoueur::countByEquipeSaison($es['id']) === 0) {
                    continue;
                }
                $visibleTeams[] = $eq;
                if ($cover === null) {
                    $cover = Photo::getEntityCover('equipe_saison', $es['id']);
                }
            }

            $cat['team_count'] = count($visibleTeams);
            $cat['cover'] = $cover;
            $cat['slug'] = SlugManager::generate($cat['nom']);
            $result[] = $cat;
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
                'Découvrez les équipes du club USM Volley-Ball classées par catégorie.'
            ),
            canonical: SeoService::absoluteUrl('/equipes'),
            ogType: 'website',
            jsonLd: $jsonLd,
            breadcrumbs: $breadcrumbs,
        );

        View::render('equipes/index.twig', [
            'meta'       => $meta,
            'categories' => $result,
            'saison'     => $saison,
            'allSaisons' => $allSaisons,
        ]);
    }

    public function category(array $params): void
    {
        $categorie = CategorieEquipe::findBySlug($params['slug']);
        if (!$categorie) {
            $this->notFound();
            return;
        }

        $saisonId = isset($_GET['saison']) ? (int)$_GET['saison'] : null;
        $saison = null;

        if ($saisonId) {
            $saison = Saison::find($saisonId);
        }

        if (!$saison) {
            $saison = Saison::getActive();
        }

        $allSaisons = Saison::all();
        $equipes = EquipeConfig::findByCategory($categorie['nom']);
        $result  = [];

        foreach ($equipes as $eq) {
            if (!$saison) continue;
            $es = EquipeSaison::findBySaisonAndEquipe($saison['id'], $eq['id']);
            if (!$es || EquipeSaisonJoueur::countByEquipeSaison($es['id']) === 0) continue;
            $eq['cover'] = Photo::getEntityCover('equipe_saison', $es['id']);
            $result[] = $eq;
        }

        $categorie['slug'] = SlugManager::generate($categorie['nom']);

        $breadcrumbs = [
            ['name' => 'Accueil', 'url' => SeoService::absoluteUrl('/')],
            ['name' => 'Équipes', 'url' => SeoService::absoluteUrl('/equipes')],
            ['name' => $categorie['nom'], 'url' => SeoService::absoluteUrl('/equipes/' . SlugManager::generate($categorie['nom']))],
        ];

        $jsonLd = [
            StructuredDataService::sportsClub(),
        ];
        $breadcrumbSchema = StructuredDataService::breadcrumbs($breadcrumbs);
        if ($breadcrumbSchema) {
            $jsonLd[] = $breadcrumbSchema;
        }

        $meta = new PageMetadata(
            title: SeoService::title($categorie['nom']),
            description: SeoService::description(
                null,
                null,
                'Découvrez les équipes de la catégorie ' . $categorie['nom'] . ' du club USM Volley-Ball.'
            ),
            canonical: SeoService::absoluteUrl('/equipes/' . SlugManager::generate($categorie['nom'])),
            ogType: 'website',
            jsonLd: $jsonLd,
            breadcrumbs: $breadcrumbs,
        );

        View::render('equipes/category.twig', [
            'meta'                   => $meta,
            'categorie'              => $categorie,
            'equipes'                => $result,
            'saison'                 => $saison,
            'allSaisons'             => $allSaisons,
            'categorie_descriptions' => [ $categorie['nom'] => $categorie ],
        ]);
    }

    public function show(array $params): void
    {
        $categorie = CategorieEquipe::findBySlug($params['categorie']);
        if (!$categorie) {
            $this->notFound();
            return;
        }

        $equipe = EquipeConfig::findByCategoryAndSlug($categorie['nom'], $params['slug']);
        if (!$equipe) {
            $this->notFound();
            return;
        }

        // Get all seasons for this team
        $allSaisons = EquipeSaison::findAllSaisonsByEquipe($equipe['id']);

        $saisonId = isset($_GET['saison']) ? (int)$_GET['saison'] : null;
        $saison = null;

        if ($saisonId) {
            $saison = Saison::find($saisonId);
        }

        if (!$saison) {
            $saison = Saison::getActive();
        }

        $es      = $saison ? EquipeSaison::findBySaisonAndEquipe($saison['id'], $equipe['id']) : null;
        $allPhotos  = $es ? Photo::forEntity('equipe_saison', $es['id']) : [];
        $cover   = $es ? Photo::getEntityCover('equipe_saison', $es['id']) : null;
        $photos  = $cover ? array_filter($allPhotos, fn($p) => $p['id'] !== $cover['id']) : $allPhotos;
        $joueurs = $es ? EquipeSaisonJoueur::findByEquipeSaison($es['id']) : [];

        // Mini agenda: upcoming matches for this team
        $miniAgendaEvents = [];
        $agendaFilterUrl = '';
        if (!empty($equipe['slug_colonne'])) {
            $miniAgendaEvents = AgendaService::getUpcomingMatchesForTeam(
                $equipe['slug_colonne'],
                MINI_AGENDA_LIMIT,
                $equipe['manifestation_filter'] ?? null
            );

            // Build agenda filter URL with team and manifestation filters
            $agendaFilterUrl = '/agenda?team=' . urlencode($equipe['slug_colonne']);
            if (!empty($equipe['manifestation_filter'])) {
                $agendaFilterUrl .= '&manifestation=' . urlencode($equipe['manifestation_filter']);
            }
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

        $categorieDesc = CategorieEquipe::findByNom($equipe['categorie']);

        // SEO metadata
        $ogImage = $es ? SeoService::pickOgImage(null, $photos) : null;
        $url = SeoService::absoluteUrl('/equipes/' . SlugManager::generate($categorie['nom']) . '/' . $equipe['slug']);
        $breadcrumbs = [
            ['name' => 'Accueil', 'url' => SeoService::absoluteUrl('/')],
            ['name' => 'Équipes', 'url' => SeoService::absoluteUrl('/equipes')],
            ['name' => $categorie['nom'], 'url' => SeoService::absoluteUrl('/equipes/' . SlugManager::generate($categorie['nom']))],
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
            'meta'               => $meta,
            'equipe'             => $equipe,
            'cover'              => $cover,
            'photos'             => $photos,
            'joueurs'            => $joueurs,
            'saison'             => $saison,
            'allSaisons'         => $allSaisons,
            'otherEquipes'       => $otherEquipes,
            'mini_agenda_events' => $miniAgendaEvents,
            'agenda_filter_url'  => $agendaFilterUrl,
            'categorie_desc'     => $categorieDesc,
            'categorie_slug'     => SlugManager::generate($categorie['nom']),
        ]);
    }
}
