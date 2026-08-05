<?php

declare(strict_types=1);

final class WooCommerceSyncService
{
    /** @return array{ok:bool,message:string,read:int,upserted:int} */
    public static function sync(int $integrationId): array
    {
        $integration = IntegrationService::find($integrationId);
        if (!$integration || $integration['provider'] !== 'woocommerce') {
            return ['ok' => false, 'message' => 'Integración WooCommerce no encontrada.', 'read' => 0, 'upserted' => 0];
        }
        if ($integration['status'] === 'revoked') {
            return ['ok' => false, 'message' => 'Integración revocada.', 'read' => 0, 'upserted' => 0];
        }

        $runId = IntegrationService::startRun($integrationId);
        $read = 0;
        $upserted = 0;
        $stockValue = 0.0;
        $salesMonths = 0;

        try {
            $credentials = IntegrationService::credentials($integrationId);
            $client = WooCommerceClient::fromIntegration($integration, $credentials);
            $config = IntegrationService::config($integration);
            $entityId = (int) $integration['entity_id'];
            $currency = (string) ($config['currency_code'] ?? 'ARS');
            $syncProducts = ($config['sync_products'] ?? true) !== false;
            $syncOrders = ($config['sync_orders'] ?? true) !== false;

            if ($syncProducts) {
                $products = $client->fetchAllProducts();
                $read += count($products);
                $inv = self::upsertInventory($client, $integrationId, $entityId, $currency, $products, $config);
                $upserted += $inv['upserted'];
                $stockValue = $inv['stock_value'];
                $read += $inv['variations_read'];
            }

            if ($syncOrders) {
                // Pedidos de los últimos 12 meses (ventas ya hechas, agrupadas por mes).
                $after = date('c', strtotime('-365 days'));
                $orders = $client->fetchOrdersSince($after, 30);
                $read += count($orders);
                $salesMonths = self::upsertSalesMetrics($integrationId, $entityId, $currency, $orders);
                $upserted += $salesMonths;
            }

            $msg = sprintf(
                'Sync WooCommerce OK. Stock valorado: %s %s. Meses de ventas: %d. Leídos: %d.',
                $currency,
                number_format($stockValue, 2, ',', '.'),
                $salesMonths,
                $read
            );
            IntegrationService::finishRun($runId, 'success', $read, $upserted, $msg);
            IntegrationService::markStatus($integrationId, 'active', null, true);
            return [
                'ok' => true,
                'message' => $msg,
                'read' => $read,
                'upserted' => $upserted,
            ];
        } catch (Throwable $e) {
            $msg = $e->getMessage();
            $authFail = str_contains(strtolower($msg), 'auth') || str_contains($msg, '401') || str_contains($msg, '403');
            IntegrationService::finishRun($runId, 'failed', $read, $upserted, $msg);
            IntegrationService::markStatus($integrationId, $authFail ? 'error' : 'active', $msg, false);
            return ['ok' => false, 'message' => $msg, 'read' => $read, 'upserted' => $upserted];
        }
    }

