<?php

declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

$router = new Router();

$router->get('/login', [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'login']);
$router->post('/logout', [AuthController::class, 'logout']);

$router->get('/', [HomeController::class, 'index']);
$router->get('/dashboard', [DashboardController::class, 'index']);
$router->get('/dashboard/export', [DashboardController::class, 'exportSeries']);
$router->post('/dashboard/snapshot', [DashboardController::class, 'captureSnapshot']);
$router->post('/dashboard/vistas', [SavedViewController::class, 'store']);
$router->post('/dashboard/vistas/{id}/eliminar', [SavedViewController::class, 'destroy']);
$router->get('/dashboard/mercadopago', [DashboardController::class, 'mercadopago']);

$router->get('/participaciones', [OwnershipController::class, 'index']);
$router->get('/participaciones/nueva', [OwnershipController::class, 'create']);
$router->post('/participaciones', [OwnershipController::class, 'store']);
$router->get('/participaciones/{id}/editar', [OwnershipController::class, 'edit']);
$router->post('/participaciones/{id}', [OwnershipController::class, 'update']);
$router->post('/participaciones/{id}/eliminar', [OwnershipController::class, 'destroy']);
$router->post('/valuaciones', [ValuationController::class, 'store']);

$router->get('/personas', [PeopleController::class, 'index']);
$router->get('/personas/nueva', [PeopleController::class, 'create']);
$router->post('/personas', [PeopleController::class, 'store']);
$router->get('/personas/{id}', [PeopleController::class, 'show']);
$router->get('/personas/{id}/editar', [PeopleController::class, 'edit']);
$router->post('/personas/{id}', [PeopleController::class, 'update']);

$router->get('/empresas', [CompanyController::class, 'index']);
$router->get('/empresas/nueva', [CompanyController::class, 'create']);
$router->post('/empresas', [CompanyController::class, 'store']);
$router->get('/empresas/{id}', [CompanyController::class, 'show']);
$router->get('/empresas/{id}/editar', [CompanyController::class, 'edit']);
$router->post('/empresas/{id}', [CompanyController::class, 'update']);
$router->post('/empresas/{id}/caja', [CompanyController::class, 'storeCashMovement']);
$router->post('/empresas/{id}/venta-caja', [CompanyController::class, 'storeCashMovement']); // alias legacy

$router->get('/cuentas', [AccountController::class, 'index']);
$router->get('/cuentas/export', [AccountController::class, 'export']);
$router->get('/cuentas/nueva', [AccountController::class, 'create']);
$router->post('/cuentas', [AccountController::class, 'store']);
$router->get('/cuentas/{id}/editar', [AccountController::class, 'edit']);
$router->post('/cuentas/{id}', [AccountController::class, 'update']);

$router->get('/activos', [AssetController::class, 'index']);
$router->get('/activos/nuevo', [AssetController::class, 'create']);
$router->post('/activos', [AssetController::class, 'store']);
$router->get('/activos/{id}/editar', [AssetController::class, 'edit']);
$router->post('/activos/{id}', [AssetController::class, 'update']);

$router->get('/propiedades', [PropertyController::class, 'index']);
$router->get('/propiedades/nueva', [PropertyController::class, 'create']);
$router->post('/propiedades', [PropertyController::class, 'store']);
$router->get('/propiedades/{id}/editar', [PropertyController::class, 'edit']);
$router->post('/propiedades/{id}', [PropertyController::class, 'update']);

$router->get('/cobrables', [ReceivableController::class, 'index']);
$router->get('/cobrables/nueva', [ReceivableController::class, 'create']);
$router->post('/cobrables', [ReceivableController::class, 'store']);
$router->get('/cobrables/{id}/editar', [ReceivableController::class, 'edit']);
$router->post('/cobrables/{id}', [ReceivableController::class, 'update']);

$router->get('/pasivos', [LiabilityController::class, 'index']);
$router->get('/pasivos/nuevo', [LiabilityController::class, 'create']);
$router->post('/pasivos', [LiabilityController::class, 'store']);
$router->get('/pasivos/{id}/editar', [LiabilityController::class, 'edit']);
$router->post('/pasivos/{id}', [LiabilityController::class, 'update']);

$router->get('/inventario', [InventoryController::class, 'index']);
$router->get('/inventario/nuevo', [InventoryController::class, 'create']);
$router->post('/inventario', [InventoryController::class, 'store']);
$router->get('/inventario/{id}/editar', [InventoryController::class, 'edit']);
$router->post('/inventario/{id}', [InventoryController::class, 'update']);

$router->get('/movimientos', [TransactionController::class, 'index']);
$router->get('/movimientos/export', [TransactionController::class, 'export']);
$router->get('/movimientos/nuevo', [TransactionController::class, 'create']);
$router->post('/movimientos', [TransactionController::class, 'store']);

$router->get('/configuracion', [SettingsController::class, 'index']);
$router->post('/configuracion/clave', [SettingsController::class, 'generateKey']);
$router->post('/configuracion/ocultar-cifras', [SettingsController::class, 'toggleHideAmounts']);

$router->get('/integraciones', [IntegrationController::class, 'index']);
$router->get('/integraciones/woocommerce/nueva', [IntegrationController::class, 'createWoo']);
$router->post('/integraciones/woocommerce', [IntegrationController::class, 'storeWoo']);
$router->get('/integraciones/{id}/editar', [IntegrationController::class, 'edit']);
$router->post('/integraciones/{id}/probar', [IntegrationController::class, 'test']);
$router->post('/integraciones/{id}/sync', [IntegrationController::class, 'sync']);
$router->post('/integraciones/{id}/revocar', [IntegrationController::class, 'revoke']);
$router->get('/integraciones/{id}', [IntegrationController::class, 'show']);
$router->post('/integraciones/{id}', [IntegrationController::class, 'update']);

$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/');
