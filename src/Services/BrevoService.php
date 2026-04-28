<?php
declare(strict_types=1);

namespace App\Services;

class BrevoService
{
    private const API_URL = 'https://api.brevo.com/v3/smtp/email';
    private string $apiKey;

    public function __construct()
    {
        $this->apiKey = BREVO_API_KEY;
        if (!$this->apiKey) {
            throw new \Exception('Brevo API key is not configured');
        }
    }

    public function sendEmail(
        string $toEmail,
        string $toName,
        string $subject,
        string $htmlContent,
        string $textContent = null
    ): bool {
        if (!$textContent) {
            $textContent = strip_tags($htmlContent);
        }

        $payload = [
            'sender' => [
                'email' => BREVO_FROM_EMAIL,
                'name'  => BREVO_FROM_NAME,
            ],
            'to' => [
                [
                    'email' => $toEmail,
                    'name'  => $toName,
                ],
            ],
            'subject'      => $subject,
            'htmlContent'  => $htmlContent,
            'textContent'  => $textContent,
        ];

        return $this->makeRequest($payload);
    }

    public function sendContactNotification(array $contact): bool
    {
        $adminEmail = \App\Models\SiteConfig::get('email') ?: ADMIN_EMAIL;
        $subject = 'Nouveau message de contact : ' . $contact['subject'];

        $htmlContent = sprintf(
            '<h2>Nouveau message de contact</h2>' .
            '<p><strong>De :</strong> %s (%s)</p>' .
            '<p><strong>Sujet :</strong> %s</p>' .
            '<p><strong>Message :</strong></p>' .
            '<p>%s</p>' .
            '<p><a href="%s/admin/contacts/%d">Voir et répondre</a></p>',
            htmlspecialchars($contact['name']),
            htmlspecialchars($contact['email']),
            htmlspecialchars($contact['subject']),
            nl2br(htmlspecialchars($contact['message'])),
            BASE_URL,
            $contact['id']
        );

        return $this->sendEmail(
            $adminEmail,
            'Admin',
            $subject,
            $htmlContent
        );
    }

    public function sendReplyToVisitor(string $visitorEmail, string $visitorName, string $replyText, ?string $fromEmail = null): bool
    {
        $subject = 'Réponse à votre message';

        $htmlContent = sprintf(
            '<p>Bonjour %s,</p>' .
            '<p>Merci de nous avoir contacté. Voici notre réponse :</p>' .
            '<p>%s</p>' .
            '<p>Cordialement,<br>USM Volley</p>',
            htmlspecialchars($visitorName),
            nl2br(htmlspecialchars($replyText))
        );

        return $this->sendEmail(
            $visitorEmail,
            $visitorName,
            $subject,
            $htmlContent
        );
    }

    private function makeRequest(array $payload): bool
    {
        $json = json_encode($payload);

        $context = stream_context_create([
            'http' => [
                'method'  => 'POST',
                'header'  => [
                    'Content-Type: application/json',
                    'api-key: ' . $this->apiKey,
                ],
                'content' => $json,
                'timeout' => 30,
            ],
        ]);

        try {
            $response = @file_get_contents(self::API_URL, false, $context);
            if ($response === false) {
                Logger::errors()->error('Brevo API request failed', ['payload' => $payload]);
                return false;
            }

            $data = json_decode($response, true);
            if (!isset($data['messageId'])) {
                Logger::errors()->error('Brevo API invalid response', ['response' => $response]);
                return false;
            }

            Logger::app()->info('Email sent via Brevo', ['message_id' => $data['messageId']]);
            return true;
        } catch (\Exception $e) {
            Logger::errors()->error('Brevo API exception', ['error' => $e->getMessage()]);
            return false;
        }
    }
}
