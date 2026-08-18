<?php

declare(strict_types=1);

/**
 * Agregados para gráficos y alertas del dashboard (sin volverse un ERP).
 */
final class DashboardAnalyticsService
{
    /** @return array{labels:list<string>,values:list<float>} */
    public static function byCurrency(?array $entityIds = null): array
    {
        $pdo = Database::pdo();
        $totals = [];

        $add = static function (string $sql, array $params = []) use ($pdo, &$totals): void {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            foreach ($stmt->fetchAll() as $row) {
                $c = (string) $row['currency_code'];
                $totals[$c] = ($totals[$c] ?? 0.0) + (float) $row['total'];
            }
        };

        $entityFilter = '';
        $assetEntityFilter = '';
        $propEntityFilter = '';
        $params = [];
        if ($entityIds !== null && $entityIds !== []) {
            $ph = [];
            foreach (array_values($entityIds) as $i => $id) {
                $ph[] = ':e' . $i;
                $params['e' . $i] = (int) $id;
            }
            $in = implode(',', $ph);
            $entityFilter = ' AND entity_id IN (' . $in . ')';
            $assetEntityFilter = ' AND a.entity_id IN (' . $in . ')';
            $propEntityFilter = ' AND p.entity_id IN (' . $in . ')';
        }

        $add(
            "SELECT currency_code, COALESCE(SUM(balance),0) AS total FROM accounts
             WHERE status = 'active' AND include_in_net_worth = 1{$entityFilter}
             GROUP BY currency_code",
            $params
        );
        $add(
            "SELECT a.currency_code, COALESCE(SUM(a.current_value),0) AS total
             FROM assets a
             INNER JOIN entities e ON e.id = a.entity_id AND e.type = 'company'
             WHERE a.status = 'active' AND a.include_in_net_worth = 1{$assetEntityFilter}
             GROUP BY a.currency_code",
            $params
        );
        AssetOwnerService::ensureSchema();
        if ($entityIds !== null && $entityIds !== []) {
            $aoParams = [];
            $ph = [];
            foreach (array_values($entityIds) as $i => $id) {
                $key = 'ao' . $i;
                $ph[] = ':' . $key;
                $aoParams[$key] = (int) $id;
            }
            $aoIn = implode(',', $ph);
            $add(
                "SELECT a.currency_code,
                        COALESCE(SUM(a.current_value * ao.ownership_pct / 100),0) AS total
                 FROM asset_owners ao
                 INNER JOIN assets a ON a.id = ao.asset_id
                 INNER JOIN entities e ON e.id = ao.entity_id AND e.type = 'person'
                 WHERE a.status = 'active' AND a.include_in_net_worth = 1
                   AND ao.entity_id IN ($aoIn)
                 GROUP BY a.currency_code",
                $aoParams
            );
            $add(
                "SELECT a.currency_code,
                        COALESCE(SUM(a.current_value * a.ownership_pct / 100),0) AS total
                 FROM assets a
                 INNER JOIN entities e ON e.id = a.entity_id AND e.type = 'person'
                 WHERE a.status = 'active' AND a.include_in_net_worth = 1
                   AND a.entity_id IN ($aoIn)
                   AND NOT EXISTS (SELECT 1 FROM asset_owners x WHERE x.asset_id = a.id)
                 GROUP BY a.currency_code",
                $aoParams
            );
        } else {
            $add(
                "SELECT a.currency_code,
                        COALESCE(SUM(a.current_value * ao.ownership_pct / 100),0) AS total
                 FROM asset_owners ao
                 INNER JOIN assets a ON a.id = ao.asset_id
                 INNER JOIN entities e ON e.id = ao.entity_id AND e.type = 'person'
                 WHERE a.status = 'active' AND a.include_in_net_worth = 1
                 GROUP BY a.currency_code",
                []
            );
            $add(
                "SELECT a.currency_code,
                        COALESCE(SUM(a.current_value * a.ownership_pct / 100),0) AS total
                 FROM assets a
                 INNER JOIN entities e ON e.id = a.entity_id AND e.type = 'person'
                 WHERE a.status = 'active' AND a.include_in_net_worth = 1
                   AND NOT EXISTS (SELECT 1 FROM asset_owners x WHERE x.asset_id = a.id)
                 GROUP BY a.currency_code",
                []
            );
        }
        $add(
            "SELECT p.currency_code,
                    COALESCE(SUM(
                        CASE WHEN e.type = 'company' THEN p.current_value
                             ELSE p.current_value * p.ownership_pct / 100
                        END
                    ),0) AS total
             FROM properties p
             INNER JOIN entities e ON e.id = p.entity_id
             WHERE p.status = 'active' AND p.include_in_net_worth = 1{$propEntityFilter}
             GROUP BY p.currency_code",
            $params
        );
        $add(
            "SELECT currency_code, COALESCE(SUM(outstanding_amount),0) AS total FROM receivables
             WHERE status IN ('current','overdue') AND include_in_net_worth = 1{$entityFilter}
             GROUP BY currency_code",
            $params
        );
        $add(
            "SELECT currency_code, COALESCE(SUM(stock_value),0) AS total FROM inventories
             WHERE include_in_net_worth = 1{$entityFilter}
             GROUP BY currency_code",
            $params
        );

        ksort($totals);
        return [
            'labels' => array_keys($totals),
            'values' => array_map('floatval', array_values($totals)),
        ];
    }

