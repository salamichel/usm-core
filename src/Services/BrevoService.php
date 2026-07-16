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
        string $textContent = null,
        array $cc = null
    ): bool {
        if (defined('BREVO_REDIRECT_EMAIL') && BREVO_REDIRECT_EMAIL !== '') {
            $subject = '[DEV-REDIRECT to ' . $toEmail . '] ' . $subject;
            $toEmail = BREVO_REDIRECT_EMAIL;
            $toName = 'Destinataire Dev';
            if (!empty($cc)) {
                $originalCcs = [];
                foreach ($cc as $c) {
                    $originalCcs[] = $c['email'];
                }
                $subject .= ' [DEV-REDIRECT CC: ' . implode(', ', $originalCcs) . ']';
                $cc = [];
            }
        }

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

        if (!empty($cc)) {
            $payload['cc'] = $cc;
        }

        return $this->makeRequest($payload);
    }

    public function sendContactNotification(array $contact): bool
    {
        $adminEmail = \App\Models\SiteConfig::get('email') ?: ADMIN_EMAIL;
        $subject = 'Nouveau message de contact : ' . $contact['subject'];

        try {
            $twig = \App\Core\View::getInstance();
            $htmlContent = $twig->render('emails/contact_notification.twig', [
                'SENDER_NAME' => $this->escapeHtml($contact['name']),
                'SENDER_EMAIL' => $this->escapeHtml($contact['email']),
                'SENDER_PHONE' => $this->escapeHtml($contact['phone'] ?? ''),
                'SUBJECT' => $this->escapeHtml($contact['subject']),
                'MESSAGE' => $this->nl2brHtml($contact['message']),
                'REPLY_URL' => BASE_URL . '/admin/contacts/' . (int)$contact['id'],
            ]);
        } catch (\Throwable $e) {
            Logger::errors()->error('Failed to render contact notification email template via Twig', ['error' => $e->getMessage()]);
            return false;
        }

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

        try {
            $twig = \App\Core\View::getInstance();
            $htmlContent = $twig->render('emails/visitor_reply.twig', [
                'VISITOR_NAME' => $this->escapeHtml($visitorName),
                'REPLY_TEXT' => $this->nl2brHtml($replyText),
                'SIGNATURE_HTML' => $signatureHtml,
                'CONTACT_INFO' => $contactInfo,
                'CLUB_NAME' => $this->escapeHtml($clubName),
            ]);
        } catch (\Throwable $e) {
            Logger::errors()->error('Failed to render reply email template via Twig', ['error' => $e->getMessage()]);
            return false;
        }

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

        try {
            $twig = \App\Core\View::getInstance();
            $htmlContent = $twig->render('emails/captain_message.twig', [
                'CAPTAIN_NAME' => $this->escapeHtml($captain['prenom']),
                'TEAM_NAME' => $this->escapeHtml($teamName),
                'SENDER_NAME' => $this->escapeHtml($messageData['name']),
                'SENDER_EMAIL' => $this->escapeHtml($messageData['email']),
                'SUBJECT' => $this->escapeHtml($messageData['subject']),
                'MESSAGE' => $this->nl2brHtml($messageData['message']),
            ]);
        } catch (\Throwable $e) {
            Logger::errors()->error('Failed to render captain message email template via Twig', ['error' => $e->getMessage()]);
            return false;
        }

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
        $eventTitle = $event['titre'] ?? 'Match';
        $subject = '🏐 Convocation match : ' . $eventTitle;

        $eventDate = $event['date_display'] ?? $event['Date'] ?? $event['date'] ?? '';
        $eventTime = $event['time_display'] ?? '';
        if ($eventTime) {
            $eventDate .= ' à ' . $eventTime;
        }
        $eventLocation = $event['lieu'] ?? '';
        $eventComment = $event['commentaire'] ?? '';

        $commentHtml = '';
        if ($eventComment) {
            $commentHtml = '<div class="field-label">💬 Commentaire</div><div class="field-value">' . $this->nl2brHtml($eventComment) . '</div>';
        }

        try {
            $twig = \App\Core\View::getInstance();
            $htmlContent = $twig->render('emails/selection.twig', [
                'PLAYER_NAME' => $this->escapeHtml($playerName),
                'EVENT_TITLE' => $this->escapeHtml($eventTitle),
                'EVENT_DATE' => $this->escapeHtml($eventDate),
                'EVENT_LOCATION' => $this->escapeHtml($eventLocation),
                'COMMENT_HTML' => $commentHtml,
                'DASHBOARD_URL' => BASE_URL . '/member/dashboard',
            ]);
        } catch (\Throwable $e) {
            Logger::errors()->error('Failed to render selection email template via Twig', ['error' => $e->getMessage()]);
            return false;
        }

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
        $eventTitle = $event['titre'] ?? 'Match';

        $type = $event['type'] ?? '';
        if (!$type && !empty($event['ManifestationTypée'])) {
            $parts = explode(' - ', $event['ManifestationTypée'], 3);
            $type = $parts[1] ?? '';
        }
        $isMatch = (mb_strtolower($type) === 'match');

        if ($isMatch) {
            $subject = '❌ Match annulé : ' . $eventTitle;
            $eventTypeUpper = 'MATCH';
            $cancellationText = 'Le match suivant pour lequel vous étiez sélectionné(e) a été annulé :';
        } else {
            $typeLabel = $type ?: 'Événement';
            $subject = '❌ ' . $typeLabel . ' annulé : ' . $eventTitle;
            $eventTypeUpper = mb_strtoupper($typeLabel);

            $firstChar = mb_strtolower(mb_substr($typeLabel, 0, 1));
            $isVowel = in_array($firstChar, ['a', 'e', 'i', 'o', 'u', 'y', 'é', 'è', 'à', 'ù']);
            $determinant = $isVowel ? "L'" : "Le ";
            if (mb_strtolower($typeLabel) === 'réunion') {
                $determinant = "La ";
            }

            $cancellationText = $determinant . mb_strtolower($typeLabel) . ' suivant pour lequel vous étiez présent(e) a été annulé :';
        }

        $eventDate = $event['date_display'] ?? $event['Date'] ?? $event['date'] ?? '';
        $eventTime = $event['time_display'] ?? '';
        if ($eventTime) {
            $eventDate .= ' à ' . $eventTime;
        }
        $eventLocation = $event['location'] ?? $event['lieu'] ?? '';

        try {
            $twig = \App\Core\View::getInstance();
            $htmlContent = $twig->render('emails/match_cancellation.twig', [
                'PLAYER_NAME' => $this->escapeHtml($playerName),
                'EVENT_TITLE' => $this->escapeHtml($eventTitle),
                'EVENT_DATE' => $this->escapeHtml($eventDate),
                'EVENT_LOCATION' => $this->escapeHtml($eventLocation),
                'DASHBOARD_URL' => BASE_URL . '/member/dashboard',
                'EVENT_TYPE_UPPER' => $eventTypeUpper,
                'CANCELLATION_TEXT' => $cancellationText,
            ]);
        } catch (\Throwable $e) {
            Logger::errors()->error('Failed to render match cancellation email template via Twig', ['error' => $e->getMessage()]);
            return false;
        }

        return $this->sendEmail(
            $playerEmail,
            $playerName,
            $subject,
            $htmlContent
        );
    }

    public function sendMatchReminderNotification(array $player, array $event, string $teamName): bool
    {
        $playerEmail = $player['Mel'] ?? $player['mel'] ?? $player['data']['Mel'] ?? null;
        if (!$playerEmail) {
            Logger::errors()->error('Cannot send reminder email to player: no email address found', ['player' => $player]);
            return false;
        }

        $playerName = trim(($player['Prénom'] ?? $player['prenom'] ?? '') . ' ' . ($player['Nom'] ?? $player['nom'] ?? ''));
        $eventTitle = $event['title'] ?? $event['titre'] ?? 'Match';
        $subject = '⏰ Rappel : réponse attendue - ' . $eventTitle;

        $eventDate = $event['date_display'] ?? $event['Date'] ?? $event['date'] ?? '';
        $eventTime = $event['time_display'] ?? '';
        if ($eventTime) {
            $eventDate .= ' à ' . $eventTime;
        }
        $eventLocation = $event['location'] ?? $event['lieu'] ?? '';

        $playerId = (int)($player['id_joueur'] ?? 0);
        $eventId = (int)($event['id'] ?? $event['id_manifestation'] ?? 0);

        $buttonsHtml = '';
        if ($playerId && $eventId) {
            $isMatch = $event['is_match'] ?? false;
            if ($isMatch) {
                $options = [
                    'Disponible' => ['label' => 'Disponible', 'color' => '#10b981'],
                    'Disponible si nécessaire' => ['label' => 'Si besoin', 'color' => '#f59e0b'],
                    'Indisponible' => ['label' => 'Indisponible', 'color' => '#ef4444']
                ];
            } else {
                $options = [
                    'Présent(e)' => ['label' => 'Présent(e)', 'color' => '#10b981'],
                    'Absent(e)' => ['label' => 'Absent(e)', 'color' => '#ef4444']
                ];
            }

            $buttonsHtml .= '<div style="margin-top: 24px; text-align: center;">';
            foreach ($options as $status => $opt) {
                $token = \App\Models\Participation::generateEmailToken($playerId, $eventId, $status);
                $url = BASE_URL . '/public/participation/update?' . http_build_query([
                    'player_id' => $playerId,
                    'event_id'  => $eventId,
                    'status'    => $status,
                    'token'     => $token
                ]);
                $buttonsHtml .= '<a href="' . htmlspecialchars($url) . '" style="display: inline-block; background-color: ' . $opt['color'] . '; color: #ffffff; padding: 12px 20px; border-radius: 10px; font-weight: bold; text-decoration: none; font-size: 14px; margin: 6px; border: 1px solid rgba(0,0,0,0.05); box-shadow: 0 2px 3px rgba(0,0,0,0.1);">' . htmlspecialchars($opt['label']) . '</a>';
            }
            $buttonsHtml .= '</div>';
        }

        try {
            $twig = \App\Core\View::getInstance();
            $htmlContent = $twig->render('emails/match_reminder.twig', [
                'PLAYER_NAME' => $this->escapeHtml($playerName),
                'TEAM_NAME' => $this->escapeHtml($teamName),
                'EVENT_TITLE' => $this->escapeHtml($eventTitle),
                'EVENT_DATE' => $this->escapeHtml($eventDate),
                'EVENT_LOCATION' => $this->escapeHtml($eventLocation),
                'BUTTONS_HTML' => $buttonsHtml,
                'DASHBOARD_URL' => BASE_URL . '/member/dashboard?event_id=' . $eventId,
            ]);
        } catch (\Throwable $e) {
            Logger::errors()->error('Failed to render match reminder email template via Twig', ['error' => $e->getMessage()]);
            return false;
        }

        return $this->sendEmail(
            $playerEmail,
            $playerName,
            $subject,
            $htmlContent
        );
    }

    public function sendPlayerDeselectionNotification(array $player, array $event): bool
    {
        $playerEmail = $player['Mel'] ?? $player['mel'] ?? $player['data']['Mel'] ?? null;
        if (!$playerEmail) {
            Logger::errors()->error('Cannot send deselection email to player: no email address found', ['player' => $player]);
            return false;
        }

        $playerName = trim(($player['Prénom'] ?? $player['prenom'] ?? '') . ' ' . ($player['Nom'] ?? $player['nom'] ?? ''));
        $eventTitle = $event['titre'] ?? 'Match';
        $subject = '❌ Annulation convocation match : ' . $eventTitle;

        $eventDate = $event['date_display'] ?? $event['Date'] ?? $event['date'] ?? '';
        $eventTime = $event['time_display'] ?? '';
        if ($eventTime) {
            $eventDate .= ' à ' . $eventTime;
        }
        $eventLocation = $event['lieu'] ?? '';

        try {
            $twig = \App\Core\View::getInstance();
            $htmlContent = $twig->render('emails/deselection.twig', [
                'playerName'    => $playerName,
                'eventTitle'    => $eventTitle,
                'eventDate'     => $eventDate,
                'eventLocation' => $eventLocation,
                'dashboardUrl'  => BASE_URL . '/member/dashboard',
            ]);
        } catch (\Throwable $e) {
            Logger::errors()->error('Failed to render deselection email template via Twig', ['error' => $e->getMessage()]);
            return false;
        }

        return $this->sendEmail(
            $playerEmail,
            $playerName,
            $subject,
            $htmlContent
        );
    }

    public function sendTrainingOverlapNotification(
        array $player,
        array $trainingEvent,
        array $matchEvent,
        array $cc = []
    ): bool {
        $playerEmail = $player['Mel'] ?? $player['mel'] ?? $player['data']['Mel'] ?? null;
        if (!$playerEmail) {
            Logger::errors()->error('Cannot send training overlap email to player: no email address found', ['player' => $player]);
            return false;
        }

        $playerName = trim(($player['Prénom'] ?? $player['prenom'] ?? '') . ' ' . ($player['Nom'] ?? $player['nom'] ?? ''));
        $trainingTitle = $trainingEvent['titre'] ?? 'Entraînement';
        $subject = '⚠️ Retrait entraînement pour cause de chevauchement : ' . $trainingTitle;

        $trainingDate = $trainingEvent['date_display'] ?? $trainingEvent['Date'] ?? $trainingEvent['date'] ?? '';
        $trainingTime = $trainingEvent['time_display'] ?? '';
        if ($trainingTime) {
            $trainingDate .= ' à ' . $trainingTime;
        }
        $trainingLocation = $trainingEvent['lieu'] ?? '';

        $matchTitle = $matchEvent['titre'] ?? 'Match';
        $matchDate = $matchEvent['date_display'] ?? $matchEvent['Date'] ?? $matchEvent['date'] ?? '';
        $matchTime = $matchEvent['time_display'] ?? '';
        if ($matchTime) {
            $matchDate .= ' à ' . $matchTime;
        }
        $matchLocation = $matchEvent['lieu'] ?? '';

        try {
            $twig = \App\Core\View::getInstance();
            $htmlContent = $twig->render('emails/training_overlap.twig', [
                'playerName'       => $playerName,
                'trainingTitle'    => $trainingTitle,
                'trainingDate'     => $trainingDate,
                'trainingLocation' => $trainingLocation,
                'matchTitle'       => $matchTitle,
                'matchDate'        => $matchDate,
                'matchLocation'    => $matchLocation,
                'dashboardUrl'     => BASE_URL . '/member/dashboard',
            ]);
        } catch (\Throwable $e) {
            Logger::errors()->error('Failed to render training overlap email template via Twig', ['error' => $e->getMessage()]);
            return false;
        }

        return $this->sendEmail(
            $playerEmail,
            $playerName,
            $subject,
            $htmlContent,
            null,
            $cc
        );
    }

    public function sendMatchModificationNotification(array $player, array $oldEvent, array $newEvent): bool
    {
        $playerEmail = $player['Mel'] ?? $player['mel'] ?? $player['data']['Mel'] ?? null;
        if (!$playerEmail) {
            Logger::errors()->error('Cannot send modification email to player: no email address found', ['player' => $player]);
            return false;
        }

        $playerName = trim(($player['Prénom'] ?? $player['prenom'] ?? '') . ' ' . ($player['Nom'] ?? $player['nom'] ?? ''));
        $eventTitle = $newEvent['titre'] ?? 'Match';
        $subject = '⚠️ Modification match : ' . $eventTitle;

        // Old Date formatting
        $oldDate = $oldEvent['date_display'] ?? $oldEvent['Date'] ?? $oldEvent['date'] ?? '';
        $oldTime = $oldEvent['time_display'] ?? '';
        if ($oldTime) {
            $oldDate .= ' à ' . $oldTime;
        }
        $oldLocation = $oldEvent['lieu'] ?? $oldEvent['location'] ?? '';

        // New Date formatting
        $newDate = $newEvent['date_display'] ?? $newEvent['Date'] ?? $newEvent['date'] ?? '';
        $newTime = $newEvent['time_display'] ?? '';
        if ($newTime) {
            $newDate .= ' à ' . $newTime;
        }
        $newLocation = $newEvent['lieu'] ?? $newEvent['location'] ?? '';

        try {
            $twig = \App\Core\View::getInstance();
            $htmlContent = $twig->render('emails/match_modification.twig', [
                'PLAYER_NAME' => $this->escapeHtml($playerName),
                'EVENT_TITLE' => $this->escapeHtml($eventTitle),
                'OLD_DATE' => $this->escapeHtml($oldDate),
                'NEW_DATE' => $this->escapeHtml($newDate),
                'OLD_LOCATION' => $this->escapeHtml($oldLocation),
                'NEW_LOCATION' => $this->escapeHtml($newLocation),
                'DASHBOARD_URL' => BASE_URL . '/member/dashboard',
            ]);
        } catch (\Throwable $e) {
            Logger::errors()->error('Failed to render match modification email template via Twig', ['error' => $e->getMessage()]);
            return false;
        }

        return $this->sendEmail(
            $playerEmail,
            $playerName,
            $subject,
            $htmlContent
        );
    }

    public function sendPasswordRecovery(array $player): bool
    {
        $subject = 'Vos identifiants de connexion - USM Volley';

        try {
            $twig = \App\Core\View::getInstance();
            $htmlContent = $twig->render('emails/password_recovery.twig', [
                'PLAYER_NAME' => $player['Prénom'] . ' ' . $player['Nom'],
                'EMAIL'       => $player['Mel'],
                'PASSWORD'    => $player['mdp'],
                'LOGIN_URL'   => BASE_URL . '/member/login',
            ]);
        } catch (\Throwable $e) {
            Logger::errors()->error('Failed to render password recovery email template via Twig', ['error' => $e->getMessage()]);
            return false;
        }

        return $this->sendEmail(
            $player['Mel'],
            $player['Prénom'] . ' ' . $player['Nom'],
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
                'ignore_errors' => true,
            ],
        ]);

        try {
            $response = @file_get_contents(self::API_URL, false, $context);

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

    public function sendEventCreationNotification(array $player, array $event, string $teamName): bool
    {
        $playerEmail = $player['Mel'] ?? $player['mel'] ?? $player['data']['Mel'] ?? null;
        if (!$playerEmail) {
            Logger::errors()->error('Cannot send creation email to player: no email address found', ['player' => $player]);
            return false;
        }

        $playerName = trim(($player['Prénom'] ?? $player['prenom'] ?? '') . ' ' . ($player['Nom'] ?? $player['nom'] ?? ''));

        $rawType = $event['ManifestationTypée'] ?? $event['manifestation_type'] ?? '';
        $parts = explode(' - ', $rawType, 3);
        $type = $parts[1] ?? '';
        $eventTitle = $parts[2] ?? $rawType;

        $isMatch = (mb_strtolower($type) === 'match');
        $subject = ($isMatch ? '🏐 Nouveau match créé' : '🏐 Nouvel entraînement/événement créé') . ' : ' . $eventTitle;

        $eventDate = $event['Date'] ?? $event['date'] ?? '';
        if ($eventDate) {
            try {
                $dt = new \DateTime($eventDate);
                $eventDateFormatted = \App\Services\Agenda\EventNormalizer::formatDateDisplay(\DateTimeImmutable::createFromMutable($dt));
                if ($dt->format('H:i') !== '00:00') {
                    $eventDateFormatted .= ' à ' . $dt->format('H:i');
                }
                $eventDate = $eventDateFormatted;
            } catch (\Throwable) {
            }
        }

        $eventLocation = $event['Lieu'] ?? $event['location'] ?? '';
        $eventComment = $event['Commentaire'] ?? $event['commentaire'] ?? '';
        $eventId = (int)($event['id'] ?? $event['id_manifestation'] ?? 0);
        $playerId = (int)($player['id_joueur'] ?? 0);

        $commentHtml = '';
        if ($eventComment) {
            $commentHtml = '<div class="field-label">💬 Commentaire</div><div class="field-value">' . $this->nl2brHtml($eventComment) . '</div>';
        }

        $buttonsHtml = '';
        if ($playerId && $eventId) {
            $buttonsHtml = $this->getEventButtonsHtml($playerId, $eventId, $isMatch);
        }

        try {
            $twig = \App\Core\View::getInstance();
            $htmlContent = $twig->render('emails/event_creation.twig', [
                'PLAYER_NAME' => $this->escapeHtml($playerName),
                'TEAM_NAME' => $this->escapeHtml($teamName),
                'EVENT_TITLE' => $this->escapeHtml($eventTitle),
                'EVENT_DATE' => $this->escapeHtml($eventDate),
                'EVENT_LOCATION' => $this->escapeHtml($eventLocation),
                'COMMENT_HTML' => $commentHtml,
                'BUTTONS_HTML' => $buttonsHtml,
                'DASHBOARD_URL' => BASE_URL . '/member/dashboard?event_id=' . $eventId,
                'PROFILE_URL' => BASE_URL . '/member/profile',
                'IS_MATCH' => $isMatch,
            ]);
        } catch (\Throwable $e) {
            Logger::errors()->error('Failed to render event creation email template via Twig', ['error' => $e->getMessage()]);
            return false;
        }

        return $this->sendEmail(
            $playerEmail,
            $playerName,
            $subject,
            $htmlContent
        );
    }

    public function sendWeeklyPresenceNotification(array $player, array $events, array $saison): bool
    {
        $playerEmail = $player['Mel'] ?? $player['mel'] ?? $player['data']['Mel'] ?? null;
        if (!$playerEmail) {
            Logger::errors()->error('Cannot send weekly presence email to player: no email address found', ['player' => $player]);
            return false;
        }

        $playerName = trim(($player['Prénom'] ?? $player['prenom'] ?? '') . ' ' . ($player['Nom'] ?? $player['nom'] ?? ''));
        $subject = '⏰ USM Volley : Votre planning et présences de la semaine';

        $formattedEvents = [];
        foreach ($events as $ev) {
            $rawType = $ev['ManifestationTypée'] ?? '';
            $parts = explode(' - ', $rawType, 3);
            $type = $parts[1] ?? '';
            $title = $parts[2] ?? $rawType;
            $isMatch = (mb_strtolower($type) === 'match');

            $dateDisplay = $ev['Date'] ?? '';
            if ($dateDisplay) {
                try {
                    $dt = new \DateTime($dateDisplay);
                    $dateFormatted = \App\Services\Agenda\EventNormalizer::formatDateDisplay(\DateTimeImmutable::createFromMutable($dt));
                    if ($dt->format('H:i') !== '00:00') {
                        $dateFormatted .= ' à ' . $dt->format('H:i');
                    }
                    $dateDisplay = $dateFormatted;
                } catch (\Throwable) {
                }
            }

            $participationStatus = new \App\Helpers\ParticipationStatus((string)$ev['current_status']);
            $hasResponded = !$participationStatus->isEmpty() && $ev['current_status'] !== '.';
            $statusLabel = $hasResponded ? $participationStatus->getLabel() : 'Sans réponse';

            $statusColor = '#64748b'; // default grey
            if ($hasResponded) {
                $statusColor = match ($participationStatus->getCategory()) {
                    "present", "selected" => "#10b981", // green
                    "available"           => "#f59e0b", // yellow-orange
                    "available_if_needed" => "#06b6d4", // cyan
                    "unavailable", "absent" => "#ef4444", // red
                    default               => "#64748b",
                };
            }

            $buttonsHtml = '';
            if (!$hasResponded) {
                $buttonsHtml = $this->getEventButtonsHtml((int)$player['id_joueur'], (int)$ev['id_manifestation'], $isMatch);
            }

            $formattedEvents[] = [
                'title'          => $title,
                'date_display'   => $dateDisplay,
                'location'       => $ev['Lieu'] ?? '',
                'comment'        => $ev['Commentaire'] ?? '',
                'status_label'   => $statusLabel,
                'status_color'   => $statusColor,
                'buttons_html'   => $buttonsHtml,
                'has_responded'  => $hasResponded,
            ];
        }

        try {
            $twig = \App\Core\View::getInstance();
            $htmlContent = $twig->render('emails/weekly_presence.twig', [
                'PLAYER_NAME'    => $this->escapeHtml($playerName),
                'SAISON_LIBELLE' => $saison['libelle'],
                'EVENTS'         => $formattedEvents,
                'DASHBOARD_URL'  => BASE_URL . '/member/dashboard',
                'PROFILE_URL'    => BASE_URL . '/member/profile',
            ]);
        } catch (\Throwable $e) {
            Logger::errors()->error('Failed to render weekly presence email template via Twig', ['error' => $e->getMessage()]);
            return false;
        }

        return $this->sendEmail(
            $playerEmail,
            $playerName,
            $subject,
            $htmlContent
        );
    }

    private function getEventButtonsHtml(int $playerId, int $eventId, bool $isMatch): string
    {
        if ($isMatch) {
            $options = [
                'Disponible' => ['label' => 'Disponible', 'color' => '#10b981'],
                'Disponible si nécessaire' => ['label' => 'Si besoin', 'color' => '#f59e0b'],
                'Indisponible' => ['label' => 'Indisponible', 'color' => '#ef4444']
            ];
        } else {
            $options = [
                'Présent(e)' => ['label' => 'Présent(e)', 'color' => '#10b981'],
                'Absent(e)' => ['label' => 'Absent(e)', 'color' => '#ef4444']
            ];
        }

        $buttonsHtml = '<div style="margin-top: 10px; margin-bottom: 10px;">';
        foreach ($options as $status => $opt) {
            $token = \App\Models\Participation::generateEmailToken($playerId, $eventId, $status);
            $url = BASE_URL . '/public/participation/update?' . http_build_query([
                'player_id' => $playerId,
                'event_id'  => $eventId,
                'status'    => $status,
                'token'     => $token
            ]);
            $buttonsHtml .= '<a href="' . htmlspecialchars($url) . '" style="display: inline-block; background-color: ' . $opt['color'] . '; color: #ffffff; padding: 8px 14px; border-radius: 8px; font-weight: bold; text-decoration: none; font-size: 13px; margin: 4px; border: 1px solid rgba(0,0,0,0.05); box-shadow: 0 1px 2px rgba(0,0,0,0.05);">' . htmlspecialchars($opt['label']) . '</a>';
        }
        $buttonsHtml .= '</div>';
        return $buttonsHtml;
    }
}