    /** @return array{ok:bool,message:string} */
    public static function test(int $integrationId): array
    {
        $integration = IntegrationService::find($integrationId);
        if (!$integration || $integration['provider'] !== 'woocommerce') {
            return ['ok' => false, 'message' => 'Integración no encontrada.'];
        }
        try {
            $client = WooCommerceClient::fromIntegration($integration, IntegrationService::credentials($integrationId));
            $result = $client->testConnection();
            if ($result['ok']) {
                IntegrationService::markStatus($integrationId, 'active', null, false);
            } else {
                IntegrationService::markStatus($integrationId, 'error', $result['message'], false);
            }
            return $result;
        } catch (Throwable $e) {
            IntegrationService::markStatus($integrationId, 'error', $e->getMessage(), false);
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * @param list<array<string,mixed>> $products
     * @return array{upserted:int,stock_value:float,variations_read:int}
     */
    private static function upsertInventory(
        WooCommerceClient $client,
        int $integrationId,
        int $entityId,
        string $currency,
        array $products,
        array $config
    ): array {
        $threshold = (int) ($config['low_stock_threshold'] ?? 5);
        $units = 0.0;
        $value = 0.0;
        $productsCount = 0;
        $variationsCount = 0;
        $variationsRead = 0;
        $outOfStock = 0;
        $lowStock = 0;

        foreach ($products as $p) {
            $productsCount++;
            $type = (string) ($p['type'] ?? 'simple');

            if ($type === 'variable') {
                $vars = $client->fetchProductVariations((int) ($p['id'] ?? 0));
                $variationsRead += count($vars);
                $variationsCount += count($vars);
                if ($vars === []) {
                    // Sin variaciones: no inventar unidades.
                    continue;
                }
                foreach ($vars as $v) {
                    $qty = self::lineQty($v);
                    $price = self::linePrice($v);
                    $units += $qty;
                    $value += $qty * $price;
                    if ($qty <= 0) {
                        $outOfStock++;
                    } elseif ($qty <= $threshold) {
                        $lowStock++;
                    }
                }
                continue;
            }

            $qty = self::lineQty($p);
            $price = self::linePrice($p);
            $units += $qty;
            $value += $qty * $price;
            if ($qty <= 0) {
                $outOfStock++;
            } elseif ($qty <= $threshold) {
                $lowStock++;
            }
        }

        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'SELECT id FROM inventories WHERE entity_id = :e AND integration_id = :i AND source = \'woocommerce\' LIMIT 1'
        );
        $stmt->execute(['e' => $entityId, 'i' => $integrationId]);
        $existingId = $stmt->fetchColumn();

        $name = 'Inventario WooCommerce';
        $payload = [
            'name' => $name,
            'currency' => $currency,
            'value' => round($value, 2),
            'units' => round($units, 3),
            'products' => $productsCount,
            'variations' => $variationsCount,
            'oos' => $outOfStock,
            'low' => $lowStock,
        ];

        if ($existingId) {
            $pdo->prepare(
                'UPDATE inventories SET name = :name, currency_code = :currency, stock_value = :value,
                 units_total = :units, products_count = :products, variations_count = :variations,
                 out_of_stock_count = :oos, low_stock_count = :low, valued_at = NOW(),
                 include_in_net_worth = 1, updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id'
            )->execute($payload + ['id' => (int) $existingId]);
            $inventoryId = (int) $existingId;
        } else {
            $pdo->prepare(
                'INSERT INTO inventories
                 (entity_id, name, source, integration_id, currency_code, stock_value, units_total,
                  products_count, variations_count, out_of_stock_count, low_stock_count,
                  valued_at, include_in_net_worth)
                 VALUES
                 (:entity_id, :name, \'woocommerce\', :integration_id, :currency, :value, :units,
                  :products, :variations, :oos, :low, NOW(), 1)'
            )->execute($payload + [
                'entity_id' => $entityId,
                'integration_id' => $integrationId,
            ]);
            $inventoryId = (int) $pdo->lastInsertId();
        }

        $pdo->prepare(
            'INSERT INTO inventory_snapshots
             (inventory_id, captured_at, stock_value, units_total, products_count, source)
             VALUES (:id, NOW(), :value, :units, :products, \'integration\')'
        )->execute([
            'id' => $inventoryId,
            'value' => round($value, 2),
            'units' => round($units, 3),
            'products' => $productsCount,
        ]);

        return [
            'upserted' => 1,
            'stock_value' => round($value, 2),
            'variations_read' => $variationsRead,
        ];
    }

    /**
     * Agrupa pedidos por mes calendario (ventas ya hechas en la ventana sync).
     * @param list<array<string,mixed>> $orders
     */
    private static function upsertSalesMetrics(
        int $integrationId,
        int $entityId,
        string $currency,
        array $orders
    ): int {
        /** @var array<string, array{period_start:string,period_end:string,orders_count:int,sales:float,refunds:float,customers:array<string,true>}> $buckets */
        $buckets = [];

        foreach ($orders as $order) {
            $date = (string) ($order['date_created'] ?? $order['date_created_gmt'] ?? '');
            $ts = $date !== '' ? strtotime($date) : false;
            if ($ts === false) {
                continue;
            }
            $status = (string) ($order['status'] ?? '');
            if (in_array($status, ['cancelled', 'failed', 'checkout-draft', 'pending', 'on-hold'], true)) {
                continue;
            }

            $key = date('Y-m', $ts);
            if (!isset($buckets[$key])) {
                $buckets[$key] = [
                    'period_start' => date('Y-m-01', $ts),
                    'period_end' => date('Y-m-t', $ts),
                    'orders_count' => 0,
                    'sales' => 0.0,
                    'refunds' => 0.0,
                    'customers' => [],
                ];
            }

            $buckets[$key]['orders_count']++;
            $total = (float) ($order['total'] ?? 0);
            if ($status === 'refunded') {
                $buckets[$key]['refunds'] += abs($total);
            } else {
                $buckets[$key]['sales'] += $total;
            }
            $email = (string) ($order['billing']['email'] ?? '');
            if ($email !== '') {
                $buckets[$key]['customers'][$email] = true;
            }
        }

        if ($buckets === []) {
            // Mes actual en cero para que la UI muestre que hubo sync.
            $buckets[date('Y-m')] = [
                'period_start' => date('Y-m-01'),
                'period_end' => date('Y-m-t'),
                'orders_count' => 0,
                'sales' => 0.0,
                'refunds' => 0.0,
                'customers' => [],
            ];
        }

        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'INSERT INTO sales_metrics
             (entity_id, integration_id, period_start, period_end, currency_code,
              orders_count, sales_total, refunds_total, customers_count)
             VALUES
             (:entity_id, :integration_id, :period_start, :period_end, :currency,
              :orders_count, :sales_total, :refunds_total, :customers_count)
             ON DUPLICATE KEY UPDATE
              orders_count = VALUES(orders_count),
              sales_total = VALUES(sales_total),
              refunds_total = VALUES(refunds_total),
              customers_count = VALUES(customers_count),
              updated_at = CURRENT_TIMESTAMP'
        );

        $n = 0;
        foreach ($buckets as $b) {
            $stmt->execute([
                'entity_id' => $entityId,
                'integration_id' => $integrationId,
                'period_start' => $b['period_start'],
                'period_end' => $b['period_end'],
                'currency' => $currency,
                'orders_count' => $b['orders_count'],
                'sales_total' => round($b['sales'], 2),
                'refunds_total' => round($b['refunds'], 2),
                'customers_count' => count($b['customers']),
            ]);
            $n++;
        }

        return $n;
    }

    /** @param array<string,mixed> $line */
    private static function lineQty(array $line): float
    {
        $manage = $line['manage_stock'] ?? null;
        $manages = $manage === true || $manage === 1 || $manage === 'true' || $manage === 'yes';
        $hasQty = array_key_exists('stock_quantity', $line)
            && $line['stock_quantity'] !== null
            && $line['stock_quantity'] !== '';
        if ($manages || $hasQty) {
            return max(0.0, (float) ($line['stock_quantity'] ?? 0));
        }
        // Sin control de stock: no inventamos unidades (evita inflar inventario).
        return 0.0;
    }

    /** @param array<string,mixed> $line */
    private static function linePrice(array $line): float
    {
        $price = $line['price'] ?? null;
        if ($price === null || $price === '') {
            $price = $line['regular_price'] ?? 0;
        }
        return max(0.0, (float) $price);
    }
}
