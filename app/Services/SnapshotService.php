<?php

declare(strict_types=1);

final class SnapshotService
{
    /** @param array<string,mixed> $summary */
    public static function capture(string $scope, array $summary, ?int $entityId = null): int
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO net_worth_snapshots
             (scope, entity_id, currency_code, captured_at, accounts, assets, properties,
              receivables, inventories, participations, liabilities, internal_netting,
              total_assets, net_worth, payload_json)
             VALUES
             (:scope, :entity_id, :currency, NOW(), :accounts, :assets, :properties,
              :receivables, :inventories, :participations, :liabilities, :internal_netting,
              :total_assets, :net_worth, :payload)'
        );
        $stmt->execute([
            'scope' => $scope,
            'entity_id' => $entityId,
            'currency' => $summary['currency'] ?? 'ARS',
            'accounts' => (float) ($summary['accounts'] ?? 0),
            'assets' => (float) ($summary['assets'] ?? 0),
            'properties' => (float) ($summary['properties'] ?? 0),
            'receivables' => (float) ($summary['receivables'] ?? 0),
            'inventories' => (float) ($summary['inventories'] ?? 0),
            'participations' => (float) ($summary['participations'] ?? 0),
            'liabilities' => (float) ($summary['liabilities'] ?? 0),
            'internal_netting' => (float) ($summary['internal_netting'] ?? 0),
            'total_assets' => (float) ($summary['total_assets'] ?? 0),
            'net_worth' => (float) ($summary['net_worth'] ?? 0),
            'payload' => json_encode([
                'mode' => $summary['mode'] ?? $scope,
                'by_entity_count' => count($summary['by_entity'] ?? []),
            ], JSON_UNESCAPED_UNICODE),
        ]);
        return (int) Database::pdo()->lastInsertId();
    }

    /** Captura consolidado + personal global + cada entidad activa. */
    public static function captureDaily(string $currency = 'ARS'): array
    {
        $ids = [];
        $consolidated = PatrimonioService::summarize(null, $currency, 'consolidated');
        $ids[] = self::capture('consolidated', $consolidated, null);

        $personal = PatrimonioService::summarize(null, $currency, 'personal');
        $ids[] = self::capture('personal', $personal, null);

        $entities = Database::pdo()
            ->query("SELECT id FROM entities WHERE is_active = 1")
            ->fetchAll(PDO::FETCH_COLUMN);
        foreach ($entities as $entityId) {
            $row = PatrimonioService::forEntity((int) $entityId, $currency);
            $scope = (($row['mode'] ?? '') === 'personal') ? 'personal' : 'entity';
            $ids[] = self::capture($scope, $row + ['currency' => $currency], (int) $entityId);
        }

        return $ids;
    }

    /** @return list<array<string,mixed>> */
    public static function history(
        string $scope,
        ?int $entityId,
        string $currency,
        int $limit = 90
    ): array {
        $sql = 'SELECT * FROM net_worth_snapshots
                WHERE scope = :scope AND currency_code = :cur';
        $params = ['scope' => $scope, 'cur' => $currency];
        if ($entityId) {
            $sql .= ' AND entity_id = :e';
            $params['e'] = $entityId;
        } else {
            $sql .= ' AND entity_id IS NULL';
        }
        $sql .= ' ORDER BY captured_at DESC, id DESC LIMIT ' . (int) $limit;
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        return array_reverse($stmt->fetchAll());
    }
}
