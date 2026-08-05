<?php

declare(strict_types=1);

final class IntegrationController
{
    public static function index(): void
    {
        Auth::requireLogin();
        $rows = array_values(array_filter(
            IntegrationService::list(),
            static fn (array $row): bool => ($row['provider'] ?? '') === 'woocommerce'
        ));
        foreach ($rows as &$row) {
            $row['config'] = IntegrationService::config($row);
            $row['stale'] = IntegrationService::isStale($row['last_sync_at'] ?? null);
        }
        unset($row);

        view('integrations/index', [
            'title' => 'Integraciones',
            'rows' => $rows,
            'cryptoOk' => CryptoService::isConfigured(),
            'success' => flash('success'),
            'error' => flash('error'),
        ]);
    }

    public static function createWoo(): void
    {
        Auth::requireLogin();
        self::ensureCrypto();
        view('integrations/woocommerce_form', [
            'title' => 'Nueva tienda WooCommerce',
            'item' => [
                'entity_id' => request_int('entity_id'),
                'name' => '',
                'status' => 'active',
                'config' => [
                    'store_url' => '',
                    'api_version' => 'wc/v3',
                    'currency_code' => 'ARS',
                    'sync_auto' => true,
                    'sync_products' => true,
                    'sync_orders' => true,
                    'low_stock_threshold' => 5,
                ],
            ],
            'entities' => Catalog::entities('company'),
            'currencies' => Catalog::currencies(),
            'hasKey' => false,
            'hasSecret' => false,
            'error' => flash('error'),
        ]);
    }

    public static function storeWoo(): void
    {
        Auth::requireLogin();
        require_csrf();
        self::ensureCrypto();

        $entityId = request_int('entity_id');
        $name = post_string('name');
        $storeUrl = rtrim(post_string('store_url'), '/');
        $key = post_string('consumer_key');
        $secret = post_string('consumer_secret');

        if (!$entityId || $name === '' || $storeUrl === '' || $key === '' || $secret === '') {
            flash('error', 'Completá entidad, nombre, URL y credenciales.');
            redirect('/integraciones/woocommerce/nueva');
        }

        try {
            $id = IntegrationService::create('woocommerce', $entityId, $name, self::wooConfigFromPost($storeUrl));
            IntegrationService::upsertCredentials($id, [
                'consumer_key' => $key,
                'consumer_secret' => $secret,
            ]);
            AuditService::log('create', 'integration', $id, 'Alta WooCommerce: ' . $name);
            flash('success', 'Tienda guardada en el sistema. Probá la conexión o sincronizá.');
            redirect('/integraciones/' . $id);
        } catch (Throwable $e) {
            flash('error', 'No se pudo guardar: ' . $e->getMessage());
            redirect('/integraciones/woocommerce/nueva');
        }
    }

    public static function show(string $id): void
    {
        Auth::requireLogin();
        $item = IntegrationService::find((int) $id);
        if (!$item || ($item['provider'] ?? '') !== 'woocommerce') {
            flash('error', 'Integración no encontrada.');
            redirect('/integraciones');
        }

        $sales = null;
        $inventory = null;
        $salesRows = [];

        $stmt = Database::pdo()->prepare(
            'SELECT * FROM sales_metrics WHERE integration_id = :id ORDER BY period_start DESC LIMIT 12'
        );
        $stmt->execute(['id' => $item['id']]);
        $salesRows = $stmt->fetchAll();
        $sales = $salesRows[0] ?? null;

        $stmt = Database::pdo()->prepare('SELECT * FROM inventories WHERE integration_id = :id LIMIT 1');
        $stmt->execute(['id' => $item['id']]);
        $inventory = $stmt->fetch() ?: null;

        view('integrations/show', [
            'title' => $item['name'],
            'item' => $item,
            'config' => IntegrationService::config($item),
            'runs' => IntegrationService::recentRuns((int) $item['id']),
            'stale' => IntegrationService::isStale($item['last_sync_at'] ?? null),
            'sales' => $sales,
            'salesRows' => $salesRows,
            'inventory' => $inventory,
            'account' => null,
            'success' => flash('success'),
            'error' => flash('error'),
        ]);
    }

    public static function edit(string $id): void
    {
        Auth::requireLogin();
        self::ensureCrypto();
        $item = IntegrationService::find((int) $id);
        if (!$item || $item['status'] === 'revoked' || ($item['provider'] ?? '') !== 'woocommerce') {
            flash('error', 'Integración no encontrada.');
            redirect('/integraciones');
        }
        $item['config'] = IntegrationService::config($item);

        view('integrations/woocommerce_form', [
            'title' => 'Editar WooCommerce',
            'item' => $item,
            'entities' => Catalog::entities('company'),
            'currencies' => Catalog::currencies(),
            'hasKey' => IntegrationService::hasCredential((int) $item['id'], 'consumer_key'),
            'hasSecret' => IntegrationService::hasCredential((int) $item['id'], 'consumer_secret'),
            'error' => flash('error'),
        ]);
    }

