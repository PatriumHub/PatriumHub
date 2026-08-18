<?php

declare(strict_types=1);

/**
 * Co-titulares de activos personales (varias personas → un activo).
 * Empresas siguen usando solo assets.entity_id al 100%.
 */
final class AssetOwnerService
{
    private static bool $ready = false;

    public static function ensureSchema(): void
    {
        if (self::$ready) {
            return;
        }
        $pdo = Database::pdo();
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS asset_owners (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                asset_id INT UNSIGNED NOT NULL,
                entity_id INT UNSIGNED NOT NULL,
                ownership_pct DECIMAL(7,4) NOT NULL DEFAULT 100.0000,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_asset_owner (asset_id, entity_id),
                KEY idx_ao_entity (entity_id),
                CONSTRAINT fk_ao_asset FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE CASCADE,
                CONSTRAINT fk_ao_entity FOREIGN KEY (entity_id) REFERENCES entities(id) ON DELETE CASCADE
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        // Migrar activos de persona existentes (una sola vez por fila).
        $pdo->exec(
            "INSERT IGNORE INTO asset_owners (asset_id, entity_id, ownership_pct)
             SELECT a.id, a.entity_id, a.ownership_pct
             FROM assets a
             INNER JOIN entities e ON e.id = a.entity_id AND e.type = 'person'"
        );

        self::$ready = true;
    }

    /**
     * @return list<array{entity_id:int,ownership_pct:float,name?:string}>
     */
    public static function forAsset(int $assetId): array
    {
        self::ensureSchema();
        $stmt = Database::pdo()->prepare(
            'SELECT ao.entity_id, ao.ownership_pct, e.name
             FROM asset_owners ao
             INNER JOIN entities e ON e.id = ao.entity_id
             WHERE ao.asset_id = :id
             ORDER BY ao.ownership_pct DESC, e.name'
        );
        $stmt->execute(['id' => $assetId]);
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
    public static function sync(int $assetId, array $owners): void
    {
        self::ensureSchema();
        $pdo = Database::pdo();
        $pdo->prepare('DELETE FROM asset_owners WHERE asset_id = :id')->execute(['id' => $assetId]);
        if ($owners === []) {
            return;
        }
        $ins = $pdo->prepare(
            'INSERT INTO asset_owners (asset_id, entity_id, ownership_pct)
             VALUES (:asset_id, :entity_id, :ownership_pct)'
        );
        foreach ($owners as $o) {
            $eid = (int) ($o['entity_id'] ?? 0);
            $pct = (float) ($o['ownership_pct'] ?? 0);
            if ($eid < 1 || $pct <= 0) {
                continue;
            }
            $ins->execute([
                'asset_id' => $assetId,
                'entity_id' => $eid,
                'ownership_pct' => round($pct, 4),
            ]);
        }
    }

    public static function clear(int $assetId): void
    {
        self::ensureSchema();
        Database::pdo()->prepare('DELETE FROM asset_owners WHERE asset_id = :id')->execute(['id' => $assetId]);
    }

    /**
     * Parseo desde POST: owner_ids[] + owner_pct[id].
     * @return list<array{entity_id:int,ownership_pct:float}>|null null si inválido
     */
    public static function ownersFromPost(): ?array
    {
        $ids = $_POST['owner_ids'] ?? [];
        if (!is_array($ids)) {
            return null;
        }
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn ($v) => $v > 0)));
        if ($ids === []) {
            return null;
        }

        $pctsRaw = $_POST['owner_pct'] ?? [];
        if (!is_array($pctsRaw)) {
            $pctsRaw = [];
        }

        $owners = [];
        $sum = 0.0;
        foreach ($ids as $id) {
            $pct = isset($pctsRaw[$id])
                ? (float) str_replace(',', '.', (string) $pctsRaw[$id])
                : 0.0;
            if ($pct <= 0) {
                continue;
            }
            $owners[] = ['entity_id' => $id, 'ownership_pct' => $pct];
            $sum += $pct;
        }
        if ($owners === [] || $sum <= 0) {
            return null;
        }

        // Si no suman ~100, normalizar; si ya suman, redondear.
        if (abs($sum - 100.0) > 0.05) {
            foreach ($owners as &$o) {
                $o['ownership_pct'] = round($o['ownership_pct'] * 100.0 / $sum, 4);
            }
            unset($o);
        }

        return $owners;
    }

    /** Etiqueta corta para listados. */
    public static function labelForAsset(int $assetId, string $fallbackEntityName, string $entityType): string
    {
        if ($entityType === 'company') {
            return $fallbackEntityName;
        }
        $owners = self::forAsset($assetId);
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
