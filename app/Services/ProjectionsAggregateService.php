<?php

declare(strict_types=1);

/**
 * Consolida proyecciones personales + empresariales (solo lectura).
 * Suma planillas por año calendario (nombre del sheet, ej. "2026").
 */
final class ProjectionsAggregateService
{
    private const MONTHS = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];

    /**
     * @param 'all'|'people'|'companies' $scope
     * @return array<string,mixed>
     */
    public static function build(?string $yearName = null, string $scope = 'all'): array
    {
        if (!in_array($scope, ['all', 'people', 'companies'], true)) {
            $scope = 'all';
        }

        $people = $scope === 'companies' ? [] : Catalog::entities('person');
        $companies = $scope === 'people' ? [] : Catalog::entities('company');

        $yearNames = self::collectYearNames($people, $companies);
        $selected = self::pickYear($yearNames, $yearName);

        $rows = [];
        $totals = self::emptyTotals($selected);

        foreach ($people as $ent) {
            $row = self::rowForEntity((int) $ent['id'], (string) $ent['name'], 'person', $selected);
            $rows[] = $row;
            if ($row['has_plan'] && $row['has_year']) {
                self::addInto($totals, $row['stats']);
            }
        }
        foreach ($companies as $ent) {
            $row = self::rowForEntity((int) $ent['id'], (string) $ent['name'], 'company', $selected);
            $rows[] = $row;
            if ($row['has_plan'] && $row['has_year']) {
                self::addInto($totals, $row['stats']);
            }
        }

        usort($rows, static function (array $a, array $b): int {
            $ta = $a['type'] === 'person' ? 0 : 1;
            $tb = $b['type'] === 'person' ? 0 : 1;
            if ($ta !== $tb) {
                return $ta <=> $tb;
            }
            return strcasecmp((string) $a['name'], (string) $b['name']);
        });

        $peopleRows = array_values(array_filter($rows, static fn (array $r): bool => $r['type'] === 'person'));
        $companyRows = array_values(array_filter($rows, static fn (array $r): bool => $r['type'] === 'company'));

        $withData = array_values(array_filter(
            $rows,
            static fn (array $r): bool => $r['has_plan'] && $r['has_year'] && (
                abs((float) $r['stats']['income_annual']) > 0.009
                || abs((float) $r['stats']['expense_annual']) > 0.009
                || abs((float) $r['stats']['savings_annual']) > 0.009
                || abs((float) $r['stats']['balance_annual']) > 0.009
            )
        ));

        $byEntityLabels = [];
        $byEntityIncome = [];
        $byEntityExpense = [];
        $byEntityBalance = [];
        $byEntitySavings = [];
        foreach ($withData as $r) {
            $byEntityLabels[] = (string) $r['name'];
            $byEntityIncome[] = (float) $r['stats']['income_annual'];
            $byEntityExpense[] = (float) $r['stats']['expense_annual'];
            $byEntityBalance[] = (float) $r['stats']['balance_annual'];
            $byEntitySavings[] = (float) $r['stats']['savings_annual'];
        }

        $peopleIncome = 0.0;
        $peopleExpense = 0.0;
        $peopleSavings = 0.0;
        $companyIncome = 0.0;
        $companyExpense = 0.0;
        $companySavings = 0.0;
        foreach ($peopleRows as $r) {
            if ($r['has_plan'] && $r['has_year']) {
                $peopleIncome += (float) $r['stats']['income_annual'];
                $peopleExpense += (float) $r['stats']['expense_annual'];
                $peopleSavings += (float) $r['stats']['savings_annual'];
            }
        }
        foreach ($companyRows as $r) {
            if ($r['has_plan'] && $r['has_year']) {
                $companyIncome += (float) $r['stats']['income_annual'];
                $companyExpense += (float) $r['stats']['expense_annual'];
                $companySavings += (float) $r['stats']['savings_annual'];
            }
        }

        $savingsMonths = $totals['savings_months'];
        $savingsCum = [];
        $running = 0.0;
        foreach ($savingsMonths as $m) {
            $running += (float) $m;
            $savingsCum[] = $running;
        }

        $burdenMonths = [];
        for ($i = 0; $i < 12; $i++) {
            $inc = (float) ($totals['income_months'][$i] ?? 0);
            $exp = max(0.0, (float) ($totals['expense_months'][$i] ?? 0));
            $burdenMonths[] = $inc > 0.009 ? round(($exp / $inc) * 1000) / 10 : 0.0;
        }
        $incomeAnnual = (float) ($totals['income_annual'] ?? 0);
        $expenseAnnual = max(0.0, (float) ($totals['expense_annual'] ?? 0));
        $burdenAnnual = $incomeAnnual > 0.009
            ? round(($expenseAnnual / $incomeAnnual) * 1000) / 10
            : null;

        $burdenByEntity = [];
        foreach ($withData as $r) {
            $ei = (float) ($r['stats']['income_annual'] ?? 0);
            $ee = max(0.0, (float) ($r['stats']['expense_annual'] ?? 0));
            $burdenByEntity[] = $ei > 0.009 ? round(($ee / $ei) * 1000) / 10 : 0.0;
        }

        $peopleBurden = $peopleIncome > 0.009
            ? round((max(0.0, $peopleExpense) / $peopleIncome) * 1000) / 10
            : 0.0;
        $companyBurden = $companyIncome > 0.009
            ? round((max(0.0, $companyExpense) / $companyIncome) * 1000) / 10
            : 0.0;

        return [
            'year' => $selected,
            'years' => $yearNames,
            'scope' => $scope,
            'rows' => $rows,
            'people' => $peopleRows,
            'companies' => $companyRows,
            'totals' => $totals,
            'counts' => [
                'people' => count($peopleRows),
                'companies' => count($companyRows),
                'with_plan' => count(array_filter($rows, static fn (array $r): bool => $r['has_plan'])),
                'with_year' => count(array_filter($rows, static fn (array $r): bool => $r['has_plan'] && $r['has_year'])),
            ],
            'months' => self::MONTHS,
            'charts' => [
                'income_months' => $totals['income_months'],
                'expense_months' => $totals['expense_months'],
                'balance_months' => $totals['balance_months'],
                'profit_months' => $totals['profit_months'],
                'savings_months' => $savingsMonths,
                'savings_cum' => $savingsCum,
                'by_entity' => [
                    'labels' => $byEntityLabels,
                    'income' => $byEntityIncome,
                    'expense' => $byEntityExpense,
                    'balance' => $byEntityBalance,
                    'savings' => $byEntitySavings,
                    'burden_pct' => $burdenByEntity,
                ],
                'mix' => [
                    'labels' => ['Ingresos', 'Egresos'],
                    'values' => [
                        max(0.0, $incomeAnnual),
                        max(0.0, $expenseAnnual),
                    ],
                ],
                'savings_mix' => [
                    'labels' => $byEntityLabels,
                    'values' => $byEntitySavings,
                ],
                'groups' => [
                    'labels' => ['Personas', 'Empresas'],
                    'income' => [$peopleIncome, $companyIncome],
                    'expense' => [$peopleExpense, $companyExpense],
                    'savings' => [$peopleSavings, $companySavings],
                    'burden_pct' => [$peopleBurden, $companyBurden],
                ],
                'burden_months' => $burdenMonths,
                'burden_annual' => $burdenAnnual,
                'burden_total' => [
                    'expense' => $expenseAnnual,
                    'savings' => max(0.0, (float) ($totals['savings_annual'] ?? 0)),
                    // Disponible neto = ingresos − egresos − ahorro (no el balance bruto).
                    'rest' => max(0.0, (float) ($totals['profit_annual'] ?? 0)),
                    'income' => $incomeAnnual,
                    'pct' => $burdenAnnual,
                ],
            ],
        ];
    }

    /**
     * @param list<array{id:int|string,name:string,type?:string}> $people
     * @param list<array{id:int|string,name:string,type?:string}> $companies
     * @return list<string>
     */
    private static function collectYearNames(array $people, array $companies): array
    {
        $set = [];
        foreach ($people as $ent) {
            $plan = PersonFinancialPlanService::find((int) $ent['id']);
            if ($plan === null) {
                continue;
            }
            foreach ($plan['workbook']['years'] ?? [] as $y) {
                $name = trim((string) ($y['name'] ?? ''));
                if ($name !== '') {
                    $set[$name] = true;
                }
            }
        }
        foreach ($companies as $ent) {
            $plan = FinancialPlanService::find((int) $ent['id']);
            if ($plan === null) {
                continue;
            }
            foreach ($plan['workbook']['years'] ?? [] as $y) {
                $name = trim((string) ($y['name'] ?? ''));
                if ($name !== '') {
                    $set[$name] = true;
                }
            }
        }
        $names = array_map('strval', array_keys($set));
        usort($names, static function (string $a, string $b): int {
            $na = (int) preg_replace('/\D+/', '', $a);
            $nb = (int) preg_replace('/\D+/', '', $b);
            if ($na > 0 && $nb > 0 && $na !== $nb) {
                return $na <=> $nb;
            }
            return strcasecmp($a, $b);
        });
        if ($names === []) {
            $names[] = (string) date('Y');
        }
        return $names;
    }

    /** @param list<string> $years */
    private static function pickYear(array $years, ?string $requested): string
    {
        $years = array_values(array_map('strval', $years));
        $requested = $requested !== null ? trim((string) $requested) : '';
        if ($requested !== '' && in_array($requested, $years, true)) {
            return $requested;
        }
        $current = (string) date('Y');
        if (in_array($current, $years, true)) {
            return $current;
        }
        $last = $years[count($years) - 1] ?? $current;
        return (string) $last;
    }

    /**
     * @return array<string,mixed>
     */
    private static function rowForEntity(int $entityId, string $name, string $type, string $yearName): array
    {
        $plan = $type === 'person'
            ? PersonFinancialPlanService::find($entityId)
            : FinancialPlanService::find($entityId);

        $href = $type === 'person'
            ? '/personas/' . $entityId . '?tab=proyecciones'
            : '/empresas/' . $entityId . '?tab=estados-proyeccion';

        if ($plan === null) {
            return [
                'entity_id' => $entityId,
                'name' => $name,
                'type' => $type,
                'href' => $href,
                'has_plan' => false,
                'has_year' => false,
                'updated_at' => null,
                'stats' => self::emptyStats($yearName),
            ];
        }

        $idx = self::yearIndexByName($plan['workbook'], $yearName);
        $hasYear = $idx !== null;
        $stats = $hasYear
            ? ($type === 'person'
                ? PersonFinancialPlanService::yearStats($plan['workbook'], $idx)
                : FinancialPlanService::yearStats($plan['workbook'], $idx))
            : self::emptyStats($yearName);

        return [
            'entity_id' => $entityId,
            'name' => $name,
            'type' => $type,
            'href' => $href,
            'has_plan' => true,
            'has_year' => $hasYear,
            'updated_at' => $plan['updated_at'] ?? null,
            'stats' => $stats,
        ];
    }

    /** @param array<string,mixed> $workbook */
    private static function yearIndexByName(array $workbook, string $yearName): ?int
    {
        foreach ($workbook['years'] ?? [] as $i => $y) {
            if (trim((string) ($y['name'] ?? '')) === $yearName) {
                return (int) $i;
            }
        }
        return null;
    }

    /** @return array<string,mixed> */
    private static function emptyStats(string $yearName): array
    {
        $z = array_fill(0, 12, 0.0);
        return [
            'name' => $yearName,
            'savings_rate' => 0.0,
            'clients_count' => 0,
            'expenses_count' => 0,
            'income_months' => $z,
            'expense_months' => $z,
            'balance_months' => $z,
            'savings_months' => $z,
            'profit_months' => $z,
            'income_annual' => 0.0,
            'expense_annual' => 0.0,
            'balance_annual' => 0.0,
            'savings_annual' => 0.0,
            'profit_annual' => 0.0,
        ];
    }

    /** @return array<string,mixed> */
    private static function emptyTotals(string $yearName): array
    {
        return self::emptyStats($yearName);
    }

    /**
     * @param array<string,mixed> $totals
     * @param array<string,mixed> $stats
     */
    private static function addInto(array &$totals, array $stats): void
    {
        foreach (['income_months', 'expense_months', 'balance_months', 'savings_months', 'profit_months'] as $key) {
            $src = $stats[$key] ?? [];
            for ($i = 0; $i < 12; $i++) {
                $totals[$key][$i] = (float) ($totals[$key][$i] ?? 0) + (float) ($src[$i] ?? 0);
            }
        }
        foreach (['income_annual', 'expense_annual', 'balance_annual', 'savings_annual', 'profit_annual'] as $key) {
            $totals[$key] = (float) ($totals[$key] ?? 0) + (float) ($stats[$key] ?? 0);
        }
        $totals['clients_count'] = (int) ($totals['clients_count'] ?? 0) + (int) ($stats['clients_count'] ?? 0);
        $totals['expenses_count'] = (int) ($totals['expenses_count'] ?? 0) + (int) ($stats['expenses_count'] ?? 0);
    }
}
