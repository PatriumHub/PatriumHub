<?php

declare(strict_types=1);

/**
 * Co-titulares de cuentas personales (varias personas → una cuenta).
 * Empresas siguen usando solo accounts.entity_id al 100%.
 */
final class AccountOwnerService
{
    private static bool $ready = false;

    public static function ensureSchema(): void
    {
        if (self::$ready) {
            return;
        }
        $pdo = Database::pdo();
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS account_owners (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                account_id INT UNSIGNED NOT NULL,
                entity_id INT UNSIGNED NOT NULL,
                ownership_pct DECIMAL(7,4) NOT NULL DEFAULT 100.0000,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_account_owner (account_id, entity_id),
                KEY idx_acco_entity (entity_id),
                CONSTRAINT fk_acco_account FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE,
                CONSTRAINT fk_acco_entity FOREIGN KEY (entity_id) REFERENCES entities(id) ON DELETE CASCADE
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        // Migrar cuentas de persona existentes (una sola vez por fila).
        $pdo->exec(
            "INSERT IGNORE INTO account_owners (account_id, entity_id, ownership_pct)
             SELECT a.id, a.entity_id, 100
             FROM accounts a
             INNER JOIN entities e ON e.id = a.entity_id AND e.type = 'person'"
        );

        self::$ready = true;
    }

    /**
     * @return list<array{entity_id:int,ownership_pct:float,name?:string}>
     */
    public static function forAccount(int $accountId): array
    {
        self::ensureSchema();
        $stmt = Database::pdo()->prepare(
            'SELECT ao.entity_id, ao.ownership_pct, e.name
             FROM account_owners ao
             INNER JOIN entities e ON e.id = ao.entity_id
             WHERE ao.account_id = :id
             ORDER BY ao.ownership_pct DESC, e.name'
        );
        $stmt->execute(['id' => $accountId]);
        $rows = [];
        foreach ($stmt->fetchAll() as $r) {
            $rows[] = [
                'entity_id' => (int) $r['entity_id'],
                'ownership_pct' => (float) $r['ownership_pct'],
                'name' => (string) $r['name'],
            ];
        }
        return $rows;
    }

    /**
     * @param list<array{entity_id:int,ownership_pct:float}> $owners
     */
    public static function sync(int $accountId, array $owners): void
    {
        self::ensureSchema();
        $pdo = Database::pdo();
        $pdo->prepare('DELETE FROM account_owners WHERE account_id = :id')->execute(['id' => $accountId]);
        if ($owners === []) {
            return;
        }
        $ins = $pdo->prepare(
            'INSERT INTO account_owners (account_id, entity_id, ownership_pct)
             VALUES (:account_id, :entity_id, :ownership_pct)'
        );
        foreach ($owners as $o) {
            $eid = (int) ($o['entity_id'] ?? 0);
            $pct = (float) ($o['ownership_pct'] ?? 0);
            if ($eid < 1 || $pct <= 0) {
                continue;
            }
            $ins->execute([
                'account_id' => $accountId,
                'entity_id' => $eid,
                'ownership_pct' => round($pct, 4),
            ]);
        }
    }

    public static function clear(int $accountId): void
    {
        self::ensureSchema();
        Database::pdo()->prepare('DELETE FROM account_owners WHERE account_id = :id')->execute(['id' => $accountId]);
    }

    /**
     * Parseo desde POST: owner_ids[] + owner_pct[id].
     * @return list<array{entity_id:int,ownership_pct:float}>|null null si inválido
     */
    public static function ownersFromPost(): ?array
    {
        return AssetOwnerService::ownersFromPost();
    }

    /** Etiqueta corta para listados. */
    public static function labelForAccount(int $accountId, string $fallbackEntityName, string $entityType): string
    {
        if ($entityType === 'company') {
            return $fallbackEntityName;
        }
        $owners = self::forAccount($accountId);
        if ($owners === []) {
            return $fallbackEntityName;
        }
        if (count($owners) === 1) {
            $o = $owners[0];
            $pct = (float) $o['ownership_pct'];
            return $pct >= 99.99
                ? (string) $o['name']
                : ((string) $o['name'] . ' (' . rtrim(rtrim(number_format($pct, 2, ',', ''), '0'), ',') . '%)');
        }
        $parts = [];
        foreach ($owners as $o) {
            $parts[] = (string) $o['name'] . ' '
                . rtrim(rtrim(number_format((float) $o['ownership_pct'], 2, ',', ''), '0'), ',') . '%';
        }
        return implode(' · ', $parts);
    }
}
