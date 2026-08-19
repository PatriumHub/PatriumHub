<?php

declare(strict_types=1);

/**
 * Objetivos financieros: reglas Dave Ramsey (auto) + metas personalizadas (CRUD).
 */
final class FinancialGoalsService
{
    private static bool $ready = false;

    public static function ensureSchema(): void
    {
        if (self::$ready) {
            return;
        }
        $pdo = Database::pdo();
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS financial_goals (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                name VARCHAR(180) NOT NULL,
                target_amount DECIMAL(18,2) NOT NULL DEFAULT 0.00,
                current_amount DECIMAL(18,2) NOT NULL DEFAULT 0.00,
                currency_code CHAR(3) NOT NULL DEFAULT \'ARS\',
                starts_on DATE NOT NULL,
                ends_on DATE NOT NULL,
                notes TEXT NULL,
                status ENUM(\'active\',\'completed\',\'archived\') NOT NULL DEFAULT \'active\',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_fg_status (status),
                KEY idx_fg_currency (currency_code),
                KEY idx_fg_dates (starts_on, ends_on),
                CONSTRAINT fk_fg_currency FOREIGN KEY (currency_code) REFERENCES currencies(code)
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $ver = SettingsService::get('schema.version', '0');
        if (version_compare((string) $ver, '0.8.7', '<')) {
            SettingsService::set('schema.version', '0.8.7');
        }

        self::$ready = true;
    }

    /**
     * @return array{ramsey:list<array<string,mixed>>,custom:list<array<string,mixed>>,context:array<string,mixed>}
     */
    public static function dashboard(string $currency): array
    {
        self::ensureSchema();
        $currency = strtoupper(trim($currency));
        if ($currency === '') {
            $currency = 'ARS';
        }

        $reality = PatrimonioService::summarize(null, $currency, 'consolidated');
        $aggAll = ProjectionsAggregateService::build(null, 'all');
        $aggPeople = ProjectionsAggregateService::build(null, 'people');
        $totalsAll = $aggAll['totals'] ?? [];
        $totalsPeople = $aggPeople['totals'] ?? [];

        $incomeAnnual = max(0.0, (float) ($totalsAll['income_annual'] ?? 0));
        $expenseAnnual = max(0.0, (float) ($totalsAll['expense_annual'] ?? 0));
        $incomeM = $incomeAnnual / 12.0;
        $expenseM = $expenseAnnual / 12.0;

        if ($expenseM < 0.01) {
            $budget = BudgetService::periodTotals(date('Y-m'), $currency, null);
            $expenseM = max(0.0, (float) ($budget['pending'] ?? 0) + (float) ($budget['paid'] ?? 0));
        }

        $peopleIncome = max(0.0, (float) ($totalsPeople['income_annual'] ?? 0));
        $peopleExpense = max(0.0, (float) ($totalsPeople['expense_annual'] ?? 0));
        $peopleBalance = (float) ($totalsPeople['balance_annual'] ?? ($peopleIncome - $peopleExpense));
        $planYear = (string) ($aggPeople['year'] ?? date('Y'));

        $liquidity = max(0.0, (float) ($reality['liquidity'] ?? 0));
        $debt = self::debtTotals($currency);
        $ef = self::emergencyFundState();
        $ms03Saved = self::ms03SavedAmount($planYear, $currency);
        $ms03Target = $peopleBalance > 0.009 ? ($peopleBalance * 0.15) : 0.0;

        $context = [
            'currency' => $currency,
            'liquidity' => $liquidity,
            'net_worth' => (float) ($reality['net_worth'] ?? 0),
            'income_monthly' => $incomeM,
            'expense_monthly' => $expenseM,
            'people_balance_annual' => $peopleBalance,
            'people_income_annual' => $peopleIncome,
            'plan_year' => $planYear,
            'ms03_target' => $ms03Target,
            'ms03_saved' => $ms03Saved,
            'debt_non_mortgage' => $debt['non_mortgage_outstanding'],
            'debt_mortgage' => $debt['mortgage_outstanding'],
            'emergency_target' => $ef['target'],
            'emergency_current' => $ef['current'],
        ];

        $ramsey = self::ramseyRules($context, $debt);
        $custom = self::listCustom($currency);

        return [
            'ramsey' => $ramsey,
            'custom' => $custom,
            'context' => $context,
            'milestones_done' => self::countDone($ramsey),
            'milestones_total' => count($ramsey),
            'custom_done' => self::countCustomDone($custom),
            'custom_total' => count($custom),
        ];
    }

    /** @param list<array<string,mixed>> $rules */
    private static function countDone(array $rules): int
    {
        $n = 0;
        foreach ($rules as $r) {
            if (!empty($r['done'])) {
                $n++;
            }
        }
        return $n;
    }

    /** @param list<array<string,mixed>> $goals */
    private static function countCustomDone(array $goals): int
    {
        $n = 0;
        foreach ($goals as $g) {
            if (($g['status'] ?? '') === 'completed' || (float) ($g['pct'] ?? 0) >= 99.95) {
                $n++;
            }
        }
        return $n;
    }

    public static function ms03SavedAmount(string $year, string $currency): float
    {
        $key = 'goals.ramsey.ms03_saved_' . preg_replace('/\W+/', '', $year) . '_' . strtoupper($currency);
        $stored = SettingsService::get($key);
        if ($stored !== null && is_numeric($stored)) {
            return max(0.0, (float) $stored);
        }
        return 0.0;
    }

    /**
     * @return array{ok:bool,error?:string}
     */
    public static function updateMs03Saved(float $currentAmount, string $year, string $currency): array
    {
        self::ensureSchema();
        $year = trim($year) !== '' ? trim($year) : (string) date('Y');
        $currency = strtoupper(trim($currency));
        if ($currency === '') {
            $currency = 'ARS';
        }
        $key = 'goals.ramsey.ms03_saved_' . preg_replace('/\W+/', '', $year) . '_' . $currency;
        SettingsService::set($key, (string) max(0.0, $currentAmount));
        AuditService::log('update', 'ramsey_ms03', null, 'Ahorro MS03 ' . $year . ' ' . $currency . ': ' . $currentAmount);
        return ['ok' => true];
    }

    /**
     * @return array{target:float,current:float}
     */
    public static function emergencyFundState(): array
    {
        $targetStored = SettingsService::get('goals.ramsey.emergency_target');
        $currentStored = SettingsService::get('goals.ramsey.emergency_current');
        $target = ($targetStored !== null && is_numeric($targetStored) && (float) $targetStored > 0)
            ? (float) $targetStored
            : 1_200_000.0;
        $current = ($currentStored !== null && is_numeric($currentStored))
            ? max(0.0, (float) $currentStored)
            : 0.0;
        return ['target' => $target, 'current' => $current];
    }

    /**
     * @return array{ok:bool,error?:string}
     */
    public static function updateEmergencyFund(float $currentAmount): array
    {
        self::ensureSchema();
        $current = max(0.0, $currentAmount);
        SettingsService::set('goals.ramsey.emergency_current', (string) $current);
        if (SettingsService::get('goals.ramsey.emergency_target') === null) {
            SettingsService::set('goals.ramsey.emergency_target', '1200000');
        }
        AuditService::log('update', 'ramsey_emergency', null, 'Fondo de emergencia: ' . $current);
        return ['ok' => true];
    }

    /**
     * @param array<string,mixed> $ctx
     * @param array<string,float> $debt
     * @return list<array<string,mixed>>
     */
    private static function ramseyRules(array $ctx, array $debt): array
    {
        $currency = (string) $ctx['currency'];
        $peopleBalance = (float) $ctx['people_balance_annual'];
        $planYear = (string) ($ctx['plan_year'] ?? date('Y'));

        // Milestone 01: fondo de emergencia ARS 1.200.000 (manual).
        $efTarget = (float) $ctx['emergency_target'];
        $efCurrent = (float) $ctx['emergency_current'];
        $efPct = self::pct($efCurrent, $efTarget);
        $efRule = self::rule(
            'ms01',
            'Milestone 01 · Fondo de emergencia',
            'Meta fija ARS 1.200.000 — cargá a mano cuánto tenés aparte para emergencias',
            $efPct,
            $efCurrent,
            $efTarget,
            'ARS',
            'Actualizá el monto juntado en esta card'
        );
        $efRule['editable'] = true;
        $efRule['edit_url'] = '/objetivos/fondo-emergencia';
        $efRule['edit_label'] = 'Juntado (ARS)';

        // Milestone 02: deudas de todas las personas.
        $debtOut = (float) $debt['total_outstanding'];
        $debtOrig = (float) $debt['total_original'];
        if ($debtOut <= 0.009) {
            $debtPct = 100.0;
        } elseif ($debtOrig > 0.009) {
            $debtPct = self::clamp(100.0 * (($debtOrig - $debtOut) / $debtOrig));
        } else {
            $debtPct = 0.0;
        }

        // Milestone 03: meta auto = 15% ingreso neto personas; juntado a mano.
        $investTargetAmt = (float) ($ctx['ms03_target'] ?? 0);
        $ms03Saved = (float) ($ctx['ms03_saved'] ?? 0);
        $investPct = $investTargetAmt > 0.009 ? self::pct($ms03Saved, $investTargetAmt) : 0.0;
        $investRateOfNet = $peopleBalance > 0.009 ? ($ms03Saved / $peopleBalance) : 0.0;
        $ms03Rule = self::rule(
            'ms03',
            'Milestone 03 · Ahorrar 15% del ingreso neto',
            'Meta automática del año ' . $planYear . ': 15% del ingreso neto personal — cargá a mano lo ahorrado',
            $investPct,
            $ms03Saved,
            $investTargetAmt,
            $currency,
            $peopleBalance > 0.009
                ? ('Meta auto · llevás ' . number_format($investRateOfNet * 100, 1, ',', '.') . '% del ingreso neto')
                : 'Sin ingreso neto en proyecciones de personas'
        );
        $ms03Rule['editable'] = true;
        $ms03Rule['edit_url'] = '/objetivos/ahorro-15';
        $ms03Rule['edit_label'] = 'Ahorrado (' . $currency . ')';
        $ms03Rule['plan_year'] = $planYear;

        return [
            $efRule,
            self::rule(
                'ms02',
                'Milestone 02 · Deudas',
                'Llegar a saldo 0 en pasivos de todas las personas',
                $debtPct,
                max(0.0, $debtOrig - $debtOut),
                max($debtOrig, $debtOut),
                $currency,
                $debtOut <= 0.009
                    ? 'Sin deudas personales abiertas'
                    : ('Pendiente personas: ' . money($debtOut, $currency))
            ),
            $ms03Rule,
        ];
    }

    /**
     * @return array{
     *   total_outstanding:float,
     *   total_original:float,
     *   non_mortgage_outstanding:float,
     *   non_mortgage_original:float,
     *   mortgage_outstanding:float,
     *   mortgage_original:float
     * }
     */
    private static function debtTotals(string $currency): array
    {
        // Solo pasivos de personas (todas juntas), no empresas.
        $stmt = Database::pdo()->prepare(
            "SELECT l.liability_type,
                    COALESCE(SUM(l.outstanding_amount),0) AS outstanding,
                    COALESCE(SUM(GREATEST(l.original_amount, l.outstanding_amount)),0) AS original
             FROM liabilities l
             INNER JOIN entities e ON e.id = l.entity_id AND e.type = 'person'
             WHERE l.status = 'open' AND l.include_in_net_worth = 1 AND l.currency_code = :c
             GROUP BY l.liability_type"
        );
        $stmt->execute(['c' => $currency]);
        $nonOut = 0.0;
        $nonOrig = 0.0;
        $mortOut = 0.0;
        $mortOrig = 0.0;
        foreach ($stmt->fetchAll() as $row) {
            $type = (string) ($row['liability_type'] ?? 'other');
            $out = (float) ($row['outstanding'] ?? 0);
            $orig = (float) ($row['original'] ?? 0);
            if ($type === 'mortgage') {
                $mortOut += $out;
                $mortOrig += $orig;
            } else {
                $nonOut += $out;
                $nonOrig += $orig;
            }
        }
        return [
            'total_outstanding' => $nonOut + $mortOut,
            'total_original' => $nonOrig + $mortOrig,
            'non_mortgage_outstanding' => $nonOut,
            'non_mortgage_original' => $nonOrig,
            'mortgage_outstanding' => $mortOut,
            'mortgage_original' => $mortOrig,
        ];
    }

    /**
     * @return list<array<string,mixed>>
     */
    public static function listCustom(string $currency): array
    {
        self::ensureSchema();
        $stmt = Database::pdo()->prepare(
            "SELECT * FROM financial_goals
             WHERE status IN ('active','completed') AND currency_code = :c
             ORDER BY
               CASE status WHEN 'active' THEN 0 ELSE 1 END,
               ends_on ASC, name ASC"
        );
        $stmt->execute(['c' => strtoupper($currency)]);
        $rows = [];
        foreach ($stmt->fetchAll() as $r) {
            $rows[] = self::hydrateCustom($r);
        }
        return $rows;
    }

    /** @param array<string,mixed> $row */
    private static function hydrateCustom(array $row): array
    {
        $target = max(0.0, (float) ($row['target_amount'] ?? 0));
        $current = max(0.0, (float) ($row['current_amount'] ?? 0));
        $pct = self::pct($current, $target);
        $starts = (string) ($row['starts_on'] ?? '');
        $ends = (string) ($row['ends_on'] ?? '');
        $today = date('Y-m-d');
        $timePct = null;
        if ($starts !== '' && $ends !== '' && $ends >= $starts) {
            $startTs = strtotime($starts);
            $endTs = strtotime($ends);
            $nowTs = strtotime($today);
            if ($startTs !== false && $endTs !== false && $nowTs !== false && $endTs > $startTs) {
                $timePct = self::clamp(100.0 * (($nowTs - $startTs) / ($endTs - $startTs)));
            }
        }
        return [
            'id' => (int) ($row['id'] ?? 0),
            'name' => (string) ($row['name'] ?? ''),
            'target_amount' => $target,
            'current_amount' => $current,
            'currency_code' => (string) ($row['currency_code'] ?? 'ARS'),
            'starts_on' => $starts,
            'ends_on' => $ends,
            'notes' => (string) ($row['notes'] ?? ''),
            'status' => (string) ($row['status'] ?? 'active'),
            'pct' => $pct,
            'tone' => self::tone($pct),
            'time_pct' => $timePct,
            'remaining' => max(0.0, $target - $current),
            'updated_at' => $row['updated_at'] ?? null,
        ];
    }

    /**
     * @return array{ok:bool,error?:string,id?:int}
     */
    public static function create(array $data): array
    {
        self::ensureSchema();
        $name = trim((string) ($data['name'] ?? ''));
        $target = max(0.0, (float) ($data['target_amount'] ?? 0));
        $current = max(0.0, (float) ($data['current_amount'] ?? 0));
        $currency = strtoupper(trim((string) ($data['currency_code'] ?? 'ARS')));
        $starts = (string) ($data['starts_on'] ?? '');
        $ends = (string) ($data['ends_on'] ?? '');
        $notes = trim((string) ($data['notes'] ?? ''));

        if ($name === '') {
            return ['ok' => false, 'error' => 'El nombre es obligatorio.'];
        }
        if ($target <= 0.009) {
            return ['ok' => false, 'error' => 'El valor total debe ser mayor a 0.'];
        }
        if ($starts === '' || $ends === '') {
            return ['ok' => false, 'error' => 'Indicá fecha de inicio y fin.'];
        }
        if ($ends < $starts) {
            return ['ok' => false, 'error' => 'La fecha fin no puede ser anterior al inicio.'];
        }
        if ($currency === '') {
            $currency = 'ARS';
        }

        $status = $current >= $target - 0.009 ? 'completed' : 'active';
        Database::pdo()->prepare(
            'INSERT INTO financial_goals
             (name, target_amount, current_amount, currency_code, starts_on, ends_on, notes, status)
             VALUES
             (:name, :target, :current, :currency, :starts, :ends, :notes, :status)'
        )->execute([
            'name' => $name,
            'target' => $target,
            'current' => $current,
            'currency' => $currency,
            'starts' => $starts,
            'ends' => $ends,
            'notes' => $notes !== '' ? $notes : null,
            'status' => $status,
        ]);
        $id = (int) Database::pdo()->lastInsertId();
        AuditService::log('create', 'financial_goal', $id, 'Alta objetivo: ' . $name);
        return ['ok' => true, 'id' => $id];
    }

    /**
     * @return array{ok:bool,error?:string}
     */
    public static function updateProgress(int $id, float $currentAmount): array
    {
        self::ensureSchema();
        $row = self::find($id);
        if ($row === null) {
            return ['ok' => false, 'error' => 'Objetivo no encontrado.'];
        }
        $current = max(0.0, $currentAmount);
        $target = (float) $row['target_amount'];
        $status = $current >= $target - 0.009 ? 'completed' : 'active';
        Database::pdo()->prepare(
            'UPDATE financial_goals
             SET current_amount = :c, status = :s, updated_at = CURRENT_TIMESTAMP
             WHERE id = :id'
        )->execute(['c' => $current, 's' => $status, 'id' => $id]);
        AuditService::log('update', 'financial_goal', $id, 'Progreso objetivo: ' . $row['name']);
        return ['ok' => true];
    }

    /**
     * @return array{ok:bool,error?:string}
     */
    public static function destroy(int $id): array
    {
        self::ensureSchema();
        $row = self::find($id);
        if ($row === null) {
            return ['ok' => false, 'error' => 'Objetivo no encontrado.'];
        }
        Database::pdo()->prepare('DELETE FROM financial_goals WHERE id = :id')->execute(['id' => $id]);
        AuditService::log('delete', 'financial_goal', $id, 'Baja objetivo: ' . $row['name']);
        return ['ok' => true];
    }

    /** @return array<string,mixed>|null */
    public static function find(int $id): ?array
    {
        self::ensureSchema();
        $stmt = Database::pdo()->prepare('SELECT * FROM financial_goals WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function tone(float $pct): string
    {
        if ($pct >= 99.95) {
            return 'ok';
        }
        if ($pct >= 67.0) {
            return 'good';
        }
        if ($pct >= 34.0) {
            return 'mid';
        }
        return 'low';
    }

    private static function pct(float $current, float $target): float
    {
        if ($target <= 0.009) {
            return $current > 0.009 ? 100.0 : 0.0;
        }
        return self::clamp(100.0 * ($current / $target));
    }

    private static function clamp(float $pct): float
    {
        if ($pct < 0.0) {
            return 0.0;
        }
        if ($pct > 100.0) {
            return 100.0;
        }
        return round($pct, 1);
    }

    /**
     * @return array<string,mixed>
     */
    private static function rule(
        string $key,
        string $title,
        string $desc,
        float $pct,
        float $current,
        float $target,
        string $currency,
        string $hint
    ): array {
        $pct = self::clamp($pct);
        return [
            'key' => $key,
            'title' => $title,
            'description' => $desc,
            'pct' => $pct,
            'tone' => self::tone($pct),
            'current' => $current,
            'target' => $target,
            'currency' => $currency,
            'hint' => $hint,
            'done' => $pct >= 99.95,
        ];
    }

    private static function formatTarget(float $amount, string $currency): string
    {
        return money($amount, $currency);
    }
}
