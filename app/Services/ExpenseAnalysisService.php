<?php

declare(strict_types=1);

/**
 * Análisis de gastos: real (movimientos) + presupuesto + proyección.
 */
final class ExpenseAnalysisService
{
    private const OUTFLOW = "('expense','payment','egreso')";
    private const INFLOW = "('income','collection','loan','ingreso')";

    /**
     * @return array<string,mixed>
     */
    public static function build(
        string $currency,
        string $from,
        string $to,
        ?int $entityId = null,
        string $preset = '30d',
        string $scope = 'all'
    ): array {
        $currency = strtoupper(trim($currency)) ?: 'ARS';
        if (!in_array($scope, ['all', 'people', 'companies'], true)) {
            $scope = 'all';
        }
        if ($entityId) {
            $scope = 'entity';
        }
        $entityIds = self::resolveEntityIds($entityId, $scope);
        [$from, $to] = self::normalizeRange($from, $to);
        $fromDt = new DateTimeImmutable($from);
        $toDt = new DateTimeImmutable($to);
        $days = max(1, (int) $fromDt->diff($toDt)->days + 1);

        $prevTo = $fromDt->modify('-1 day');
        $prevFrom = $prevTo->modify('-' . ($days - 1) . ' days');
        $yoyFrom = $fromDt->modify('-1 year')->format('Y-m-d');
        $yoyTo = $toDt->modify('-1 year')->format('Y-m-d');

        $flow = self::flowTotals($currency, $from, $to, $entityIds);
        $prev = self::flowTotals($currency, $prevFrom->format('Y-m-d'), $prevTo->format('Y-m-d'), $entityIds);
        $yoy = self::flowTotals($currency, $yoyFrom, $yoyTo, $entityIds);

        $expense = (float) $flow['expense'];
        $income = (float) $flow['income'];
        $prevExpense = (float) $prev['expense'];
        $yoyExpense = (float) $yoy['expense'];

        $deltaPrev = $expense - $prevExpense;
        $deltaPrevPct = $prevExpense > 0.009 ? round(($deltaPrev / $prevExpense) * 1000) / 10 : null;
        $deltaYoy = $expense - $yoyExpense;
        $deltaYoyPct = $yoyExpense > 0.009 ? round(($deltaYoy / $yoyExpense) * 1000) / 10 : null;
        $burdenPct = $income > 0.009 ? round(($expense / $income) * 1000) / 10 : null;

        $periodYm = $toDt->format('Y-m');
        BudgetService::ensurePeriod($periodYm);
        $budget = self::budgetTotals($periodYm, $currency, $entityIds);
        $budgetPlanned = (float) $budget['pending'] + (float) $budget['paid'] + (float) $budget['skipped'];
        $budgetPaid = (float) $budget['paid'];
        $budgetPending = (float) $budget['pending'];
        $budgetSkipped = (float) $budget['skipped'];
        $budgetExecPct = $budgetPlanned > 0.009
            ? round(($budgetPaid / $budgetPlanned) * 1000) / 10
            : null;

        $projYear = (string) (int) $toDt->format('Y');
        $projectedAnnual = self::projectedExpenseForFilter($entityId, $scope, $projYear);
        $projectedPeriod = $projectedAnnual * ($days / 365.0);
        $vsProjDelta = $expense - $projectedPeriod;
        $vsProjPct = $projectedPeriod > 0.009
            ? round(($vsProjDelta / $projectedPeriod) * 1000) / 10
            : null;

        $byEntity = self::byEntity($currency, $from, $to, $entityIds);
        $byCategory = self::byCategory($currency, $from, $to, $entityIds);
        $byType = self::byType($currency, $from, $to, $entityIds);
        $byAccount = self::byAccount($currency, $from, $to, $entityIds);
        $byMonth = self::byMonth($currency, $from, $to, $entityIds);
        $byCategoryMonth = self::byCategoryMonth($currency, $to, $entityIds);
        $topConcepts = self::topConcepts($currency, $from, $to, $entityIds, 12);
        $splitBudget = self::budgetLinkedSplit($currency, $from, $to, $entityIds);
        $ticket = self::ticketStats($currency, $from, $to, $entityIds);
        $alerts = self::alerts(
            $expense,
            $prevExpense,
            $deltaPrevPct,
            $budget,
            $byCategory,
            $byEntity
        );

        $top3Cat = 0.0;
        foreach (array_slice($byCategory['values'], 0, 3) as $v) {
            $top3Cat += (float) $v;
        }
        $concentrationPct = $expense > 0.009 ? round(($top3Cat / $expense) * 1000) / 10 : null;

        $peopleExpense = 0.0;
        $companyExpense = 0.0;
        foreach ($byEntity['rows'] as $row) {
            if (($row['type'] ?? '') === 'company') {
                $companyExpense += (float) $row['amount'];
            } else {
                $peopleExpense += (float) $row['amount'];
            }
        }

        return [
            'currency' => $currency,
            'from' => $from,
            'to' => $to,
            'preset' => $preset,
            'scope' => $scope === 'entity' ? 'entity' : $scope,
            'entity_id' => $entityId,
            'days' => $days,
            'prev_from' => $prevFrom->format('Y-m-d'),
            'prev_to' => $prevTo->format('Y-m-d'),
            'yoy_from' => $yoyFrom,
            'yoy_to' => $yoyTo,
            'kpis' => [
                'expense' => $expense,
                'income' => $income,
                'avg_daily' => $expense / $days,
                'avg_weekly' => $expense / max(1, $days / 7),
                'avg_monthly' => $expense / max(1, $days / 30.4375),
                'prev_expense' => $prevExpense,
                'delta_prev' => $deltaPrev,
                'delta_prev_pct' => $deltaPrevPct,
                'yoy_expense' => $yoyExpense,
                'delta_yoy' => $deltaYoy,
                'delta_yoy_pct' => $deltaYoyPct,
                'burden_pct' => $burdenPct,
                'budget_planned' => $budgetPlanned,
                'budget_paid' => $budgetPaid,
                'budget_pending' => $budgetPending,
                'budget_skipped' => $budgetSkipped,
                'budget_exec_pct' => $budgetExecPct,
                'budget_count_pending' => (int) ($budget['count_pending'] ?? 0),
                'projected_annual' => $projectedAnnual,
                'projected_period' => $projectedPeriod,
                'vs_proj_delta' => $vsProjDelta,
                'vs_proj_pct' => $vsProjPct,
                'tx_count' => (int) $ticket['count'],
                'ticket_avg' => (float) $ticket['avg'],
                'ticket_median' => (float) $ticket['median'],
                'ticket_max' => (float) $ticket['max'],
                'concentration_top3_pct' => $concentrationPct,
                'people_expense' => $peopleExpense,
                'company_expense' => $companyExpense,
                'budget_linked' => (float) $splitBudget['linked'],
                'budget_free' => (float) $splitBudget['free'],
                'available_est' => $income - $expense,
            ],
            'budget_period' => $periodYm,
            'proj_year' => $projYear,
            'by_entity' => $byEntity,
            'by_category' => $byCategory,
            'by_type' => $byType,
            'by_account' => $byAccount,
            'by_month' => $byMonth,
            'by_category_month' => $byCategoryMonth,
            'top_concepts' => $topConcepts,
            'split_budget' => $splitBudget,
            'alerts' => $alerts,
        ];
    }

