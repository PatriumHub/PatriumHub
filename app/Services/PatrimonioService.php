<?php

declare(strict_types=1);

/**
 * Motor patrimonial Fase 4.
 *
 * - entity: solo elementos de esa entidad
 * - personal: elementos de la persona + valor de participaciones; liquidez propia + % de liquidez de empresas
 * - consolidated: suma multi-entidad sin dobles por ownership y con neto de deudas internas
 */
final class PatrimonioService
{
    /**
     * @param list<int>|null $entityIds
     * @return array<string, mixed>
     */
    public static function summarize(
        ?array $entityIds = null,
        string $currency = 'ARS',
        string $mode = 'entity'
    ): array {
        return match ($mode) {
            'personal' => self::personal($entityIds, $currency),
            'consolidated' => self::consolidated($entityIds, $currency),
            default => self::byEntities($entityIds, $currency, false),
        };
    }

    public static function forEntity(int $entityId, string $currency = 'ARS'): array
    {
        $meta = self::entityMeta([$entityId]);
        $type = $meta[$entityId]['type'] ?? 'company';
        if ($type === 'person') {
            $summary = self::personal([$entityId], $currency);
            $row = $summary['by_entity'][0] ?? null;
            if ($row) {
                $row['currency'] = $currency;
                $row['mode'] = 'personal';
                return $row;
            }
        }

        $summary = self::byEntities([$entityId], $currency, false);
        $row = $summary['by_entity'][0] ?? [
            'entity_id' => $entityId,
            'name' => '',
            'type' => $type,
            'accounts' => 0.0,
            'assets' => 0.0,
            'properties' => 0.0,
            'receivables' => 0.0,
            'inventories' => 0.0,
            'participations' => 0.0,
            'liabilities' => 0.0,
            'liquidity' => 0.0,
            'internal_netting' => 0.0,
            'total_assets' => 0.0,
            'net_worth' => 0.0,
        ];
        $row['currency'] = $currency;
        $row['mode'] = 'entity';
        return $row;
    }

    /**
     * Patrimonio personal: propios + participaciones valuadas.
     * @param list<int>|null $personIds null = todas las personas activas
     */
    public static function personal(?array $personIds, string $currency): array
    {
        $pdo = Database::pdo();
        if ($personIds === null) {
            $personIds = array_map(
                'intval',
                $pdo->query("SELECT id FROM entities WHERE is_active = 1 AND type = 'person'")->fetchAll(PDO::FETCH_COLUMN)
            );
        } else {
            $personIds = array_values(array_unique(array_map('intval', $personIds)));
        }

        $base = self::emptySummary($currency, 'personal');
        $base['liquidity_own'] = 0.0;
        $base['liquidity_from_participations'] = 0.0;
        if ($personIds === []) {
            return $base;
        }

        $direct = self::byEntities($personIds, $currency, false);
        $ownershipValues = self::participationValues($personIds, $currency);
        $meta = self::entityMeta($personIds);
        $rows = [];

        foreach ($direct['by_entity'] as $row) {
            $id = (int) $row['entity_id'];
            $part = $ownershipValues[$id] ?? ['total' => 0.0, 'details' => [], 'liquidity' => 0.0];
            $participations = (float) $part['total'];
            $liqFromParts = (float) ($part['liquidity'] ?? 0);
            $ownLiquidity = (float) $row['liquidity'];
            $liquidity = $ownLiquidity + $liqFromParts;
            $totalAssets = (float) $row['total_assets'] + $participations;
            $net = $totalAssets - (float) $row['liabilities'];
            $rows[] = [
                'entity_id' => $id,
                'name' => $row['name'],
                'type' => $meta[$id]['type'] ?? 'person',
                'accounts' => (float) $row['accounts'],
                'assets' => (float) $row['assets'],
                'properties' => (float) $row['properties'],
                'receivables' => (float) $row['receivables'],
                'inventories' => (float) $row['inventories'],
                'participations' => $participations,
                'participation_details' => $part['details'],
                'liabilities' => (float) $row['liabilities'],
                'liquidity' => $liquidity,
                'liquidity_own' => $ownLiquidity,
                'liquidity_from_participations' => $liqFromParts,
                'internal_netting' => 0.0,
                'total_assets' => $totalAssets,
                'net_worth' => $net,
            ];
            $base['accounts'] += (float) $row['accounts'];
            $base['assets'] += (float) $row['assets'];
            $base['properties'] += (float) $row['properties'];
            $base['receivables'] += (float) $row['receivables'];
            $base['inventories'] += (float) $row['inventories'];
            $base['participations'] += $participations;
            $base['liabilities'] += (float) $row['liabilities'];
            $base['liquidity'] += $liquidity;
            $base['liquidity_own'] += $ownLiquidity;
            $base['liquidity_from_participations'] += $liqFromParts;
        }

        usort($rows, static fn ($a, $b) => $b['net_worth'] <=> $a['net_worth']);
        $base['by_entity'] = $rows;
        $base['total_assets'] = $base['accounts'] + $base['assets'] + $base['properties']
            + $base['receivables'] + $base['inventories'] + $base['participations'];
        $base['net_worth'] = $base['total_assets'] - $base['liabilities'];
        $base['mode'] = 'personal';
        $base['note'] = 'Personal: activos propios + % de valuación de empresas. '
            . 'Liquidez = caja propia + % de liquidez de las empresas donde participás.';
        return $base;
    }

