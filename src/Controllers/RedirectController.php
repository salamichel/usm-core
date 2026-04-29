<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;

/**
 * Handle URL redirects for old URLs (301 permanent redirect).
 * Used for migrating from old URL schemes to new ones.
 */
class RedirectController
{
    public function handleOldBlogUrl(array $params): void
    {
        $oldPath = '/' . ltrim($params['path'], '/');

        $stmt = Database::get()->prepare(
            "SELECT new_slug FROM url_redirects WHERE old_path = ? LIMIT 1"
        );
        $stmt->execute([$oldPath]);
        $redirect = $stmt->fetch();

        if ($redirect) {
            header('HTTP/1.1 301 Moved Permanently');
            header('Location: ' . BASE_URL . '/blog/' . $redirect['new_slug']);
        } else {
            http_response_code(404);
            echo "Redirect not found";
        }
        exit;
    }
}