    /**
     * Ranking de variación de neto por entidad.
     * Prefiere delta entre los 2 últimos snapshots; si hay uno solo, compara vs patrimonio actual.
     * @return array{labels:list<string>,values:list<float>,ids:list<int>}
     */
    public static function entityRanking(string $currency, int $limit = 8): array
    {
        $stmt = Database::pdo()->prepare(
            "SELECT s.entity_id, e.name, s.net_worth, s.captured_at
             FROM net_worth_snapshots s
             INNER JOIN entities e ON e.id = s.entity_id
             WHERE s.currency_code = :c
               AND s.entity_id IS NOT NULL
               AND e.is_active = 1
               AND s.scope IN ('entity','personal')
             ORDER BY s.entity_id ASC, s.captured_at DESC, s.id DESC"
        );
        $stmt->execute(['c' => $currency]);
        $latest = [];
        $prev = [];
        foreach ($stmt->fetchAll() as $row) {
            $id = (int) $row['entity_id'];
            if (!isset($latest[$id])) {
                $latest[$id] = $row;
                continue;
            }
            if (!isset($prev[$id])) {
                $prev[$id] = $row;
            }
        }

        $deltas = [];
        foreach ($latest as $id => $now) {
            if (isset($prev[$id])) {
                $delta = (float) $now['net_worth'] - (float) $prev[$id]['net_worth'];
            } else {
                // Un solo snapshot: variación desde ese baseline vs valor actual.
                $live = PatrimonioService::forEntity($id, $currency);
                $delta = (float) ($live['net_worth'] ?? 0) - (float) $now['net_worth'];
            }
            $deltas[] = [
                'id' => $id,
                'name' => (string) $now['name'],
                'delta' => $delta,
            ];
        }
        usort($deltas, static fn ($a, $b) => abs($b['delta']) <=> abs($a['delta']));
        $deltas = array_slice($deltas, 0, $limit);

        return [
            'labels' => array_column($deltas, 'name'),
            'values' => array_map('floatval', array_column($deltas, 'delta')),
            'ids' => array_map('intval', array_column($deltas, 'id')),
        ];
    }

    /**
     * Flujo período actual vs período anterior de igual duración.
     * @return array{current:array{income:float,expense:float},previous:array{income:float,expense:float},from:string,to:string,prev_from:string,prev_to:string}
     */
    public static function flowComparison(
        ?int $entityId,
        string $currency,
        ?string $from,
        ?string $to
    ): array {
        $toDt = $to ? new DateTimeImmutable($to) : new DateTimeImmutable('today');
        $fromDt = $from ? new DateTimeImmutable($from) : $toDt->modify('first day of this month');
        if ($fromDt > $toDt) {
            [$fromDt, $toDt] = [$toDt, $fromDt];
        }
        $days = max(1, (int) $fromDt->diff($toDt)->days + 1);
        $prevTo = $fromDt->modify('-1 day');
        $prevFrom = $prevTo->modify('-' . ($days - 1) . ' days');

        $current = self::flowBetween($entityId, $currency, $fromDt->format('Y-m-d'), $toDt->format('Y-m-d'));
        $previous = self::flowBetween($entityId, $currency, $prevFrom->format('Y-m-d'), $prevTo->format('Y-m-d'));

        return [
            'current' => $current,
            'previous' => $previous,
            'from' => $fromDt->format('Y-m-d'),
            'to' => $toDt->format('Y-m-d'),
            'prev_from' => $prevFrom->format('Y-m-d'),
            'prev_to' => $prevTo->format('Y-m-d'),
        ];
    }