    /** @return array{0:string,1:string} */
    public static function rangeForPreset(string $preset, ?string $from = null, ?string $to = null): array
    {
        $today = new DateTimeImmutable('today');
        return match ($preset) {
            'month' => [$today->format('Y-m-01'), $today->format('Y-m-d')],
            '90d' => [$today->modify('-89 days')->format('Y-m-d'), $today->format('Y-m-d')],
            'ytd' => [$today->format('Y-01-01'), $today->format('Y-m-d')],
            'year' => [$today->format('Y-01-01'), $today->format('Y-12-31')],
            'custom' => self::normalizeRange((string) $from, (string) $to),
            default => [$today->modify('-29 days')->format('Y-m-d'), $today->format('Y-m-d')],
        };
    }

    /** @return array{0:string,1:string} */
    private static function normalizeRange(string $from, string $to): array
    {
        $from = preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) ? $from : date('Y-m-d', strtotime('-29 days'));
        $to = preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) ? $to : date('Y-m-d');
        if ($from > $to) {
            return [$to, $from];
        }
        return [$from, $to];
    }

    private static function entityType(int $entityId): ?string
    {
        $stmt = Database::pdo()->prepare('SELECT type FROM entities WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $entityId]);
        $t = $stmt->fetchColumn();
        return $t !== false ? (string) $t : null;
    }

    /**
     * null = sin filtro (todas). Lista vacía = ninguna entidad.
     *
     * @return list<int>|null
     */
    private static function resolveEntityIds(?int $entityId, string $scope): ?array
    {
        if ($entityId) {
            return [$entityId];
        }
        if ($scope === 'people') {
            return array_values(array_map(
                static fn (array $e): int => (int) $e['id'],
                Catalog::entities('person')
            ));
        }
        if ($scope === 'companies') {
            return array_values(array_map(
                static fn (array $e): int => (int) $e['id'],
                Catalog::entities('company')
            ));
        }
        return null;
    }

    /**
     * @param list<int>|null $entityIds
     * @param array<string,mixed> $params
     */
    private static function appendEntityFilter(string $sql, array &$params, string $column, ?array $entityIds): string
    {
        if ($entityIds === null) {
            return $sql;
        }
        if ($entityIds === []) {
            return $sql . ' AND 1=0';
        }
        if (count($entityIds) === 1) {
            $params['ef0'] = $entityIds[0];
            return $sql . ' AND ' . $column . ' = :ef0';
        }
        $ph = [];
        foreach (array_values($entityIds) as $i => $id) {
            $key = 'ef' . $i;
            $ph[] = ':' . $key;
            $params[$key] = $id;
        }
        return $sql . ' AND ' . $column . ' IN (' . implode(',', $ph) . ')';
    }

    /** @param list<int>|null $entityIds */
    private static function budgetTotals(string $periodYm, string $currency, ?array $entityIds): array
    {
        $periodYm = BudgetService::normalizePeriod($periodYm);
        $sql = "SELECT
                    COALESCE(SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END),0) AS pending,
                    COALESCE(SUM(CASE WHEN status = 'paid' THEN COALESCE(paid_amount, amount) ELSE 0 END),0) AS paid,
                    COALESCE(SUM(CASE WHEN status = 'skipped' THEN amount ELSE 0 END),0) AS skipped,
                    COALESCE(SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END),0) AS count_pending
                FROM budget_items
                WHERE period_ym = :p AND currency_code = :c";
        $params = ['p' => $periodYm, 'c' => $currency];
        $sql = self::appendEntityFilter($sql, $params, 'entity_id', $entityIds);
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch() ?: [];
        return [
            'pending' => (float) ($row['pending'] ?? 0),
            'paid' => (float) ($row['paid'] ?? 0),
            'skipped' => (float) ($row['skipped'] ?? 0),
            'count_pending' => (int) ($row['count_pending'] ?? 0),
        ];
    }

    private static function projectedExpenseForFilter(?int $entityId, string $scope, string $projYear): float
    {
        if ($entityId) {
            $type = self::entityType($entityId);
            if ($type === 'company') {
                $plan = FinancialPlanService::ensureForCompany($entityId);
                $wb = $plan['workbook'] ?? [];
                $idx = self::yearIndexByName($wb, $projYear);
                $stats = FinancialPlanService::yearStats($wb, $idx);
                return max(0.0, (float) ($stats['expense_annual'] ?? 0));
            }
            $plan = PersonFinancialPlanService::ensureForPerson($entityId);
            $wb = $plan['workbook'] ?? [];
            $idx = PersonFinancialPlanService::yearIndexForCalendar($wb, (int) $projYear);
            $stats = PersonFinancialPlanService::yearStats($wb, $idx);
            return max(0.0, (float) ($stats['expense_annual'] ?? 0));
        }
        $aggScope = match ($scope) {
            'people' => 'people',
            'companies' => 'companies',
            default => 'all',
        };
        $agg = ProjectionsAggregateService::build($projYear, $aggScope);
        return max(0.0, (float) (($agg['totals']['expense_annual'] ?? 0)));
    }

    /** @param array<string,mixed> $workbook */
    private static function yearIndexByName(array $workbook, string $yearName): int
    {
        $years = $workbook['years'] ?? [];
        if (!is_array($years) || $years === []) {
            return 0;
        }
        $want = (int) preg_replace('/\D+/', '', $yearName);
        foreach ($years as $i => $y) {
            $n = (int) preg_replace('/\D+/', '', (string) ($y['name'] ?? ''));
            if ($want > 0 && $n === $want) {
                return (int) $i;
            }
        }
        return 0;
    }

    /** @param list<int>|null $entityIds @return array{income:float,expense:float} */
    private static function flowTotals(string $currency, string $from, string $to, ?array $entityIds): array
    {
        $sql = 'SELECT
                    COALESCE(SUM(CASE WHEN type IN ' . self::INFLOW . ' THEN amount ELSE 0 END),0) AS income,
                    COALESCE(SUM(CASE WHEN type IN ' . self::OUTFLOW . ' THEN amount ELSE 0 END),0) AS expense
                FROM transactions
                WHERE currency_code = :cur
                  AND occurred_at >= :from
                  AND occurred_at <= :to';
        $params = [
            'cur' => $currency,
            'from' => $from . ' 00:00:00',
            'to' => $to . ' 23:59:59',
        ];
        $sql = self::appendEntityFilter($sql, $params, 'entity_id', $entityIds);
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch() ?: [];
        return [
            'income' => (float) ($row['income'] ?? 0),
            'expense' => (float) ($row['expense'] ?? 0),
        ];
    }

    /**
     * @param list<int>|null $entityIds
     * @return array{labels:list<string>,values:list<float>,ids:list<int>,rows:list<array<string,mixed>>}
     */
    private static function byEntity(string $currency, string $from, string $to, ?array $entityIds): array
    {
        $sql = 'SELECT e.id, e.name, e.type,
                       COALESCE(SUM(t.amount),0) AS total
                FROM transactions t
                INNER JOIN entities e ON e.id = t.entity_id
                WHERE t.currency_code = :cur
                  AND t.occurred_at >= :from AND t.occurred_at <= :to
                  AND t.type IN ' . self::OUTFLOW;
        $params = [
            'cur' => $currency,
            'from' => $from . ' 00:00:00',
            'to' => $to . ' 23:59:59',
        ];
        $sql = self::appendEntityFilter($sql, $params, 't.entity_id', $entityIds);
        $sql .= ' GROUP BY e.id, e.name, e.type HAVING total > 0.009 ORDER BY total DESC';
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        $rows = [];
        $labels = [];
        $values = [];
        $ids = [];
        foreach ($stmt->fetchAll() as $row) {
            $amt = (float) $row['total'];
            $rows[] = [
                'id' => (int) $row['id'],
                'name' => (string) $row['name'],
                'type' => (string) $row['type'],
                'amount' => $amt,
            ];
            $labels[] = (string) $row['name'];
            $values[] = $amt;
            $ids[] = (int) $row['id'];
        }
        return compact('labels', 'values', 'ids', 'rows');
    }

    /** @param list<int>|null $entityIds @return array{labels:list<string>,values:list<float>} */
    private static function byCategory(string $currency, string $from, string $to, ?array $entityIds): array
    {
        $sql = 'SELECT COALESCE(c.name, \'Sin categoría\') AS label,
                       COALESCE(SUM(t.amount),0) AS total
                FROM transactions t
                LEFT JOIN transaction_categories c ON c.id = t.category_id
                WHERE t.currency_code = :cur
                  AND t.occurred_at >= :from AND t.occurred_at <= :to
                  AND t.type IN ' . self::OUTFLOW;
        $params = [
            'cur' => $currency,
            'from' => $from . ' 00:00:00',
            'to' => $to . ' 23:59:59',
        ];
        $sql = self::appendEntityFilter($sql, $params, 't.entity_id', $entityIds);
        $sql .= ' GROUP BY label HAVING total > 0.009 ORDER BY total DESC LIMIT 12';
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        $labels = [];
        $values = [];
        foreach ($stmt->fetchAll() as $row) {
            $labels[] = (string) $row['label'];
            $values[] = (float) $row['total'];
        }
        return compact('labels', 'values');
    }

    /** @param list<int>|null $entityIds @return array{labels:list<string>,values:list<float>} */
    private static function byType(string $currency, string $from, string $to, ?array $entityIds): array
    {
        $sql = 'SELECT t.type, COALESCE(SUM(t.amount),0) AS total
                FROM transactions t
                WHERE t.currency_code = :cur
                  AND t.occurred_at >= :from AND t.occurred_at <= :to
                  AND t.type IN ' . self::OUTFLOW;
        $params = [
            'cur' => $currency,
            'from' => $from . ' 00:00:00',
            'to' => $to . ' 23:59:59',
        ];
        $sql = self::appendEntityFilter($sql, $params, 't.entity_id', $entityIds);
        $sql .= ' GROUP BY t.type HAVING total > 0.009 ORDER BY total DESC';
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        $map = [
            'expense' => 'Egreso',
            'payment' => 'Pago',
            'egreso' => 'Egreso',
        ];
        $labels = [];
        $values = [];
        foreach ($stmt->fetchAll() as $row) {
            $t = (string) $row['type'];
            $labels[] = $map[$t] ?? $t;
            $values[] = (float) $row['total'];
        }
        return compact('labels', 'values');
    }

    /** @param list<int>|null $entityIds @return array{labels:list<string>,values:list<float>} */
    private static function byAccount(string $currency, string $from, string $to, ?array $entityIds): array
    {
        $sql = 'SELECT COALESCE(a.name, \'Sin cuenta\') AS label,
                       COALESCE(SUM(t.amount),0) AS total
                FROM transactions t
                LEFT JOIN accounts a ON a.id = t.account_id
                WHERE t.currency_code = :cur
                  AND t.occurred_at >= :from AND t.occurred_at <= :to
                  AND t.type IN ' . self::OUTFLOW;
        $params = [
            'cur' => $currency,
            'from' => $from . ' 00:00:00',
            'to' => $to . ' 23:59:59',
        ];
        $sql = self::appendEntityFilter($sql, $params, 't.entity_id', $entityIds);
        $sql .= ' GROUP BY label HAVING total > 0.009 ORDER BY total DESC LIMIT 10';
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        $labels = [];
        $values = [];
        foreach ($stmt->fetchAll() as $row) {
            $labels[] = (string) $row['label'];
            $values[] = (float) $row['total'];
        }
        return compact('labels', 'values');
    }

    /** @param list<int>|null $entityIds @return array{labels:list<string>,expense:list<float>,income:list<float>} */
    private static function byMonth(string $currency, string $from, string $to, ?array $entityIds): array
    {
        $sql = "SELECT DATE_FORMAT(occurred_at, '%Y-%m') AS ym,
                       COALESCE(SUM(CASE WHEN type IN " . self::OUTFLOW . ' THEN amount ELSE 0 END),0) AS expense,
                       COALESCE(SUM(CASE WHEN type IN ' . self::INFLOW . ' THEN amount ELSE 0 END),0) AS income
                FROM transactions
                WHERE currency_code = :cur
                  AND occurred_at >= :from AND occurred_at <= :to';
        $params = [
            'cur' => $currency,
            'from' => $from . ' 00:00:00',
            'to' => $to . ' 23:59:59',
        ];
        $sql = self::appendEntityFilter($sql, $params, 'entity_id', $entityIds);
        $sql .= ' GROUP BY ym ORDER BY ym';
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        $labels = [];
        $expense = [];
        $income = [];
        foreach ($stmt->fetchAll() as $row) {
            $labels[] = (string) $row['ym'];
            $expense[] = (float) $row['expense'];
            $income[] = (float) $row['income'];
        }
        return compact('labels', 'expense', 'income');
    }

    /**
     * Barras apiladas: gasto por categoría en los 12 meses del año de $anchorDate.
     * Siempre incluye ene–dic (ceros si no hay movimientos).
     *
     * @param list<int>|null $entityIds
     * @return array{
     *   labels:list<string>,
     *   year:string,
     *   month_totals:list<float>,
     *   series:list<array{name:string,color:string,values:list<float>,pcts:list<float|null>}>
     * }
     */
    private static function byCategoryMonth(
        string $currency,
        string $anchorDate,
        ?array $entityIds,
        int $topCats = 10
    ): array {
        $palette = [
            '#f87171', '#fb923c', '#fbbf24', '#a78bfa',
            '#60a5fa', '#22d3ee', '#34d399', '#f472b6',
            '#94a3b8', '#c084fc', '#f0abfc', '#67e8f9',
        ];

        $year = (int) (new DateTimeImmutable($anchorDate))->format('Y');
        if ($year < 1900) {
            $year = (int) date('Y');
        }
        $from = sprintf('%04d-01-01', $year);
        $to = sprintf('%04d-12-31', $year);
        $labels = [];
        for ($m = 1; $m <= 12; $m++) {
            $labels[] = sprintf('%04d-%02d', $year, $m);
        }

        $sql = "SELECT DATE_FORMAT(t.occurred_at, '%Y-%m') AS ym,
                       COALESCE(c.name, 'Sin categoría') AS cat,
                       COALESCE(SUM(t.amount),0) AS total
                FROM transactions t
                LEFT JOIN transaction_categories c ON c.id = t.category_id
                WHERE t.currency_code = :cur
                  AND t.occurred_at >= :from AND t.occurred_at <= :to
                  AND t.type IN " . self::OUTFLOW;
        $params = [
            'cur' => $currency,
            'from' => $from . ' 00:00:00',
            'to' => $to . ' 23:59:59',
        ];
        $sql = self::appendEntityFilter($sql, $params, 't.entity_id', $entityIds);
        $sql .= ' GROUP BY ym, cat HAVING total > 0.009 ORDER BY ym, total DESC';
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $catTotals = [];
        $matrix = [];
        foreach ($rows as $row) {
            $ym = (string) $row['ym'];
            $cat = (string) $row['cat'];
            $amt = (float) $row['total'];
            $catTotals[$cat] = ($catTotals[$cat] ?? 0.0) + $amt;
            $matrix[$ym][$cat] = ($matrix[$ym][$cat] ?? 0.0) + $amt;
        }

        if ($catTotals === []) {
            return [
                'labels' => $labels,
                'year' => (string) $year,
                'month_totals' => array_fill(0, 12, 0.0),
                'series' => [],
            ];
        }

        arsort($catTotals);
        $topNames = array_slice(array_keys($catTotals), 0, max(1, $topCats));
        $topSet = array_fill_keys($topNames, true);
        $hasOther = count($catTotals) > count($topNames);

        $seriesMap = [];
        foreach ($topNames as $i => $name) {
            $seriesMap[$name] = [
                'name' => $name,
                'color' => $palette[$i % count($palette)],
                'values' => [],
                'pcts' => [],
            ];
        }
        if ($hasOther) {
            $seriesMap['Otros'] = [
                'name' => 'Otros',
                'color' => $palette[count($topNames) % count($palette)],
                'values' => [],
                'pcts' => [],
            ];
        }

        $monthTotals = [];
        foreach ($labels as $ym) {
            $monthTotal = 0.0;
            $bucket = [];
            foreach ($topNames as $name) {
                $bucket[$name] = 0.0;
            }
            if ($hasOther) {
                $bucket['Otros'] = 0.0;
            }
            foreach ($matrix[$ym] ?? [] as $cat => $amt) {
                $monthTotal += $amt;
                if (isset($topSet[$cat])) {
                    $bucket[$cat] += $amt;
                } elseif ($hasOther) {
                    $bucket['Otros'] += $amt;
                }
            }
            $monthTotals[] = $monthTotal;
            foreach ($seriesMap as $name => &$serie) {
                $val = (float) ($bucket[$name] ?? 0);
                $serie['values'][] = $val;
                $serie['pcts'][] = $monthTotal > 0.009
                    ? round(($val / $monthTotal) * 1000) / 10
                    : null;
            }
            unset($serie);
        }

        return [
            'labels' => $labels,
            'year' => (string) $year,
            'month_totals' => $monthTotals,
            'series' => array_values($seriesMap),
        ];
    }

    /**
     * @param list<int>|null $entityIds
     * @return list<array{name:string,amount:float,count:int}>
     */
    private static function topConcepts(
        string $currency,
        string $from,
        string $to,
        ?array $entityIds,
        int $limit = 12
    ): array {
        $sql = "SELECT COALESCE(NULLIF(TRIM(description), ''), '(sin descripción)') AS name,
                       COALESCE(SUM(amount),0) AS total,
                       COUNT(*) AS cnt
                FROM transactions
                WHERE currency_code = :cur
                  AND occurred_at >= :from AND occurred_at <= :to
                  AND type IN " . self::OUTFLOW;
        $params = [
            'cur' => $currency,
            'from' => $from . ' 00:00:00',
            'to' => $to . ' 23:59:59',
        ];
        $sql = self::appendEntityFilter($sql, $params, 'entity_id', $entityIds);
        $sql .= ' GROUP BY name HAVING total > 0.009 ORDER BY total DESC LIMIT ' . max(1, $limit);
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            $out[] = [
                'name' => (string) $row['name'],
                'amount' => (float) $row['total'],
                'count' => (int) $row['cnt'],
            ];
        }
        return $out;
    }

    /** @param list<int>|null $entityIds @return array{linked:float,free:float,labels:list<string>,values:list<float>} */
    private static function budgetLinkedSplit(
        string $currency,
        string $from,
        string $to,
        ?array $entityIds
    ): array {
        $sql = "SELECT
                    COALESCE(SUM(CASE
                        WHEN JSON_EXTRACT(metadata_json, '$.budget_item_id') IS NOT NULL
                          OR JSON_EXTRACT(metadata_json, '$.budget_template_id') IS NOT NULL
                        THEN amount ELSE 0 END),0) AS linked,
                    COALESCE(SUM(CASE
                        WHEN JSON_EXTRACT(metadata_json, '$.budget_item_id') IS NULL
                         AND JSON_EXTRACT(metadata_json, '$.budget_template_id') IS NULL
                        THEN amount ELSE 0 END),0) AS free
                FROM transactions
                WHERE currency_code = :cur
                  AND occurred_at >= :from AND occurred_at <= :to
                  AND type IN " . self::OUTFLOW;
        $params = [
            'cur' => $currency,
            'from' => $from . ' 00:00:00',
            'to' => $to . ' 23:59:59',
        ];
        $sql = self::appendEntityFilter($sql, $params, 'entity_id', $entityIds);
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch() ?: [];
        $linked = (float) ($row['linked'] ?? 0);
        $free = (float) ($row['free'] ?? 0);
        return [
            'linked' => $linked,
            'free' => $free,
            'labels' => ['De presupuesto', 'Libres'],
            'values' => [$linked, $free],
        ];
    }

    /** @param list<int>|null $entityIds @return array{count:int,avg:float,median:float,max:float} */
    private static function ticketStats(
        string $currency,
        string $from,
        string $to,
        ?array $entityIds
    ): array {
        $sql = 'SELECT amount FROM transactions
                WHERE currency_code = :cur
                  AND occurred_at >= :from AND occurred_at <= :to
                  AND type IN ' . self::OUTFLOW . '
                  AND amount > 0.009';
        $params = [
            'cur' => $currency,
            'from' => $from . ' 00:00:00',
            'to' => $to . ' 23:59:59',
        ];
        $sql = self::appendEntityFilter($sql, $params, 'entity_id', $entityIds);
        $sql .= ' ORDER BY amount';
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        $amounts = array_map('floatval', array_column($stmt->fetchAll(), 'amount'));
        $n = count($amounts);
        if ($n === 0) {
            return ['count' => 0, 'avg' => 0.0, 'median' => 0.0, 'max' => 0.0];
        }
        $sum = array_sum($amounts);
        $mid = intdiv($n, 2);
        $median = $n % 2 === 1
            ? $amounts[$mid]
            : (($amounts[$mid - 1] + $amounts[$mid]) / 2);
        return [
            'count' => $n,
            'avg' => $sum / $n,
            'median' => $median,
            'max' => max($amounts),
        ];
    }

    /**
     * @param array{labels:list<string>,values:list<float>} $byCategory
     * @param array{rows:list<array<string,mixed>>} $byEntity
     * @param array<string,mixed> $budget
     * @return list<array{level:string,message:string}>
     */
    private static function alerts(
        float $expense,
        float $prevExpense,
        ?float $deltaPrevPct,
        array $budget,
        array $byCategory,
        array $byEntity
    ): array {
        $out = [];
        if ($deltaPrevPct !== null && $deltaPrevPct >= 25) {
            $out[] = [
                'level' => 'warn',
                'message' => 'El gasto subió ' . number_format($deltaPrevPct, 1, ',', '.') . '% vs el período anterior.',
            ];
        }
        if ((int) ($budget['count_pending'] ?? 0) > 0 && (float) ($budget['pending'] ?? 0) > 0.009) {
            $out[] = [
                'level' => 'warn',
                'message' => (int) $budget['count_pending'] . ' presupuesto(s) pendiente(s) en el mes de cierre del rango.',
            ];
        }
        if ($expense > 0.009 && $byCategory['values'] !== []) {
            $topShare = ((float) $byCategory['values'][0] / $expense) * 100;
            if ($topShare >= 45) {
                $out[] = [
                    'level' => 'info',
                    'message' => 'La categoría «' . $byCategory['labels'][0] . '» concentra '
                        . number_format($topShare, 1, ',', '.') . '% del gasto.',
                ];
            }
        }
        if (count($byEntity['rows']) >= 2) {
            $top = (float) ($byEntity['rows'][0]['amount'] ?? 0);
            if ($expense > 0.009 && ($top / $expense) >= 0.6) {
                $out[] = [
                    'level' => 'info',
                    'message' => ($byEntity['rows'][0]['name'] ?? 'Una entidad')
                        . ' concentra la mayor parte del gasto del período.',
                ];
            }
        }
        if ($prevExpense < 0.01 && $expense > 0.009) {
            $out[] = [
                'level' => 'info',
                'message' => 'No había egresos en el período anterior comparable.',
            ];
        }
        return $out;
    }
}
