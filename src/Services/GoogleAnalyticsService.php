<?php
declare(strict_types=1);

namespace App\Services;

use App\Services\Logger;

class GoogleAnalyticsService
{
    private static function base64UrlEncode(string $data): string
    {
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($data));
    }

    /**
     * Échange la clé JSON du compte de service contre un jeton d'accès OAuth2.
     */
    public static function getAccessToken(array $serviceAccount): ?string
    {
        try {
            $privateKey = $serviceAccount['private_key'] ?? null;
            $clientEmail = $serviceAccount['client_email'] ?? null;
            $tokenUri = $serviceAccount['token_uri'] ?? 'https://oauth2.googleapis.com/token';

            if (!$privateKey || !$clientEmail) {
                Logger::errors()->error('GA4: Clé privée ou client_email manquant dans la config du compte de service.');
                return null;
            }

            // JWT Header
            $header = self::base64UrlEncode((string)json_encode([
                'alg' => 'RS256',
                'typ' => 'JWT'
            ]));

            // JWT Payload (expire dans 1 heure)
            $now = time();
            $payload = self::base64UrlEncode((string)json_encode([
                'iss' => $clientEmail,
                'scope' => 'https://www.googleapis.com/auth/analytics.readonly',
                'aud' => $tokenUri,
                'exp' => $now + 3600,
                'iat' => $now
            ]));

            // Signature du JWT
            $signatureInput = $header . '.' . $payload;
            $signature = '';
            
            // Initialisation de la clé privée OpenSSL
            $pkeyId = openssl_pkey_get_private($privateKey);
            if (!$pkeyId) {
                Logger::errors()->error('GA4: Clé privée invalide (impossible de la lire via OpenSSL).');
                return null;
            }

            if (!openssl_sign($signatureInput, $signature, $pkeyId, OPENSSL_ALGO_SHA256)) {
                Logger::errors()->error('GA4: Échec de signature openssl_sign.');
                return null;
            }
            
            // Libération de la ressource clé sous PHP < 8.0, sans effet sous PHP 8.0+
            if (PHP_VERSION_ID < 80000) {
                openssl_free_key($pkeyId);
            }

            $signatureEncoded = self::base64UrlEncode($signature);
            $jwt = $signatureInput . '.' . $signatureEncoded;

            // Envoi de la requête POST pour obtenir le Token d'accès
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $tokenUri);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion'  => $jwt
            ]));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($httpCode !== 200) {
                Logger::errors()->error("GA4: Échec d'échange de token ({$httpCode}). Erreur curl: {$curlError}. Réponse: {$response}");
                return null;
            }

            $data = json_decode((string)$response, true);
            return $data['access_token'] ?? null;

        } catch (\Throwable $e) {
            Logger::errors()->error('GA4: Exception lors de la génération du token d\'accès : ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Récupère un rapport groupé (batch) depuis GA4.
     */
    public static function fetchBatchReports(string $propertyId, array $serviceAccount, array $requestsPayload): ?array
    {
        $accessToken = self::getAccessToken($serviceAccount);
        if (!$accessToken) {
            return null;
        }

        try {
            $url = "https://analyticsdata.googleapis.com/v1beta/properties/{$propertyId}:batchRunReports";
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json'
            ]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, (string)json_encode(['requests' => $requestsPayload]));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 20);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($httpCode !== 200) {
                Logger::errors()->error("GA4: Erreur batchRunReports ({$httpCode}). Erreur curl: {$curlError}. Réponse: {$response}");
                return null;
            }

            return json_decode((string)$response, true);
        } catch (\Throwable $e) {
            Logger::errors()->error('GA4: Exception lors du batchRunReports : ' . $e->getMessage());
            return null;
        }
    }
}
