<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

class SlugManager
{
    public static function generate(string $text): string
    {
        $text = strtolower($text);
        $text = self::removeAccents($text);
        // Preserve / and . (e.g. CanalBlog paths like "2025/09/article.html")
        $text = preg_replace('/[^a-z0-9\s\-\/\.]/', '', $text) ?? $text;
        $text = preg_replace('/\s+/', '-', trim($text)) ?? $text;
        $text = preg_replace('/-+/', '-', $text) ?? $text;
        return trim($text, '-');
    }

    public static function generateWithDate(string $text, ?\DateTime $date = null): string
    {
        $basePath = '';
        if ($date !== null) {
            $basePath = $date->format('Y/m/d') . '/';
        }
        return $basePath . self::generate($text);
    }

    private static function removeAccents(string $text): string
    {
        if (function_exists('transliterator_transliterate')) {
            return transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $text) ?: $text;
        }

        $replacements = [
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a',
            'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
            'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
            'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
            'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c', 'ñ' => 'n',
            'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A', 'Å' => 'A',
            'È' => 'E', 'É' => 'E', 'Ê' => 'E', 'Ë' => 'E',
            'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I',
            'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O',
            'Ù' => 'U', 'Ú' => 'U', 'Û' => 'U', 'Ü' => 'U',
            'Ç' => 'C', 'Ñ' => 'N',
        ];
        return strtr($text, $replacements);
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
