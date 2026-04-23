<?php
declare(strict_types=1);

namespace App\Services;

class AgendaService
{
    private const API_URL   = 'https://unionsallesmiosvolley.free.nf/api_agenda.php';
    private const CACHE_TTL = 900; // 15 minutes

    /**
     * Upcoming matches (type_manifestation=Match), limited to $limit events.
     */
    public static function getUpcomingMatches(int $limit = 5): array
    {
        return array_slice(
            self::getEvents(['type_manifestation' => 'Match']),
            0,
            $limit
        );
    }

    /**
     * Upcoming trainings (type_manifestation=Entraînement), limited to $limit events.
     */
    public static function getUpcomingTrainings(int $limit = 5): array
    {
        return array_slice(
            self::getEvents(['type_manifestation' => 'Entraînement']),
            0,
            $limit
        );
    }

    /**
     * Fetch events from the API with optional query filters.
     * Results are cached to a file for CACHE_TTL seconds.
     */
    public static function getEvents(array $params = []): array
    {
        $params['endpoint'] = 'events';
        $cacheKey = md5(serialize($params));
        $cacheDir = ROOT . '/cache/agenda';
        $cacheFile = $cacheDir . '/' . $cacheKey . '.json';

        // Return cached result if fresh
        if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < self::CACHE_TTL) {
            $data = json_decode(file_get_contents($cacheFile), true);
            if (is_array($data)) {
                return $data;
            }
        }

        $data = self::fetch($params);

        // Write cache (silently ignore write errors on read-only hosting)
        if (is_array($data) && is_dir($cacheDir) && is_writable($cacheDir)) {
            file_put_contents($cacheFile, json_encode($data), LOCK_EX);
        }

        return is_array($data) ? $data : [];
    }

    private static function fetch(array $params): array
    {
        $url = self::API_URL . '?' . http_build_query($params);

        if (function_exists('curl_init')) {
            return self::fetchWithCurl($url);
        }

        // Fallback: file_get_contents
        $ctx = stream_context_create([
            'http' => [
                'timeout'        => 5,
                'ignore_errors'  => true,
                'user_agent'     => 'USM-Volley/1.0',
            ],
        ]);
        $raw = @file_get_contents($url, false, $ctx);
        if ($raw === false) return [];
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }

    private static function fetchWithCurl(string $url): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 5,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_USERAGENT      => 'USM-Volley/1.0',
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
        ]);
        $raw  = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false || $code !== 200) return [];
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }
}