    /** @return list<array{type:string,level:string,message:string,href:?string}> */
    public static function alerts(?int $entityId = null): array
    {
        $pdo = Database::pdo();
        $out = [];

        $sql = "SELECT id, name, provider, status, last_sync_at, last_error
                FROM integrations WHERE status != 'revoked'";
        $params = [];
        if ($entityId) {
            $sql .= ' AND entity_id = :e';
            $params['e'] = $entityId;
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        foreach ($stmt->fetchAll() as $row) {
            if ($row['status'] === 'error') {
                $out[] = [
                    'type' => 'integration',
                    'level' => 'danger',
                    'message' => 'Integración con error: ' . $row['name'] . ($row['last_error'] ? (' — ' . $row['last_error']) : ''),
                    'href' => '/integraciones/' . $row['id'],
                ];
            } elseif (IntegrationService::isStale($row['last_sync_at'] ?? null)) {
                $out[] = [
                    'type' => 'integration',
                    'level' => 'warn',
                    'message' => 'Sync desactualizada: ' . $row['name'] . ' (' . ($row['last_sync_at'] ?? 'nunca') . ')',
                    'href' => '/integraciones/' . $row['id'],
                ];
            }
        }

        $recvSql = "SELECT COUNT(*) FROM receivables
                    WHERE status = 'overdue' AND include_in_net_worth = 1";
        $recvParams = [];
        if ($entityId) {
            $recvSql .= ' AND entity_id = :e';
            $recvParams['e'] = $entityId;
        }
        $recvStmt = $pdo->prepare($recvSql);
        $recvStmt->execute($recvParams);
        $overdue = (int) $recvStmt->fetchColumn();
        if ($overdue > 0) {
            $out[] = [
                'type' => 'receivable',
                'level' => 'warn',
                'message' => $overdue . ' cobrable(s) vencido(s)',
                'href' => '/cobrables',
            ];
        }

        $liabSql = "SELECT name, outstanding_amount, currency_code FROM liabilities
                    WHERE status = 'open' AND include_in_net_worth = 1
                      AND outstanding_amount >= 100000";
        $liabParams = [];
        if ($entityId) {
            $liabSql .= ' AND entity_id = :e';
            $liabParams['e'] = $entityId;
        }
        $liabSql .= ' ORDER BY outstanding_amount DESC LIMIT 5';
        $liabStmt = $pdo->prepare($liabSql);
        $liabStmt->execute($liabParams);
        foreach ($liabStmt->fetchAll() as $row) {
            $out[] = [
                'type' => 'liability',
                'level' => 'warn',
                'message' => 'Pasivo relevante: ' . $row['name'] . ' (' . $row['currency_code'] . ' ' .
                    number_format((float) $row['outstanding_amount'], 0, ',', '.') . ')',
                'href' => '/pasivos',
            ];
        }

        return $out;
    }

    /**
     * Serie mensual de ventas WooCommerce (sales_metrics).
     * @return array{labels:list<string>,sales:list<float>,orders:list<int>,rows:list<array<string,mixed>>,total_sales:float,total_orders:int}
     */
    public static function wooSalesSeries(?int $entityId = null, ?string $currency = null, int $months = 12): array
    {
        $sql = 'SELECT sm.*, e.name AS entity_name, i.name AS integration_name
                FROM sales_metrics sm
                INNER JOIN entities e ON e.id = sm.entity_id
                LEFT JOIN integrations i ON i.id = sm.integration_id
                WHERE 1=1';
        $params = [];
        if ($entityId) {
            $sql .= ' AND sm.entity_id = :e';
            $params['e'] = $entityId;
        }
        if ($currency) {
            $sql .= ' AND sm.currency_code = :c';
            $params['c'] = $currency;
        }
        $sql .= ' ORDER BY sm.period_start DESC LIMIT ' . max(1, min(36, $months));
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        // Chronological for charts
        $chrono = array_reverse($rows);
        $labels = [];
        $sales = [];
        $orders = [];
        $totalSales = 0.0;
        $totalOrders = 0;
        foreach ($chrono as $r) {
            $labels[] = substr((string) $r['period_start'], 0, 7);
            $sales[] = (float) $r['sales_total'];
            $orders[] = (int) $r['orders_count'];
            $totalSales += (float) $r['sales_total'];
            $totalOrders += (int) $r['orders_count'];
        }

        return [
            'labels' => $labels,
            'sales' => $sales,
            'orders' => $orders,
            'rows' => $rows,
            'total_sales' => $totalSales,
            'total_orders' => $totalOrders,
        ];
    }

    /**
     * Serie mensual de ingresos (últimos N meses, con ceros).
     * @param 'mp'|'cash' $channel
     * @return array{labels:list<string>,sales:list<float>,total_sales:float}
     */
    public static function channelSalesSeries(
        int $entityId,
        string $currency,
        string $channel,
        int $months = 12
    ): array {
        $months = max(1, min(36, $months));
        $labels = [];
        $map = [];
        $cursor = new DateTimeImmutable('first day of this month');
        for ($i = $months - 1; $i >= 0; $i--) {
            $ym = $cursor->modify("-{$i} months")->format('Y-m');
            $labels[] = $ym;
            $map[$ym] = 0.0;
        }
        $from = ($labels[0] ?? date('Y-m')) . '-01 00:00:00';

        if ($channel === 'mp') {
            $sql = "SELECT DATE_FORMAT(t.occurred_at, '%Y-%m') AS ym, COALESCE(SUM(t.amount),0) AS total
                    FROM transactions t
                    INNER JOIN accounts a ON a.id = t.account_id
                    WHERE t.entity_id = :e AND t.currency_code = :c
                      AND t.type = 'income' AND a.type = 'mercadopago'
                      AND t.occurred_at >= :from
                    GROUP BY ym";
        } else {
            $sql = "SELECT DATE_FORMAT(t.occurred_at, '%Y-%m') AS ym, COALESCE(SUM(t.amount),0) AS total
                    FROM transactions t
                    WHERE t.entity_id = :e AND t.currency_code = :c
                      AND t.type = 'income'
                      AND (
                        t.description LIKE 'Venta caja%'
                        OR JSON_UNQUOTE(JSON_EXTRACT(t.metadata_json, '$.sale_channel')) = 'cash'
                      )
                      AND t.occurred_at >= :from
                    GROUP BY ym";
        }

        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute(['e' => $entityId, 'c' => $currency, 'from' => $from]);
        foreach ($stmt->fetchAll() as $row) {
            $ym = (string) ($row['ym'] ?? '');
            if (isset($map[$ym])) {
                $map[$ym] = (float) $row['total'];
            }
        }

        $sales = [];
        $total = 0.0;
        foreach ($labels as $ym) {
            $v = $map[$ym] ?? 0.0;
            $sales[] = $v;
            $total += $v;
        }

        return [
            'labels' => $labels,
            'sales' => $sales,
            'total_sales' => round($total, 2),
        ];
    }

    /**
     * Neto mensual de movimientos marcados "en caja" (ingresos − egresos).
     * @return array{labels:list<string>,sales:list<float>,total_sales:float}
     */
    public static function enCajaSeries(int $entityId, string $currency, int $months = 12): array
    {
        $months = max(1, min(36, $months));
        $labels = [];
        $map = [];
        $cursor = new DateTimeImmutable('first day of this month');
        for ($i = $months - 1; $i >= 0; $i--) {
            $ym = $cursor->modify("-{$i} months")->format('Y-m');
            $labels[] = $ym;
            $map[$ym] = 0.0;
        }
        $from = ($labels[0] ?? date('Y-m')) . '-01 00:00:00';

        $sql = "SELECT DATE_FORMAT(t.occurred_at, '%Y-%m') AS ym,
                       COALESCE(SUM(CASE WHEN t.type = 'income' THEN t.amount
                                         WHEN t.type = 'expense' THEN -t.amount
                                         ELSE 0 END), 0) AS total
                FROM transactions t
                WHERE t.entity_id = :e AND t.currency_code = :c
                  AND t.occurred_at >= :from
                  AND (
                    JSON_UNQUOTE(JSON_EXTRACT(t.metadata_json, '$.en_caja')) = 'true'
                    OR t.description LIKE 'En caja%'
                  )
                GROUP BY ym";
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute(['e' => $entityId, 'c' => $currency, 'from' => $from]);
        foreach ($stmt->fetchAll() as $row) {
            $ym = (string) ($row['ym'] ?? '');
            if (isset($map[$ym])) {
                $map[$ym] = (float) $row['total'];
            }
        }

        $sales = [];
        $total = 0.0;
        foreach ($labels as $ym) {
            $v = $map[$ym] ?? 0.0;
            $sales[] = $v;
            $total += $v;
        }

        return [
            'labels' => $labels,
            'sales' => $sales,
            'total_sales' => round($total, 2),
        ];
    }

    /**
     * @return array{
     *   cash:float,en_caja:float,mp:float,inventory:float,
     *   sales:float,mp_sales:float,cash_sales:float,sales_total:float,orders:int
     * }
     */
    public static function companyExtras(int $entityId, string $currency): array
    {
        $pdo = Database::pdo();

        $cash = $pdo->prepare(
            "SELECT COALESCE(SUM(balance),0) FROM accounts
             WHERE entity_id = :e AND currency_code = :c AND status = 'active'
               AND include_in_net_worth = 1
               AND type IN ('bank','checking','savings','cash','wallet','virtual')"
        );
        $cash->execute(['e' => $entityId, 'c' => $currency]);

        // "En caja" = saldo de cuentas tipo efectivo (billetes). No se suma aparte al patrimonio.
        $enCaja = $pdo->prepare(
            "SELECT COALESCE(SUM(balance),0) FROM accounts
             WHERE entity_id = :e AND currency_code = :c AND status = 'active'
               AND include_in_net_worth = 1
               AND type = 'cash'"
        );
        $enCaja->execute(['e' => $entityId, 'c' => $currency]);

        $mp = $pdo->prepare(
            "SELECT COALESCE(SUM(balance),0), COALESCE(SUM(balance_held),0)
             FROM accounts
             WHERE entity_id = :e AND currency_code = :c AND status = 'active'
               AND type = 'mercadopago'"
        );
        $mp->execute(['e' => $entityId, 'c' => $currency]);
        $mpRow = $mp->fetch(\PDO::FETCH_NUM) ?: [0, 0];
        $mpAvailable = (float) $mpRow[0];
        if ($mpAvailable <= 0.009 && (float) $mpRow[1] <= 0.009) {
            $mpAny = $pdo->prepare(
                "SELECT COALESCE(SUM(balance),0) FROM accounts
                 WHERE entity_id = :e AND status = 'active' AND type = 'mercadopago'"
            );
            $mpAny->execute(['e' => $entityId]);
            $mpAvailable = (float) $mpAny->fetchColumn();
        }

        $inv = $pdo->prepare(
            "SELECT COALESCE(SUM(stock_value),0) FROM inventories
             WHERE entity_id = :e AND currency_code = :c AND include_in_net_worth = 1"
        );
        $inv->execute(['e' => $entityId, 'c' => $currency]);

        $ordersStmt = $pdo->prepare(
            'SELECT COALESCE(SUM(orders_count),0) FROM sales_metrics
             WHERE entity_id = :e AND currency_code = :c'
        );
        $ordersStmt->execute(['e' => $entityId, 'c' => $currency]);

        $wcSales = self::wcSalesForCompany($entityId, $currency, null);
        $mpSales = self::mpIncomeForCompany($entityId, $currency, null);

        return [
            'cash' => (float) $cash->fetchColumn(),
            'en_caja' => (float) $enCaja->fetchColumn(),
            'mp' => $mpAvailable,
            'inventory' => (float) $inv->fetchColumn(),
            'sales' => $wcSales,
            'mp_sales' => $mpSales,
            'cash_sales' => 0.0,
            'sales_total' => round($wcSales + $mpSales, 2),
            'orders' => (int) $ordersStmt->fetchColumn(),
        ];
    }

    private static function isCompany(int $entityId): bool
    {
        $stmt = Database::pdo()->prepare("SELECT type FROM entities WHERE id = :e LIMIT 1");
        $stmt->execute(['e' => $entityId]);
        return (string) $stmt->fetchColumn() === 'company';
    }

    /** Ventas WooCommerce. $months null = histórico completo. */
    public static function wcSalesForCompany(int $entityId, string $currency, ?int $months = null): float
    {
        if (!self::isCompany($entityId)) {
            return 0.0;
        }
        $sql = 'SELECT COALESCE(SUM(sales_total),0) FROM sales_metrics
                WHERE entity_id = :e AND currency_code = :c';
        $params = ['e' => $entityId, 'c' => $currency];
        if ($months !== null) {
            $sql .= ' AND period_start >= :from';
            $params['from'] = date('Y-m-d', strtotime('-' . max(1, $months) . ' months'));
        }
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        return round((float) $stmt->fetchColumn(), 2);
    }

    /** Ingresos MP = ventas (solo empresas). $months null = histórico completo. */
    public static function mpIncomeForCompany(int $entityId, string $currency, ?int $months = null): float
    {
        if (!self::isCompany($entityId)) {
            return 0.0;
        }

        $sql = "SELECT COALESCE(SUM(t.amount),0)
                FROM transactions t
                INNER JOIN accounts a ON a.id = t.account_id
                WHERE t.entity_id = :e
                  AND t.currency_code = :c
                  AND t.type = 'income'
                  AND a.type = 'mercadopago'";
        $params = ['e' => $entityId, 'c' => $currency];
        if ($months !== null) {
            $sql .= ' AND t.occurred_at >= :from';
            $params['from'] = date('Y-m-d', strtotime('-' . max(1, $months) . ' months')) . ' 00:00:00';
        }
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        return round((float) $stmt->fetchColumn(), 2);
    }

    /** Ventas en efectivo / caja (solo empresas). $months null = histórico completo. */
    public static function cashSalesForCompany(int $entityId, string $currency, ?int $months = null): float
    {
        if (!self::isCompany($entityId)) {
            return 0.0;
        }

        // Solo ventas de caja explícitas (no aportes de capital a la cuenta efectivo).
        $sql = "SELECT COALESCE(SUM(t.amount),0)
                FROM transactions t
                WHERE t.entity_id = :e
                  AND t.currency_code = :c
                  AND t.type = 'income'
                  AND (
                    t.description LIKE 'Venta caja%'
                    OR JSON_UNQUOTE(JSON_EXTRACT(t.metadata_json, '$.sale_channel')) = 'cash'
                  )";
        $params = ['e' => $entityId, 'c' => $currency];
        if ($months !== null) {
            $sql .= ' AND t.occurred_at >= :from';
            $params['from'] = date('Y-m-d', strtotime('-' . max(1, $months) . ' months')) . ' 00:00:00';
        }
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        return round((float) $stmt->fetchColumn(), 2);
    }

    /** @return array{income:float,expense:float} */
    private static function flowBetween(?int $entityId, string $currency, string $from, string $to): array
    {
        // Todas las cuentas / orígenes (manual + integraciones). No filtra por MP.
        $sql = "SELECT
                    COALESCE(SUM(CASE WHEN type IN ('income','collection','loan','ingreso') THEN amount ELSE 0 END),0) AS income,
                    COALESCE(SUM(CASE WHEN type IN ('expense','payment','egreso') THEN amount ELSE 0 END),0) AS expense
                FROM transactions
                WHERE currency_code = :cur
                  AND occurred_at >= :from
                  AND occurred_at <= :to";
        $params = [
            'cur' => $currency,
            'from' => $from . ' 00:00:00',
            'to' => $to . ' 23:59:59',
        ];
        if ($entityId) {
            $sql .= ' AND entity_id = :e';
            $params['e'] = $entityId;
        }
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch() ?: ['income' => 0, 'expense' => 0];
        return [
            'income' => (float) $row['income'],
            'expense' => (float) $row['expense'],
        ];
    }
}