    /**
     * Consolidado multi-entidad: suma directa, sin sumar participaciones,
     * y elimina deudas internas entre entidades del conjunto.
     * @param list<int>|null $entityIds
     */
    public static function consolidated(?array $entityIds, string $currency): array
    {
        $pdo = Database::pdo();
        if ($entityIds === null) {
            $entityIds = array_map(
                'intval',
                $pdo->query("SELECT id FROM entities WHERE is_active = 1")->fetchAll(PDO::FETCH_COLUMN)
            );
        } else {
            $entityIds = array_values(array_unique(array_map('intval', $entityIds)));
        }

        $base = self::byEntities($entityIds, $currency, true);
        $netting = self::internalNetting($entityIds, $currency);

        // Quitar cobrables/pasivos internos del total consolidado
        $base['receivables'] = max(0.0, $base['receivables'] - $netting);
        $base['liabilities'] = max(0.0, $base['liabilities'] - $netting);
        $base['internal_netting'] = $netting;
        $base['participations'] = 0.0;
        $base['total_assets'] = $base['accounts'] + $base['assets'] + $base['properties']
            + $base['receivables'] + $base['inventories'];
        $base['net_worth'] = $base['total_assets'] - $base['liabilities'];
        $base['mode'] = 'consolidated';
        $base['note'] = 'Consolidado sin doble conteo por participaciones; deudas internas neteadas.';

        // by_entity queda como detalle bruto (útil para explorar); el total ya está neteado
        return $base;
    }

    /**
     * @param list<int>|null $entityIds
     * @return array<string, mixed>
     */
    private static function byEntities(?array $entityIds, string $currency, bool $asConsolidatedShell): array
    {
        $pdo = Database::pdo();
        $ids = $entityIds;
        if ($ids === null) {
            $ids = array_map(
                'intval',
                $pdo->query("SELECT id FROM entities WHERE is_active = 1")->fetchAll(PDO::FETCH_COLUMN)
            );
        } else {
            $ids = array_values(array_unique(array_map('intval', $ids)));
        }

        $empty = self::emptySummary($currency, $asConsolidatedShell ? 'consolidated' : 'entity');
        if ($ids === []) {
            return $empty;
        }

        $byEntity = [];
        foreach ($ids as $id) {
            $byEntity[$id] = [
                'entity_id' => $id,
                'accounts' => 0.0,
                'assets' => 0.0,
                'properties' => 0.0,
                'receivables' => 0.0,
                'inventories' => 0.0,
                'participations' => 0.0,
                'liabilities' => 0.0,
                'liquidity' => 0.0,
                'internal_netting' => 0.0,
            ];
        }

        self::accumulateAccounts($byEntity, $ids, $currency);
        self::accumulateAssets($byEntity, $ids, $currency);
        self::accumulateProperties($byEntity, $ids, $currency);
        self::accumulateReceivables($byEntity, $ids, $currency);
        self::accumulateInventories($byEntity, $ids, $currency);
        self::accumulateLiabilities($byEntity, $ids, $currency);

        $meta = self::entityMeta($ids);
        $rows = [];
        foreach ($byEntity as $id => $row) {
            $totalAssets = $row['accounts'] + $row['assets'] + $row['properties'] + $row['receivables'] + $row['inventories'];
            $net = $totalAssets - $row['liabilities'];
            $rows[] = [
                'entity_id' => $id,
                'name' => $meta[$id]['name'] ?? ('#' . $id),
                'type' => $meta[$id]['type'] ?? 'company',
                'accounts' => $row['accounts'],
                'assets' => $row['assets'],
                'properties' => $row['properties'],
                'receivables' => $row['receivables'],
                'inventories' => $row['inventories'],
                'participations' => 0.0,
                'liabilities' => $row['liabilities'],
                'liquidity' => $row['liquidity'],
                'internal_netting' => 0.0,
                'total_assets' => $totalAssets,
                'net_worth' => $net,
            ];
            $empty['accounts'] += $row['accounts'];
            $empty['assets'] += $row['assets'];
            $empty['properties'] += $row['properties'];
            $empty['receivables'] += $row['receivables'];
            $empty['inventories'] += $row['inventories'];
            $empty['liabilities'] += $row['liabilities'];
            $empty['liquidity'] += $row['liquidity'];
        }

        usort($rows, static fn ($a, $b) => $b['net_worth'] <=> $a['net_worth']);
        $empty['by_entity'] = $rows;
        $empty['total_assets'] = $empty['accounts'] + $empty['assets'] + $empty['properties']
            + $empty['receivables'] + $empty['inventories'];
        $empty['net_worth'] = $empty['total_assets'] - $empty['liabilities'];
        return $empty;
    }

