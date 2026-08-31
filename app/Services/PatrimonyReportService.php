<?php

declare(strict_types=1);

/**
 * Reporte patrimonial mensual.
 *
 * Todo se calcula desde los datos, sin depender de `net_worth_snapshots`: el total de cada
 * clase es la suma de sus propias líneas, así el reporte siempre cierra consigo mismo y se
 * puede emitir cualquier mes, incluso anterior a la primera captura.
 *
 * El corte es el último día del mes, o hoy si el mes está en curso: nada posterior al corte
 * entra, aunque ya esté cargado.
 *
 * Qué tan histórico es cada número:
 *   · Cuentas → saldo al corte, reconstruido desde los movimientos (ver `accountLines()`).
 *   · Cobrables → cuotas exigibles al corte.
 *   · Presupuestos pendientes → los del período y anteriores sin liquidar.
 *   · Activos, propiedades, inventario, participaciones y deudas → valor vigente: de esas
 *     clases no existe histórico por línea en el esquema.
 *
 * No escribe nada de meses pasados ni captura snapshots.
 */
final class PatrimonyReportService
{
    private const OUTFLOW = "('expense','payment','egreso')";
    private const INFLOW = "('income','collection','loan','ingreso')";

    /**
     * @return array<string,mixed>
     */
    public static function build(
        string $periodYm,
        string $scope = 'all',
        ?int $entityId = null,
        string $currency = 'ARS'
    ): array {
        $periodYm = self::normalizePeriod($periodYm);
        $currency = strtoupper(trim($currency)) ?: 'ARS';
        if ($entityId) {
            $scope = 'entity';
        } elseif (!in_array($scope, ['all', 'people', 'companies'], true)) {
            $scope = 'all';
        }

        $entityIds = self::resolveEntityIds($scope, $entityId);
        $prevYm = self::shiftPeriod($periodYm, -1);
        $yoyYm = self::shiftPeriod($periodYm, -12);

        $figures = self::figures($periodYm, $scope, $entityId, $currency);

        return [
            'period' => $periodYm,
            'period_label' => self::periodLabel($periodYm),
            'prev_period_label' => self::periodLabel($prevYm),
            'yoy_period_label' => self::periodLabel($yoyYm),
            'currency' => $currency,
            'scope' => $scope,
            'scope_label' => self::scopeLabel($scope, $entityId),
            'entity_id' => $entityId,
            'is_current' => $periodYm === date('Y-m'),
            'is_future' => $periodYm > date('Y-m'),
            'generated_at' => date('Y-m-d H:i'),
            'cutoff' => self::cutoffLabel($periodYm),
            'figures' => $figures,
            'prev' => self::figures($prevYm, $scope, $entityId, $currency),
            'yoy' => self::figures($yoyYm, $scope, $entityId, $currency),
            'flow' => self::flow($periodYm, $currency, $entityIds),
            'by_category' => self::expensesByCategory($periodYm, $currency, $entityIds),
            'budget' => self::budgetTotals($periodYm, $currency, $entityIds),
            'by_entity' => self::byEntity($periodYm, $currency, self::breakdownIds($scope, $entityIds)),
        ];
    }

    /**
     * Activo, pasivo y neto de un período, todo sumado desde el detalle.
     *
     * @return array{
     *     assets:list<array<string,mixed>>,
     *     liabilities:list<array<string,mixed>>,
     *     netting:float,
     *     total_assets:float,
     *     total_liabilities:float,
     *     net_worth:float
     * }
     */
    private static function figures(
        string $periodYm,
        string $scope,
        ?int $entityId,
        string $currency
    ): array {
        $entityIds = self::resolveEntityIds($scope, $entityId);

        // Deudas y cobrables entre entidades del propio grupo: se descuentan de los dos lados
        // para no inflar el consolidado. Con una sola entidad no hay nada que netear.
        $netting = $scope === 'all'
            ? self::internalNetting($periodYm, $currency)
            : 0.0;

        $assets = self::assetGroups($periodYm, $currency, $entityIds, $scope);
        $liabilities = self::liabilityGroups($periodYm, $currency, $entityIds);

        $totalAssets = 0.0;
        foreach ($assets as $g) {
            $totalAssets += (float) $g['total'];
        }
        $totalLiabilities = 0.0;
        foreach ($liabilities as $g) {
            $totalLiabilities += (float) $g['total'];
        }

        $totalAssets = max(0.0, $totalAssets - $netting);
        $totalLiabilities = max(0.0, $totalLiabilities - $netting);

        return [
            'assets' => $assets,
            'liabilities' => $liabilities,
            'netting' => $netting,
            'total_assets' => $totalAssets,
            'total_liabilities' => $totalLiabilities,
            'net_worth' => $totalAssets - $totalLiabilities,
        ];
    }

