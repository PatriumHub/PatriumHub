<?php

declare(strict_types=1);

final class IntegrationService
{
    public static function list(?string $provider = null): array
    {
        $sql = "SELECT i.*, e.name AS entity_name
                FROM integrations i
                INNER JOIN entities e ON e.id = i.entity_id
                WHERE i.status <> 'revoked'";
        $params = [];
        if ($provider !== null) {
            $sql .= ' AND i.provider = :p';
            $params['p'] = $provider;
        }
        $sql .= ' ORDER BY i.updated_at DESC, i.id DESC';
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT i.*, e.name AS entity_name
             FROM integrations i
             INNER JOIN entities e ON e.id = i.entity_id
             WHERE i.id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function config(array $integration): array
    {
        $raw = $integration['config_json'] ?? null;
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            return is_array($decoded) ? $decoded : [];
        }
        return [];
    }

    /** @return array<string, string> */
    public static function credentials(int $integrationId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT key_name, value_encrypted FROM integration_credentials WHERE integration_id = :id'
        );
        $stmt->execute(['id' => $integrationId]);
        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            $out[$row['key_name']] = CryptoService::decrypt($row['value_encrypted']);
        }
        return $out;
    }

    public static function hasCredential(int $integrationId, string $keyName): bool
    {
        $stmt = Database::pdo()->prepare(
            'SELECT 1 FROM integration_credentials WHERE integration_id = :id AND key_name = :k LIMIT 1'
        );
        $stmt->execute(['id' => $integrationId, 'k' => $keyName]);
        return (bool) $stmt->fetchColumn();
    }

    /** @param array<string, string|null> $secrets */
    public static function upsertCredentials(int $integrationId, array $secrets): void
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'INSERT INTO integration_credentials (integration_id, key_name, value_encrypted)
             VALUES (:id, :key_name, :value_encrypted)
             ON DUPLICATE KEY UPDATE value_encrypted = VALUES(value_encrypted), updated_at = CURRENT_TIMESTAMP'
        );
        foreach ($secrets as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $stmt->execute([
                'id' => $integrationId,
                'key_name' => $key,
                'value_encrypted' => CryptoService::encrypt($value),
            ]);
        }
    }

    public static function create(string $provider, int $entityId, string $name, array $config): int
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO integrations (provider, entity_id, name, status, config_json)
             VALUES (:provider, :entity_id, :name, \'active\', :config_json)'
        );
        $stmt->execute([
            'provider' => $provider,
            'entity_id' => $entityId,
            'name' => $name,
            'config_json' => json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
        return (int) Database::pdo()->lastInsertId();
    }

    public static function update(int $id, int $entityId, string $name, array $config, string $status): void
    {
        Database::pdo()->prepare(
            'UPDATE integrations
             SET entity_id = :entity_id, name = :name, config_json = :config_json, status = :status
             WHERE id = :id'
        )->execute([
            'entity_id' => $entityId,
            'name' => $name,
            'config_json' => json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'status' => $status,
            'id' => $id,
        ]);
    }

    public static function markStatus(int $id, string $status, ?string $error = null, bool $touchSync = false): void
    {
        $sql = 'UPDATE integrations SET status = :status, last_error = :err';
        if ($touchSync) {
            $sql .= ', last_sync_at = NOW()';
        }
        $sql .= ' WHERE id = :id';
        Database::pdo()->prepare($sql)->execute([
            'status' => $status,
            'err' => $error,
            'id' => $id,
        ]);
    }

    /** Revoca y elimina por completo la integración y todo lo que trajo al sistema. */
    public static function revoke(int $id): void
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT account_id FROM integrations WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $id]);
            $linkedAccountId = (int) ($stmt->fetchColumn() ?: 0);

            $accStmt = $pdo->prepare(
                'SELECT id FROM accounts
                 WHERE integration_id = :id OR (:aid > 0 AND id = :aid2)'
            );
            $accStmt->execute([
                'id' => $id,
                'aid' => $linkedAccountId,
                'aid2' => $linkedAccountId,
            ]);
            $accountIds = array_map('intval', $accStmt->fetchAll(\PDO::FETCH_COLUMN));
            if ($linkedAccountId > 0 && !in_array($linkedAccountId, $accountIds, true)) {
                $accountIds[] = $linkedAccountId;
            }

            $pdo->prepare('DELETE FROM transactions WHERE integration_id = :id')
                ->execute(['id' => $id]);
            $pdo->prepare('DELETE FROM sales_metrics WHERE integration_id = :id')
                ->execute(['id' => $id]);
            $pdo->prepare('DELETE FROM inventories WHERE integration_id = :id')
                ->execute(['id' => $id]);

            if ($accountIds !== []) {
                $placeholders = implode(',', array_fill(0, count($accountIds), '?'));
                $pdo->prepare("DELETE FROM transactions WHERE account_id IN ({$placeholders})")
                    ->execute($accountIds);
                $pdo->prepare("DELETE FROM account_balances WHERE account_id IN ({$placeholders})")
                    ->execute($accountIds);
                $pdo->prepare("UPDATE assets SET account_id = NULL WHERE account_id IN ({$placeholders})")
                    ->execute($accountIds);
            }

            // Liberar FK account_id antes de borrar cuentas / la fila.
            $pdo->prepare('UPDATE integrations SET account_id = NULL WHERE id = :id')
                ->execute(['id' => $id]);

            if ($accountIds !== []) {
                $placeholders = implode(',', array_fill(0, count($accountIds), '?'));
                $pdo->prepare("DELETE FROM accounts WHERE id IN ({$placeholders})")
                    ->execute($accountIds);
            }

            // CASCADE: credentials, sync_runs, sync_cursors.
            $pdo->prepare('DELETE FROM integrations WHERE id = :id')->execute(['id' => $id]);

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function recentRuns(int $integrationId, int $limit = 10): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM sync_runs WHERE integration_id = :id ORDER BY id DESC LIMIT ' . (int) $limit
        );
        $stmt->execute(['id' => $integrationId]);
        return $stmt->fetchAll();
    }

    public static function startRun(int $integrationId): int
    {
        Database::pdo()->prepare(
            'INSERT INTO sync_runs (integration_id, started_at, status) VALUES (:id, NOW(), \'running\')'
        )->execute(['id' => $integrationId]);
        return (int) Database::pdo()->lastInsertId();
    }

    public static function finishRun(int $runId, string $status, int $read, int $upserted, ?string $message): void
    {
        Database::pdo()->prepare(
            'UPDATE sync_runs
             SET finished_at = NOW(), status = :status, records_read = :read,
                 records_upserted = :upserted, message = :message
             WHERE id = :id'
        )->execute([
            'status' => $status,
            'read' => $read,
            'upserted' => $upserted,
            'message' => $message,
            'id' => $runId,
        ]);
    }

    public static function forEntity(int $entityId): array
    {
        $stmt = Database::pdo()->prepare(
            "SELECT * FROM integrations
             WHERE entity_id = :e AND status <> 'revoked'
             ORDER BY provider, name"
        );
        $stmt->execute(['e' => $entityId]);
        return $stmt->fetchAll();
    }

    public static function isStale(?string $lastSyncAt, int $hours = 24): bool
    {
        if ($lastSyncAt === null || $lastSyncAt === '') {
            return true;
        }
        $ts = strtotime($lastSyncAt);
        if ($ts === false) {
            return true;
        }
        return (time() - $ts) > ($hours * 3600);
    }
}
