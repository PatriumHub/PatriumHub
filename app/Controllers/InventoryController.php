<?php

declare(strict_types=1);

final class InventoryController
{
    public static function index(): void
    {
        Auth::requireAdminArea();
        $rows = Database::pdo()->query(
            "SELECT i.*, e.name AS entity_name
             FROM inventories i
             INNER JOIN entities e ON e.id = i.entity_id
             ORDER BY e.name, i.name"
        )->fetchAll();

        $currency = preferred_list_currency($rows, 'stock_value', 'ARS');

        $value = 0.0;
        $units = 0.0;
        $inNw = 0;
        $entities = [];
        $byEntity = [];
        $bySource = [];
        foreach ($rows as $row) {
            $rowCurrency = strtoupper(trim((string) ($row['currency_code'] ?? '')));
            if ($rowCurrency !== $currency) {
                continue;
            }
            $v = (float) ($row['stock_value'] ?? 0);
            $value += $v;
            $units += (float) ($row['units_total'] ?? 0);
            if ((int) ($row['include_in_net_worth'] ?? 1)) {
                $inNw++;
            }
            $eid = (int) ($row['entity_id'] ?? 0);
            $entities[$eid] = true;
            $ename = (string) ($row['entity_name'] ?? 'Sin entidad');
            $byEntity[$ename] = ($byEntity[$ename] ?? 0.0) + $v;
            $src = (string) ($row['source'] ?? 'manual');
            $srcLabel = $src === 'manual' ? 'Manual' : $src;
            $bySource[$srcLabel] = ($bySource[$srcLabel] ?? 0.0) + $v;
        }
        arsort($byEntity);
        arsort($bySource);

        view('inventories/index', [
            'title' => 'Inventario',
            'rows' => $rows,
            'currency' => $currency,
            'currencies' => Catalog::currencies(),
            'totals' => [
                'value' => $value,
                'units' => $units,
                'in_net_worth' => $inNw,
                'entities' => count($entities),
                'count' => count($rows),
            ],
            'charts' => [
                'byType' => [
                    'labels' => array_keys($bySource),
                    'values' => array_values($bySource),
                ],
                'byEntity' => [
                    'labels' => array_keys($byEntity),
                    'values' => array_values($byEntity),
                ],
            ],
            'success' => flash('success'),
            'error' => flash('error'),
        ]);
    }

    public static function create(): void
    {
        Auth::requireAdminArea();
        view('inventories/form', [
            'title' => 'Nuevo inventario',
            'item' => [
                'entity_id' => request_int('entity_id'),
                'name' => 'Inventario',
                'source' => 'manual',
                'currency_code' => 'ARS',
                'stock_value' => 0,
                'units_total' => 0,
                'include_in_net_worth' => 1,
            ],
            'entities' => Catalog::entities('company'),
            'currencies' => Catalog::currencies(),
            'error' => flash('error'),
        ]);
    }

    public static function store(): void
    {
        Auth::requireAdminArea();
        require_csrf();
        $entityId = request_int('entity_id');
        $name = post_string('name', 'Inventario');
        if (!$entityId) {
            flash('error', 'La entidad es obligatoria.');
            redirect('/inventario/nuevo');
        }
        $value = post_float('stock_value');
        $units = post_float('units_total');
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $pdo->prepare(
                'INSERT INTO inventories
                 (entity_id, name, source, currency_code, stock_value, units_total,
                  products_count, valued_at, include_in_net_worth, notes)
                 VALUES
                 (:entity_id, :name, \'manual\', :currency_code, :stock_value, :units_total,
                  :products_count, NOW(), :include_in_net_worth, :notes)'
            )->execute([
                'entity_id' => $entityId,
                'name' => $name,
                'currency_code' => post_string('currency_code', 'ARS'),
                'stock_value' => $value,
                'units_total' => $units,
                'products_count' => (int) post_float('products_count'),
                'include_in_net_worth' => bool_post('include_in_net_worth'),
                'notes' => post_string('notes') ?: null,
            ]);
            $id = (int) $pdo->lastInsertId();
            $pdo->prepare(
                'INSERT INTO inventory_snapshots (inventory_id, captured_at, stock_value, units_total, products_count, source)
                 VALUES (:id, NOW(), :value, :units, :products, \'manual\')'
            )->execute([
                'id' => $id,
                'value' => $value,
                'units' => $units,
                'products' => (int) post_float('products_count'),
            ]);
            $pdo->commit();
            AuditService::log('create', 'inventory', $id, 'Alta inventario: ' . $name);
            flash('success', 'Inventario creado.');
            redirect('/inventario');
        } catch (Throwable $e) {
            $pdo->rollBack();
            flash('error', 'No se pudo guardar: ' . $e->getMessage());
            redirect('/inventario/nuevo');
        }
    }

    public static function edit(string $id): void
    {
        Auth::requireAdminArea();
        $item = self::find((int) $id);
        if (!$item) {
            flash('error', 'Inventario no encontrado.');
            redirect('/inventario');
        }
        view('inventories/form', [
            'title' => 'Editar inventario',
            'item' => $item,
            'entities' => Catalog::entities('company'),
            'currencies' => Catalog::currencies(),
            'error' => flash('error'),
        ]);
    }

    public static function update(string $id): void
    {
        Auth::requireAdminArea();
        require_csrf();
        $invId = (int) $id;
        $before = self::find($invId);
        if (!$before) {
            flash('error', 'Inventario no encontrado.');
            redirect('/inventario');
        }
        $entityId = request_int('entity_id');
        $name = post_string('name', 'Inventario');
        if (!$entityId) {
            flash('error', 'La entidad es obligatoria.');
            redirect('/inventario/' . $invId . '/editar');
        }
        $value = post_float('stock_value');
        $units = post_float('units_total');
        $products = (int) post_float('products_count');
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $pdo->prepare(
                'UPDATE inventories SET entity_id = :entity_id, name = :name, currency_code = :currency_code,
                 stock_value = :stock_value, units_total = :units_total, products_count = :products_count,
                 valued_at = NOW(), include_in_net_worth = :include_in_net_worth, notes = :notes
                 WHERE id = :id'
            )->execute([
                'entity_id' => $entityId,
                'name' => $name,
                'currency_code' => post_string('currency_code', 'ARS'),
                'stock_value' => $value,
                'units_total' => $units,
                'products_count' => $products,
                'include_in_net_worth' => bool_post('include_in_net_worth'),
                'notes' => post_string('notes') ?: null,
                'id' => $invId,
            ]);
            if ((float) $before['stock_value'] !== $value || (float) $before['units_total'] !== $units) {
                $pdo->prepare(
                    'INSERT INTO inventory_snapshots (inventory_id, captured_at, stock_value, units_total, products_count, source)
                     VALUES (:id, NOW(), :value, :units, :products, \'manual\')'
                )->execute([
                    'id' => $invId,
                    'value' => $value,
                    'units' => $units,
                    'products' => $products,
                ]);
            }
            $pdo->commit();
            AuditService::log('update', 'inventory', $invId, 'Edición inventario: ' . $name, $before, self::find($invId));
            flash('success', 'Inventario actualizado.');
            redirect('/inventario');
        } catch (Throwable $e) {
            $pdo->rollBack();
            flash('error', 'No se pudo actualizar: ' . $e->getMessage());
            redirect('/inventario/' . $invId . '/editar');
        }
    }

    private static function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM inventories WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}
