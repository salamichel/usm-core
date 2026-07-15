<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Models\Photo;

class PhotoAdminController extends BaseAdminController
{
    public function reorder(array $params): void
    {
        header('Content-Type: application/json');
        $body = json_decode(file_get_contents('php://input'), true);
        $ids  = array_values(array_filter(
            array_map('intval', $body['ids'] ?? []),
            fn($id) => $id > 0
        ));
        if (empty($ids)) {
            echo json_encode(['ok' => false, 'error' => 'No IDs provided']);
            return;
        }
        Photo::reorder($ids);
        echo json_encode(['ok' => true]);
    }
}
