<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class EmailLog
{
    public const TYPES_MAP = [
        'weekly_presence' => 'Rappel de présence hebdomadaire',
        'player_selection' => 'Convocation match',
        'player_deselection' => 'Annulation convocation',
        'match_cancellation' => 'Annulation match/événement',
        'match_reminder' => 'Relance réponse match',
        'match_modification' => 'Modification match',
        'training_overlap' => 'Chevauchement entraînement',
        'event_creation' => 'Création événement',
        'password_recovery' => 'Récupération identifiants',
        'contact_notification' => 'Notification contact (admin)',
        'visitor_reply' => 'Réponse à un visiteur',
        'captain_message' => 'Message au capitaine',
        'unknown' => 'Autre / Inconnu',
    ];

    public static function log(
        string $recipientEmail,
        ?string $recipientName,
        string $subject,
        string $emailType,
        string $status,
        ?string $errorMessage = null,
        ?string $messageId = null
    ): int {
        try {
            $stmt = Database::get()->prepare(
                'INSERT INTO email_logs (recipient_email, recipient_name, subject, email_type, sent_at, status, error_message, message_id)
                 VALUES (?, ?, ?, ?, NOW(), ?, ?, ?)'
            );
            $stmt->execute([
                $recipientEmail,
                $recipientName,
                $subject,
                $emailType,
                $status,
                $errorMessage,
                $messageId
            ]);
            return (int)Database::get()->lastInsertId();
        } catch (\Throwable $e) {
            // Do not break the main flow if email logging fails, but log to error log
            \App\Services\Logger::errors()->error('Failed to write email log to database', [
                'error' => $e->getMessage(),
                'recipient' => $recipientEmail
            ]);
            return 0;
        }
    }

    public static function getPaginated(int $page, int $limit, array $filters = []): array
    {
        $offset = ($page - 1) * $limit;
        $where = [];
        $params = [];

        if (!empty($filters['status'])) {
            $where[] = 'status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['email_type'])) {
            $where[] = 'email_type = ?';
            $params[] = $filters['email_type'];
        }
        if (!empty($filters['search'])) {
            $where[] = '(recipient_email LIKE ? OR recipient_name LIKE ? OR subject LIKE ?)';
            $searchParam = '%' . $filters['search'] . '%';
            $params[] = $searchParam;
            $params[] = $searchParam;
            $params[] = $searchParam;
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        $sql = "SELECT * FROM email_logs 
                $whereClause 
                ORDER BY sent_at DESC 
                LIMIT ? OFFSET ?";
        
        $stmt = Database::get()->prepare($sql);
        
        // Bind integer parameters manually for compatibility
        $paramIndex = 1;
        foreach ($params as $param) {
            $stmt->bindValue($paramIndex++, $param);
        }
        $stmt->bindValue($paramIndex++, $limit, \PDO::PARAM_INT);
        $stmt->bindValue($paramIndex++, $offset, \PDO::PARAM_INT);
        
        $stmt->execute();
        
        $logs = $stmt->fetchAll() ?? [];
        
        // Enrich logs with type labels
        foreach ($logs as &$log) {
            $log['type_label'] = self::TYPES_MAP[$log['email_type']] ?? $log['email_type'];
        }
        
        return $logs;
    }

    public static function count(array $filters = []): int
    {
        $where = [];
        $params = [];

        if (!empty($filters['status'])) {
            $where[] = 'status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['email_type'])) {
            $where[] = 'email_type = ?';
            $params[] = $filters['email_type'];
        }
        if (!empty($filters['search'])) {
            $where[] = '(recipient_email LIKE ? OR recipient_name LIKE ? OR subject LIKE ?)';
            $searchParam = '%' . $filters['search'] . '%';
            $params[] = $searchParam;
            $params[] = $searchParam;
            $params[] = $searchParam;
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        $sql = "SELECT COUNT(*) as count FROM email_logs $whereClause";
        $stmt = Database::get()->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();
        return (int)($result['count'] ?? 0);
    }

    public static function getStats(): array
    {
        $db = Database::get();
        
        // Total sent
        $totalSent = (int)$db->query("SELECT COUNT(*) FROM email_logs")->fetchColumn();
        
        // Succeeded
        $successCount = (int)$db->query("SELECT COUNT(*) FROM email_logs WHERE status = 'success'")->fetchColumn();
        
        // Failed
        $failedCount = (int)$db->query("SELECT COUNT(*) FROM email_logs WHERE status = 'failed'")->fetchColumn();
        
        // Stats by type
        $stmt = $db->query("
            SELECT email_type, COUNT(*) as count, SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as success_count
            FROM email_logs
            GROUP BY email_type
            ORDER BY count DESC
        ");
        $byTypeRaw = $stmt->fetchAll() ?? [];
        $byType = [];
        foreach ($byTypeRaw as $row) {
            $type = $row['email_type'];
            $byType[] = [
                'type' => $type,
                'label' => self::TYPES_MAP[$type] ?? $type,
                'count' => (int)$row['count'],
                'success_count' => (int)$row['success_count'],
                'failed_count' => (int)($row['count'] - $row['success_count']),
            ];
        }

        return [
            'total' => $totalSent,
            'success' => $successCount,
            'failed' => $failedCount,
            'by_type' => $byType
        ];
    }

    public static function getDistinctEmailTypes(): array
    {
        $db = Database::get();
        $stmt = $db->query("SELECT DISTINCT email_type FROM email_logs ORDER BY email_type ASC");
        $types = $stmt->fetchAll(\PDO::FETCH_COLUMN) ?? [];
        
        $result = [];
        foreach ($types as $type) {
            $result[$type] = self::TYPES_MAP[$type] ?? $type;
        }
        return $result;
    }
}
