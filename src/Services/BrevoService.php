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

    public function sendCaptainMessage(array $captain, array $messageData, string $teamName): bool
    {
        $captainEmail = $captain['data']['Mel'] ?? null;
        if (!$captainEmail) {
            Logger::errors()->error('Cannot send email to captain: no email address found', ['captain' => $captain]);
            return false;
        }

        $captainName = trim($captain['prenom'] . ' ' . $captain['nom']);
        $subject = '🏐 Message pour le capitaine (' . $teamName . ') : ' . $messageData['subject'];
        $htmlContent = $this->renderCaptainMessageTemplate($captain, $messageData, $teamName);

        return $this->sendEmail(
            $captainEmail,
            $captainName,
            $subject,
            $htmlContent
        );
    }

    public function sendPlayerSelectionNotification(array $player, array $event): bool
    {
        $playerEmail = $player['Mel'] ?? $player['mel'] ?? $player['data']['Mel'] ?? null;
        if (!$playerEmail) {
            Logger::errors()->error('Cannot send selection email to player: no email address found', ['player' => $player]);
            return false;
        }

        $playerName = trim(($player['Prénom'] ?? $player['prenom'] ?? '') . ' ' . ($player['Nom'] ?? $player['nom'] ?? ''));
        $eventTitle = $event['title'] ?? $event['titre'] ?? 'Match';
        $subject = '🏐 Convocation match : ' . $eventTitle;
        $htmlContent = $this->renderPlayerSelectionTemplate($player, $event);

        return $this->sendEmail(
            $playerEmail,
            $playerName,
            $subject,
            $htmlContent
        );
    }

    public function sendMatchCancellationNotification(array $player, array $event): bool
    {
        $playerEmail = $player['Mel'] ?? $player['mel'] ?? $player['data']['Mel'] ?? null;
        if (!$playerEmail) {
            Logger::errors()->error('Cannot send cancellation email to player: no email address found', ['player' => $player]);
            return false;
        }

        $playerName = trim(($player['Prénom'] ?? $player['prenom'] ?? '') . ' ' . ($player['Nom'] ?? $player['nom'] ?? ''));
        $eventTitle = $event['title'] ?? $event['titre'] ?? 'Match';
        $subject = '❌ Match annulé : ' . $eventTitle;
        $htmlContent = $this->renderMatchCancellationTemplate($player, $event);

        return $this->sendEmail(
            $playerEmail,
            $playerName,
            $subject,
            $htmlContent
        );
    }

    private function renderPlayerSelectionTemplate(array $player, array $event): string
    {
        $playerName = trim(($player['Prénom'] ?? $player['prenom'] ?? '') . ' ' . ($player['Nom'] ?? $player['nom'] ?? ''));
        $eventTitle = $event['title'] ?? $event['titre'] ?? 'Match';
        $eventDate = $event['date_display'] ?? $event['Date'] ?? $event['date'] ?? '';
        $eventTime = $event['time_display'] ?? '';
        if ($eventTime) {
            $eventDate .= ' à ' . $eventTime;
        }
        $eventLocation = $event['location'] ?? $event['lieu'] ?? '';
        $eventComment = $event['comment'] ?? $event['commentaire'] ?? '';

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
        .match-info { background: #f3f4f6; padding: 16px; border-left: 4px solid #94674d; margin: 16px 0; }
        .field-label { font-weight: 900; text-transform: uppercase; font-size: 12px; color: #666; margin-top: 12px; margin-bottom: 2px; }
        .field-value { font-size: 16px; color: #111; }
        .cta-button { display: inline-block; background: #94674d; color: white; padding: 12px 24px; border: 3px solid #000; font-weight: 900; text-decoration: none; box-shadow: 4px 4px 0 #000; margin-top: 20px; }
        .footer { text-align: center; font-size: 12px; color: #666; margin-top: 20px; padding-top: 20px; border-top: 2px solid #000; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🏐 CONVOCATION MATCH</h1>
        </div>

        <div class="content">
            <p>Bonjour <strong>{{PLAYER_NAME}}</strong>,</p>
            <p style="margin-top: 8px;">Vous avez été sélectionné(e) pour participer au match suivant :</p>

            <div class="match-info">
                <div class="field-label">🏆 Rencontre</div>
                <div class="field-value" style="font-size: 18px; font-weight: 900;">{{EVENT_TITLE}}</div>

                <div class="field-label">📅 Date et Heure</div>
                <div class="field-value"><strong>{{EVENT_DATE}}</strong></div>

                <div class="field-label">📍 Lieu</div>
                <div class="field-value">{{EVENT_LOCATION}}</div>
                
                {{COMMENT_HTML}}
            </div>

            <p style="margin-top: 16px;">Merci de vous connecter à votre espace adhérent pour confirmer vos informations ou consulter l'agenda complet.</p>
            
            <a href="{{DASHBOARD_URL}}" class="cta-button">👉 Accéder à mon espace</a>
        </div>

        <div class="footer">
            © USM Volley — Ne pas répondre directement à cet email.
        </div>
    </div>
</body>
</html>
HTML;

        $commentHtml = '';
        if ($eventComment) {
            $commentHtml = '<div class="field-label">💬 Commentaire</div><div class="field-value">' . $this->nl2brHtml($eventComment) . '</div>';
        }

        return strtr($template, [
            '{{PLAYER_NAME}}' => $this->escapeHtml($playerName),
            '{{EVENT_TITLE}}' => $this->escapeHtml($eventTitle),
            '{{EVENT_DATE}}' => $this->escapeHtml($eventDate),
            '{{EVENT_LOCATION}}' => $this->escapeHtml($eventLocation),
            '{{COMMENT_HTML}}' => $commentHtml,
            '{{DASHBOARD_URL}}' => BASE_URL . '/member/dashboard',
        ]);
    }

    private function renderMatchCancellationTemplate(array $player, array $event): string
    {
        $playerName = trim(($player['Prénom'] ?? $player['prenom'] ?? '') . ' ' . ($player['Nom'] ?? $player['nom'] ?? ''));
        $eventTitle = $event['title'] ?? $event['titre'] ?? 'Match';
        $eventDate = $event['date_display'] ?? $event['Date'] ?? $event['date'] ?? '';
        $eventTime = $event['time_display'] ?? '';
        if ($eventTime) {
            $eventDate .= ' à ' . $eventTime;
        }
        $eventLocation = $event['location'] ?? $event['lieu'] ?? '';

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
        .header { background: #b91c1c; color: white; padding: 20px; border: 4px solid #000; box-shadow: 6px 6px 0 #000; margin-bottom: 20px; }
        .header h1 { font-size: 28px; font-weight: 900; letter-spacing: -1px; }
        .content { background: white; padding: 24px; border: 4px solid #000; box-shadow: 6px 6px 0 #000; margin-bottom: 20px; }
        .match-info { background: #fef2f2; padding: 16px; border-left: 4px solid #b91c1c; margin: 16px 0; }
        .field-label { font-weight: 900; text-transform: uppercase; font-size: 12px; color: #666; margin-top: 12px; margin-bottom: 2px; }
        .field-value { font-size: 16px; color: #111; }
        .cta-button { display: inline-block; background: #000; color: white; padding: 12px 24px; border: 3px solid #000; font-weight: 900; text-decoration: none; box-shadow: 4px 4px 0 #fff; margin-top: 20px; }
        .footer { text-align: center; font-size: 12px; color: #666; margin-top: 20px; padding-top: 20px; border-top: 2px solid #000; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>❌ MATCH ANNULÉ</h1>
        </div>

        <div class="content">
            <p>Bonjour <strong>{{PLAYER_NAME}}</strong>,</p>
            <p style="margin-top: 8px; color: #b91c1c; font-weight: bold;">Le match suivant pour lequel vous étiez sélectionné(e) a été annulé :</p>

            <div class="match-info">
                <div class="field-label">🏆 Rencontre</div>
                <div class="field-value" style="font-size: 18px; font-weight: 900;">{{EVENT_TITLE}}</div>

                <div class="field-label">📅 Date et Heure initiales</div>
                <div class="field-value"><strong>{{EVENT_DATE}}</strong></div>

                <div class="field-label">📍 Lieu</div>
                <div class="field-value">{{EVENT_LOCATION}}</div>
            </div>

            <p style="margin-top: 16px;">Veuillez prendre en compte cette annulation dans votre agenda. Vous pouvez consulter les autres événements à venir sur votre espace adhérent.</p>
            
            <a href="{{DASHBOARD_URL}}" class="cta-button" style="background: #94674d; color: white; box-shadow: 4px 4px 0 #000;">👉 Accéder à mon espace</a>
        </div>

        <div class="footer">
            © USM Volley — Ne pas répondre directement à cet email.
        </div>
    </div>
</body>
</html>
HTML;

        return strtr($template, [
            '{{PLAYER_NAME}}' => $this->escapeHtml($playerName),
            '{{EVENT_TITLE}}' => $this->escapeHtml($eventTitle),
            '{{EVENT_DATE}}' => $this->escapeHtml($eventDate),
            '{{EVENT_LOCATION}}' => $this->escapeHtml($eventLocation),
            '{{DASHBOARD_URL}}' => BASE_URL . '/member/dashboard',
        ]);
    }

    private function renderCaptainMessageTemplate(array $captain, array $messageData, string $teamName): string
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
        .footer { text-align: center; font-size: 12px; color: #666; margin-top: 20px; padding-top: 20px; border-top: 2px solid #000; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🏐 MESSAGE CAPITAINE</h1>
        </div>

        <div class="content">
            <p>Bonjour <strong>{{CAPTAIN_NAME}}</strong>,</p>
            <p style="margin-top: 8px;">Vous avez reçu un nouveau message depuis le site web en tant que capitaine de l'équipe <strong>{{TEAM_NAME}}</strong>.</p>

            <div class="sender-info">
                <div class="field-label">💬 Message de</div>
                <div class="field-value"><strong>{{SENDER_NAME}}</strong></div>
                <div class="field-label">📧 Email de contact</div>
                <div class="field-value"><a href="mailto:{{SENDER_EMAIL}}" style="color: #94674d; text-decoration: none; font-weight: 900;">{{SENDER_EMAIL}}</a></div>
            </div>

            <div class="field-label">🏷️ Sujet</div>
            <div class="field-value" style="font-size: 18px; font-weight: 900; margin-bottom: 12px;">{{SUBJECT}}</div>

            <div class="field-label">💭 Message</div>
            <div class="message-box">{{MESSAGE}}</div>

            <p style="font-size: 14px; margin-top: 20px; color: #555;">💡 Vous pouvez répondre directement à ce message en écrivant à l'adresse de contact ci-dessus.</p>
        </div>

        <div class="footer">
            © USM Volley — Votre adresse email n'a pas été divulguée à l'expéditeur.
        </div>
    </div>
</body>
</html>
HTML;

        return strtr($template, [
            '{{CAPTAIN_NAME}}' => $this->escapeHtml($captain['prenom']),
            '{{TEAM_NAME}}' => $this->escapeHtml($teamName),
            '{{SENDER_NAME}}' => $this->escapeHtml($messageData['name']),
            '{{SENDER_EMAIL}}' => $this->escapeHtml($messageData['email']),
            '{{SUBJECT}}' => $this->escapeHtml($messageData['subject']),
            '{{MESSAGE}}' => $this->nl2brHtml($messageData['message']),
        ]);
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
                <div class="field-label">📱 Téléphone</div>
                <div class="field-value"><a href="tel:{{SENDER_PHONE}}" style="color: #94674d; text-decoration: none; font-weight: 900;">{{SENDER_PHONE}}</a></div>
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
            '{{SENDER_PHONE}}' => $this->escapeHtml($contact['phone'] ?? ''),
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

        $signatureHtml = '<div class="signature">🏐 ' . $this->escapeHtml($clubName) . '</div>';

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
                'method'        => 'POST',
                'header'        => [
                    'Content-Type: application/json',
                    'api-key: ' . $this->apiKey,
                ],
                'content'       => $json,
                'timeout'       => 30,
                'ignore_errors' => true, // Permet de récupérer le corps de la réponse en cas d'erreur HTTP (4xx, 5xx)
            ],
        ]);

        try {
            $response = @file_get_contents(self::API_URL, false, $context);
            
            // Récupérer le code de statut HTTP
            $statusCode = 0;
            if (isset($http_response_header) && is_array($http_response_header)) {
                foreach ($http_response_header as $header) {
                    if (preg_match('/^HTTP\/\d\.\d\s+(\d+)/i', $header, $matches)) {
                        $statusCode = (int)$matches[1];
                        break;
                    }
                }
            }

            if ($response === false) {
                Logger::errors()->error('Brevo API network request failed (connection error)', ['payload' => $payload]);
                return false;
            }

            $data = json_decode($response, true);

            if ($statusCode < 200 || $statusCode >= 300) {
                Logger::errors()->error('Brevo API returned error status ' . $statusCode, [
                    'status_code' => $statusCode,
                    'response'    => $data ?: $response,
                    'payload'     => $payload
                ]);
                return false;
            }

            if (!isset($data['messageId'])) {
                Logger::errors()->error('Brevo API invalid response (missing messageId)', ['response' => $response]);
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

