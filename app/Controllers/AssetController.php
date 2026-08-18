<?php

declare(strict_types=1);

final class AssetController
{
    public static function index(): void
    {
        Auth::requireAdminArea();
        AssetOwnerService::ensureSchema();
        $rows = Database::pdo()->query(
            "SELECT a.*, e.name AS entity_name, e.type AS entity_type, ac.name AS account_name
             FROM assets a
             INNER JOIN entities e ON e.id = a.entity_id
             LEFT JOIN accounts ac ON ac.id = a.account_id
             ORDER BY e.name, a.name"
        )->fetchAll();
        foreach ($rows as &$row) {
            $row['owners_label'] = AssetOwnerService::labelForAsset(
                (int) $row['id'],
                (string) $row['entity_name'],
                (string) ($row['entity_type'] ?? 'company')
            );
        }
        unset($row);

        $currency = preferred_list_currency($rows, 'current_value', 'ARS');
        $catLabels = [
            'vehicle' => 'Vehículos',
            'equipment' => 'Equipos',
            'machinery' => 'Maquinaria',
            'investment' => 'Inversiones',
            'intangible' => 'Intangibles',
            'crypto' => 'Cripto',
            'other' => 'Otros',
        ];

        $value = 0.0;
        $inNw = 0;
        $active = 0;
        $byCategory = [];
        $byEntity = [];
        foreach ($rows as $row) {
            if ((string) ($row['status'] ?? '') === 'active') {
                $active++;
            }
            $rowCurrency = strtoupper(trim((string) ($row['currency_code'] ?? '')));
            if ($rowCurrency !== $currency) {
                continue;
            }
            $v = (float) ($row['current_value'] ?? 0);
            $value += $v;
            if ((int) ($row['include_in_net_worth'] ?? 1)) {
                $inNw++;
            }
            $cat = (string) ($row['category'] ?? 'other');
            $catName = $catLabels[$cat] ?? $cat;
            $byCategory[$catName] = ($byCategory[$catName] ?? 0.0) + $v;

            $owners = AssetOwnerService::forAsset((int) $row['id']);
            if ($owners === []) {
                $ename = (string) ($row['entity_name'] ?? 'Sin entidad');
                $byEntity[$ename] = ($byEntity[$ename] ?? 0.0) + $v;
            } else {
                $pctTotal = 0.0;
                foreach ($owners as $ow) {
                    $pctTotal += (float) ($ow['ownership_pct'] ?? 0);
                }
                if ($pctTotal <= 0.0001) {
                    $pctTotal = 100.0;
                }
                foreach ($owners as $ow) {
                    $share = $v * (((float) ($ow['ownership_pct'] ?? 0)) / $pctTotal);
                    $oname = (string) ($ow['name'] ?? 'Titular');
                    $byEntity[$oname] = ($byEntity[$oname] ?? 0.0) + $share;
                }
            }
        }
        arsort($byCategory);
        arsort($byEntity);
        $topCat = array_key_first($byCategory);
        $topCatValue = $topCat !== null ? (float) $byCategory[$topCat] : 0.0;

        view('assets/index', [
            'title' => 'Activos varios',
            'rows' => $rows,
            'currency' => $currency,
            'currencies' => Catalog::currencies(),
            'categoryLabels' => $catLabels,
            'totals' => [
                'value' => $value,
                'count' => count($rows),
                'active' => $active,
                'in_net_worth' => $inNw,
                'top_category' => $topCat ?? '—',
                'top_category_value' => $topCatValue,
            ],
            'charts' => [
                'byType' => [
                    'labels' => array_keys($byCategory),
                    'values' => array_values($byCategory),
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
        AssetOwnerService::ensureSchema();
        view('assets/form', [
            'title' => 'Nuevo activo',
            'asset' => [
                'entity_id' => request_int('entity_id'),
                'account_id' => null,
                'category' => 'other',
                'currency_code' => 'ARS',
                'ownership_pct' => 100,
                'include_in_net_worth' => 1,
                'status' => 'active',
                'current_value' => 0,
            ],
            'owners' => [],
            'people' => Catalog::entities('person'),
            'companies' => Catalog::entities('company'),
            'entities' => Catalog::entities(),
            'accounts' => self::accountsCatalog(),
            'currencies' => Catalog::currencies(),
            'error' => flash('error'),
        ]);
    }

    public static function store(): void
    {
        Auth::requireAdminArea();
        require_csrf();
        AssetOwnerService::ensureSchema();

        $parsed = self::resolveOwnersAndEntity();
        if ($parsed === null) {
            flash('error', 'Elegí una empresa o al menos una persona titular.');
            redirect('/activos/nuevo');
        }
        [$entityId, $owners, $ownershipPct] = $parsed;
        $name = post_string('name');
        if ($name === '') {
            flash('error', 'El nombre es obligatorio.');
            redirect('/activos/nuevo');
        }

        $payload = self::payload($entityId, $name, $ownershipPct);
        if ($payload === null) {
            flash('error', 'La cuenta asociada debe pertenecer a la misma entidad titular.');
            redirect('/activos/nuevo');
        }

        $debitAccountId = request_int('debit_account_id');
        $amount = abs((float) ($payload['current_value'] ?? 0));
        if ($debitAccountId && $amount <= 0) {
            flash('error', 'Para debitar una cuenta el valor actual debe ser mayor a 0.');
            redirect('/activos/nuevo');
        }

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            if ($debitAccountId) {
                self::assertActiveAccount($pdo, $debitAccountId);
            }

            $stmt = $pdo->prepare(
                'INSERT INTO assets
                 (entity_id, account_id, name, category, currency_code, current_value, ownership_pct,
                  valuation_method, valued_at, include_in_net_worth, status, notes)
                 VALUES
                 (:entity_id, :account_id, :name, :category, :currency_code, :current_value, :ownership_pct,
                  :valuation_method, :valued_at, :include_in_net_worth, :status, :notes)'
            );
            $stmt->execute($payload);
            $id = (int) $pdo->lastInsertId();

            if ($owners !== []) {
                AssetOwnerService::sync($id, $owners);
            } else {
                AssetOwnerService::clear($id);
            }

            if ($debitAccountId && $amount > 0) {
                $user = Auth::user();
                $pdo->prepare(
                    'INSERT INTO transactions
                     (entity_id, account_id, counterparty_account_id, category_id, type, occurred_at,
                      currency_code, amount, description, origin, created_by)
                     VALUES
                     (:entity_id, :account_id, NULL, NULL, \'expense\', :occurred_at,
                      :currency_code, :amount, :description, \'manual\', :created_by)'
                )->execute([
                    'entity_id' => $entityId,
                    'account_id' => $debitAccountId,
                    'occurred_at' => date('Y-m-d H:i:s'),
                    'currency_code' => $payload['currency_code'] ?: 'ARS',
                    'amount' => round($amount, 2),
                    'description' => 'Compra activo: ' . $name,
                    'created_by' => $user['id'] ?? null,
                ]);
                self::adjustBalance($pdo, $debitAccountId, -$amount);
            }

            $pdo->commit();
            AuditService::log('create', 'asset', $id, 'Alta activo: ' . $name);
            flash(
                'success',
                $debitAccountId
                    ? 'Activo creado y debitado de la cuenta seleccionada.'
                    : 'Activo creado.'
            );
            redirect('/activos');
        } catch (Throwable $e) {
            $pdo->rollBack();
            flash('error', 'No se pudo crear el activo: ' . $e->getMessage());
            redirect('/activos/nuevo');
        }
    }

    public static function edit(string $id): void
    {
        Auth::requireAdminArea();
        AssetOwnerService::ensureSchema();
        $asset = self::find((int) $id);
        if (!$asset) {
            flash('error', 'Activo no encontrado.');
            redirect('/activos');
        }
        view('assets/form', [
            'title' => 'Editar activo',
            'asset' => $asset,
            'owners' => AssetOwnerService::forAsset((int) $id),
            'people' => Catalog::entities('person'),
            'companies' => Catalog::entities('company'),
            'entities' => Catalog::entities(),
            'accounts' => self::accountsCatalog(),
            'currencies' => Catalog::currencies(),
            'error' => flash('error'),
        ]);
    }

    public static function update(string $id): void
    {
        Auth::requireAdminArea();
        require_csrf();
        AssetOwnerService::ensureSchema();
        $assetId = (int) $id;
        $before = self::find($assetId);
        if (!$before) {
            flash('error', 'Activo no encontrado.');
            redirect('/activos');
        }

        $parsed = self::resolveOwnersAndEntity();
        if ($parsed === null) {
            flash('error', 'Elegí una empresa o al menos una persona titular.');
            redirect('/activos/' . $assetId . '/editar');
        }
        [$entityId, $owners, $ownershipPct] = $parsed;
        $name = post_string('name');
        if ($name === '') {
            flash('error', 'El nombre es obligatorio.');
            redirect('/activos/' . $assetId . '/editar');
        }

        $payload = self::payload($entityId, $name, $ownershipPct);
        if ($payload === null) {
            flash('error', 'La cuenta elegida debe pertenecer a la misma entidad titular.');
            redirect('/activos/' . $assetId . '/editar');
        }
        $payload['id'] = $assetId;
        Database::pdo()->prepare(
            'UPDATE assets SET entity_id = :entity_id, account_id = :account_id, name = :name, category = :category,
             currency_code = :currency_code, current_value = :current_value, ownership_pct = :ownership_pct,
             valuation_method = :valuation_method, valued_at = :valued_at,
             include_in_net_worth = :include_in_net_worth, status = :status, notes = :notes
             WHERE id = :id'
        )->execute($payload);

        if ($owners !== []) {
            AssetOwnerService::sync($assetId, $owners);
        } else {
            AssetOwnerService::clear($assetId);
        }

        AuditService::log('update', 'asset', $assetId, 'Edición activo: ' . $name, $before, self::find($assetId));
        flash('success', 'Activo actualizado.');
        redirect('/activos');
    }

    /**
     * @return array{0:int,1:list<array{entity_id:int,ownership_pct:float}>,2:float}|null
     */
    private static function resolveOwnersAndEntity(): ?array
    {
        $mode = post_string('owner_mode', 'people');
        if ($mode === 'company') {
            $entityId = request_int('company_entity_id') ?: request_int('entity_id');
            if (!$entityId) {
                return null;
            }
            $stmt = Database::pdo()->prepare('SELECT type FROM entities WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $entityId]);
            if ((string) $stmt->fetchColumn() !== 'company') {
                return null;
            }
            return [$entityId, [], 100.0];
        }

        $owners = AssetOwnerService::ownersFromPost();
        if ($owners === null || $owners === []) {
            return null;
        }
        usort($owners, static fn ($a, $b) => $b['ownership_pct'] <=> $a['ownership_pct']);
        $entityId = (int) $owners[0]['entity_id'];
        // En assets.ownership_pct guardamos 100: el desglose vive en asset_owners.
        return [$entityId, $owners, 100.0];
    }

    /** @return array<string,mixed>|null null si la cuenta no pertenece a la entidad */
    private static function payload(int $entityId, string $name, float $ownershipPct): ?array
    {
        $accountId = request_int('account_id');
        if ($accountId) {
            $stmt = Database::pdo()->prepare(
                'SELECT entity_id FROM accounts WHERE id = :id LIMIT 1'
            );
            $stmt->execute(['id' => $accountId]);
            $accEntity = $stmt->fetchColumn();
            if ((int) $accEntity !== $entityId) {
                return null;
            }
        }

        return [
            'entity_id' => $entityId,
            'account_id' => $accountId,
            'name' => $name,
            'category' => post_string('category', 'other'),
            'currency_code' => post_string('currency_code', 'ARS'),
            'current_value' => post_float('current_value'),
            'ownership_pct' => $ownershipPct,
            'valuation_method' => post_string('valuation_method') ?: null,
            'valued_at' => post_string('valued_at') ?: null,
            'include_in_net_worth' => bool_post('include_in_net_worth'),
            'status' => post_string('status', 'active'),
            'notes' => post_string('notes') ?: null,
        ];
    }

    /** @return list<array{id:int,name:string,entity_id:int,entity_name:string}> */
    private static function accountsCatalog(): array
    {
        return Database::pdo()->query(
            "SELECT a.id, a.name, a.entity_id, e.name AS entity_name
             FROM accounts a
             INNER JOIN entities e ON e.id = a.entity_id
             WHERE a.status = 'active'
             ORDER BY e.name, a.name"
        )->fetchAll();
    }

    private static function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM assets WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private static function assertActiveAccount(PDO $pdo, int $accountId): void
    {
        $stmt = $pdo->prepare("SELECT id, status FROM accounts WHERE id = ? LIMIT 1");
        $stmt->execute([$accountId]);
        $row = $stmt->fetch();
        if (!$row) {
            throw new RuntimeException('La cuenta a debitar no existe.');
        }
        if (($row['status'] ?? '') !== 'active') {
            throw new RuntimeException('La cuenta a debitar no está activa.');
        }
    }

    private static function adjustBalance(PDO $pdo, int $accountId, float $delta): void
    {
        $delta = round($delta, 2);
        $stmt = $pdo->prepare(
            'UPDATE accounts
             SET balance = ROUND(balance + ?, 2), updated_at = CURRENT_TIMESTAMP
             WHERE id = ?'
        );
        $stmt->execute([$delta, $accountId]);
        if ($stmt->rowCount() < 1) {
            throw new RuntimeException('No se actualizó el saldo de la cuenta #' . $accountId . '.');
        }

        $accStmt = $pdo->prepare('SELECT balance, balance_held FROM accounts WHERE id = ?');
        $accStmt->execute([$accountId]);
        $acc = $accStmt->fetch();
        if ($acc) {
            $pdo->prepare(
                'INSERT INTO account_balances (account_id, captured_at, balance, balance_held, source)
                 VALUES (?, NOW(), ?, ?, \'system\')'
            )->execute([
                $accountId,
                $acc['balance'],
                $acc['balance_held'] ?? 0,
            ]);
        }
    }
}