    // ------------------------------------------------------------------- activo

    /**
     * Todas las clases del activo con su detalle. Se emiten siempre, aunque den cero, para que
     * el reporte tenga la misma estructura entre meses y entre alcances.
     *
     * @param list<int> $entityIds
     * @return list<array{group:string,historic:bool,total:float,lines:list<array<string,mixed>>}>
     */
    private static function assetGroups(
        string $periodYm,
        string $currency,
        array $entityIds,
        string $scope
    ): array {
        $next = self::cutoffTs($periodYm);

        $groups = [
            ['group' => 'Cuentas', 'historic' => true, 'sorted' => false,
             'lines' => self::accountLines($currency, $next, $entityIds)],
            ['group' => 'Activos varios', 'historic' => false, 'sorted' => false,
             'lines' => self::assetLines($currency, $entityIds)],
            ['group' => 'Propiedades', 'historic' => false, 'sorted' => false,
             'lines' => self::propertyLines($currency, $entityIds)],
            ['group' => 'Cobrables', 'historic' => true, 'sorted' => true,
             'lines' => self::receivableLines($currency, self::cutoffDate($periodYm), $entityIds)],
            ['group' => 'Inventario', 'historic' => false, 'sorted' => false,
             'lines' => self::inventoryLines($currency, $entityIds)],
            ['group' => 'Participaciones', 'historic' => false, 'sorted' => false,
             'lines' => self::participationLines($currency, $entityIds, $scope)],
        ];

        foreach ($groups as &$g) {
            if (!$g['sorted']) {
                $g['lines'] = self::sortByAmount($g['lines']);
            }
            $g['total'] = self::sumLines($g['lines']);
        }
        unset($g);
        return $groups;
    }

