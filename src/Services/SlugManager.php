<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

class SlugManager
{
    public static function generate(string $text): string
    {
        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9\s-]/', '', $text) ?? $text;
        $text = preg_replace('/\s+/', '-', trim($text)) ?? $text;
        $text = preg_replace('/-+/', '-', $text) ?? $text;
        return trim($text, '-');
    }

    public static function makeUnique(string $slug, string $table, string $idField = 'id', ?int $currentId = null): string
    {
        $db = Database::get();
        $original = $slug;
        $counter = 0;

        while (true) {
            $testSlug = $counter > 0 ? $original . '-' . $counter : $slug;

            $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM {$table} WHERE slug = ?");
            $stmt->execute([$testSlug]);
            $result = $stmt->fetch();

            if ($result['cnt'] == 0) {
                return $testSlug;
            }

            // If we have a current ID, check if the duplicate is from the same row (update case)
            if ($currentId !== null) {
                $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM {$table} WHERE slug = ? AND id = ?");
                $stmt->execute([$testSlug, $currentId]);
                $result = $stmt->fetch();
                if ($result['cnt'] == 1) {
                    return $testSlug;
                }
            }

            $counter++;
        }
    }
}
