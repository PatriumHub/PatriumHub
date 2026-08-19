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
$router->get('/proyecciones', [ProjectionsController::class, 'index']);
$router->get('/objetivos', [FinancialGoalsController::class, 'index']);
$router->get('/objetivos/nuevo', [FinancialGoalsController::class, 'create']);
$router->post('/objetivos', [FinancialGoalsController::class, 'store']);
$router->post('/objetivos/fondo-emergencia', [FinancialGoalsController::class, 'updateEmergency']);
$router->post('/objetivos/ahorro-15', [FinancialGoalsController::class, 'updateMs03']);
$router->post('/objetivos/{id}/progreso', [FinancialGoalsController::class, 'updateProgress']);
$router->post('/objetivos/{id}/eliminar', [FinancialGoalsController::class, 'destroy']);

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
$router->post('/personas/{id}/eliminar', [PeopleController::class, 'destroy']);
$router->post('/personas/{id}/proyecciones', [PeopleController::class, 'saveFinancialPlan']);
$router->post('/personas/{id}/proyecciones/reset', [PeopleController::class, 'resetFinancialPlan']);

$router->get('/empresas', [CompanyController::class, 'index']);
$router->get('/empresas/nueva', [CompanyController::class, 'create']);
$router->post('/empresas', [CompanyController::class, 'store']);
$router->get('/empresas/{id}', [CompanyController::class, 'show']);
$router->get('/empresas/{id}/editar', [CompanyController::class, 'edit']);
$router->post('/empresas/{id}', [CompanyController::class, 'update']);
$router->post('/empresas/{id}/eliminar', [CompanyController::class, 'destroy']);
$router->post('/empresas/{id}/caja', [CompanyController::class, 'storeCashMovement']);
$router->post('/empresas/{id}/venta-caja', [CompanyController::class, 'storeCashMovement']); // alias legacy
$router->post('/empresas/{id}/estados-proyeccion', [CompanyController::class, 'saveFinancialPlan']);
$router->post('/empresas/{id}/estados-proyeccion/reset', [CompanyController::class, 'resetFinancialPlan']);
$router->get('/empresas/{id}/clientes/nuevo', [CompanyClientController::class, 'create']);
$router->post('/empresas/{id}/clientes', [CompanyClientController::class, 'store']);
$router->get('/empresas/{id}/clientes/{clientId}', [CompanyClientController::class, 'show']);
$router->get('/empresas/{id}/clientes/{clientId}/editar', [CompanyClientController::class, 'edit']);
$router->post('/empresas/{id}/clientes/{clientId}', [CompanyClientController::class, 'update']);
$router->post('/empresas/{id}/clientes/{clientId}/eliminar', [CompanyClientController::class, 'destroy']);
$router->post('/empresas/{id}/clientes/{clientId}/contrato', [CompanyClientController::class, 'uploadContract']);
$router->post('/empresas/{id}/clientes/{clientId}/documentos', [CompanyClientController::class, 'uploadDoc']);
$router->post('/empresas/{id}/clientes/{clientId}/documentos/{docId}/eliminar', [CompanyClientController::class, 'deleteDoc']);
$router->get('/empresas/{id}/clientes/{clientId}/archivos/{docId}', [CompanyClientController::class, 'download']);

$router->get('/cuentas', [AccountController::class, 'index']);
$router->get('/cuentas/export', [AccountController::class, 'export']);
$router->get('/cuentas/nueva', [AccountController::class, 'create']);
$router->post('/cuentas', [AccountController::class, 'store']);
$router->get('/cuentas/{id}/editar', [AccountController::class, 'edit']);
$router->post('/cuentas/{id}', [AccountController::class, 'update']);
$router->post('/cuentas/{id}/eliminar', [AccountController::class, 'destroy']);

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
$router->post('/propiedades/{id}/eliminar', [PropertyController::class, 'destroy']);

$router->get('/cobrables', [ReceivableController::class, 'index']);
$router->get('/cobrables/nueva', [ReceivableController::class, 'create']);
$router->post('/cobrables', [ReceivableController::class, 'store']);
$router->get('/cobrables/{id}/editar', [ReceivableController::class, 'edit']);
$router->post('/cobrables/{id}', [ReceivableController::class, 'update']);
$router->get('/cobrables/{id}/pagar', [ReceivableController::class, 'payForm']);
$router->post('/cobrables/{id}/pagar', [ReceivableController::class, 'pay']);

$router->get('/pasivos', [LiabilityController::class, 'index']);
$router->get('/pasivos/nuevo', [LiabilityController::class, 'create']);
$router->post('/pasivos', [LiabilityController::class, 'store']);
$router->get('/pasivos/{id}/editar', [LiabilityController::class, 'edit']);
$router->post('/pasivos/{id}', [LiabilityController::class, 'update']);

$router->get('/presupuestos', [BudgetController::class, 'index']);
$router->get('/presupuestos/plantillas', [BudgetController::class, 'templates']);
$router->get('/presupuestos/plantillas/nueva', [BudgetController::class, 'createTemplate']);
$router->post('/presupuestos/plantillas', [BudgetController::class, 'storeTemplate']);
$router->get('/presupuestos/plantillas/{id}/editar', [BudgetController::class, 'editTemplate']);
$router->post('/presupuestos/plantillas/{id}', [BudgetController::class, 'updateTemplate']);
$router->post('/presupuestos/plantillas/grupo/{entityId}/eliminar', [BudgetController::class, 'destroyTemplateGroup']);
$router->post('/presupuestos/plantillas/{id}/eliminar', [BudgetController::class, 'destroyTemplate']);
$router->post('/presupuestos/items/{id}/pagar', [BudgetController::class, 'payItem']);
$router->post('/presupuestos/items/{id}/omitir', [BudgetController::class, 'skipItem']);
$router->post('/presupuestos/items/{id}/revertir', [BudgetController::class, 'revertItem']);
$router->post('/presupuestos/items/{id}/eliminar', [BudgetController::class, 'deleteItem']);

$router->get('/inventario', [InventoryController::class, 'index']);
$router->get('/inventario/nuevo', [InventoryController::class, 'create']);
$router->post('/inventario', [InventoryController::class, 'store']);
$router->get('/inventario/{id}/editar', [InventoryController::class, 'edit']);
$router->post('/inventario/{id}', [InventoryController::class, 'update']);

$router->get('/movimientos', [TransactionController::class, 'index']);
$router->get('/movimientos/export', [TransactionController::class, 'export']);
$router->get('/movimientos/nuevo', [TransactionController::class, 'create']);
$router->post('/movimientos', [TransactionController::class, 'store']);
$router->get('/movimientos/{id}/editar', [TransactionController::class, 'edit']);
$router->post('/movimientos/{id}', [TransactionController::class, 'update']);

$router->get('/configuracion', [SettingsController::class, 'index']);
$router->post('/configuracion/perfil', [SettingsController::class, 'updateProfile']);
$router->post('/configuracion/password', [SettingsController::class, 'changePassword']);
$router->post('/configuracion/usuarios', [SettingsController::class, 'storeUser']);
$router->post('/configuracion/usuarios/{id}', [SettingsController::class, 'updateUser']);
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
