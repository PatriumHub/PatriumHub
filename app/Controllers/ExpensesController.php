<?php

declare(strict_types=1);

final class ExpensesController
{
    public static function index(): void
    {
        Auth::requireAdminArea();

        $currency = strtoupper(trim((string) ($_GET['currency'] ?? 'ARS'))) ?: 'ARS';
        $preset = (string) ($_GET['preset'] ?? '30d');
        if (!in_array($preset, ['30d', 'month', '90d', 'ytd', 'year', 'custom'], true)) {
            $preset = '30d';
        }

        $rawEntity = trim((string) ($_GET['entity_id'] ?? ''));
        $entityId = null;
        $scope = 'all';
        $entityFilter = '';
        if ($rawEntity === 'people' || $rawEntity === 'companies') {
            $scope = $rawEntity;
            $entityFilter = $rawEntity;
        } elseif ($rawEntity !== '' && ctype_digit($rawEntity)) {
            $entityId = (int) $rawEntity;
            $entityFilter = (string) $entityId;
        }

        [$from, $to] = ExpenseAnalysisService::rangeForPreset(
            $preset,
            isset($_GET['from']) ? (string) $_GET['from'] : null,
            isset($_GET['to']) ? (string) $_GET['to'] : null
        );

        Catalog::ensureTransactionCategories();
        $analysis = ExpenseAnalysisService::build($currency, $from, $to, $entityId, $preset, $scope);

        view('gastos/index', [
            'title' => 'Gastos',
            'currency' => $currency,
            'currencies' => Catalog::currencies(),
            'entities' => Catalog::entities(),
            'entityId' => $entityId,
            'entityFilter' => $entityFilter,
            'preset' => $preset,
            'from' => $analysis['from'],
            'to' => $analysis['to'],
            'analysis' => $analysis,
            'success' => flash('success'),
            'error' => flash('error'),
        ]);
    }
}
