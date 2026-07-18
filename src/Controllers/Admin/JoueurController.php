<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\View;
use App\Models\Joueur;
use App\Models\Saison;
use App\Models\EquipeConfig;
use App\Services\Pagination;

class JoueurController extends BaseAdminController
{
    /**
     * Affiche la liste des joueurs sous forme de tableur avec filtres et pagination.
     */
    public function index(array $params): void
    {
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $search = isset($_GET['search']) ? trim($_GET['search']) : null;
        $equipe = isset($_GET['equipe']) && $_GET['equipe'] !== '' ? trim($_GET['equipe']) : null;

        $perPage = 30; // Nombre de joueurs par page
        $total = Joueur::count($search, $equipe);
        $pagination = new Pagination($total, $perPage, $page);

        $joueurs = Joueur::allPaginated($pagination->currentPage, $pagination->perPage, $search, $equipe);

        // Récupération des catégories d'équipes dynamiquement depuis les mots-clés
        $categorySlugs = \App\Models\MotsClef::getByCategory('EquipeParEquipe');
        $categoryColumns = [];
        foreach ($categorySlugs as $slug) {
            $categoryColumns[$slug] = str_replace('_', ' ', $slug);
        }

        // Pour le filtre de sélection d'équipe
        $filterTeams = $categorySlugs;
        sort($filterTeams);

        // Récupération des saisons et de la saison active pour le formulaire de flashage
        $seasons = Saison::all();
        $saisonActive = Saison::getActive();

        // Calcul des métriques globales et répartition
        $totalPlayers = Joueur::count(null, null);
        $teamStats = Joueur::getStatsByTeam($categorySlugs);

        View::render('admin/joueurs/list.twig', [
            'joueurs'          => $joueurs,
            'categoryColumns'  => $categoryColumns,
            'filterTeams'      => $filterTeams,
            'seasons'          => $seasons,
            'saison_active'    => $saisonActive,
            'currentPage'      => $pagination->currentPage,
            'pagesCount'       => $pagination->totalPages,
            'total'            => $total,
            'search'           => $search,
            'selectedEquipe'   => $equipe,
            'totalPlayers'     => $totalPlayers,
            'teamStats'        => $teamStats,
        ]);
    }

    /**
     * AJAX endpoint pour créer un nouveau joueur.
     */
    public function create(array $params): void
    {
        $this->requirePost('/admin/joueurs');

        $nom = trim($_POST['Nom'] ?? '');
        $prenom = trim($_POST['Prenom'] ?? '');

        if ($nom === '' || $prenom === '') {
            $this->jsonError('Le nom et le prénom sont obligatoires.', 422);
        }

        $extraData = [
            'Sexe' => trim($_POST['Sexe'] ?? 'M'),
            'Mel' => !empty($_POST['Mel']) ? trim($_POST['Mel']) : null,
            'Téléphone' => !empty($_POST['Téléphone']) ? trim($_POST['Téléphone']) : '',
            'NLicence' => !empty($_POST['NLicence']) ? (int)$_POST['NLicence'] : null,
        ];

        try {
            $id = Joueur::create($nom, $prenom, $extraData);
            $newPlayer = Joueur::findById($id);
            $this->jsonSuccess(['joueur' => $newPlayer]);
        } catch (\Throwable $e) {
            $this->jsonError('Erreur lors de la création du joueur : ' . $e->getMessage(), 500);
        }
    }

    /**
     * AJAX endpoint pour mettre à jour un champ spécifique d'un joueur.
     */
    public function updateField(array $params): void
    {
        $this->requirePost('/admin/joueurs');

        $id = (int)$params['id'];
        $field = $_POST['field'] ?? '';
        $value = $_POST['value'] ?? '';

        $categorySlugs = \App\Models\MotsClef::getByCategory('EquipeParEquipe');
        $whitelist = array_merge([
            'Nom', 'Prénom', 'Sexe', 'Mel', 'Téléphone', 'NLicence', 'DateNaissance', 'Adresse', 'CodePostal', 'Commune', 'Caracteristique', 'Equipe', 'Equipes', 'mdp'
        ], $categorySlugs);

        if (!in_array($field, $whitelist, true)) {
            $this->jsonError('Champ non autorisé pour modification.', 400);
        }

        if ($field === 'Nom' && trim((string)$value) === '') {
            $this->jsonError('Le nom ne peut pas être vide.', 422);
        }
        if ($field === 'Prénom' && trim((string)$value) === '') {
            $this->jsonError('Le prénom ne peut pas être vide.', 422);
        }

        // Uppercase pour le Nom
        if ($field === 'Nom') {
            $value = strtoupper(trim((string)$value));
        }

        // Gestion des flags tinyint
        $flagFields = array_merge([
            'Compétition', 'Débutant'
        ], $categorySlugs);

        if (in_array($field, $flagFields, true)) {
            $value = ($value === '1' || $value === 'true' || $value === true || (int)$value === 1) ? 1 : 0;
        } else {
            // Trim et gestion des valeurs nulles pour les autres champs
            $value = trim((string)$value);
            if ($value === '' && in_array($field, ['Mel', 'Téléphone', 'NLicence', 'DateNaissance', 'Adresse', 'CodePostal', 'Commune', 'Caracteristique', 'mdp'], true)) {
                $value = null;
            }
        }

        try {
            Joueur::update($id, [$field => $value]);
            $this->jsonSuccess(['field' => $field, 'value' => $value]);
        } catch (\Throwable $e) {
            $this->jsonError('Erreur de base de données : ' . $e->getMessage(), 500);
        }
    }

    /**
     * AJAX endpoint pour supprimer un joueur.
     */
    public function delete(array $params): void
    {
        $this->requirePost('/admin/joueurs');

        $id = (int)$params['id'];
        try {
            Joueur::delete($id);
            $this->jsonSuccess([]);
        } catch (\Throwable $e) {
            $this->jsonError('Erreur lors de la suppression du joueur : ' . $e->getMessage(), 500);
        }
    }
}
