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

        $htmlContent = $this->renderContactNotificationTemplate($contact);

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

        $config = \App\Models\SiteConfig::all();
        $htmlContent = $this->renderReplyTemplate($visitorName, $replyText, $config);

        return $this->sendEmail(
            $visitorEmail,
            $visitorName,
            $subject,
            $htmlContent
        );
    }

    private function escapeHtml(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }

    private function nl2brHtml(string $text): string
    {
        return nl2br($this->escapeHtml($text));
    }

    private function renderContactNotificationTemplate(array $contact): string
    {
        $template = <<<'HTML'
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #fef3c7; color: #111; }
        .container { max-width: 600px; margin: 20px auto; }
        .header { background: #94674d; color: white; padding: 20px; border: 4px solid #000; box-shadow: 6px 6px 0 #000; margin-bottom: 20px; }
        .header h1 { font-size: 28px; font-weight: 900; letter-spacing: -1px; }
        .content { background: white; padding: 24px; border: 4px solid #000; box-shadow: 6px 6px 0 #000; margin-bottom: 20px; }
        .message-box { background: #f3f4f6; padding: 16px; border-left: 4px solid #94674d; margin: 16px 0; line-height: 1.6; white-space: pre-wrap; word-wrap: break-word; }
        .field-label { font-weight: 900; text-transform: uppercase; font-size: 12px; color: #666; margin-top: 16px; margin-bottom: 4px; }
        .field-value { font-size: 16px; color: #111; }
        .sender-info { background: #fef3c7; padding: 12px; border: 2px solid #000; margin: 16px 0; }
        .cta-button { display: inline-block; background: #94674d; color: white; padding: 12px 24px; border: 3px solid #000; font-weight: 900; text-decoration: none; box-shadow: 4px 4px 0 #000; margin-top: 20px; }
        .footer { text-align: center; font-size: 12px; color: #666; margin-top: 20px; padding-top: 20px; border-top: 2px solid #000; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📬 NOUVEAU MESSAGE</h1>
        </div>

        <div class="content">
            <div class="sender-info">
                <div class="field-label">💬 Message de</div>
                <div class="field-value"><strong>{{SENDER_NAME}}</strong></div>
                <div class="field-label">📧 Email</div>
                <div class="field-value"><a href="mailto:{{SENDER_EMAIL}}" style="color: #94674d; text-decoration: none; font-weight: 900;">{{SENDER_EMAIL}}</a></div>
            </div>

            <div class="field-label">🏷️ Sujet</div>
            <div class="field-value" style="font-size: 20px; font-weight: 900; margin-bottom: 20px;">{{SUBJECT}}</div>

            <div class="field-label">💭 Message</div>
            <div class="message-box">{{MESSAGE}}</div>

            <a href="{{REPLY_URL}}" class="cta-button">👉 Lire et répondre</a>
        </div>

        <div class="footer">
            © USM Volley — Ne pas répondre à cet email, utilisez le lien ci-dessus
        </div>
    </div>
</body>
</html>
HTML;

        return strtr($template, [
            '{{SENDER_NAME}}' => $this->escapeHtml($contact['name']),
            '{{SENDER_EMAIL}}' => $this->escapeHtml($contact['email']),
            '{{SUBJECT}}' => $this->escapeHtml($contact['subject']),
            '{{MESSAGE}}' => $this->nl2brHtml($contact['message']),
            '{{REPLY_URL}}' => BASE_URL . '/admin/contacts/' . (int)$contact['id'],
        ]);
    }

    private function renderReplyTemplate(string $visitorName, string $replyText, array $config = []): string
    {
        $clubName = $config['club_name'] ?? 'USM VOLLEY';
        $address = $config['address'] ?? '';
        $phone = $config['phone'] ?? '';
        $email = $config['email'] ?? '';

        $signatureHtml = '<div class="signature">⚽ ' . $this->escapeHtml($clubName) . '</div>';

        $contactInfo = '';
        if ($address) {
            $contactInfo .= '<div class="contact-line">' . nl2br($this->escapeHtml($address)) . '</div>';
        }
        if ($phone) {
            $contactInfo .= '<div class="contact-line">📱 <a href="tel:' . str_replace(' ', '', $phone) . '" style="color: #94674d; text-decoration: none;">' . $this->escapeHtml($phone) . '</a></div>';
        }
        if ($email) {
            $contactInfo .= '<div class="contact-line">📧 <a href="mailto:' . $this->escapeHtml($email) . '" style="color: #94674d; text-decoration: none;">' . $this->escapeHtml($email) . '</a></div>';
        }

        $template = <<<'HTML'
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #fef3c7; color: #111; }
        .container { max-width: 600px; margin: 20px auto; }
        .header { background: #94674d; color: white; padding: 20px; border: 4px solid #000; box-shadow: 6px 6px 0 #000; margin-bottom: 20px; }
        .header h1 { font-size: 28px; font-weight: 900; letter-spacing: -1px; }
        .content { background: white; padding: 24px; border: 4px solid #000; box-shadow: 6px 6px 0 #000; margin-bottom: 20px; }
        .greeting { font-size: 18px; margin-bottom: 24px; }
        .greeting strong { font-weight: 900; }
        .message-box { background: #f3f4f6; padding: 20px; border-left: 4px solid #94674d; margin: 20px 0; line-height: 1.6; white-space: pre-wrap; word-wrap: break-word; }
        .signature { margin-top: 24px; font-weight: 900; color: #94674d; font-size: 18px; margin-bottom: 16px; }
        .signature-box { background: #fef3c7; padding: 16px; border: 2px solid #000; margin-top: 20px; }
        .contact-line { font-size: 13px; color: #555; margin: 6px 0; line-height: 1.4; }
        .footer { text-align: center; font-size: 12px; color: #666; margin-top: 20px; padding-top: 20px; border-top: 2px solid #000; }
        p { line-height: 1.6; margin-bottom: 16px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>✉️ RÉPONSE</h1>
        </div>

        <div class="content">
            <p class="greeting">Bonjour <strong>{{VISITOR_NAME}}</strong>,</p>

            <p>Merci de nous avoir contacté. Voici notre réponse à votre message :</p>

            <div class="message-box">{{REPLY_TEXT}}</div>

            <p>Si vous avez d'autres questions, n'hésitez pas à nous recontacter.</p>

            <div class="signature-box">
                {{SIGNATURE_HTML}}
                {{CONTACT_INFO}}
            </div>
        </div>

        <div class="footer">
            © {{CLUB_NAME}}
        </div>
    </div>
</body>
</html>
HTML;

        return strtr($template, [
            '{{VISITOR_NAME}}' => $this->escapeHtml($visitorName),
            '{{REPLY_TEXT}}' => $this->nl2brHtml($replyText),
            '{{SIGNATURE_HTML}}' => $signatureHtml,
            '{{CONTACT_INFO}}' => $contactInfo,
            '{{CLUB_NAME}}' => $this->escapeHtml($clubName),
        ]);
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
