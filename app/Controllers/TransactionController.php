<?php

declare(strict_types=1);

final class TransactionController
{
    public static function index(): void
    {
        Auth::requireLogin();
        $entityId = request_int('entity_id');
        $pdo = Database::pdo();

        $where = '';
        $params = [];
        if ($entityId) {
            $where = ' WHERE t.entity_id = :e';
            $params['e'] = $entityId;
        }

        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM transactions t{$where}");
        $countStmt->execute($params);
        $pagination = Pagination::make((int) $countStmt->fetchColumn(), null, 50);

        $sql = "SELECT t.*, e.name AS entity_name, a.name AS account_name,
                       dest.name AS counterparty_name, c.name AS category_name
                FROM transactions t
                INNER JOIN entities e ON e.id = t.entity_id
                LEFT JOIN accounts a ON a.id = t.account_id
                LEFT JOIN accounts dest ON dest.id = t.counterparty_account_id
                LEFT JOIN transaction_categories c ON c.id = t.category_id
                {$where}
                ORDER BY t.occurred_at DESC, t.id DESC
                LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        view('transactions/index', [
            'title' => 'Movimientos',
            'rows' => $stmt->fetchAll(),
            'entities' => Catalog::entities(),
            'entityId' => $entityId,
            'pagination' => $pagination,
            'success' => flash('success'),
            'error' => flash('error'),
        ]);
    }

    public static function export(): void
    {
        Auth::requireLogin();
        $entityId = request_int('entity_id');
        $sql = "SELECT t.occurred_at, e.name AS entity_name, t.type, a.name AS account_name,
                       dest.name AS counterparty_name, c.name AS category_name, t.description,
                       t.currency_code, t.amount, t.origin
                FROM transactions t
                INNER JOIN entities e ON e.id = t.entity_id
                LEFT JOIN accounts a ON a.id = t.account_id
                LEFT JOIN accounts dest ON dest.id = t.counterparty_account_id
                LEFT JOIN transaction_categories c ON c.id = t.category_id";
        $params = [];
        if ($entityId) {
            $sql .= ' WHERE t.entity_id = :e';
            $params['e'] = $entityId;
        }
        $sql .= ' ORDER BY t.occurred_at DESC, t.id DESC LIMIT 5000';
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        $rows = [];
        foreach ($stmt->fetchAll() as $r) {
            $rows[] = [
                $r['occurred_at'],
                $r['entity_name'],
                $r['type'],
                $r['account_name'],
                $r['counterparty_name'],
                $r['category_name'],
                $r['description'],
                $r['currency_code'],
                $r['amount'],
                $r['origin'],
            ];
        }
        ExportService::csv('movimientos.csv', [
            'Fecha', 'Entidad', 'Tipo', 'Cuenta origen', 'Cuenta destino', 'Categoría',
            'Descripción', 'Moneda', 'Importe', 'Origen',
        ], $rows);
    }

    public static function create(): void
    {
        Auth::requireLogin();
        view('transactions/form', [
            'title' => 'Nuevo movimiento',
            'item' => [
                'entity_id' => request_int('entity_id'),
                'account_id' => request_int('account_id'),
                'type' => 'expense',
                'currency_code' => 'ARS',
                'occurred_at' => date('Y-m-d\TH:i'),
                'amount' => 0,
                'adjustment_direction' => 'plus',
            ],
            'entities' => Catalog::entities(),
            'accounts' => Catalog::accountsForEntity(),
            'categories' => Catalog::transactionCategories(),
            'currencies' => Catalog::currencies(),
            'error' => flash('error'),
        ]);
    }

    public static function store(): void
    {
        Auth::requireLogin();
        require_csrf();

        $payload = self::payloadFromRequest();
        if ($payload === null) {
            redirect('/movimientos/nuevo');
        }

        $user = Auth::user();
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            self::assertAccountsForPayload($pdo, $payload);

            $meta = [];
            if ($payload['type'] === 'adjustment') {
                $meta['adjustment_direction'] = $payload['adjustment_direction'];
            }

            $pdo->prepare(
                'INSERT INTO transactions
                 (entity_id, account_id, counterparty_account_id, category_id, type, occurred_at,
                  currency_code, amount, description, origin, metadata_json, created_by)
                 VALUES
                 (:entity_id, :account_id, :counterparty_account_id, :category_id, :type, :occurred_at,
                  :currency_code, :amount, :description, \'manual\', :metadata_json, :created_by)'
            )->execute([
                'entity_id' => $payload['entity_id'],
                'account_id' => $payload['account_id'],
                'counterparty_account_id' => $payload['counterparty_account_id'],
                'category_id' => $payload['category_id'],
                'type' => $payload['type'],
                'occurred_at' => $payload['occurred_at'],
                'currency_code' => $payload['currency_code'],
                'amount' => $payload['amount'],
                'description' => $payload['description'],
                'metadata_json' => $meta !== [] ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null,
                'created_by' => $user['id'] ?? null,
            ]);
            $id = (int) $pdo->lastInsertId();

            self::applyBalanceEffects($pdo, $payload);

            $pdo->commit();
            AuditService::log('create', 'transaction', $id, 'Movimiento ' . $payload['type'] . ': ' . $payload['amount']);
            flash('success', $payload['type'] === 'transfer'
                ? 'Transferencia OK: se restó de la cuenta origen y se sumó en la destino.'
                : 'Movimiento registrado.');
            redirect('/movimientos');
        } catch (Throwable $e) {
            $pdo->rollBack();
            flash('error', 'No se pudo guardar: ' . $e->getMessage());
            redirect('/movimientos/nuevo');
        }
    }

