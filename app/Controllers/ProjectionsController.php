<?php

declare(strict_types=1);

final class ProjectionsController
{
    public static function index(): void
    {
        Auth::requireAdminArea();

        $currency = strtoupper(trim((string) ($_GET['currency'] ?? 'ARS')));
        if ($currency === '') {
            $currency = 'ARS';
        }
        $year = isset($_GET['year']) ? trim((string) $_GET['year']) : null;
        $scope = (string) ($_GET['scope'] ?? 'all');
        if (!in_array($scope, ['all', 'people', 'companies'], true)) {
            $scope = 'all';
        }

        $agg = ProjectionsAggregateService::build($year, $scope);
        $reality = PatrimonioService::summarize(null, $currency, 'consolidated');

        view('proyecciones/index', [
            'title' => 'Proyecciones',
            'currency' => $currency,
            'currencies' => Catalog::currencies(),
            'agg' => $agg,
            'reality' => $reality,
            'success' => flash('success'),
            'error' => flash('error'),
        ]);
    }
}