    /**
     * Saldo al corte, reconstruido desde los movimientos.
     *
     * `accounts.balance` es un acumulado que se mueve al guardar cada movimiento, sin mirar
     * `occurred_at`: un ingreso cargado hoy con fecha de mañana ya está sumado. Por eso el saldo
     * de un mes se calcula descontando del saldo actual todo lo que ocurre después del corte,
     * con las mismas reglas de signo que `TransactionController::applyBalanceEffects()`.
     *
     * Tampoco sirve `account_balances`: guarda cuándo se capturó el saldo, no a qué fecha
     * corresponde, así que arrastraría el mismo movimiento adelantado.
     *
     * @param list<int> $entityIds
     * @return list<array<string,mixed>>
     */
    private static function accountLines(string $currency, string $next, array $entityIds): array
    {
        $balance = static fn (string $p): string =>
            "(a.balance - COALESCE((
                SELECT SUM(CASE
                    WHEN t.account_id = a.id AND t.type IN ('income','collection') THEN t.amount
                    WHEN t.account_id = a.id AND t.type IN ('expense','payment','transfer') THEN -t.amount
                    WHEN t.account_id = a.id AND t.type = 'adjustment' THEN
                        CASE WHEN JSON_UNQUOTE(JSON_EXTRACT(t.metadata_json, '$.adjustment_direction')) = 'minus'
                             THEN -t.amount ELSE t.amount END
                    WHEN t.counterparty_account_id = a.id AND t.type = 'transfer' THEN t.amount
                    ELSE 0 END)
                FROM transactions t
                WHERE (t.account_id = a.id OR t.counterparty_account_id = a.id)
                  AND t.occurred_at >= :$p
            ), 0))";
        $lines = [];

        // Empresas: valor completo.
        $params = ['cur' => $currency, 'nx1' => $next];
        $sql = 'SELECT a.name AS label, e.name AS entity_name, 100 AS pct, '
            . $balance('nx1') . " AS amount
                FROM accounts a
                INNER JOIN entities e ON e.id = a.entity_id AND e.type = 'company'
                WHERE a.status = 'active' AND a.include_in_net_worth = 1
                  AND a.currency_code = :cur";
        $sql = self::appendEntityFilter($sql, $params, 'a.entity_id', $entityIds, 'ca');
        $lines = array_merge($lines, self::rows($sql, $params));

        // Personas con co-titulares.
        $params = ['cur' => $currency, 'nx2' => $next];
        $sql = 'SELECT a.name AS label, e.name AS entity_name, ao.ownership_pct AS pct, '
            . $balance('nx2') . " * ao.ownership_pct / 100 AS amount
                FROM account_owners ao
                INNER JOIN accounts a ON a.id = ao.account_id
                INNER JOIN entities e ON e.id = ao.entity_id AND e.type = 'person'
                WHERE a.status = 'active' AND a.include_in_net_worth = 1
                  AND a.currency_code = :cur";
        $sql = self::appendEntityFilter($sql, $params, 'ao.entity_id', $entityIds, 'cb');
        $lines = array_merge($lines, self::rows($sql, $params));

        // Personas sin filas en account_owners (legacy: 100%).
        $params = ['cur' => $currency, 'nx3' => $next];
        $sql = 'SELECT a.name AS label, e.name AS entity_name, 100 AS pct, '
            . $balance('nx3') . " AS amount
                FROM accounts a
                INNER JOIN entities e ON e.id = a.entity_id AND e.type = 'person'
                WHERE a.status = 'active' AND a.include_in_net_worth = 1
                  AND a.currency_code = :cur
                  AND NOT EXISTS (SELECT 1 FROM account_owners ao WHERE ao.account_id = a.id)";
        $sql = self::appendEntityFilter($sql, $params, 'a.entity_id', $entityIds, 'cc');
        return array_merge($lines, self::rows($sql, $params));
    }

    /**
     * @param list<int> $entityIds
     * @return list<array<string,mixed>>
     */
    private static function assetLines(string $currency, array $entityIds): array
    {
        $lines = [];

        $params = ['cur' => $currency];
        $sql = "SELECT a.name AS label, e.name AS entity_name, 100 AS pct, a.current_value AS amount
                FROM assets a
                INNER JOIN entities e ON e.id = a.entity_id AND e.type = 'company'
                WHERE a.status = 'active' AND a.include_in_net_worth = 1
                  AND a.currency_code = :cur";
        $sql = self::appendEntityFilter($sql, $params, 'a.entity_id', $entityIds, 'va');
        $lines = array_merge($lines, self::rows($sql, $params));

        $params = ['cur' => $currency];
        $sql = "SELECT a.name AS label, e.name AS entity_name, ao.ownership_pct AS pct,
                       a.current_value * ao.ownership_pct / 100 AS amount
                FROM asset_owners ao
                INNER JOIN assets a ON a.id = ao.asset_id
                INNER JOIN entities e ON e.id = ao.entity_id AND e.type = 'person'
                WHERE a.status = 'active' AND a.include_in_net_worth = 1
                  AND a.currency_code = :cur";
        $sql = self::appendEntityFilter($sql, $params, 'ao.entity_id', $entityIds, 'vb');
        $lines = array_merge($lines, self::rows($sql, $params));

        $params = ['cur' => $currency];
        $sql = "SELECT a.name AS label, e.name AS entity_name, a.ownership_pct AS pct,
                       a.current_value * a.ownership_pct / 100 AS amount
                FROM assets a
                INNER JOIN entities e ON e.id = a.entity_id AND e.type = 'person'
                WHERE a.status = 'active' AND a.include_in_net_worth = 1
                  AND a.currency_code = :cur
                  AND NOT EXISTS (SELECT 1 FROM asset_owners ao WHERE ao.asset_id = a.id)";
        $sql = self::appendEntityFilter($sql, $params, 'a.entity_id', $entityIds, 'vc');
        return array_merge($lines, self::rows($sql, $params));
    }

    /**
     * @param list<int> $entityIds
     * @return list<array<string,mixed>>
     */
    private static function propertyLines(string $currency, array $entityIds): array
    {
        $params = ['cur' => $currency];
        $sql = "SELECT p.name AS label, e.name AS entity_name,
                       CASE WHEN e.type = 'company' THEN 100 ELSE p.ownership_pct END AS pct,
                       CASE WHEN e.type = 'company' THEN p.current_value
                            ELSE p.current_value * p.ownership_pct / 100 END AS amount
                FROM properties p
                INNER JOIN entities e ON e.id = p.entity_id
                WHERE p.status = 'active' AND p.include_in_net_worth = 1
                  AND p.currency_code = :cur";
        $sql = self::appendEntityFilter($sql, $params, 'p.entity_id', $entityIds, 'pr');
        return self::rows($sql, $params);
    }

    /**
     * Cobrables **exigibles al cierre del mes**: pendientes cuyo vencimiento cae dentro del mes
     * o antes. Las cuotas que vencen después no se cuentan todavía, igual que un presupuesto de
     * un mes futuro no suma al pasivo de hoy.
     *
     * @param list<int> $entityIds
     * @return list<array<string,mixed>>
     */
    private static function receivableLines(string $currency, string $cutoff, array $entityIds): array
    {
        $params = ['cur' => $currency, 'nx' => $cutoff];
        $sql = "SELECT r.debtor_name AS label, e.name AS entity_name, 100 AS pct,
                       r.outstanding_amount AS amount, r.due_date
                FROM receivables r
                INNER JOIN entities e ON e.id = r.entity_id
                WHERE r.status IN ('current','overdue') AND r.include_in_net_worth = 1
                  AND r.currency_code = :cur
                  AND (r.due_date IS NULL OR r.due_date <= :nx)";
        $sql = self::appendEntityFilter($sql, $params, 'r.entity_id', $entityIds, 'rc');
        $lines = self::rows($sql, $params);

        foreach ($lines as &$line) {
            $due = (string) ($line['due_date'] ?? '');
            $line['note'] = $due !== '' ? 'vence ' . self::shortDate($due) : 'sin vencimiento';
        }
        unset($line);

        usort($lines, static function (array $a, array $b): int {
            $cmp = ((string) ($a['due_date'] ?? '9999')) <=> ((string) ($b['due_date'] ?? '9999'));
            return $cmp !== 0 ? $cmp : ((string) $a['label'] <=> (string) $b['label']);
        });
        return $lines;
    }

    /**
     * @param list<int> $entityIds
     * @return list<array<string,mixed>>
     */
    private static function inventoryLines(string $currency, array $entityIds): array
    {
        $params = ['cur' => $currency];
        $sql = "SELECT i.name AS label, e.name AS entity_name, 100 AS pct,
                       i.stock_value AS amount
                FROM inventories i
                INNER JOIN entities e ON e.id = i.entity_id
                WHERE i.include_in_net_worth = 1 AND i.currency_code = :cur";
        $sql = self::appendEntityFilter($sql, $params, 'i.entity_id', $entityIds, 'iv');
        return self::rows($sql, $params);
    }

    /**
     * Participaciones en empresas, atribuidas a cada persona por su % de tenencia.
     *
     * Solo aplican al modo personal: en el consolidado sumar la participación además del
     * patrimonio de la empresa contaría dos veces lo mismo.
     *
     * @param list<int> $entityIds
     * @return list<array<string,mixed>>
     */
    private static function participationLines(string $currency, array $entityIds, string $scope): array
    {
        if ($scope !== 'people' && $scope !== 'entity') {
            return [];
        }
        $personIds = [];
        foreach (Catalog::entities('person') as $row) {
            $id = (int) $row['id'];
            if ($entityIds === [] || in_array($id, $entityIds, true)) {
                $personIds[] = $id;
            }
        }
        if ($personIds === []) {
            return [];
        }

        $names = self::entityMeta();
        $lines = [];
        foreach (PatrimonioService::participationValues($personIds, $currency) as $ownerId => $data) {
            foreach ($data['details'] as $d) {
                $lines[] = [
                    'label' => (string) ($d['company_name'] ?? ''),
                    'entity_name' => $names[(int) $ownerId]['name'] ?? ('#' . $ownerId),
                    'pct' => (float) ($d['ownership_pct'] ?? 0),
                    'amount' => (float) ($d['attributable'] ?? 0),
                ];
            }
        }
        return $lines;
    }

    // ------------------------------------------------------------------- pasivo

    /**
     * @param list<int> $entityIds
     * @return list<array{group:string,total:float,lines:list<array<string,mixed>>}>
     */
    private static function liabilityGroups(string $periodYm, string $currency, array $entityIds): array
    {
        $types = [
            'credit_card' => 'Tarjeta', 'loan' => 'Préstamo', 'mortgage' => 'Hipoteca',
            'supplier' => 'Proveedor', 'tax' => 'Impuestos', 'overdraft' => 'Descubierto',
            'internal' => 'Interna', 'other' => 'Otra',
        ];

        $params = ['cur' => $currency];
        $sql = "SELECT l.name AS label, e.name AS entity_name, l.liability_type,
                       l.creditor_name, l.due_date, l.outstanding_amount AS amount
                FROM liabilities l
                INNER JOIN entities e ON e.id = l.entity_id
                WHERE l.status = 'open' AND l.include_in_net_worth = 1
                  AND l.currency_code = :cur";
        $sql = self::appendEntityFilter($sql, $params, 'l.entity_id', $entityIds, 'li');
        $debts = self::rows($sql, $params);
        foreach ($debts as &$d) {
            $d['detail'] = trim((string) ($types[$d['liability_type'] ?? ''] ?? 'Otra')
                . (($d['creditor_name'] ?? '') !== '' ? ' · ' . $d['creditor_name'] : ''));
        }
        unset($d);

        $params = ['cur' => $currency, 'p' => $periodYm];
        $sql = "SELECT bi.name AS label, e.name AS entity_name, bi.period_ym,
                       bi.due_date, bi.amount AS amount
                FROM budget_items bi
                INNER JOIN entities e ON e.id = bi.entity_id
                WHERE bi.status = 'pending' AND bi.currency_code = :cur
                  AND bi.period_ym <= :p";
        $sql = self::appendEntityFilter($sql, $params, 'bi.entity_id', $entityIds, 'bp');
        $pending = self::rows($sql, $params);
        foreach ($pending as &$b) {
            $b['detail'] = 'Período ' . (string) ($b['period_ym'] ?? '');
        }
        unset($b);

        $groups = [
            ['group' => 'Deudas', 'lines' => self::sortByAmount($debts)],
            ['group' => 'Presupuestos pendientes', 'lines' => self::sortByAmount($pending)],
        ];
        foreach ($groups as &$g) {
            $g['total'] = self::sumLines($g['lines']);
        }
        unset($g);
        return $groups;
    }

    /**
     * Cobrables del grupo cuyo deudor es otra entidad del grupo: son activo de una y pasivo de
     * la otra, así que en el consolidado se descuentan de los dos lados.
     */
    private static function internalNetting(string $periodYm, string $currency): float
    {
        $sql = "SELECT COALESCE(SUM(r.outstanding_amount), 0)
                FROM receivables r
                INNER JOIN entities d ON d.id = r.debtor_entity_id AND d.is_active = 1
                WHERE r.status IN ('current','overdue') AND r.include_in_net_worth = 1
                  AND r.currency_code = :cur
                  AND (r.due_date IS NULL OR r.due_date <= :nx)";
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute(['cur' => $currency, 'nx' => self::cutoffDate($periodYm)]);
        return (float) $stmt->fetchColumn();
    }

    // -------------------------------------------------------- desglose y flujo

    /**
     * Patrimonio de cada entidad, para el desglose cuando el alcance abarca varias.
     *
     * @param list<int> $entityIds
     * @return list<array{id:int,name:string,type:string,net_worth:float,total_assets:float,liabilities:float}>
     */
    private static function byEntity(string $periodYm, string $currency, array $entityIds): array
    {
        if (count($entityIds) < 2) {
            return [];
        }
        $meta = self::entityMeta();
        $out = [];
        foreach ($entityIds as $id) {
            $f = self::figures($periodYm, 'entity', $id, $currency);
            if (
                abs($f['total_assets']) < 0.009
                && abs($f['total_liabilities']) < 0.009
            ) {
                continue;
            }
            $out[] = [
                'id' => $id,
                'name' => (string) ($meta[$id]['name'] ?? ('#' . $id)),
                'type' => (string) ($meta[$id]['type'] ?? 'company'),
                'net_worth' => $f['net_worth'],
                'total_assets' => $f['total_assets'],
                'liabilities' => $f['total_liabilities'],
            ];
        }
        usort($out, static fn (array $a, array $b): int => $b['net_worth'] <=> $a['net_worth']);
        return $out;
    }

    /**
     * @param list<int> $entityIds
     * @return array{income:float,expense:float,net:float}
     */
    private static function flow(string $periodYm, string $currency, array $entityIds): array
    {
        $sql = 'SELECT
                    COALESCE(SUM(CASE WHEN type IN ' . self::INFLOW . ' THEN amount ELSE 0 END),0) AS income,
                    COALESCE(SUM(CASE WHEN type IN ' . self::OUTFLOW . ' THEN amount ELSE 0 END),0) AS expense
                FROM transactions
                WHERE currency_code = :cur
                  AND occurred_at >= :start AND occurred_at < :next';
        $params = [
            'cur' => $currency,
            'start' => $periodYm . '-01 00:00:00',
            'next' => self::cutoffTs($periodYm),
        ];
        $sql = self::appendEntityFilter($sql, $params, 'entity_id', $entityIds);

        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch() ?: [];
        $income = (float) ($row['income'] ?? 0);
        $expense = (float) ($row['expense'] ?? 0);
        return ['income' => $income, 'expense' => $expense, 'net' => $income - $expense];
    }

    /**
     * @param list<int> $entityIds
     * @return list<array{label:string,value:float}>
     */
    private static function expensesByCategory(string $periodYm, string $currency, array $entityIds): array
    {
        $sql = "SELECT COALESCE(c.name, 'Sin categoría') AS label,
                       COALESCE(SUM(t.amount),0) AS total
                FROM transactions t
                LEFT JOIN transaction_categories c ON c.id = t.category_id
                WHERE t.currency_code = :cur
                  AND t.occurred_at >= :start AND t.occurred_at < :next
                  AND t.type IN " . self::OUTFLOW;
        $params = [
            'cur' => $currency,
            'start' => $periodYm . '-01 00:00:00',
            'next' => self::cutoffTs($periodYm),
        ];
        $sql = self::appendEntityFilter($sql, $params, 't.entity_id', $entityIds);
        $sql .= ' GROUP BY label HAVING total > 0.009 ORDER BY total DESC';

        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            $out[] = ['label' => (string) $row['label'], 'value' => (float) $row['total']];
        }
        return $out;
    }

    /**
     * @param list<int> $entityIds
     * @return array{pending:float,paid:float,skipped:float,planned:float}
     */
    private static function budgetTotals(string $periodYm, string $currency, array $entityIds): array
    {
        $sql = "SELECT
                    COALESCE(SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END),0) AS pending,
                    COALESCE(SUM(CASE WHEN status = 'paid' THEN COALESCE(paid_amount, amount) ELSE 0 END),0) AS paid,
                    COALESCE(SUM(CASE WHEN status = 'skipped' THEN amount ELSE 0 END),0) AS skipped
                FROM budget_items
                WHERE period_ym = :p AND currency_code = :cur";
        $params = ['p' => $periodYm, 'cur' => $currency];
        $sql = self::appendEntityFilter($sql, $params, 'entity_id', $entityIds);

        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch() ?: [];
        $pending = (float) ($row['pending'] ?? 0);
        $paid = (float) ($row['paid'] ?? 0);
        $skipped = (float) ($row['skipped'] ?? 0);
        return [
            'pending' => $pending,
            'paid' => $paid,
            'skipped' => $skipped,
            'planned' => $pending + $paid + $skipped,
        ];
    }

    // ------------------------------------------------------------------ helpers

    /**
     * @param array<string,mixed> $params
     * @return list<array<string,mixed>>
     */
    private static function rows(string $sql, array $params): array
    {
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * @param array<string,mixed> $params
     * @param list<int> $entityIds
     */
    private static function appendEntityFilter(
        string $sql,
        array &$params,
        string $column,
        array $entityIds,
        string $prefix = 'ent'
    ): string {
        if ($entityIds === []) {
            return $sql;
        }
        $names = [];
        foreach (array_values($entityIds) as $i => $id) {
            $key = $prefix . $i;
            $names[] = ':' . $key;
            $params[$key] = $id;
        }
        return $sql . ' AND ' . $column . ' IN (' . implode(',', $names) . ')';
    }

    /**
     * Entidades alcanzadas. Vacío = sin filtro (todas), que es lo que corresponde a «all».
     *
     * @return list<int>
     */
    private static function resolveEntityIds(string $scope, ?int $entityId): array
    {
        if ($entityId) {
            return [$entityId];
        }
        if ($scope === 'people' || $scope === 'companies') {
            $type = $scope === 'people' ? 'person' : 'company';
            return array_map(
                static fn (array $row): int => (int) $row['id'],
                Catalog::entities($type)
            );
        }
        return [];
    }

    /**
     * Entidades a listar en el desglose. En «todas» el filtro de consultas va vacío (sin WHERE),
     * pero el desglose sí necesita la lista explícita.
     *
     * @param list<int> $entityIds
     * @return list<int>
     */
    private static function breakdownIds(string $scope, array $entityIds): array
    {
        if ($entityIds !== []) {
            return $entityIds;
        }
        if ($scope !== 'all') {
            return [];
        }
        return array_map(
            static fn (array $row): int => (int) $row['id'],
            Catalog::entities()
        );
    }

    /** @return array<int,array{name:string,type:string}> */
    private static function entityMeta(): array
    {
        $out = [];
        foreach (Catalog::entities() as $row) {
            $out[(int) $row['id']] = [
                'name' => (string) $row['name'],
                'type' => (string) $row['type'],
            ];
        }
        return $out;
    }

    private static function scopeLabel(string $scope, ?int $entityId): string
    {
        if ($scope === 'entity' && $entityId) {
            foreach (Catalog::entities() as $row) {
                if ((int) $row['id'] === $entityId) {
                    return (string) $row['name'];
                }
            }
            return 'Entidad #' . $entityId;
        }
        return match ($scope) {
            'people' => 'Todas las personas',
            'companies' => 'Todas las empresas',
            default => 'Todas las entidades',
        };
    }

    /**
     * @param list<array<string,mixed>> $lines
     * @return list<array<string,mixed>>
     */
    private static function sortByAmount(array $lines): array
    {
        usort($lines, static fn (array $a, array $b): int => (float) $b['amount'] <=> (float) $a['amount']);
        return $lines;
    }

    /** @param list<array<string,mixed>> $lines */
    private static function sumLines(array $lines): float
    {
        $total = 0.0;
        foreach ($lines as $line) {
            $total += (float) $line['amount'];
        }
        return $total;
    }

    public static function normalizePeriod(string $periodYm): string
    {
        return preg_match('/^\d{4}-\d{2}$/', $periodYm) ? $periodYm : date('Y-m');
    }

    private static function shiftPeriod(string $periodYm, int $months): string
    {
        [$y, $m] = array_map('intval', explode('-', $periodYm));
        return date('Y-m', (int) mktime(0, 0, 0, $m + $months, 1, $y));
    }

    private static function nextMonthStart(string $periodYm): string
    {
        return self::shiftPeriod($periodYm, 1) . '-01 00:00:00';
    }

    /**
     * Fecha de corte del reporte: el último día del mes, o hoy si el mes está en curso.
     *
     * Lo que ocurre después del corte no entra, aunque ya esté cargado: un movimiento con fecha
     * de mañana pertenece a mañana.
     */
    private static function cutoffDate(string $periodYm): string
    {
        if ($periodYm === date('Y-m')) {
            return date('Y-m-d');
        }
        [$y, $m] = array_map('intval', explode('-', $periodYm));
        return date('Y-m-d', (int) mktime(0, 0, 0, $m + 1, 0, $y));
    }

    /** Instante exclusivo del corte: todo lo que ocurre desde acá queda fuera del reporte. */
    private static function cutoffTs(string $periodYm): string
    {
        if ($periodYm === date('Y-m')) {
            return date('Y-m-d H:i:s');
        }
        return self::nextMonthStart($periodYm);
    }

    private static function cutoffLabel(string $periodYm): string
    {
        return self::shortDate(self::cutoffDate($periodYm));
    }

    private static function shortDate(string $ymd): string
    {
        [$y, $m, $d] = array_pad(explode('-', substr($ymd, 0, 10)), 3, '');
        return $d !== '' ? "$d/$m/$y" : $ymd;
    }

    private static function periodLabel(string $periodYm): string
    {
        $meses = [
            1 => 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
            'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre',
        ];
        [$y, $m] = array_map('intval', explode('-', $periodYm));
        return ($meses[$m] ?? $periodYm) . ' ' . $y;
    }
}