    /** @return array<string, mixed> */
    private static function emptySummary(string $currency, string $mode): array
    {
        return [
            'currency' => $currency,
            'mode' => $mode,
            'accounts' => 0.0,
            'assets' => 0.0,
            'properties' => 0.0,
            'receivables' => 0.0,
            'inventories' => 0.0,
            'participations' => 0.0,
            'liabilities' => 0.0,
            'liquidity' => 0.0,
            'internal_netting' => 0.0,
            'total_assets' => 0.0,
            'net_worth' => 0.0,
            'by_entity' => [],
        ];
    }

    /**
     * Valor atribuible de ownerships por persona.
     * @param list<int> $personIds
     * @return array<int, array{total:float, liquidity:float, details:list<array>}>
     */
    public static function participationValues(array $personIds, string $currency): array
    {
        $out = [];
        foreach ($personIds as $id) {
            $out[$id] = ['total' => 0.0, 'liquidity' => 0.0, 'details' => []];
        }
        if ($personIds === []) {
            return $out;
        }

        $in = self::placeholders($personIds);
        $stmt = Database::pdo()->prepare(
            "SELECT o.owner_entity_id, o.company_entity_id, o.ownership_pct, e.name AS company_name
             FROM ownerships o
             INNER JOIN entities e ON e.id = o.company_entity_id
             WHERE o.owner_entity_id IN ($in)"
        );
        $stmt->execute(self::bindIds($personIds));

        $companyCache = [];
        foreach ($stmt->fetchAll() as $own) {
            $ownerId = (int) $own['owner_entity_id'];
            $companyId = (int) $own['company_entity_id'];
            $pct = (float) $own['ownership_pct'];

            if (!isset($companyCache[$companyId])) {
                $companySummary = self::byEntities([$companyId], $currency, false);
                $companyRow = $companySummary['by_entity'][0] ?? null;
                $companyCache[$companyId] = [
                    'value' => self::companyValuation($companyId, $currency),
                    'liquidity' => (float) ($companyRow['liquidity'] ?? $companySummary['liquidity'] ?? 0),
                    'accounts' => (float) ($companyRow['accounts'] ?? $companySummary['accounts'] ?? 0),
                    'net_worth' => (float) ($companyRow['net_worth'] ?? $companySummary['net_worth'] ?? 0),
                ];
            }

            $companyValue = (float) $companyCache[$companyId]['value'];
            $companyLiquidity = (float) $companyCache[$companyId]['liquidity'];
            $attributable = $companyValue * $pct / 100.0;
            $attributableLiquidity = $companyLiquidity * $pct / 100.0;

            if (!isset($out[$ownerId])) {
                $out[$ownerId] = ['total' => 0.0, 'liquidity' => 0.0, 'details' => []];
            }
            $out[$ownerId]['total'] += $attributable;
            $out[$ownerId]['liquidity'] += $attributableLiquidity;
            $out[$ownerId]['details'][] = [
                'company_entity_id' => $companyId,
                'company_name' => $own['company_name'],
                'ownership_pct' => $pct,
                'company_value' => $companyValue,
                'company_liquidity' => $companyLiquidity,
                'attributable' => $attributable,
                'attributable_liquidity' => $attributableLiquidity,
            ];
        }
        return $out;
    }

