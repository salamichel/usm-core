<?php
declare(strict_types=1);

namespace App\Services;

class FacebookService
{
    /**
     * Publie un message sur la page Facebook
     *
     * @param string $message Le texte de la publication
     * @param string $link L'URL vers l'article complet
     * @return string|null L'ID de la publication Facebook si succès, null sinon
     * @throws \Exception En cas d'erreur de configuration ou d'API
     */
    public static function publishPost(string $message, string $link): ?string
    {
        $pageId = FB_PAGE_ID;
        $accessToken = FB_ACCESS_TOKEN;

        if (empty($pageId) || empty($accessToken)) {
            throw new \Exception("Configuration Facebook manquante dans .env (FB_PAGE_ID, FB_ACCESS_TOKEN).");
        }

        $url = "https://graph.facebook.com/v19.0/{$pageId}/feed";

        $postData = [
            'message' => $message,
            'link'    => $link,
            'access_token' => $accessToken,
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        // En production, il est recommandé de laisser la vérification SSL active (CURLOPT_SSL_VERIFYPEER à true par défaut)
        
        $response = curl_exec($ch);
        
        if(curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \Exception("Erreur CURL lors de la publication Facebook : " . $error);
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $result = json_decode($response, true);

        if ($httpCode !== 200 || isset($result['error'])) {
            $errorMsg = $result['error']['message'] ?? 'Erreur inconnue';
            throw new \Exception("Erreur API Facebook : " . $errorMsg);
        }

        return $result['id'] ?? null;
    }
}
