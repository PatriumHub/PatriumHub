<?php

declare(strict_types=1);

final class AccountController
{
    public static function index(): void
    {
        Auth::requireAdminArea();
        AccountOwnerService::ensureSchema();
        $entityId = request_int('entity_id');
        $currency = trim((string) ($_GET['currency'] ?? 'ARS')) ?: 'ARS';

        $sql = "SELECT a.*, e.name AS entity_name, e.type AS entity_type
                FROM accounts a
                INNER JOIN entities e ON e.id = a.entity_id
                WHERE a.status = 'active'";
        $params = [];
        if ($entityId) {
            $sql .= ' AND (
                a.entity_id = :e
                OR EXISTS (
                    SELECT 1 FROM account_owners ao
                    WHERE ao.account_id = a.id AND ao.entity_id = :e2
                )
            )';
            $params['e'] = $entityId;
            $params['e2'] = $entityId;
        }
        $sql .= ' ORDER BY e.name, a.name';
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $typeLabels = [
            'bank' => 'Banco',
            'checking' => 'Caja de ahorro',
            'savings' => 'Ahorro',
            'wallet' => 'Billetera',
            'mercadopago' => 'Mercado Pago',
            'cash' => 'Efectivo',
            'broker' => 'Broker',
            'virtual' => 'Virtual',
            'other' => 'Otro',
        ];

        $total = 0.0;
        $mpTotal = 0.0;
        $mpCount = 0;
        $bankTotal = 0.0;
        $bankCount = 0;
        $bankTypes = ['bank', 'checking', 'savings'];
        $byType = [];
        $byEntity = [];
        $mpByAccountLabels = [];
        $mpByAccountValues = [];
        $mpByAccountIds = [];
        $mpByEntity = [];
        $seenForTotal = [];

        foreach ($rows as &$row) {
            $aid = (int) ($row['id'] ?? 0);
            $etype = (string) ($row['entity_type'] ?? 'person');
            $row['owners_label'] = AccountOwnerService::labelForAccount(
                $aid,
                (string) ($row['entity_name'] ?? ''),
                $etype
            );

            if ((string) ($row['currency_code'] ?? '') !== $currency) {
                continue;
            }
            if (!(int) ($row['include_in_net_worth'] ?? 1)) {
                continue;
            }
            $bal = (float) ($row['balance'] ?? 0);

            // Totales globales: cada cuenta una vez (salvo filtro por entidad → solo su %).
            $share = 1.0;
            if ($entityId && $etype === 'person') {
                $owners = AccountOwnerService::forAccount($aid);
                if ($owners !== []) {
                    $share = 0.0;
                    foreach ($owners as $ow) {
                        if ((int) $ow['entity_id'] === $entityId) {
                            $share = ((float) $ow['ownership_pct']) / 100.0;
                            break;
                        }
                    }
                }
            }
            $shareBal = $bal * $share;

            if (!$entityId) {
                if (isset($seenForTotal[$aid])) {
                    continue;
                }
                $seenForTotal[$aid] = true;
            }

            $total += $shareBal;
            $type = (string) ($row['type'] ?? 'other');
            $typeName = $typeLabels[$type] ?? $type;
            $byType[$typeName] = ($byType[$typeName] ?? 0.0) + $shareBal;

            if ($etype === 'person') {
                $owners = AccountOwnerService::forAccount($aid);
                if ($owners !== []) {
                    foreach ($owners as $ow) {
                        if ($entityId && (int) $ow['entity_id'] !== $entityId) {
                            continue;
                        }
                        $ename = (string) ($ow['name'] ?? 'Sin entidad');
                        $part = $bal * (((float) $ow['ownership_pct']) / 100.0);
                        $byEntity[$ename] = ($byEntity[$ename] ?? 0.0) + $part;
                        if ($type === 'mercadopago') {
                            $mpByEntity[$ename] = ($mpByEntity[$ename] ?? 0.0) + $part;
                        }
                    }
                } else {
                    $ename = (string) ($row['entity_name'] ?? 'Sin entidad');
                    $byEntity[$ename] = ($byEntity[$ename] ?? 0.0) + $shareBal;
                    if ($type === 'mercadopago') {
                        $mpByEntity[$ename] = ($mpByEntity[$ename] ?? 0.0) + $shareBal;
                    }
                }
            } else {
                $ename = (string) ($row['entity_name'] ?? 'Sin entidad');
                $byEntity[$ename] = ($byEntity[$ename] ?? 0.0) + $shareBal;
                if ($type === 'mercadopago') {
                    $mpByEntity[$ename] = ($mpByEntity[$ename] ?? 0.0) + $shareBal;
                }
            }

            if (in_array($type, $bankTypes, true)) {
                $bankTotal += $shareBal;
                $bankCount++;
            }

            if ($type === 'mercadopago') {
                $mpTotal += $shareBal;
                $mpCount++;
                $mpByAccountLabels[] = (string) ($row['name'] ?? 'MP');
                $mpByAccountValues[] = $shareBal;
                $mpByAccountIds[] = $aid;
            }
        }
        unset($row);

