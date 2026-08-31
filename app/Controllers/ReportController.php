<?php

declare(strict_types=1);

final class ReportController
{
    public static function patrimonial(): void
    {
        Auth::requireAdminArea();

        $currency = strtoupper(trim((string) ($_GET['currency'] ?? 'ARS'))) ?: 'ARS';
        $period = PatrimonyReportService::normalizePeriod((string) ($_GET['period'] ?? date('Y-m')));

        $rawEntity = trim((string) ($_GET['entity_id'] ?? ''));
        $entityId = null;
        $scope = 'all';
        $entityFilter = '';
        if ($rawEntity === 'people' || $rawEntity === 'companies') {
            $scope = $rawEntity;
            $entityFilter = $rawEntity;
        } elseif ($rawEntity !== '' && ctype_digit($rawEntity)) {
            $entityId = (int) $rawEntity;
            $scope = 'entity';
            $entityFilter = (string) $entityId;
        }

        view('reports/patrimonial', [
            'title' => 'Reporte patrimonial',
            'report' => PatrimonyReportService::build($period, $scope, $entityId, $currency),
            'period' => $period,
            'currency' => $currency,
            'currencies' => Catalog::currencies(),
            'entities' => Catalog::entities(),
            'entityFilter' => $entityFilter,
            'success' => flash('success'),
            'error' => flash('error'),
        ]);
    }
}
