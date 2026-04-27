<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Services\AIContentService;

class AdminApiController
{
    public function improveContent(): void
    {
        Auth::require();

        header('Content-Type: application/json');

        $text = $_POST['text'] ?? '';

        if (empty($text)) {
            http_response_code(400);
            echo json_encode(['error' => 'Texte vide']);
            return;
        }

        $improved = AIContentService::improveContent($text);

        if ($improved) {
            echo json_encode(['success' => true, 'improved_text' => $improved]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Impossible de générer une amélioration']);
        }
    }
}
