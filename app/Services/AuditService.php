<?php

declare(strict_types=1);

final class AuditService
{
    public static function log(
        string $action,
        ?string $entityType = null,
        ?int $entityId = null,
        ?string $summary = null,
        ?array $before = null,
        ?array $after = null
    ): void {
        $user = Auth::user();
        $stmt = Database::pdo()->prepare(
            'INSERT INTO audit_log (user_id, action, entity_type, entity_id, summary, before_json, after_json, ip)
             VALUES (:user_id, :action, :entity_type, :entity_id, :summary, :before_json, :after_json, :ip)'
        );
        $stmt->execute([
            'user_id' => $user['id'] ?? null,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'summary' => $summary,
            'before_json' => $before !== null ? json_encode($before, JSON_UNESCAPED_UNICODE) : null,
            'after_json' => $after !== null ? json_encode($after, JSON_UNESCAPED_UNICODE) : null,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    }
}
