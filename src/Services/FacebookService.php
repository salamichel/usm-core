<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Wrapper minimaliste autour de l'API Graph Facebook.
 *
 * Aucune dépendance externe : utilise cURL natif (disponible sur InfinityFree).
 * Si la config est absente (token vide), toutes les méthodes échouent
 * silencieusement et renvoient false / [] — l'intégration est strictement
 * optionnelle.
 *
 * Voir FACEBOOK_API_FEASIBILITY.md pour le contexte et la procédure de setup.
 */
class FacebookService
{
    public static function isConfigured(): bool
    {
        return FB_PAGE_ID !== '' && FB_PAGE_ACCESS_TOKEN !== '';
    }

    /**
     * Publie un message (texte + lien optionnel) sur le feed de la page.
     * Retourne l'ID du post Facebook ou null en cas d'échec.
     */
    public static function publishPost(string $message, ?string $link = null): ?string
    {
        if (!self::isConfigured()) {
            Logger::app()->warning('FacebookService.publishPost: not configured');
            return null;
        }

        $params = ['message' => $message];
        if ($link !== null && $link !== '') {
            $params['link'] = $link;
        }

        $endpoint = sprintf(
            'https://graph.facebook.com/%s/%s/feed',
            FB_GRAPH_VERSION,
            FB_PAGE_ID
        );

        $response = self::httpPost($endpoint, $params);
        if ($response === null || !isset($response['id'])) {
            Logger::errors()->error('FacebookService.publishPost failed', [
                'endpoint' => $endpoint,
                'response' => $response,
            ]);
            return null;
        }

        Logger::audit()->info('Facebook post published', [
            'fb_post_id' => $response['id'],
            'message'    => mb_substr($message, 0, 80),
        ]);

        return (string)$response['id'];
    }

    /**
     * Publie un article du site sur la page Facebook.
     * Construit le message à partir du Post (title + excerpt) + lien public.
     * Retourne l'ID Facebook ou null.
     */
    public static function shareArticle(array $post): ?string
    {
        $title   = trim((string)($post['title'] ?? ''));
        $excerpt = trim((string)($post['excerpt'] ?? ''));
        $slug    = (string)($post['slug'] ?? '');

        if ($title === '' || $slug === '') {
            return null;
        }

        $message = $title;
        if ($excerpt !== '') {
            $message .= "\n\n" . $excerpt;
        }
        $link = BASE_URL . '/blog/' . $slug;

        return self::publishPost($message, $link);
    }

    /**
     * Récupère les N derniers posts de la page (avec cache TTL).
     * Retourne un tableau normalisé pour affichage côté Twig :
     *   [{ id, message, created_at, permalink, picture, story }, …]
     */
    public static function getPagePosts(int $limit = 5): array
    {
        if (!self::isConfigured()) {
            return [];
        }

        $cacheKey = 'page_feed:' . $limit;
        $cached = self::cacheGet($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $endpoint = sprintf(
            'https://graph.facebook.com/%s/%s/posts',
            FB_GRAPH_VERSION,
            FB_PAGE_ID
        );

        $response = self::httpGet($endpoint, [
            'fields'       => 'id,message,story,created_time,permalink_url,full_picture',
            'limit'        => $limit,
            'access_token' => FB_PAGE_ACCESS_TOKEN,
        ]);

        if ($response === null || !isset($response['data']) || !is_array($response['data'])) {
            Logger::errors()->error('FacebookService.getPagePosts failed', [
                'response' => $response,
            ]);
            return [];
        }

        $posts = array_map([self::class, 'normalizePost'], $response['data']);
        self::cacheSet($cacheKey, $posts, FB_CACHE_TTL);

        return $posts;
    }

    private static function normalizePost(array $row): array
    {
        $createdAt = $row['created_time'] ?? null;
        $dateDisplay = '';
        if ($createdAt) {
            try {
                $dt = new \DateTimeImmutable($createdAt);
                $dateDisplay = $dt->format('d/m/Y');
            } catch (\Throwable) {
                $dateDisplay = '';
            }
        }

        return [
            'id'           => $row['id']            ?? '',
            'message'      => $row['message']       ?? ($row['story'] ?? ''),
            'created_at'   => $createdAt,
            'date_display' => $dateDisplay,
            'permalink'    => $row['permalink_url'] ?? '',
            'picture'      => $row['full_picture']  ?? null,
        ];
    }

    // ── HTTP ──────────────────────────────────────────────────────────────────

    private static function httpPost(string $url, array $params): ?array
    {
        $params['access_token'] = FB_PAGE_ACCESS_TOKEN;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($params),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_CONNECTTIMEOUT => 5,
        ]);
        $body = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($body === false || $code >= 400) {
            Logger::errors()->error('FacebookService HTTP POST error', [
                'url'  => $url,
                'code' => $code,
                'err'  => $err,
                'body' => is_string($body) ? mb_substr($body, 0, 500) : null,
            ]);
            return null;
        }
        return json_decode((string)$body, true) ?: null;
    }

    private static function httpGet(string $url, array $params): ?array
    {
        $full = $url . '?' . http_build_query($params);

        $ch = curl_init($full);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
        ]);
        $body = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($body === false || $code >= 400) {
            Logger::errors()->error('FacebookService HTTP GET error', [
                'url'  => $url,
                'code' => $code,
                'err'  => $err,
                'body' => is_string($body) ? mb_substr($body, 0, 500) : null,
            ]);
            return null;
        }
        return json_decode((string)$body, true) ?: null;
    }

    // ── Cache (table facebook_cache) ──────────────────────────────────────────

    private static function cacheGet(string $key): ?array
    {
        try {
            $stmt = Database::get()->prepare(
                "SELECT payload FROM facebook_cache
                 WHERE cache_key = ? AND expires_at > NOW() LIMIT 1"
            );
            $stmt->execute([$key]);
            $row = $stmt->fetch();
            if (!$row) {
                return null;
            }
            $decoded = json_decode($row['payload'], true);
            return is_array($decoded) ? $decoded : null;
        } catch (\Throwable $e) {
            Logger::errors()->error('FacebookService cacheGet error', ['err' => $e->getMessage()]);
            return null;
        }
    }

    private static function cacheSet(string $key, array $value, int $ttlSeconds): void
    {
        try {
            $payload   = json_encode($value, JSON_UNESCAPED_UNICODE);
            $expiresAt = date('Y-m-d H:i:s', time() + max(60, $ttlSeconds));

            Database::get()->prepare(
                "INSERT INTO facebook_cache (cache_key, payload, expires_at)
                 VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                   payload    = VALUES(payload),
                   fetched_at = CURRENT_TIMESTAMP,
                   expires_at = VALUES(expires_at)"
            )->execute([$key, $payload, $expiresAt]);
        } catch (\Throwable $e) {
            Logger::errors()->error('FacebookService cacheSet error', ['err' => $e->getMessage()]);
        }
    }
}
