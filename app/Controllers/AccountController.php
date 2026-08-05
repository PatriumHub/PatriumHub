<?php

declare(strict_types=1);

final class AccountController
{
    public static function index(): void
    {
        Auth::requireLogin();
        $entityId = request_int('entity_id');
        $sql = "SELECT a.*, e.name AS entity_name
                FROM accounts a
                INNER JOIN entities e ON e.id = a.entity_id
                WHERE a.status = 'active'";
        $params = [];
        if ($entityId) {
            $sql .= ' AND a.entity_id = :e';
            $params['e'] = $entityId;
        }
        $sql .= ' ORDER BY e.name, a.name';
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);

        view('accounts/index', [
            'title' => 'Cuentas',
            'rows' => $stmt->fetchAll(),
            'entities' => Catalog::entities(),
            'entityId' => $entityId,
            'success' => flash('success'),
            'error' => flash('error'),
        ]);
    }

    public static function export(): void
    {
        Auth::requireLogin();
        $entityId = request_int('entity_id');
        $sql = "SELECT e.name AS entity_name, a.name, a.type, a.currency_code, a.balance,
                       a.balance_held, a.provider, a.status, a.include_in_net_worth
                FROM accounts a
                INNER JOIN entities e ON e.id = a.entity_id
                WHERE a.status = 'active'";
        $params = [];
        if ($entityId) {
            $sql .= ' AND a.entity_id = :e';
            $params['e'] = $entityId;
        }
        $sql .= ' ORDER BY e.name, a.name';
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        $rows = [];
        foreach ($stmt->fetchAll() as $r) {
            $rows[] = [
                $r['entity_name'],
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
            'Entidad', 'Nombre', 'Tipo', 'Moneda', 'Saldo', 'Retenido', 'Proveedor', 'Estado', 'En patrimonio',
        ], $rows);
    }

    public static function create(): void
    {
        Auth::requireLogin();
        $type = post_string('type') ?: (string) ($_GET['type'] ?? 'bank');
        $allowed = ['bank', 'checking', 'savings', 'wallet', 'mercadopago', 'cash', 'broker', 'virtual', 'other'];
        if (!in_array($type, $allowed, true)) {
            $type = 'bank';
        }
        $title = $type === 'mercadopago' ? 'Nueva cuenta Mercado Pago' : 'Nueva cuenta';
        view('accounts/form', [
            'title' => $title,
            'account' => [
                'entity_id' => request_int('entity_id'),
                'currency_code' => 'ARS',
                'type' => $type,
                'include_in_net_worth' => 1,
                'status' => 'active',
                'balance' => 0,
                'provider' => $type === 'mercadopago' ? 'Mercado Pago' : null,
            ],
            'entities' => Catalog::entities(),
            'currencies' => Catalog::currencies(),
            'error' => flash('error'),
        ]);
    }

    public static function store(): void
    {
        Auth::requireLogin();
        require_csrf();
        $entityId = request_int('entity_id');
        $name = post_string('name');
        if (!$entityId || $name === '') {
            flash('error', 'Entidad y nombre son obligatorios.');
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
            $pdo->commit();
            AuditService::log('create', 'account', $id, 'Alta cuenta: ' . $name);
            flash('success', 'Cuenta creada.');
            if ($type === 'mercadopago') {
                redirect('/dashboard/mercadopago');
            }
            redirect('/cuentas');
        } catch (Throwable $e) {
            $pdo->rollBack();
            flash('error', 'No se pudo guardar: ' . $e->getMessage());
            redirect('/cuentas/nueva' . ($type === 'mercadopago' ? '?type=mercadopago' : ''));
        }
    }

    public static function edit(string $id): void
    {
        Auth::requireLogin();
        $account = self::find((int) $id);
        if (!$account) {
            flash('error', 'Cuenta no encontrada.');
            redirect('/cuentas');
        }
        view('accounts/form', [
            'title' => 'Editar cuenta',
            'account' => $account,
            'entities' => Catalog::entities(),
            'currencies' => Catalog::currencies(),
            'error' => flash('error'),
        ]);
    }

    public static function update(string $id): void
    {
        Auth::requireLogin();
        require_csrf();
        $accountId = (int) $id;
        $before = self::find($accountId);
        if (!$before) {
            flash('error', 'Cuenta no encontrada.');
            redirect('/cuentas');
        }

        $entityId = request_int('entity_id');
        $name = post_string('name');
        $balance = post_float('balance');
        if (!$entityId || $name === '') {
            flash('error', 'Entidad y nombre son obligatorios.');
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

    private static function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM accounts WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}
