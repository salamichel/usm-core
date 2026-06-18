<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\View;
use App\Models\Joueur;
use App\Services\Validator;

class JoueurController
{
    /**
     * Vérifie si l'utilisateur possède les droits d'administrateur web.
     */
    private function requireAdmin(): void
    {
        Auth::require(); // Vérifie qu'il est connecté

        if (empty($_SESSION['AdminWeb'])) {
            View::flash('error', 'Vous n\'avez pas les privilèges "Admin Web" pour accéder à cette page.');
            header('Location: /member/dashboard');
            exit;
        }
    }

    /**
     * Affiche la liste des joueurs et le formulaire d'ajout.
     * Route: GET /joueurs/edit
     */
    public function index(): void
    {
        $this->requireAdmin();

        $joueurs = Joueur::getAll();

        View::render('joueurs/index.twig', [
            'joueurs' => $joueurs
        ]);
    }

    /**
     * Traite l'ajout d'un nouveau joueur.
     * Route: POST /joueurs/store
     */
    public function store(): void
    {
        $this->requireAdmin();

        $v = Validator::make($_POST)
            ->required('Nom', 'Le nom est requis.')
            ->required('Prenom', 'Le prénom est requis.');

        if ($v->fails()) {
            View::flash('error', $v->firstError());
            header('Location: /joueurs/edit');
            exit;
        }

        $data = $v->getCleanData(['Nom', 'Prenom']);
        
        Joueur::create($data['Nom'], $data['Prenom']);

        View::flash('success', 'Le joueur a été ajouté avec succès.');
        header('Location: /joueurs/edit');
        exit;
    }

    /**
     * Traite la suppression d'un joueur.
     * Route: POST /joueurs/delete/{id}
     */
    public function delete(array $params): void
    {
        $this->requireAdmin();
        
        $id = (int) $params['id'];
        
        Joueur::delete($id);

        View::flash('success', 'Le joueur a bien été supprimé.');
        header('Location: /joueurs/edit');
        exit;
    }
}