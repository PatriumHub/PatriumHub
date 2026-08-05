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

        // Si hay origen y destino distintos, es transferencia aunque el tipo diga otra cosa.
        if ($fromId && $toId && $fromId !== $toId) {
            $type = 'transfer';
        }

        if (!$entityId || $amount <= 0) {
            flash('error', 'Entidad e importe son obligatorios.');
            redirect('/movimientos/nuevo');
        }
        if (in_array($type, ['income', 'expense', 'adjustment'], true) && !$fromId) {
            flash('error', 'Seleccioná una cuenta.');
            redirect('/movimientos/nuevo');
        }
        if ($type === 'transfer' && (!$fromId || !$toId || $fromId === $toId)) {
            flash('error', 'Transferencia: elegí cuenta origen y cuenta destino distintas.');
            redirect('/movimientos/nuevo');
        }

        $user = Auth::user();
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            if ($fromId) {
                self::assertAccount($pdo, $fromId);
            }
            if ($type === 'transfer' && $toId) {
                self::assertAccount($pdo, $toId);
            }

            $description = post_string('description');
            if ($type === 'transfer' && $description === '') {
                $description = 'Transferencia entre cuentas';
            }

            $pdo->prepare(
                'INSERT INTO transactions
                 (entity_id, account_id, counterparty_account_id, category_id, type, occurred_at,
                  currency_code, amount, description, origin, created_by)
                 VALUES
                 (:entity_id, :account_id, :counterparty_account_id, :category_id, :type, :occurred_at,
                  :currency_code, :amount, :description, \'manual\', :created_by)'
            )->execute([
                'entity_id' => $entityId,
                'account_id' => $fromId,
                'counterparty_account_id' => $type === 'transfer' ? $toId : null,
                'category_id' => request_int('category_id'),
                'type' => $type,
                'occurred_at' => $occurred,
                'currency_code' => post_string('currency_code', 'ARS') ?: 'ARS',
                'amount' => round($amount, 2),
                'description' => $description !== '' ? $description : null,
                'created_by' => $user['id'] ?? null,
            ]);
            $id = (int) $pdo->lastInsertId();

            if ($type === 'income' && $fromId) {
                self::adjustBalance($pdo, $fromId, $amount);
            } elseif ($type === 'expense' && $fromId) {
                self::adjustBalance($pdo, $fromId, -$amount);
            } elseif ($type === 'adjustment' && $fromId) {
                $sign = post_string('adjustment_direction', 'plus') === 'minus' ? -1.0 : 1.0;
                self::adjustBalance($pdo, $fromId, $amount * $sign);
            } elseif ($type === 'transfer' && $fromId && $toId) {
                // Sale de origen → entra en destino.
                self::adjustBalance($pdo, $fromId, -$amount);
                self::adjustBalance($pdo, $toId, $amount);
            }

            $pdo->commit();
            AuditService::log('create', 'transaction', $id, 'Movimiento ' . $type . ': ' . $amount);
            if ($type === 'transfer') {
                flash('success', 'Transferencia OK: se restó de la cuenta origen y se sumó en la destino.');
            } else {
                flash('success', 'Movimiento registrado.');
            }
            redirect('/movimientos');
        } catch (Throwable $e) {
            $pdo->rollBack();
            flash('error', 'No se pudo guardar: ' . $e->getMessage());
            redirect('/movimientos/nuevo');
        }
    }

    private static function assertAccount(PDO $pdo, int $accountId): void
    {
        $stmt = $pdo->prepare("SELECT id, status FROM accounts WHERE id = ? LIMIT 1");
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
