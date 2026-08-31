<?php

declare(strict_types=1);

$appConfig = require dirname(__DIR__) . '/config/app.php';
$dbConfig = require dirname(__DIR__) . '/config/database.php';

date_default_timezone_set($appConfig['timezone']);

if ($appConfig['debug']) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
    ini_set('display_errors', '0');
}

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/Router.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/Support/Catalog.php';
require_once __DIR__ . '/Support/Pagination.php';
require_once __DIR__ . '/Services/AuditService.php';
require_once __DIR__ . '/Services/PatrimonioService.php';
require_once __DIR__ . '/Services/CryptoService.php';
require_once __DIR__ . '/Services/IntegrationService.php';
require_once __DIR__ . '/Services/Integrations/WooCommerceClient.php';
require_once __DIR__ . '/Services/Integrations/WooCommerceSyncService.php';
require_once __DIR__ . '/Services/SyncOrchestrator.php';
require_once __DIR__ . '/Services/MercadoPagoDashboardService.php';
require_once __DIR__ . '/Services/SnapshotService.php';
require_once __DIR__ . '/Services/SavedViewService.php';
require_once __DIR__ . '/Services/SettingsService.php';
require_once __DIR__ . '/Services/ExportService.php';
require_once __DIR__ . '/Services/DashboardAnalyticsService.php';
require_once __DIR__ . '/Services/ValuationService.php';
require_once __DIR__ . '/Services/BudgetService.php';
require_once __DIR__ . '/Services/FinancialPlanService.php';
require_once __DIR__ . '/Services/ReceivableService.php';
require_once __DIR__ . '/Services/CompanySchema.php';
require_once __DIR__ . '/Services/UserService.php';
require_once __DIR__ . '/Services/EntityService.php';
require_once __DIR__ . '/Services/CompanyClientService.php';
require_once __DIR__ . '/Services/ClientDocumentService.php';
require_once __DIR__ . '/Services/AssetOwnerService.php';
require_once __DIR__ . '/Services/AccountOwnerService.php';
require_once __DIR__ . '/Services/PersonFinancialPlanService.php';
require_once __DIR__ . '/Services/ProjectionsAggregateService.php';
require_once __DIR__ . '/Services/FinancialGoalsService.php';
require_once __DIR__ . '/Services/ExpenseAnalysisService.php';
require_once __DIR__ . '/Services/PatrimonyReportService.php';

foreach ([
    'AuthController',
    'HomeController',
    'DashboardController',
    'ProjectionsController',
    'FinancialGoalsController',
    'ExpensesController',
    'PeopleController',
    'CompanyController',
    'CompanyClientController',
    'AccountController',
    'AssetController',
    'PropertyController',
    'ReceivableController',
    'LiabilityController',
    'InventoryController',
    'TransactionController',
    'IntegrationController',
    'SettingsController',
    'OwnershipController',
    'ValuationController',
    'SavedViewController',
    'BudgetController',
    'ReportController',
] as $controller) {
    require_once __DIR__ . '/Controllers/' . $controller . '.php';
}

if (PHP_SAPI !== 'cli') {
    Auth::startSession($appConfig['session_name']);
    // Hardening headers (HTTPS redirect is deployer's responsibility; see guía).
    if (!headers_sent()) {
        header('X-Frame-Options: SAMEORIGIN');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        $env = (string) ($appConfig['env'] ?? 'local');
        if (in_array($env, ['production', 'prod'], true)) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }
}

try {
    Database::connect($dbConfig);
} catch (Throwable $e) {
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, 'DB error: ' . $e->getMessage() . PHP_EOL);
        exit(1);
    }
    http_response_code(503);
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="utf-8"><title>PatriumHub</title></head><body>';
    echo '<h1>PatriumHub</h1><p>No se pudo conectar a la base de datos.</p>';
    if ($appConfig['debug']) {
        echo '<pre>' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</pre>';
        echo '<p>Importá <code>databases/patriumhub.sql</code> y configurá el archivo <code>.env</code>.</p>';
    }
    echo '</body></html>';
    exit;
}

return [
    'app' => $appConfig,
    'db' => $dbConfig,
];