    /** Valuación de empresa: manual vigente, o automática (patrimonio neto). */
    public static function companyValuation(int $companyEntityId, string $currency): float
    {
        if (class_exists('ValuationService')) {
            return (float) ValuationService::current($companyEntityId, $currency)['value'];
        }
        $entity = self::byEntities([$companyEntityId], $currency, false);
        return (float) ($entity['net_worth'] ?? 0);
    }

    /** Monto de cobrables internos entre entidades del set (también = pasivos espejo). */
    public static function internalNetting(array $entityIds, string $currency): float
    {
        if (count($entityIds) < 2) {
            return 0.0;
        }
        $in = self::placeholders($entityIds);
        $sql = "SELECT COALESCE(SUM(outstanding_amount),0)
                FROM receivables
                WHERE status IN ('current','overdue')
                  AND include_in_net_worth = 1
                  AND currency_code = :cur
                  AND entity_id IN ($in)
                  AND debtor_entity_id IN ($in)";
        // placeholders duplicated for two IN clauses — need unique names
        $bind = ['cur' => $currency];
        $partsA = [];
        $partsB = [];
        foreach ($entityIds as $i => $id) {
            $partsA[] = ':a' . $i;
            $partsB[] = ':b' . $i;
            $bind['a' . $i] = $id;
            $bind['b' . $i] = $id;
        }
        $sql = 'SELECT COALESCE(SUM(outstanding_amount),0)
                FROM receivables
                WHERE status IN (\'current\',\'overdue\')
                  AND include_in_net_worth = 1
                  AND currency_code = :cur
                  AND entity_id IN (' . implode(',', $partsA) . ')
                  AND debtor_entity_id IN (' . implode(',', $partsB) . ')';
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($bind);
        return (float) $stmt->fetchColumn();
    }

    /** @param array<int, array> $byEntity @param list<int> $ids */
    private static function accumulateAccounts(array &$byEntity, array $ids, string $currency): void
    {
        $in = self::placeholders($ids);
        $sql = "SELECT entity_id, COALESCE(SUM(balance),0) AS total
                FROM accounts
                WHERE status = 'active' AND include_in_net_worth = 1
                  AND currency_code = :cur AND entity_id IN ($in)
                GROUP BY entity_id";
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute(self::bindIds($ids, ['cur' => $currency]));
        foreach ($stmt->fetchAll() as $r) {
            $id = (int) $r['entity_id'];
            if (isset($byEntity[$id])) {
                $byEntity[$id]['accounts'] += (float) $r['total'];
                $byEntity[$id]['liquidity'] += (float) $r['total'];
            }
        }
    }

    /** @param array<int, array> $byEntity @param list<int> $ids */
    private static function accumulateAssets(array &$byEntity, array $ids, string $currency): void
    {
        $in = self::placeholders($ids);
        // Empresas: valor completo del activo (el % personal va por participaciones).
        // Personas: sí aplica ownership_pct del activo (co-titularidad).
        $sql = "SELECT a.entity_id,
                       COALESCE(SUM(
                           CASE WHEN e.type = 'company' THEN a.current_value
                                ELSE a.current_value * a.ownership_pct / 100
                           END
                       ), 0) AS total
                FROM assets a
                INNER JOIN entities e ON e.id = a.entity_id
                WHERE a.status = 'active' AND a.include_in_net_worth = 1
                  AND a.currency_code = :cur AND a.entity_id IN ($in)
                GROUP BY a.entity_id";
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute(self::bindIds($ids, ['cur' => $currency]));
        foreach ($stmt->fetchAll() as $r) {
            $id = (int) $r['entity_id'];
            if (isset($byEntity[$id])) {
                $byEntity[$id]['assets'] += (float) $r['total'];
            }
        }
    }

    /** @param array<int, array> $byEntity @param list<int> $ids */
    private static function accumulateProperties(array &$byEntity, array $ids, string $currency): void
    {
        $in = self::placeholders($ids);
        $sql = "SELECT p.entity_id,
                       COALESCE(SUM(
                           CASE WHEN e.type = 'company' THEN p.current_value
                                ELSE p.current_value * p.ownership_pct / 100
                           END
                       ), 0) AS total
                FROM properties p
                INNER JOIN entities e ON e.id = p.entity_id
                WHERE p.status = 'active' AND p.include_in_net_worth = 1
                  AND p.currency_code = :cur AND p.entity_id IN ($in)
                GROUP BY p.entity_id";
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute(self::bindIds($ids, ['cur' => $currency]));
        foreach ($stmt->fetchAll() as $r) {
            $id = (int) $r['entity_id'];
            if (isset($byEntity[$id])) {
                $byEntity[$id]['properties'] += (float) $r['total'];
            }
        }
    }

    /** @param array<int, array> $byEntity @param list<int> $ids */
    private static function accumulateReceivables(array &$byEntity, array $ids, string $currency): void
    {
        $in = self::placeholders($ids);
        $sql = "SELECT entity_id, COALESCE(SUM(outstanding_amount),0) AS total
                FROM receivables
                WHERE status IN ('current','overdue') AND include_in_net_worth = 1
                  AND currency_code = :cur AND entity_id IN ($in)
                GROUP BY entity_id";
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute(self::bindIds($ids, ['cur' => $currency]));
        foreach ($stmt->fetchAll() as $r) {
            $id = (int) $r['entity_id'];
            if (isset($byEntity[$id])) {
                $byEntity[$id]['receivables'] += (float) $r['total'];
            }
        }
    }

    /** @param array<int, array> $byEntity @param list<int> $ids */
    private static function accumulateInventories(array &$byEntity, array $ids, string $currency): void
    {
        $in = self::placeholders($ids);
        $sql = "SELECT entity_id, COALESCE(SUM(stock_value),0) AS total
                FROM inventories
                WHERE include_in_net_worth = 1
                  AND currency_code = :cur AND entity_id IN ($in)
                GROUP BY entity_id";
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute(self::bindIds($ids, ['cur' => $currency]));
        foreach ($stmt->fetchAll() as $r) {
            $id = (int) $r['entity_id'];
            if (isset($byEntity[$id])) {
                $byEntity[$id]['inventories'] += (float) $r['total'];
            }
        }
    }

    /** @param array<int, array> $byEntity @param list<int> $ids */
    private static function accumulateLiabilities(array &$byEntity, array $ids, string $currency): void
    {
        $in = self::placeholders($ids);
        $sql = "SELECT entity_id, COALESCE(SUM(outstanding_amount),0) AS total
                FROM liabilities
                WHERE status = 'open' AND include_in_net_worth = 1
                  AND currency_code = :cur AND entity_id IN ($in)
                GROUP BY entity_id";
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute(self::bindIds($ids, ['cur' => $currency]));
        foreach ($stmt->fetchAll() as $r) {
            $id = (int) $r['entity_id'];
            if (isset($byEntity[$id])) {
                $byEntity[$id]['liabilities'] += (float) $r['total'];
            }
        }
    }

    /** @param list<int> $ids */
    private static function placeholders(array $ids): string
    {
        return implode(',', array_map(static fn ($i) => ':id' . $i, array_keys($ids)));
    }

    /** @param list<int> $ids */
    private static function bindIds(array $ids, array $extra = []): array
    {
        $bind = $extra;
        foreach ($ids as $i => $id) {
            $bind['id' . $i] = $id;
        }
        return $bind;
    }

    /** @param list<int> $ids @return array<int, array{name:string,type:string}> */
    private static function entityMeta(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $in = self::placeholders($ids);
        $stmt = Database::pdo()->prepare("SELECT id, name, type FROM entities WHERE id IN ($in)");
        $stmt->execute(self::bindIds($ids));
        $out = [];
        foreach ($stmt->fetchAll() as $r) {
            $out[(int) $r['id']] = ['name' => $r['name'], 'type' => $r['type']];
        }
        return $out;
    }
}
