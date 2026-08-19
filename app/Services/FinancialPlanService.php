<?php

declare(strict_types=1);

/**
 * Estados y proyección por empresa (dashboard + tablas HTML, JSON v2).
 */
final class FinancialPlanService
{
    private static bool $tableReady = false;

    public static function ensureTable(): void
    {
        if (self::$tableReady) {
            return;
        }
        Database::pdo()->exec(
            'CREATE TABLE IF NOT EXISTS company_financial_plans (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                entity_id INT UNSIGNED NOT NULL,
                workbook_json LONGTEXT NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_cfp_entity (entity_id),
                CONSTRAINT fk_cfp_entity FOREIGN KEY (entity_id) REFERENCES entities(id) ON DELETE CASCADE
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        self::$tableReady = true;
    }

    /** @return array{id:int,entity_id:int,workbook:array<string,mixed>,updated_at:?string} */
    public static function ensureForCompany(
        int $entityId,
        ?string $companyName = null,
        ?string $businessModel = null
    ): array {
        self::ensureTable();
        CompanySchema::ensure();
        $existing = self::find($entityId);
        if ($existing !== null) {
            return $existing;
        }

        $name = $companyName ?? self::companyName($entityId);
        $model = $businessModel ?? self::businessModelFor($entityId);
        return self::insert($entityId, self::seedWorkbookFor($name, $model));
    }

    public static function createForNewCompany(
        int $entityId,
        string $companyName,
        string $businessModel = 'services'
    ): void {
        self::ensureForCompany($entityId, $companyName, $businessModel);
    }

    public static function ensureAllCompanies(): int
    {
        self::ensureTable();
        $rows = Database::pdo()->query(
            "SELECT e.id, e.name
             FROM entities e
             INNER JOIN companies c ON c.entity_id = e.id
             WHERE e.type = 'company'
             ORDER BY e.id"
        )->fetchAll();
        $created = 0;
        foreach ($rows as $row) {
            $before = self::find((int) $row['id']);
            self::ensureForCompany((int) $row['id'], (string) $row['name']);
            if ($before === null) {
                $created++;
            }
        }
        return $created;
    }

    /** Fuerza plantilla según business_model (Soup seed solo servicios). */
    public static function forceReset(int $entityId, ?string $companyName = null): array
    {
        CompanySchema::ensure();
        $name = $companyName ?? self::companyName($entityId);
        $model = self::businessModelFor($entityId);
        return self::save($entityId, self::seedWorkbookFor($name, $model));
    }

    /** @return array{id:int,entity_id:int,workbook:array<string,mixed>,updated_at:?string}|null */
    public static function find(int $entityId): ?array
    {
        self::ensureTable();
        $stmt = Database::pdo()->prepare(
            'SELECT id, entity_id, workbook_json, updated_at
             FROM company_financial_plans
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
        $normalized = self::normalizeWorkbook($workbook);
        // Persist migration grid→v2 once
        if ((int) ($workbook['version'] ?? 0) < 2) {
            self::persistJson($entityId, $normalized);
        }
        return [
            'id' => (int) $row['id'],
            'entity_id' => (int) $row['entity_id'],
            'workbook' => $normalized,
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
        self::persistJson($entityId, $workbook);
        $fresh = self::find($entityId);
        if ($fresh === null) {
            $fresh = self::insert($entityId, $workbook);
        }
        if (($fresh['workbook']['template'] ?? 'services') === 'services') {
            try {
                CompanyClientService::syncFromProjection($entityId, $fresh['workbook']);
            } catch (Throwable) {
                // Fichas son complemento; no bloquear el guardado del plan.
            }
        }
        return $fresh;
    }

    /**
     * Clientes con nombre en el workbook (servicios), agregados por nombre.
     *
     * @param array<string,mixed> $workbook
     * @return list<array{
     *   name:string,
     *   value_total:float,
     *   years:array<string,float>
     * }>
     */
    public static function clientsFromWorkbook(array $workbook): array
    {
        $workbook = self::normalizeWorkbook($workbook);
        if (($workbook['template'] ?? 'services') === 'products') {
            return [];
        }
        $years = $workbook['years'] ?? [];
        if (!is_array($years) || $years === []) {
            return [];
        }

        /** @var array<string,array{name:string,years:array<string,float>}> $byKey */
        $byKey = [];
        foreach ($years as $year) {
            if (!is_array($year)) {
                continue;
            }
            $yearName = trim((string) ($year['name'] ?? ''));
            if ($yearName === '') {
                $yearName = 'Año';
            }
            $clients = $year['clients'] ?? [];
            if (!is_array($clients)) {
                continue;
            }
            foreach ($clients as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $name = trim((string) ($row['name'] ?? ''));
                if ($name === '') {
                    continue;
                }
                $months = $row['months'] ?? [];
                $sum = 0.0;
                if (is_array($months)) {
                    foreach ($months as $m) {
                        $sum += (float) $m;
                    }
                }
                $key = mb_strtolower($name);
                if (!isset($byKey[$key])) {
                    $byKey[$key] = ['name' => $name, 'years' => []];
                }
                $byKey[$key]['years'][$yearName] = round(($byKey[$key]['years'][$yearName] ?? 0) + $sum, 2);
            }
        }

        $out = [];
        foreach ($byKey as $item) {
            $yearsMap = $item['years'];
            $total = 0.0;
            foreach ($yearsMap as $v) {
                $total += $v;
            }
            $out[] = [
                'name' => $item['name'],
                'value_total' => round($total, 2),
                'years' => $yearsMap,
            ];
        }
        usort($out, static fn (array $a, array $b): int => strcasecmp($a['name'], $b['name']));
        return $out;
    }

    public static function reset(int $entityId, ?string $companyName = null): array
    {
        return self::forceReset($entityId, $companyName);
    }

    /** @return array<string,mixed> */
    public static function emptyWorkbook(?int $baseYear = null, string $template = 'services'): array
    {
        $template = $template === 'products' ? 'products' : 'services';
        $y = $baseYear ?? (int) date('Y');
        $years = [];
        foreach ([$y - 1, $y, $y + 1] as $year) {
            $years[] = self::emptyYear((string) $year, $template);
        }
        return [
            'version' => 2,
            'template' => $template,
            'activeYear' => 1,
            'profit_split_n' => 1,
            'years' => $years,
        ];
    }

    public static function businessModelFor(int $entityId): string
    {
        CompanySchema::ensure();
        $stmt = Database::pdo()->prepare(
            'SELECT business_model FROM companies WHERE entity_id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $entityId]);
        return CompanySchema::normalizeModel($stmt->fetchColumn() ?: 'services');
    }

    /** @return array<string,mixed> */
    private static function seedWorkbookFor(string $companyName, string $businessModel): array
    {
        $model = CompanySchema::normalizeModel($businessModel);
        if ($model === 'products') {
            return self::emptyWorkbook(null, 'products');
        }
        if (self::isSoupCompany($companyName)) {
            $seed = self::loadSoupSeed();
            if ($seed !== null) {
                $seed['template'] = 'services';
                return self::normalizeWorkbook($seed);
            }
        }
        return self::emptyWorkbook(null, 'services');
    }

    public static function isSoupCompany(string $name): bool
    {
        $n = mb_strtolower(trim($name));
        return $n === 'soup it' || str_contains($n, 'soup');
    }

    /** @return array<string,mixed>|null */
    public static function loadSoupSeed(): ?array
    {
        $paths = [
            dirname(__DIR__, 2) . '/storage/templates/gestion_financiera_soup_v2.json',
            dirname(__DIR__, 2) . '/storage/templates/gestion_financiera_soup.json',
        ];
        foreach ($paths as $path) {
            if (!is_file($path)) {
                continue;
            }
            $raw = file_get_contents($path);
            if ($raw === false) {
                continue;
            }
            $data = json_decode($raw, true);
            if (!is_array($data)) {
                continue;
            }
            return self::normalizeWorkbook($data);
        }
        return null;
    }

    /**
     * KPIs del año activo (o índice dado).
     *
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
        $template = (($workbook['template'] ?? 'services') === 'products') ? 'products' : 'services';
        $year = $years[$idx] ?? self::emptyYear((string) date('Y'), $template);
        return self::computeYearStats($year, $template);
    }

    /**
     * @param array<string,mixed> $year
     * @return array<string,mixed>
     */
    public static function computeYearStats(array $year, string $template = 'services'): array
    {
        $clients = $year['clients'] ?? [];
        $expenses = $year['expenses'] ?? [];
        $rate = (float) ($year['savings_rate'] ?? 0.1);
        $incomeMonths = array_fill(0, 12, 0.0);
        $expenseMonths = array_fill(0, 12, 0.0);

        if ($template === 'products') {
            $src = $year['income_months'] ?? [];
            for ($i = 0; $i < 12; $i++) {
                $incomeMonths[$i] = (float) ($src[$i] ?? 0);
            }
        } else {
            foreach ($clients as $row) {
                $months = $row['months'] ?? [];
                for ($i = 0; $i < 12; $i++) {
                    $incomeMonths[$i] += (float) ($months[$i] ?? 0);
                }
            }
        }
        foreach ($expenses as $row) {
            $months = $row['months'] ?? [];
            for ($i = 0; $i < 12; $i++) {
                $expenseMonths[$i] += (float) ($months[$i] ?? 0);
            }
        }

        $balanceMonths = [];
        $savingsMonths = [];
        $profitMonths = [];
        for ($i = 0; $i < 12; $i++) {
            $bal = $incomeMonths[$i] - $expenseMonths[$i];
            // Meta de ahorro solo sobre saldo positivo (no “ahorro” negativo).
            $sav = $bal > 0.0 ? ($bal * $rate) : 0.0;
            $balanceMonths[] = $bal;
            $savingsMonths[] = $sav;
            $profitMonths[] = $bal - $sav;
        }

        $incomeAnnual = array_sum($incomeMonths);
        $expenseAnnual = array_sum($expenseMonths);
        $balanceAnnual = array_sum($balanceMonths);
        $savingsAnnual = array_sum($savingsMonths);
        $profitAnnual = array_sum($profitMonths);

        $activeClients = 0;
        foreach ($clients as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            $sum = array_sum(array_map('floatval', $row['months'] ?? []));
            if ($name !== '' || $sum > 0) {
                $activeClients++;
            }
        }
        $activeExpenses = 0;
        foreach ($expenses as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            $sum = array_sum(array_map('floatval', $row['months'] ?? []));
            if ($name !== '' || $sum > 0) {
                $activeExpenses++;
            }
        }

        return [
            'name' => (string) ($year['name'] ?? ''),
            'savings_rate' => $rate,
            'clients_count' => $activeClients,
            'expenses_count' => $activeExpenses,
            'income_months' => $incomeMonths,
            'expense_months' => $expenseMonths,
            'balance_months' => $balanceMonths,
            'savings_months' => $savingsMonths,
            'profit_months' => $profitMonths,
            'income_annual' => $incomeAnnual,
            'expense_annual' => $expenseAnnual,
            'balance_annual' => $balanceAnnual,
            'savings_annual' => $savingsAnnual,
            'profit_annual' => $profitAnnual,
        ];
    }

    private static function companyName(int $entityId): string
    {
        $stmt = Database::pdo()->prepare('SELECT name FROM entities WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $entityId]);
        return (string) ($stmt->fetchColumn() ?: '');
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
            throw new RuntimeException('No se pudo serializar la planilla.');
        }
        Database::pdo()->prepare(
            'INSERT INTO company_financial_plans (entity_id, workbook_json)
             VALUES (:e, :j)
             ON DUPLICATE KEY UPDATE workbook_json = VALUES(workbook_json), updated_at = CURRENT_TIMESTAMP'
        )->execute(['e' => $entityId, 'j' => $json]);
        $fresh = self::find($entityId);
        if ($fresh === null) {
            throw new RuntimeException('No se pudo crear el plan financiero.');
        }
        return $fresh;
    }

    /** @param array<string,mixed> $workbook */
    private static function persistJson(int $entityId, array $workbook): void
    {
        $json = json_encode($workbook, JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new RuntimeException('No se pudo serializar la planilla.');
        }
        $pdo = Database::pdo();
        $exists = $pdo->prepare('SELECT id FROM company_financial_plans WHERE entity_id = :e LIMIT 1');
        $exists->execute(['e' => $entityId]);
        if ($exists->fetchColumn()) {
            $pdo->prepare(
                'UPDATE company_financial_plans
                 SET workbook_json = :j, updated_at = CURRENT_TIMESTAMP
                 WHERE entity_id = :e'
            )->execute(['j' => $json, 'e' => $entityId]);
            return;
        }
        $pdo->prepare(
            'INSERT INTO company_financial_plans (entity_id, workbook_json) VALUES (:e, :j)'
        )->execute(['e' => $entityId, 'j' => $json]);
    }

    /**
     * @param array<string,mixed> $workbook
     * @return array<string,mixed>
     */
    private static function normalizeWorkbook(array $workbook): array
    {
        $version = (int) ($workbook['version'] ?? 0);
        if ($version < 2 && isset($workbook['sheets']) && is_array($workbook['sheets'])) {
            $workbook = self::fromGridWorkbook($workbook);
        }

        $template = (($workbook['template'] ?? 'services') === 'products') ? 'products' : 'services';
        $yearsIn = $workbook['years'] ?? null;
        if (!is_array($yearsIn) || $yearsIn === []) {
            return self::emptyWorkbook(null, $template);
        }

        $years = [];
        foreach ($yearsIn as $year) {
            if (!is_array($year)) {
                continue;
            }
            $years[] = self::normalizeYear($year, $template);
        }
        if ($years === []) {
            return self::emptyWorkbook(null, $template);
        }

        $active = (int) ($workbook['activeYear'] ?? $workbook['activeSheet'] ?? 0);
        if ($active < 0 || $active >= count($years)) {
            $active = 0;
        }

        return [
            'version' => 2,
            'template' => $template,
            'activeYear' => $active,
            'profit_split_n' => self::normalizeSplitN($workbook['profit_split_n'] ?? 1),
            'years' => $years,
        ];
    }

    private static function normalizeSplitN(mixed $n): int
    {
        $v = (int) $n;
        if ($v < 1) {
            return 1;
        }
        if ($v > 99) {
            return 99;
        }
        return $v;
    }

    /**
     * @param array<string,mixed> $year
     * @return array<string,mixed>
     */
    private static function normalizeYear(array $year, string $template = 'services'): array
    {
        $name = trim((string) ($year['name'] ?? date('Y')));
        if ($name === '') {
            $name = (string) date('Y');
        }
        $rate = (float) ($year['savings_rate'] ?? 0.1);
        if ($rate < 0) {
            $rate = 0.0;
        }
        if ($rate > 1) {
            $rate = $rate > 100 ? 0.1 : $rate / 100;
        }

        $out = [
            'name' => $name,
            'savings_rate' => $rate,
            'clients' => $template === 'products' ? [] : self::normalizeLines($year['clients'] ?? []),
            'expenses' => self::normalizeLines($year['expenses'] ?? []),
        ];
        if ($template === 'products') {
            $months = [];
            $src = $year['income_months'] ?? [];
            if (!is_array($src)) {
                $src = [];
            }
            for ($i = 0; $i < 12; $i++) {
                $months[] = round((float) ($src[$i] ?? 0), 2);
            }
            $out['income_months'] = $months;
        }
        return $out;
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
        foreach ($lines as $line) {
            if (!is_array($line)) {
                continue;
            }
            $months = [];
            $src = $line['months'] ?? [];
            if (!is_array($src)) {
                $src = [];
            }
            for ($i = 0; $i < 12; $i++) {
                $months[] = round((float) ($src[$i] ?? 0), 2);
            }
            $out[] = [
                'name' => trim((string) ($line['name'] ?? '')),
                'months' => $months,
            ];
        }
        return $out;
    }

    /** @return array<string,mixed> */
    private static function emptyYear(string $name, string $template = 'services'): array
    {
        $blankMonths = array_fill(0, 12, 0.0);
        $blankLines = static function (int $n) use ($blankMonths): array {
            $rows = [];
            for ($i = 0; $i < $n; $i++) {
                $rows[] = ['name' => '', 'months' => $blankMonths];
            }
            return $rows;
        };
        if ($template === 'products') {
            return [
                'name' => $name,
                'savings_rate' => 0.1,
                'income_months' => $blankMonths,
                'clients' => [],
                'expenses' => $blankLines(6),
            ];
        }
        return [
            'name' => $name,
            'savings_rate' => 0.1,
            'clients' => $blankLines(6),
            'expenses' => $blankLines(6),
        ];
    }

    /**
     * Migra workbook v1 (grid LuckySheet-like) → v2 estructurado.
     *
     * @param array<string,mixed> $workbook
     * @return array<string,mixed>
     */
    private static function fromGridWorkbook(array $workbook): array
    {
        $years = [];
        foreach ($workbook['sheets'] ?? [] as $sheet) {
            if (!is_array($sheet)) {
                continue;
            }
            $years[] = self::yearFromGrid(
                (string) ($sheet['name'] ?? date('Y')),
                $sheet['grid'] ?? []
            );
        }
        if ($years === []) {
            return self::emptyWorkbook();
        }
        $active = (int) ($workbook['activeSheet'] ?? 0);
        if ($active < 0 || $active >= count($years)) {
            $active = 0;
        }
        return [
            'version' => 2,
            'activeYear' => $active,
            'profit_split_n' => self::normalizeSplitN($workbook['profit_split_n'] ?? 1),
            'years' => $years,
        ];
    }

    /**
     * @param list<mixed> $grid
     * @return array<string,mixed>
     */
    private static function yearFromGrid(string $name, array $grid): array
    {
        $clients = [];
        $expenses = [];
        $savingsRate = 0.1;
        $mode = null;

        foreach ($grid as $row) {
            if (!is_array($row)) {
                continue;
            }
            $labelCell = $row[0] ?? null;
            $label = self::cellText($labelCell);
            $low = mb_strtolower($label);

            if ($label === '') {
                continue;
            }
            if (str_starts_with($low, 'cliente')) {
                $mode = 'income';
                continue;
            }
            if (
                str_contains($low, 'proveedor')
                || str_contains($low, 'costos')
                || str_contains($low, 'salarios')
                || str_contains($low, 'haberes')
            ) {
                $mode = 'expense';
                continue;
            }
            if (str_contains($low, 'ingresos totales')) {
                $mode = null;
                continue;
            }
            if (str_contains($low, 'egresos totales')) {
                $mode = null;
                continue;
            }
            if ($low === 'subtotal' || str_contains($low, 'balance') || str_contains($low, 'ganancia')) {
                $mode = null;
                continue;
            }
            if ($low === 'ahorro') {
                for ($c = 1; $c <= 12; $c++) {
                    $f = self::cellFormula($row[$c] ?? null);
                    if ($f !== null && preg_match('/\*\\s*(0\\.\\d+)/', $f, $m)) {
                        $savingsRate = (float) $m[1];
                        break;
                    }
                }
                $mode = null;
                continue;
            }
            if (
                str_contains($low, 'total anual')
                || str_contains($low, 'ahorro anual')
                || in_array($low, ['lean', 'german', 'jp'], true)
            ) {
                $mode = null;
                continue;
            }

            $months = [];
            for ($c = 1; $c <= 12; $c++) {
                $months[] = self::cellNumber($row[$c] ?? null);
            }

            if ($mode === 'income') {
                $clients[] = ['name' => $label, 'months' => $months];
            } elseif ($mode === 'expense') {
                $expenses[] = ['name' => $label, 'months' => $months];
            }
        }

        if ($clients === []) {
            $clients = self::emptyYear($name)['clients'];
        }
        if ($expenses === []) {
            $expenses = self::emptyYear($name)['expenses'];
        }

        return [
            'name' => $name,
            'savings_rate' => $savingsRate,
            'clients' => $clients,
            'expenses' => $expenses,
        ];
    }

    private static function cellText(mixed $cell): string
    {
        if ($cell === null) {
            return '';
        }
        if (is_array($cell)) {
            if (isset($cell['v'])) {
                return trim((string) $cell['v']);
            }
            return '';
        }
        return trim((string) $cell);
    }

    private static function cellFormula(mixed $cell): ?string
    {
        if (is_array($cell) && isset($cell['f']) && is_string($cell['f'])) {
            return $cell['f'];
        }
        if (is_string($cell) && str_starts_with($cell, '=')) {
            return $cell;
        }
        return null;
    }

    private static function cellNumber(mixed $cell): float
    {
        if ($cell === null) {
            return 0.0;
        }
        if (is_array($cell)) {
            if (isset($cell['f'])) {
                return 0.0;
            }
            if (isset($cell['v']) && is_numeric($cell['v'])) {
                return round((float) $cell['v'], 2);
            }
            return 0.0;
        }
        if (is_numeric($cell)) {
            return round((float) $cell, 2);
        }
        return 0.0;
    }
}
