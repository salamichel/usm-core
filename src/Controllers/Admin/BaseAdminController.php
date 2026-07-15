<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\View;

/**
 * Classe de base pour tous les contrôleurs admin.
 * Fournit les helpers communs : notFound, redirect, requirePost, jsonError.
 */
abstract class BaseAdminController
{
    public function __construct()
    {
        Auth::require();
    }
    /**
     * Renvoie une réponse 404 avec le template approprié.
     */
    protected function notFound(string $template = '404.twig', array $context = []): void
    {
        http_response_code(404);
        View::render($template, $context);
    }

    /**
     * Redirige vers l'URL absolue BASE_URL + $path et termine l'exécution.
     */
    protected function redirect(string $path): void
    {
        header('Location: ' . BASE_URL . $path);
        exit;
    }

    /**
     * Vérifie que la requête est de type POST.
     * Si non, redirige vers $redirectPath.
     */
    protected function requirePost(string $redirectPath): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect($redirectPath);
        }
    }

    /**
     * Renvoie une réponse JSON d'erreur et termine l'exécution.
     */
    protected function jsonError(string $message, int $code = 422): void
    {
        header('Content-Type: application/json');
        http_response_code($code);
        echo json_encode(['error' => $message]);
        exit;
    }

    /**
     * Renvoie une réponse JSON de succès générique et termine l'exécution.
     */
    protected function jsonSuccess(array $data): void
    {
        header('Content-Type: application/json');
        echo json_encode(array_merge(['ok' => true], $data));
        exit;
    }

    /**
     * Tente de trouver une entité par son ID.
     * Si l'entité n'existe pas, affiche une page 404 et interrompt l'exécution.
     */
    protected function findOr404(string $modelClass, int $id, string $template = 'error.twig', array $context = []): array
    {
        $entity = $modelClass::find($id);
        if (!$entity) {
            $this->notFound($template, array_merge(['error' => 'Élément introuvable.'], $context));
            exit;
        }
        return $entity;
    }
}
