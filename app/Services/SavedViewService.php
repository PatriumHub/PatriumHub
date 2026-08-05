<?php

declare(strict_types=1);

final class SavedViewService
{
    /** @return list<array<string,mixed>> */
    public static function listForUser(int $userId, string $context = 'dashboard'): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM saved_views WHERE user_id = :u AND context = :c ORDER BY name'
        );
        $stmt->execute(['u' => $userId, 'c' => $context]);
        return $stmt->fetchAll();
    }

    public static function find(int $id, int $userId): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM saved_views WHERE id = :id AND user_id = :u LIMIT 1'
        );
        $stmt->execute(['id' => $id, 'u' => $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** @param array<string,mixed> $filters */
    public static function save(int $userId, string $name, string $context, array $filters): int
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO saved_views (user_id, name, context, filters_json)
             VALUES (:u, :n, :c, :f)
             ON DUPLICATE KEY UPDATE filters_json = VALUES(filters_json), updated_at = CURRENT_TIMESTAMP'
        );
        $stmt->execute([
            'u' => $userId,
            'n' => $name,
            'c' => $context,
            'f' => json_encode($filters, JSON_UNESCAPED_UNICODE),
        ]);
        if ($stmt->rowCount() === 1) {
            return (int) Database::pdo()->lastInsertId();
        }
        $existing = Database::pdo()->prepare(
            'SELECT id FROM saved_views WHERE user_id = :u AND context = :c AND name = :n LIMIT 1'
        );
        $existing->execute(['u' => $userId, 'c' => $context, 'n' => $name]);
        return (int) $existing->fetchColumn();
    }

    public static function delete(int $id, int $userId): void
    {
        Database::pdo()->prepare('DELETE FROM saved_views WHERE id = :id AND user_id = :u')
            ->execute(['id' => $id, 'u' => $userId]);
    }

    /** @return array<string,mixed> */
    public static function filters(array $row): array
    {
        $decoded = json_decode((string) ($row['filters_json'] ?? '{}'), true);
        return is_array($decoded) ? $decoded : [];
    }
}
