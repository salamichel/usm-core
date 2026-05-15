<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class AiImageContext
{
    public static function all(): array
    {
        $stmt = Database::get()->query("SELECT * FROM ai_image_contexts ORDER BY is_default DESC, name ASC");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::get()->prepare("SELECT * FROM ai_image_contexts WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    public static function getDefault(): ?array
    {
        $stmt = Database::get()->query("SELECT * FROM ai_image_contexts WHERE is_default = 1 LIMIT 1");
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    public static function create(array $data): int
    {
        $db = Database::get();
        $stmt = $db->prepare("INSERT INTO ai_image_contexts (name, style_prompt, gemini_model, imagen_model, is_default) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            $data['name'],
            $data['style_prompt'],
            $data['gemini_model'] ?? 'gemini-2.0-flash',
            $data['imagen_model'] ?? 'imagen-3.0-generate-002',
            $data['is_default'] ? 1 : 0,
        ]);
        return (int) $db->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $stmt = Database::get()->prepare("UPDATE ai_image_contexts SET name=?, style_prompt=?, gemini_model=?, imagen_model=?, is_default=? WHERE id=?");
        $stmt->execute([
            $data['name'],
            $data['style_prompt'],
            $data['gemini_model'] ?? 'gemini-2.0-flash',
            $data['imagen_model'] ?? 'imagen-3.0-generate-002',
            $data['is_default'] ? 1 : 0,
            $id,
        ]);
    }

    public static function delete(int $id): void
    {
        Database::get()->prepare("DELETE FROM ai_image_contexts WHERE id = ?")->execute([$id]);
    }

    public static function setDefault(int $id): void
    {
        $db = Database::get();
        $db->exec("UPDATE ai_image_contexts SET is_default = 0");
        $db->prepare("UPDATE ai_image_contexts SET is_default = 1 WHERE id = ?")->execute([$id]);
    }
}