    public static function update(string $id): void
    {
        Auth::requireLogin();
        require_csrf();
        self::ensureCrypto();
        $integrationId = (int) $id;
        $before = IntegrationService::find($integrationId);
        if (!$before || ($before['provider'] ?? '') !== 'woocommerce') {
            flash('error', 'Integración no encontrada.');
            redirect('/integraciones');
        }

        self::updateWoo($integrationId, $before);
    }

    public static function test(string $id): void
    {
        Auth::requireLogin();
        require_csrf();
        self::ensureCrypto();
        $integrationId = (int) $id;
        $item = IntegrationService::find($integrationId);
        if (!$item || ($item['provider'] ?? '') !== 'woocommerce') {
            flash('error', 'Integración no encontrada.');
            redirect('/integraciones');
        }

        $result = WooCommerceSyncService::test($integrationId);

        AuditService::log('test', 'integration', $integrationId, $result['message'] ?? '');
        flash($result['ok'] ? 'success' : 'error', $result['message'] ?? 'Sin respuesta');
        redirect('/integraciones/' . $integrationId);
    }

    public static function sync(string $id): void
    {
        Auth::requireLogin();
        require_csrf();
        self::ensureCrypto();
        $integrationId = (int) $id;
        $result = SyncOrchestrator::runOne($integrationId);
        AuditService::log(
            'sync',
            'integration',
            $integrationId,
            ($result['ok'] ? 'OK: ' : 'FAIL: ') . $result['message']
        );
        flash($result['ok'] ? 'success' : 'error', $result['message']);
        redirect('/integraciones/' . $integrationId);
    }

    public static function revoke(string $id): void
    {
        Auth::requireLogin();
        require_csrf();
        $integrationId = (int) $id;
        $item = IntegrationService::find($integrationId);
        if (!$item) {
            flash('error', 'Integración no encontrada.');
            redirect('/integraciones');
        }
        IntegrationService::revoke($integrationId);
        AuditService::log('revoke', 'integration', $integrationId, 'Revocada: ' . $item['name']);
        flash('success', 'Integración eliminada. Ya no aparece en ningún listado del sistema.');
        redirect('/integraciones');
    }

    private static function updateWoo(int $integrationId, array $before): void
    {
        $entityId = request_int('entity_id');
        $name = post_string('name');
        $storeUrl = rtrim(post_string('store_url'), '/');
        if (!$entityId || $name === '' || $storeUrl === '') {
            flash('error', 'Completá entidad, nombre y URL.');
            redirect('/integraciones/' . $integrationId . '/editar');
        }
        $status = post_string('status', 'active');
        if (!in_array($status, ['active', 'disabled', 'error'], true)) {
            $status = 'active';
        }
        try {
            IntegrationService::update($integrationId, $entityId, $name, self::wooConfigFromPost($storeUrl), $status);
            IntegrationService::upsertCredentials($integrationId, [
                'consumer_key' => post_string('consumer_key') ?: null,
                'consumer_secret' => post_string('consumer_secret') ?: null,
            ]);
            AuditService::log('update', 'integration', $integrationId, 'Edición WooCommerce: ' . $name, $before, IntegrationService::find($integrationId));
            flash('success', 'Integración actualizada.');
            redirect('/integraciones/' . $integrationId);
        } catch (Throwable $e) {
            flash('error', 'No se pudo actualizar: ' . $e->getMessage());
            redirect('/integraciones/' . $integrationId . '/editar');
        }
    }

    private static function wooConfigFromPost(string $storeUrl): array
    {
        return [
            'store_url' => $storeUrl,
            'api_version' => post_string('api_version', 'wc/v3') ?: 'wc/v3',
            'currency_code' => post_string('currency_code', 'ARS') ?: 'ARS',
            'sync_auto' => isset($_POST['sync_auto']),
            'sync_products' => isset($_POST['sync_products']),
            'sync_orders' => isset($_POST['sync_orders']),
            'low_stock_threshold' => (int) post_float('low_stock_threshold', 5),
        ];
    }

    private static function ensureCrypto(): void
    {
        if (!CryptoService::isConfigured()) {
            flash('error', 'Primero generá la clave de cifrado en Configuración. Los tokens de WooCommerce se cargan solo desde Integraciones.');
            redirect('/configuracion');
        }
    }
}
