<?php
namespace App\Services;

class AIContentService
{
    private const API_URL = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent';
    private const TIMEOUT = 10;

    private static function getApiKey(): ?string
    {
        return $_ENV['GEMINI_API_KEY'] ?? null;
    }

    private static function callApi(string $userMessage): ?string
    {
        $apiKey = self::getApiKey();
        if (!$apiKey) {
            error_log('AIContentService: GEMINI_API_KEY not set');
            return null;
        }

        $payload = json_encode([
            'contents' => [
                [
                    'parts' => [
                        ['text' => $userMessage],
                    ],
                ],
            ],
        ]);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => self::API_URL . '?key=' . urlencode($apiKey),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => self::TIMEOUT,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            error_log("AIContentService: cURL error - {$error}");
            return null;
        }

        if ($httpCode !== 200) {
            error_log("AIContentService: HTTP {$httpCode} - {$response}");
            return null;
        }

        $data = json_decode($response, true);
        if (!isset($data['candidates'][0]['content']['parts'][0]['text'])) {
            error_log("AIContentService: Unexpected API response structure");
            return null;
        }

        return trim($data['candidates'][0]['content']['parts'][0]['text']);
    }

    /**
     * Génère un excerpt court et engageant à partir du contenu
     */
    public static function generateExcerpt(string $content, int $maxLength = 200): ?string
    {
        if (empty($content)) {
            return null;
        }

        $plaintext = strip_tags($content);
        $plaintext = str_replace(['&nbsp;', '&lt;', '&gt;', '&amp;'], ['', '<', '>', '&'], $plaintext);
        $plaintext = trim(substr($plaintext, 0, 1000));

        $message = "Génère un excerpt court et engageant pour un article en français. "
            . "Maximum {$maxLength} caractères. Le voici : {$plaintext}";

        $result = self::callApi($message);
        if (!$result) {
            return null;
        }

        if (strlen($result) > $maxLength) {
            $result = substr($result, 0, $maxLength - 3) . '...';
        }

        return $result;
    }

    /**
     * Génère une description SEO optimisée
     */
    public static function generateMetaDescription(string $title, string $content, int $maxLength = 160): ?string
    {
        if (empty($content)) {
            return null;
        }

        $plaintext = strip_tags($content);
        $plaintext = str_replace(['&nbsp;', '&lt;', '&gt;', '&amp;'], ['', '<', '>', '&'], $plaintext);
        $plaintext = trim(substr($plaintext, 0, 1000));

        $message = "Génère une description SEO optimisée pour Google (max {$maxLength} caractères) "
            . "pour un article avec le titre : \"{$title}\". "
            . "Voici le contenu : {$plaintext}";

        $result = self::callApi($message);
        if (!$result) {
            return null;
        }

        if (strlen($result) > $maxLength) {
            $result = substr($result, 0, $maxLength - 3) . '...';
        }

        return $result;
    }

    /**
     * Propose des améliorations pour le contenu (engagement + clarté + style)
     */
    public static function improveContent(string $content): ?string
    {
        if (empty($content)) {
            return null;
        }

        $plaintext = strip_tags($content);
        $plaintext = str_replace(['&nbsp;', '&lt;', '&gt;', '&amp;'], ['', '<', '>', '&'], $plaintext);
        $plaintext = trim(substr($plaintext, 0, 2000));

        $message = "Tu es un éditeur français expert en contenu web. "
            . "Améliore ce texte pour le rendre plus engageant, clair et percutant. "
            . "Conserve l'essence du message. "
            . "Réponds uniquement avec le texte amélioré, sans explications. "
            . "Voici le texte : {$plaintext}";

        return self::callApi($message);
    }
}

