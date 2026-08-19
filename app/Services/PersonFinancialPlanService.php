<?php

declare(strict_types=1);

/**
 * Proyección de flujo personal (ingresos/egresos mensuales por año).
 * Workbook JSON v2 template=personal.
 */
final class PersonFinancialPlanService
{
    private static bool $ready = false;

    public static function ensureTable(): void
    {
        if (self::$ready) {
            return;
        }
        Database::pdo()->exec(
            'CREATE TABLE IF NOT EXISTS person_financial_plans (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                entity_id INT UNSIGNED NOT NULL,
                workbook_json LONGTEXT NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_pfp_entity (entity_id),
                CONSTRAINT fk_pfp_entity FOREIGN KEY (entity_id) REFERENCES entities(id) ON DELETE CASCADE
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        self::$ready = true;
    }

    /** @return array{id:int,entity_id:int,workbook:array<string,mixed>,updated_at:?string} */
    public static function ensureForPerson(int $entityId): array
    {
        self::ensureTable();
        $existing = self::find($entityId);
        if ($existing !== null) {
            return $existing;
        }
        return self::insert($entityId, self::emptyWorkbook());
    }

    /** @return array{id:int,entity_id:int,workbook:array<string,mixed>,updated_at:?string}|null */
    public static function find(int $entityId): ?array
    {
        self::ensureTable();
        $stmt = Database::pdo()->prepare(
            'SELECT id, entity_id, workbook_json, updated_at
             FROM person_financial_plans
             WHERE entity_id = :e
             LIMIT 1'
        );
        $stmt->execute(['e' => $entityId]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }
        $workbook = json_decode((string) $row['workbook_json'], true);
        if (!is_array($workbook)) {
            $workbook = self::emptyWorkbook();
        }
        return [
            'id' => (int) $row['id'],
            'entity_id' => (int) $row['entity_id'],
            'workbook' => self::normalizeWorkbook($workbook),
            'updated_at' => $row['updated_at'] !== null ? (string) $row['updated_at'] : null,
        ];
    }

    /**
     * @param array<string,mixed> $workbook
     * @return array{id:int,entity_id:int,workbook:array<string,mixed>,updated_at:?string}
     */
    public static function save(int $entityId, array $workbook): array
    {
        self::ensureTable();
        $workbook = self::normalizeWorkbook($workbook);
        $json = json_encode($workbook, JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new RuntimeException('No se pudo serializar la proyección.');
        }
        $existing = self::find($entityId);
        if ($existing === null) {
            return self::insert($entityId, $workbook);
        }
        Database::pdo()->prepare(
            'UPDATE person_financial_plans
             SET workbook_json = :j, updated_at = CURRENT_TIMESTAMP
             WHERE entity_id = :e'
        )->execute(['j' => $json, 'e' => $entityId]);
        $found = self::find($entityId);
        if ($found === null) {
            throw new RuntimeException('No se pudo releer la proyección.');
        }
        return $found;
    }

    /** @return array{id:int,entity_id:int,workbook:array<string,mixed>,updated_at:?string} */
    public static function reset(int $entityId): array
    {
        return self::save($entityId, self::emptyWorkbook());
    }

    /** @return array<string,mixed> */
    public static function emptyWorkbook(?int $baseYear = null): array
    {
        $y = $baseYear ?? (int) date('Y');
        $years = [];
        foreach ([$y - 1, $y, $y + 1] as $year) {
            $years[] = self::emptyYear((string) $year);
        }
        return [
            'version' => 2,
            'template' => 'personal',
            'activeYear' => 1,
            'years' => $years,
        ];
    }

    /**
     * @param array<string,mixed> $workbook
     * @return array<string,mixed>
     */
    public static function yearStats(array $workbook, ?int $yearIndex = null): array
    {
        $workbook = self::normalizeWorkbook($workbook);
        $idx = $yearIndex ?? (int) ($workbook['activeYear'] ?? 0);
        $years = $workbook['years'];
        if ($years === [] || !isset($years[$idx])) {
            $idx = 0;
        }
        $year = $years[$idx] ?? self::emptyYear((string) date('Y'));
        return self::computeYearStats($year);
    }

    /**
     * Índice de la hoja del año calendario (o la más cercana).
     *
     * @param array<string,mixed> $workbook
     */
    public static function yearIndexForCalendar(array $workbook, ?int $calendarYear = null): int
    {
        $workbook = self::normalizeWorkbook($workbook);
        $years = $workbook['years'] ?? [];
        if (!is_array($years) || $years === []) {
            return 0;
        }
        $now = $calendarYear ?? (int) date('Y');
        $exact = null;
        $closest = 0;
        $closestDist = PHP_INT_MAX;
        foreach ($years as $i => $y) {
            $n = (int) preg_replace('/\D+/', '', (string) ($y['name'] ?? ''));
            if ($n < 1900) {
                continue;
            }
            if ($n === $now) {
                $exact = (int) $i;
                break;
            }
            $dist = abs($n - $now);
            if ($dist < $closestDist) {
                $closestDist = $dist;
                $closest = (int) $i;
            }
        }
        return $exact ?? $closest;
    }

    /**
     * @param array<string,mixed> $year
     * @return array<string,mixed>
     */
    public static function computeYearStats(array $year): array
    {
        // Reusa la matemática de empresas: income[] ≡ clients[].
        $asServices = $year;
        $asServices['clients'] = $year['income'] ?? [];
        return FinancialPlanService::computeYearStats($asServices, 'services');
    }

    /**
     * Compara flujo proyectado vs patrimonio real de la persona.
     *
     * @param array<string,mixed> $summary PatrimonioService::forEntity
     * @param array<string,mixed> $stats yearStats
     * @return array<string,mixed>
     */
    public static function compareToReality(array $summary, array $stats): array
    {
        $net = (float) ($summary['net_worth'] ?? 0);
        $liq = (float) ($summary['liquidity'] ?? 0);
        $liab = (float) ($summary['liabilities'] ?? 0);
        $ownAssets = (float) ($summary['accounts'] ?? 0)
            + (float) ($summary['assets'] ?? 0)
            + (float) ($summary['properties'] ?? 0)
            + (float) ($summary['receivables'] ?? 0)
            + (float) ($summary['inventories'] ?? 0);
        $parts = (float) ($summary['participations'] ?? 0);

        $income = (float) ($stats['income_annual'] ?? 0);
        $expense = (float) ($stats['expense_annual'] ?? 0);
        $balance = (float) ($stats['balance_annual'] ?? 0);
        $savings = (float) ($stats['savings_annual'] ?? 0);
        $profit = (float) ($stats['profit_annual'] ?? 0);
        $avgExpenseM = $expense / 12.0;
        $avgIncomeM = $income / 12.0;
        $avgBalanceM = $balance / 12.0;
        $avgProfitM = $profit / 12.0;

        $debtCover = $liab > 0.01 ? ($liq / $liab) : null;
        $saveRateReal = $income > 0.01 ? ($balance / $income) : null;
        $incomeVsAssets = $ownAssets > 0.01 ? ($income / $ownAssets) : null;
        $projectedNet = $net + $profit;

        return [
            'real' => [
                'net_worth' => $net,
                'liquidity' => $liq,
                'liabilities' => $liab,
                'own_assets' => $ownAssets,
                'participations' => $parts,
            ],
            'projected' => [
                'income_annual' => $income,
                'expense_annual' => $expense,
                'balance_annual' => $balance,
                'savings_annual' => $savings,
                'profit_annual' => $profit,
                'avg_income_m' => $avgIncomeM,
                'avg_expense_m' => $avgExpenseM,
                'avg_balance_m' => $avgBalanceM,
                'avg_profit_m' => $avgProfitM,
            ],
            'insights' => [
                'monthly_avg' => round($avgProfitM, 2),
                'debt_cover' => $debtCover !== null ? round($debtCover, 2) : null,
                'cashflow_rate' => $saveRateReal !== null ? round($saveRateReal * 100, 1) : null,
                'income_vs_assets' => $incomeVsAssets !== null ? round($incomeVsAssets * 100, 1) : null,
                'projected_net_after_year' => $projectedNet,
            ],
        ];
    }

    /** @return array<string,mixed> */
    private static function emptyYear(string $name): array
    {
        return [
            'name' => $name,
            'savings_rate' => 0.1,
            'income' => [
                ['name' => 'Sueldo / honorarios', 'months' => array_fill(0, 12, 0.0)],
            ],
            'expenses' => [
                ['name' => 'Gastos fijos', 'months' => array_fill(0, 12, 0.0)],
            ],
        ];
    }

    /**
     * @param array<string,mixed> $workbook
     * @return array<string,mixed>
     */
    private static function normalizeWorkbook(array $workbook): array
    {
        $yearsIn = is_array($workbook['years'] ?? null) ? $workbook['years'] : [];
        $years = [];
        foreach ($yearsIn as $y) {
            if (!is_array($y)) {
                continue;
            }
            $years[] = [
                'name' => trim((string) ($y['name'] ?? '')) ?: (string) date('Y'),
                'savings_rate' => max(0.0, min(1.0, (float) ($y['savings_rate'] ?? 0.1))),
                'income' => self::normalizeLines($y['income'] ?? $y['clients'] ?? []),
                'expenses' => self::normalizeLines($y['expenses'] ?? []),
            ];
        }
        if ($years === []) {
            return self::emptyWorkbook();
        }
        $active = (int) ($workbook['activeYear'] ?? 0);
        if ($active < 0 || $active >= count($years)) {
            $active = min(1, count($years) - 1);
        }
        return [
            'version' => 2,
            'template' => 'personal',
            'activeYear' => $active,
            'years' => $years,
        ];
    }

    /**
     * @param mixed $lines
     * @return list<array{name:string,months:list<float>}>
     */
    private static function normalizeLines(mixed $lines): array
    {
        if (!is_array($lines)) {
            return [];
        }
        $out = [];
        foreach ($lines as $row) {
            if (!is_array($row)) {
                continue;
            }
            $months = [];
            $src = is_array($row['months'] ?? null) ? $row['months'] : [];
            for ($i = 0; $i < 12; $i++) {
                $months[] = round((float) ($src[$i] ?? 0), 2);
            }
            $out[] = [
                'name' => trim((string) ($row['name'] ?? '')),
                'months' => $months,
            ];
        }
        return $out;
    }

    /**
     * @param array<string,mixed> $workbook
     * @return array{id:int,entity_id:int,workbook:array<string,mixed>,updated_at:?string}
     */
    private static function insert(int $entityId, array $workbook): array
    {
        $workbook = self::normalizeWorkbook($workbook);
        $json = json_encode($workbook, JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new RuntimeException('No se pudo serializar la proyección.');
        }
        Database::pdo()->prepare(
            'INSERT INTO person_financial_plans (entity_id, workbook_json)
             VALUES (:e, :j)'
        )->execute(['e' => $entityId, 'j' => $json]);
        $found = self::find($entityId);
        if ($found === null) {
            throw new RuntimeException('No se pudo crear la proyección.');
        }
        return $found;
    }
}