        arsort($byType);
        arsort($byEntity);

        view('accounts/index', [
            'title' => 'Cuentas',
            'rows' => $rows,
            'entities' => Catalog::entities(),
            'currencies' => Catalog::currencies(),
            'entityId' => $entityId,
            'currency' => $currency,
            'typeLabels' => $typeLabels,
            'totals' => [
                'balance' => $total,
                'bank' => $bankTotal,
                'bank_count' => $bankCount,
                'mp' => $mpTotal,
                'mp_count' => $mpCount,
                'count' => count($rows),
            ],
            'charts' => [
                'byType' => [
                    'labels' => array_keys($byType),
                    'values' => array_values($byType),
                ],
                'byEntity' => [
                    'labels' => array_keys($byEntity),
                    'values' => array_values($byEntity),
                ],
                'byAccount' => [
                    'labels' => $mpByAccountLabels,
                    'values' => $mpByAccountValues,
                    'ids' => $mpByAccountIds,
                ],
                'mpByEntity' => [
                    'labels' => array_keys($mpByEntity),
                    'values' => array_values($mpByEntity),
                ],
            ],
            'success' => flash('success'),
            'error' => flash('error'),
        ]);
    }

    public static function export(): void
    {
        Auth::requireAdminArea();
        AccountOwnerService::ensureSchema();
        $entityId = request_int('entity_id');
        $sql = "SELECT e.name AS entity_name, e.type AS entity_type, a.id, a.name, a.type, a.currency_code, a.balance,
                       a.balance_held, a.provider, a.status, a.include_in_net_worth
                FROM accounts a
                INNER JOIN entities e ON e.id = a.entity_id
                WHERE a.status = 'active'";
        $params = [];
        if ($entityId) {
            $sql .= ' AND (
                a.entity_id = :e
                OR EXISTS (SELECT 1 FROM account_owners ao WHERE ao.account_id = a.id AND ao.entity_id = :e2)
            )';
            $params['e'] = $entityId;
            $params['e2'] = $entityId;
        }
        $sql .= ' ORDER BY e.name, a.name';
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        $rows = [];
        foreach ($stmt->fetchAll() as $r) {
            $label = AccountOwnerService::labelForAccount(
                (int) $r['id'],
                (string) $r['entity_name'],
                (string) ($r['entity_type'] ?? 'person')
            );
            $rows[] = [
                $label,
                $r['name'],
                $r['type'],
                $r['currency_code'],
                $r['balance'],
                $r['balance_held'],
                $r['provider'],
                $r['status'],
                (int) $r['include_in_net_worth'] ? '1' : '0',
            ];
        }
        ExportService::csv('cuentas.csv', [
            'Titularidad', 'Nombre', 'Tipo', 'Moneda', 'Saldo', 'Retenido', 'Proveedor', 'Estado', 'En patrimonio',
        ], $rows);
    }

    public static function create(): void
    {
        Auth::requireAdminArea();
        AccountOwnerService::ensureSchema();
        $type = post_string('type') ?: (string) ($_GET['type'] ?? 'bank');
        $allowed = ['bank', 'checking', 'savings', 'wallet', 'mercadopago', 'cash', 'broker', 'virtual', 'other'];
        if (!in_array($type, $allowed, true)) {
            $type = 'bank';
        }
        $title = $type === 'mercadopago' ? 'Nueva cuenta Mercado Pago' : 'Nueva cuenta';
        $entityId = request_int('entity_id');
        view('accounts/form', [
            'title' => $title,
            'account' => [
                'entity_id' => $entityId,
                'currency_code' => 'ARS',
                'type' => $type,
                'include_in_net_worth' => 1,
                'status' => 'active',
                'balance' => 0,
                'provider' => $type === 'mercadopago' ? 'Mercado Pago' : null,
            ],
            'owners' => [],
            'people' => Catalog::entities('person'),
            'companies' => Catalog::entities('company'),
            'entities' => Catalog::entities(),
            'currencies' => Catalog::currencies(),
            'error' => flash('error'),
        ]);
    }

    public static function store(): void
    {
        Auth::requireAdminArea();
        require_csrf();
        AccountOwnerService::ensureSchema();

        $parsed = self::parseOwnership();
        if ($parsed === null) {
            flash('error', 'Definí la titularidad (persona/s o empresa).');
            redirect('/cuentas/nueva');
        }
        [$entityId, $owners, $isCompany] = $parsed;

        $name = post_string('name');
        if ($name === '') {
            flash('error', 'El nombre es obligatorio.');
            redirect('/cuentas/nueva');
        }

        $balance = post_float('balance');
        $type = post_string('type', 'bank');
        $provider = post_string('provider') ?: null;
        if ($type === 'mercadopago' && ($provider === null || $provider === '')) {
            $provider = 'Mercado Pago';
        }
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $pdo->prepare(
                'INSERT INTO accounts
                 (entity_id, name, type, currency_code, balance, balance_held, provider, origin,
                  include_in_net_worth, status, notes)
                 VALUES
                 (:entity_id, :name, :type, :currency_code, :balance, :balance_held, :provider, \'manual\',
                  :include_in_net_worth, :status, :notes)'
            )->execute([
                'entity_id' => $entityId,
                'name' => $name,
                'type' => $type,
                'currency_code' => post_string('currency_code', 'ARS'),
                'balance' => $balance,
                'balance_held' => post_float('balance_held'),
                'provider' => $provider,
                'include_in_net_worth' => bool_post('include_in_net_worth'),
                'status' => post_string('status', 'active'),
                'notes' => post_string('notes') ?: null,
            ]);
            $id = (int) $pdo->lastInsertId();
            $pdo->prepare(
                'INSERT INTO account_balances (account_id, captured_at, balance, balance_held, source)
                 VALUES (:id, NOW(), :balance, :held, \'manual\')'
            )->execute([
                'id' => $id,
                'balance' => $balance,
                'held' => post_float('balance_held'),
            ]);
            if ($isCompany) {
                AccountOwnerService::clear($id);
            } else {
                AccountOwnerService::sync($id, $owners);
            }
            $pdo->commit();
            AuditService::log('create', 'account', $id, 'Alta cuenta: ' . $name);
            flash('success', 'Cuenta creada.');
            redirect('/cuentas');
        } catch (Throwable $e) {
            $pdo->rollBack();
            flash('error', 'No se pudo guardar: ' . $e->getMessage());
            redirect('/cuentas/nueva' . ($type === 'mercadopago' ? '?type=mercadopago' : ''));
        }
    }

    public static function edit(string $id): void
    {
        Auth::requireAdminArea();
        AccountOwnerService::ensureSchema();
        $account = self::find((int) $id);
        if (!$account) {
            flash('error', 'Cuenta no encontrada.');
            redirect('/cuentas');
        }
        view('accounts/form', [
            'title' => 'Editar cuenta',
            'account' => $account,
            'owners' => AccountOwnerService::forAccount((int) $id),
            'people' => Catalog::entities('person'),
            'companies' => Catalog::entities('company'),
            'entities' => Catalog::entities(),
            'currencies' => Catalog::currencies(),
            'error' => flash('error'),
        ]);
    }

    public static function update(string $id): void
    {
        Auth::requireAdminArea();
        require_csrf();
        AccountOwnerService::ensureSchema();
        $accountId = (int) $id;
        $before = self::find($accountId);
        if (!$before) {
            flash('error', 'Cuenta no encontrada.');
            redirect('/cuentas');
        }

        $parsed = self::parseOwnership();
        if ($parsed === null) {
            flash('error', 'Definí la titularidad (persona/s o empresa).');
            redirect('/cuentas/' . $accountId . '/editar');
        }
        [$entityId, $owners, $isCompany] = $parsed;

        $name = post_string('name');
        $balance = post_float('balance');
        if ($name === '') {
            flash('error', 'El nombre es obligatorio.');
            redirect('/cuentas/' . $accountId . '/editar');
        }

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $pdo->prepare(
                'UPDATE accounts SET entity_id = :entity_id, name = :name, type = :type,
                 currency_code = :currency_code, balance = :balance, balance_held = :balance_held,
                 provider = :provider, include_in_net_worth = :include_in_net_worth,
                 status = :status, notes = :notes WHERE id = :id'
            )->execute([
                'entity_id' => $entityId,
                'name' => $name,
                'type' => post_string('type', 'bank'),
                'currency_code' => post_string('currency_code', 'ARS'),
                'balance' => $balance,
                'balance_held' => post_float('balance_held'),
                'provider' => post_string('provider') ?: null,
                'include_in_net_worth' => bool_post('include_in_net_worth'),
                'status' => post_string('status', 'active'),
                'notes' => post_string('notes') ?: null,
                'id' => $accountId,
            ]);

            if ((float) $before['balance'] !== $balance || (float) $before['balance_held'] !== post_float('balance_held')) {
                $pdo->prepare(
                    'INSERT INTO account_balances (account_id, captured_at, balance, balance_held, source)
                     VALUES (:id, NOW(), :balance, :held, \'manual\')'
                )->execute([
                    'id' => $accountId,
                    'balance' => $balance,
                    'held' => post_float('balance_held'),
                ]);
            }

            if ($isCompany) {
                AccountOwnerService::clear($accountId);
            } else {
                AccountOwnerService::sync($accountId, $owners);
            }

            $pdo->commit();
            AuditService::log('update', 'account', $accountId, 'Edición cuenta: ' . $name, $before, self::find($accountId));
            flash('success', 'Cuenta actualizada.');
            redirect('/cuentas');
        } catch (Throwable $e) {
            $pdo->rollBack();
            flash('error', 'No se pudo actualizar: ' . $e->getMessage());
            redirect('/cuentas/' . $accountId . '/editar');
        }
    }

    public static function destroy(string $id): void
    {
        Auth::requireAdminArea();
        require_csrf();
        $accountId = (int) $id;
        $account = self::find($accountId);
        if (!$account) {
            flash('error', 'Cuenta no encontrada.');
            redirect('/cuentas');
        }

        $balance = abs((float) ($account['balance'] ?? 0));
        $held = abs((float) ($account['balance_held'] ?? 0));
        if ($balance > 0.009 || $held > 0.009) {
            flash('error', 'Solo se puede eliminar una cuenta con saldo y retenido en 0. Transferí o ajustá el saldo antes.');
            redirect('/cuentas/' . $accountId . '/editar');
        }

        try {
            Database::pdo()->prepare('DELETE FROM accounts WHERE id = :id')->execute(['id' => $accountId]);
            AuditService::log('delete', 'account', $accountId, 'Eliminación cuenta: ' . ($account['name'] ?? ''), $account, null);
            flash('success', 'Cuenta eliminada.');
        } catch (Throwable $e) {
            flash('error', 'No se pudo eliminar: ' . $e->getMessage());
            redirect('/cuentas/' . $accountId . '/editar');
        }
        redirect('/cuentas');
    }

    /**
     * @return array{0:int,1:list<array{entity_id:int,ownership_pct:float}>,2:bool}|null
     */
    private static function parseOwnership(): ?array
    {
        $mode = post_string('owner_mode', 'people');
        if ($mode === 'company') {
            $entityId = request_int('company_entity_id') ?: request_int('entity_id');
            if ($entityId < 1) {
                return null;
            }
            return [$entityId, [], true];
        }

        $owners = AccountOwnerService::ownersFromPost();
        if ($owners === null || $owners === []) {
            // Compat: formulario viejo con entity_id solo.
            $entityId = request_int('entity_id');
            if ($entityId < 1) {
                return null;
            }
            $type = Database::pdo()->prepare('SELECT type FROM entities WHERE id = :id LIMIT 1');
            $type->execute(['id' => $entityId]);
            $t = (string) ($type->fetchColumn() ?: '');
            if ($t === 'company') {
                return [$entityId, [], true];
            }
            return [$entityId, [['entity_id' => $entityId, 'ownership_pct' => 100.0]], false];
        }

        usort($owners, static fn ($a, $b) => $b['ownership_pct'] <=> $a['ownership_pct']);
        $entityId = (int) $owners[0]['entity_id'];
        return [$entityId, $owners, false];
    }

    private static function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM accounts WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}
