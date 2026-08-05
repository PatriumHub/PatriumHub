<?php

declare(strict_types=1);

/**
 * Valuación de empresa: automática desde patrimonio neto,
 * con override manual opcional (última manual gana sobre auto).
 */
final class ValuationService
{
    /** @return array{value:float,source:string,method:?string,valued_at:?string,is_manual:bool} */
    public static function current(int $companyEntityId, string $currency): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT total_value, method, valued_at, currency_code
             FROM business_valuations
             WHERE company_entity_id = :id AND currency_code = :c
             ORDER BY valued_at DESC, id DESC
             LIMIT 1'
        );
        $stmt->execute(['id' => $companyEntityId, 'c' => $currency]);
        $row = $stmt->fetch();

        $auto = self::netWorthValue($companyEntityId, $currency);

        $method = (string) ($row['method'] ?? '');
        if ($row && $method !== '' && $method !== 'patrimonio_neto') {
            $label = match ($method) {
                'multiplo_ventas' => 'Múltiplo de ventas',
                'manual' => 'Manual',
                default => 'Override (' . $method . ')',
            };
            return [
                'value' => (float) $row['total_value'],
                'source' => $label,
                'method' => $method,
                'valued_at' => (string) ($row['valued_at'] ?? ''),
                'is_manual' => true,
                'auto_value' => $auto,
            ];
        }

        return [
            'value' => $auto,
            'source' => 'Automática (patrimonio neto)',
            'method' => 'patrimonio_neto',
            'valued_at' => date('Y-m-d'),
            'is_manual' => false,
            'auto_value' => $auto,
        ];
    }

    /** Persiste valuación automática si cambió o no hay ninguna. */
    public static function refreshAuto(int $companyEntityId, string $currency): float
    {
        $value = self::netWorthValue($companyEntityId, $currency);
        $pdo = Database::pdo();

        $stmt = $pdo->prepare(
            "SELECT id, total_value FROM business_valuations
             WHERE company_entity_id = :id AND currency_code = :c
               AND method = 'patrimonio_neto' AND valued_at = CURDATE()
             ORDER BY id DESC LIMIT 1"
        );
        $stmt->execute(['id' => $companyEntityId, 'c' => $currency]);
        $today = $stmt->fetch();

        if ($today) {
            if (abs((float) $today['total_value'] - $value) > 0.009) {
                $pdo->prepare(
                    'UPDATE business_valuations SET total_value = :v, notes = :n WHERE id = :id'
                )->execute([
                    'v' => round($value, 2),
                    'n' => 'Actualizada automáticamente desde patrimonio neto',
                    'id' => (int) $today['id'],
                ]);
            }
            return $value;
        }

        // No pisar un override vigente (manual / múltiplo de ventas).
        $last = $pdo->prepare(
            'SELECT method, valued_at FROM business_valuations
             WHERE company_entity_id = :id AND currency_code = :c
             ORDER BY valued_at DESC, id DESC LIMIT 1'
        );
        $last->execute(['id' => $companyEntityId, 'c' => $currency]);
        $lastRow = $last->fetch();
        if ($lastRow && (string) $lastRow['method'] !== 'patrimonio_neto') {
            return $value;
        }

        $user = Auth::user();
        $pdo->prepare(
            'INSERT INTO business_valuations
             (company_entity_id, valued_at, currency_code, total_value, method, notes, created_by)
             VALUES (:c, CURDATE(), :cur, :v, \'patrimonio_neto\', :n, :u)'
        )->execute([
            'c' => $companyEntityId,
            'cur' => $currency,
            'v' => round($value, 2),
            'n' => 'Calculada automáticamente desde patrimonio neto',
            'u' => $user['id'] ?? null,
        ]);

        return $value;
    }

    /**
     * Salud financiera simple (no es un score de ERP).
     * @return array{status:string,label:string,reasons:list<string>}
     */
    public static function health(array $summary, int $entityId): array
    {
        $net = (float) ($summary['net_worth'] ?? 0);
        $assets = (float) ($summary['total_assets'] ?? 0);
        $liab = (float) ($summary['liabilities'] ?? 0);
        $liq = (float) ($summary['liquidity'] ?? 0);
        $reasons = [];

        $overdue = Database::pdo()->prepare(
            "SELECT COUNT(*) FROM receivables
             WHERE entity_id = :e AND status = 'overdue' AND include_in_net_worth = 1"
        );
        $overdue->execute(['e' => $entityId]);
        $overdueN = (int) $overdue->fetchColumn();

        if ($net < 0) {
            $reasons[] = 'Patrimonio neto negativo';
        }
        if ($assets > 0 && $liab > $assets) {
            $reasons[] = 'Pasivos mayores que activos';
        } elseif ($assets > 0 && ($liab / $assets) >= 0.8) {
            $reasons[] = 'Endeudamiento alto (≥ 80% de activos)';
        }
        if ($liab > 0 && $liq < ($liab * 0.1) && $net >= 0) {
            $reasons[] = 'Liquidez muy baja frente a deudas';
        }
        if ($overdueN > 0) {
            $reasons[] = $overdueN . ' cobrable(s) vencido(s)';
        }

        if ($reasons !== [] && ($net < 0 || ($assets > 0 && $liab > $assets))) {
            return ['status' => 'bad', 'label' => 'No sano', 'reasons' => $reasons];
        }
        if ($reasons !== []) {
            return ['status' => 'warn', 'label' => 'Atención', 'reasons' => $reasons];
        }
        if ($assets <= 0 && $liab <= 0) {
            return ['status' => 'warn', 'label' => 'Sin datos', 'reasons' => ['Todavía no hay activos ni pasivos cargados']];
        }

        return ['status' => 'ok', 'label' => 'Sano', 'reasons' => ['Patrimonio positivo y sin alertas fuertes']];
    }

    /**
     * Sugerencia de valuación por ventas (no se suma al patrimonio: es criterio de negocio).
     * Base = ventas WC (12m) + ingresos MP sync (12m) en la moneda pedida.
     *
     * @return array{
     *   wc_sales:float,mp_income:float,total_sales:float,multiple:float,
     *   suggested:float,book_value:float,months:int,has_sales:bool
     * }
     */
    public static function salesMultipleSuggestion(
        int $companyEntityId,
        string $currency,
        float $multiple = 1.0
    ): array {
        $multiple = max(0.0, $multiple);
        $from = date('Y-m-d', strtotime('-12 months'));
        $pdo = Database::pdo();

        $typeStmt = $pdo->prepare("SELECT type FROM entities WHERE id = :e LIMIT 1");
        $typeStmt->execute(['e' => $companyEntityId]);
        $isCompany = (string) $typeStmt->fetchColumn() === 'company';

        $wcSales = 0.0;
        $mpIncome = 0.0;
        if ($isCompany) {
            // Múltiplo usa últimos 12 meses; el resumen de empresa usa histórico completo.
            $wcSales = DashboardAnalyticsService::wcSalesForCompany($companyEntityId, $currency, 12);
            $mpIncome = DashboardAnalyticsService::mpIncomeForCompany($companyEntityId, $currency, 12);
        }

        $total = $wcSales + $mpIncome;
        $book = self::netWorthValue($companyEntityId, $currency);

        return [
            'wc_sales' => round($wcSales, 2),
            'mp_income' => round($mpIncome, 2),
            'cash_sales' => 0.0,
            'total_sales' => round($total, 2),
            'multiple' => $multiple,
            'suggested' => round($total * $multiple, 2),
            'book_value' => round($book, 2),
            'months' => 12,
            'has_sales' => $total > 0.009,
        ];
    }

    /** Aplica valuación por múltiplo y la deja vigente para participaciones. */
    public static function applySalesMultiple(
        int $companyEntityId,
        string $currency,
        float $multiple,
        ?string $notes = null
    ): int {
        $s = self::salesMultipleSuggestion($companyEntityId, $currency, $multiple);
        $user = Auth::user();
        $note = $notes ?: sprintf(
            'Múltiplo %.2fx sobre ventas 12m (WC %s + MP %s = %s)',
            $s['multiple'],
            number_format($s['wc_sales'], 2, ',', '.'),
            number_format($s['mp_income'], 2, ',', '.'),
            number_format($s['total_sales'], 2, ',', '.')
        );

        Database::pdo()->prepare(
            'INSERT INTO business_valuations
             (company_entity_id, valued_at, currency_code, total_value, method, notes, created_by)
             VALUES (:c, CURDATE(), :cur, :v, \'multiplo_ventas\', :n, :u)'
        )->execute([
            'c' => $companyEntityId,
            'cur' => $currency,
            'v' => $s['suggested'],
            'n' => $note,
            'u' => $user['id'] ?? null,
        ]);

        return (int) Database::pdo()->lastInsertId();
    }

    private static function netWorthValue(int $companyEntityId, string $currency): float
    {
        $row = PatrimonioService::forEntity($companyEntityId, $currency);
        return (float) ($row['net_worth'] ?? 0);
    }
}