    public static function edit(string $id): void
    {
        Auth::requireLogin();
        $item = self::find((int) $id);
        if (!$item) {
            flash('error', 'Movimiento no encontrado.');
            redirect('/movimientos');
        }

        $occurred = (string) ($item['occurred_at'] ?? '');
        $occurred = str_replace(' ', 'T', substr($occurred, 0, 16));

        $meta = [];
        if (!empty($item['metadata_json'])) {
            $decoded = json_decode((string) $item['metadata_json'], true);
            if (is_array($decoded)) {
                $meta = $decoded;
            }
        }

        $item['occurred_at'] = $occurred;
        $item['adjustment_direction'] = (string) ($meta['adjustment_direction'] ?? 'plus');

        view('transactions/form', [
            'title' => 'Editar movimiento',
            'item' => $item,
            'entities' => Catalog::entities(),
            'accounts' => Catalog::accountsForEntity(),
            'categories' => Catalog::transactionCategories(),
            'currencies' => Catalog::currencies(),
            'error' => flash('error'),
        ]);
    }

    public static function update(string $id): void
    {
        Auth::requireLogin();
        require_csrf();
        $txId = (int) $id;
        $before = self::find($txId);
        if (!$before) {
            flash('error', 'Movimiento no encontrado.');
            redirect('/movimientos');
        }

        $payload = self::payloadFromRequest();
        if ($payload === null) {
            redirect('/movimientos/' . $txId . '/editar');
        }

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            self::assertAccountsForPayload($pdo, $payload);

            $oldPayload = self::payloadFromRow($before);
            self::applyBalanceEffects($pdo, $oldPayload, -1.0);

            $meta = [];
            if (!empty($before['metadata_json'])) {
                $decoded = json_decode((string) $before['metadata_json'], true);
                if (is_array($decoded)) {
                    $meta = $decoded;
                }
            }
            if ($payload['type'] === 'adjustment') {
                $meta['adjustment_direction'] = $payload['adjustment_direction'];
            } else {
                unset($meta['adjustment_direction']);
            }

            $pdo->prepare(
                'UPDATE transactions
                 SET entity_id = :entity_id, account_id = :account_id,
                     counterparty_account_id = :counterparty_account_id, category_id = :category_id,
                     type = :type, occurred_at = :occurred_at, currency_code = :currency_code,
                     amount = :amount, description = :description, metadata_json = :metadata_json
                 WHERE id = :id'
            )->execute([
                'entity_id' => $payload['entity_id'],
                'account_id' => $payload['account_id'],
                'counterparty_account_id' => $payload['counterparty_account_id'],
                'category_id' => $payload['category_id'],
                'type' => $payload['type'],
                'occurred_at' => $payload['occurred_at'],
                'currency_code' => $payload['currency_code'],
                'amount' => $payload['amount'],
                'description' => $payload['description'],
                'metadata_json' => $meta !== [] ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null,
                'id' => $txId,
            ]);

            self::applyBalanceEffects($pdo, $payload);

            // Si está ligado a un presupuesto pagado, alinear el importe liquidado.
            $pdo->prepare(
                'UPDATE budget_items SET paid_amount = :a, updated_at = CURRENT_TIMESTAMP
                 WHERE transaction_id = :tx AND status = \'paid\''
            )->execute(['a' => $payload['amount'], 'tx' => $txId]);

            $pdo->commit();
            AuditService::log('update', 'transaction', $txId, 'Edición movimiento', $before, self::find($txId));
            flash('success', 'Movimiento actualizado. Los saldos se recalcularon.');
            redirect('/movimientos');
        } catch (Throwable $e) {
            $pdo->rollBack();
            flash('error', 'No se pudo actualizar: ' . $e->getMessage());
            redirect('/movimientos/' . $txId . '/editar');
        }
    }

    /** @return array<string,mixed>|null */
    private static function payloadFromRequest(): ?array
    {
        $entityId = request_int('entity_id');
        $type = post_string('type', 'expense');
        $amount = abs(post_float('amount'));
        $fromId = request_int('account_id');
        $toId = request_int('counterparty_account_id');
        $occurred = post_string('occurred_at') ?: date('Y-m-d H:i:s');
        $occurred = str_replace('T', ' ', $occurred);
        if (strlen($occurred) === 16) {
            $occurred .= ':00';
        }
        $adjDir = post_string('adjustment_direction', 'plus') === 'minus' ? 'minus' : 'plus';

        if ($fromId && $toId && $fromId !== $toId) {
            $type = 'transfer';
        }

        if (!$entityId || $amount <= 0) {
            flash('error', 'Entidad e importe son obligatorios.');
            return null;
        }
        if (in_array($type, ['income', 'expense', 'adjustment'], true) && !$fromId) {
            flash('error', 'Seleccioná una cuenta.');
            return null;
        }
        if ($type === 'transfer' && (!$fromId || !$toId || $fromId === $toId)) {
            flash('error', 'Transferencia: elegí cuenta origen y cuenta destino distintas.');
            return null;
        }

        $description = post_string('description');
        if ($type === 'transfer' && $description === '') {
            $description = 'Transferencia entre cuentas';
        }

        return [
            'entity_id' => $entityId,
            'account_id' => $fromId,
            'counterparty_account_id' => $type === 'transfer' ? $toId : null,
            'category_id' => request_int('category_id'),
            'type' => $type,
            'occurred_at' => $occurred,
            'currency_code' => post_string('currency_code', 'ARS') ?: 'ARS',
            'amount' => round($amount, 2),
            'description' => $description !== '' ? $description : null,
            'adjustment_direction' => $adjDir,
        ];
    }

    /** @param array<string,mixed> $row */
    private static function payloadFromRow(array $row): array
    {
        $meta = [];
        if (!empty($row['metadata_json'])) {
            $decoded = json_decode((string) $row['metadata_json'], true);
            if (is_array($decoded)) {
                $meta = $decoded;
            }
        }

        return [
            'entity_id' => (int) $row['entity_id'],
            'account_id' => $row['account_id'] !== null ? (int) $row['account_id'] : null,
            'counterparty_account_id' => $row['counterparty_account_id'] !== null
                ? (int) $row['counterparty_account_id']
                : null,
            'category_id' => $row['category_id'] !== null ? (int) $row['category_id'] : null,
            'type' => (string) $row['type'],
            'occurred_at' => (string) $row['occurred_at'],
            'currency_code' => (string) ($row['currency_code'] ?? 'ARS'),
            'amount' => round((float) $row['amount'], 2),
            'description' => $row['description'] ?? null,
            'adjustment_direction' => (($meta['adjustment_direction'] ?? 'plus') === 'minus') ? 'minus' : 'plus',
        ];
    }

    /** @param array<string,mixed> $payload */
    private static function assertAccountsForPayload(PDO $pdo, array $payload): void
    {
        if (!empty($payload['account_id'])) {
            self::assertAccount($pdo, (int) $payload['account_id']);
        }
        if (($payload['type'] ?? '') === 'transfer' && !empty($payload['counterparty_account_id'])) {
            self::assertAccount($pdo, (int) $payload['counterparty_account_id']);
        }
    }

    /**
     * Aplica (o revierte con $sign = -1) el efecto del movimiento sobre saldos.
     * @param array<string,mixed> $payload
     */
    private static function applyBalanceEffects(PDO $pdo, array $payload, float $sign = 1.0): void
    {
        $type = (string) ($payload['type'] ?? '');
        $amount = round((float) ($payload['amount'] ?? 0), 2);
        $fromId = !empty($payload['account_id']) ? (int) $payload['account_id'] : 0;
        $toId = !empty($payload['counterparty_account_id']) ? (int) $payload['counterparty_account_id'] : 0;
        $sign = $sign < 0 ? -1.0 : 1.0;

        if ($type === 'income' && $fromId) {
            self::adjustBalance($pdo, $fromId, $amount * $sign);
        } elseif ($type === 'expense' && $fromId) {
            self::adjustBalance($pdo, $fromId, -$amount * $sign);
        } elseif ($type === 'adjustment' && $fromId) {
            $dir = (($payload['adjustment_direction'] ?? 'plus') === 'minus') ? -1.0 : 1.0;
            self::adjustBalance($pdo, $fromId, $amount * $dir * $sign);
        } elseif ($type === 'transfer' && $fromId && $toId) {
            self::adjustBalance($pdo, $fromId, -$amount * $sign);
            self::adjustBalance($pdo, $toId, $amount * $sign);
        }
    }

    private static function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM transactions WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private static function assertAccount(PDO $pdo, int $accountId): void
    {
        $stmt = $pdo->prepare('SELECT id, status FROM accounts WHERE id = ? LIMIT 1');
        $stmt->execute([$accountId]);
        $row = $stmt->fetch();
        if (!$row) {
            throw new RuntimeException('La cuenta #' . $accountId . ' no existe.');
        }
        if (($row['status'] ?? '') !== 'active') {
            throw new RuntimeException('La cuenta #' . $accountId . ' no está activa.');
        }
    }

    private static function adjustBalance(PDO $pdo, int $accountId, float $delta): void
    {
        $delta = round($delta, 2);
        if (abs($delta) < 0.00001) {
            return;
        }
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
                $acc['balance_held'],
            ]);
        }
    }
}
