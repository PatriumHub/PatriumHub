<?php

declare(strict_types=1);

final class ValuationController
{
    public static function store(): void
    {
        Auth::requireAdminArea();
        require_csrf();

        $companyId = request_int('company_entity_id');
        $currency = post_string('currency_code', 'ARS') ?: 'ARS';
        $method = post_string('method', 'manual') ?: 'manual';
        $valuedAt = post_string('valued_at') ?: date('Y-m-d');

        if (!$companyId) {
            flash('error', 'Empresa obligatoria.');
            redirect('/empresas');
        }

        $q = '?tab=valuacion&currency=' . urlencode($currency);

        if ($method === 'patrimonio_neto') {
            ValuationService::refreshAuto($companyId, $currency);
            flash('success', 'Valuación automática actualizada desde el patrimonio neto.');
            redirect('/empresas/' . $companyId . $q);
        }

        if ($method === 'multiplo_ventas') {
            $multiple = post_float('sales_multiple', 1.0);
            if ($multiple < 0) {
                flash('error', 'El múltiplo no puede ser negativo.');
                redirect('/empresas/' . $companyId . $q);
            }
            $s = ValuationService::salesMultipleSuggestion($companyId, $currency, $multiple);
            if (!$s['has_sales']) {
                flash('error', 'No hay ventas WC ni ingresos MP en 12 meses para esta moneda. Sincronizá integraciones primero.');
                redirect('/empresas/' . $companyId . $q);
            }
            $id = ValuationService::applySalesMultiple($companyId, $currency, $multiple);
            AuditService::log('create', 'business_valuation', $id, "Valuación múltiplo {$multiple}x");
            flash(
                'success',
                'Valuación por múltiplo aplicada: ' . number_format($s['suggested'], 2, ',', '.') . ' ' . $currency
                . ' (' . number_format($multiple, 2, ',', '.') . '× ventas 12m). Las participaciones usarán este valor.'
            );
            redirect('/empresas/' . $companyId . $q);
        }

        $value = post_float('total_value');
        if ($value < 0) {
            flash('error', 'El valor no puede ser negativo.');
            redirect('/empresas/' . $companyId . $q);
        }

        $user = Auth::user();
        Database::pdo()->prepare(
            'INSERT INTO business_valuations
             (company_entity_id, valued_at, currency_code, total_value, method, notes, created_by)
             VALUES (:c, :d, :cur, :v, :m, :n, :u)'
        )->execute([
            'c' => $companyId,
            'd' => $valuedAt,
            'cur' => $currency,
            'v' => $value,
            'm' => $method,
            'n' => post_string('notes') ?: null,
            'u' => $user['id'] ?? null,
        ]);
        $id = (int) Database::pdo()->lastInsertId();
        AuditService::log('create', 'business_valuation', $id, "Valuación {$currency} {$value}");
        flash('success', 'Valuación manual registrada. Las participaciones usarán este valor.');
        redirect('/empresas/' . $companyId . $q);
    }
}
