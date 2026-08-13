<?php

declare(strict_types=1);

/**
 * Cobros de cuentas por cobrar → suma a una cuenta de la entidad acreedora.
 */
final class ReceivableService
{
    public static function ensurePaidStatus(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        try {
            Database::pdo()->exec(
                "ALTER TABLE receivables
                 MODIFY COLUMN status ENUM('current','overdue','uncollectible','cancelled','paid')
                 NOT NULL DEFAULT 'current'"
            );
        } catch (Throwable) {
            // Already migrated or unsupported — ignore.
        }
    }

    /** @return list<array<string,mixed>> */
    public static function accountsForEntity(int $entityId, ?string $currency = null): array
    {
        $sql = "SELECT id, name, currency_code, balance, type
                FROM accounts
                WHERE entity_id = :e AND status = 'active'";
        $params = ['e' => $entityId];
        if ($currency) {
            $sql .= ' AND currency_code = :c';
            $params['c'] = $currency;
        }
        $sql .= ' ORDER BY name';
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Marca cobrable como pagado y acredita la cuenta.
     * @return array{ok:bool,error?:string}
     */
    public static function markPaid(int $receivableId, int $accountId, ?string $occurredAt = null): array
    {
        self::ensurePaidStatus();
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT * FROM receivables WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $receivableId]);
        $recv = $stmt->fetch();
        if (!$recv) {
            return ['ok' => false, 'error' => 'Cobrable no encontrado.'];
        }
        if (($recv['status'] ?? '') === 'paid') {
            return ['ok' => false, 'error' => 'Ya está marcado como pago.'];
        }

        $amount = round((float) ($recv['outstanding_amount'] ?? 0), 2);
        if ($amount <= 0) {
            $amount = round((float) ($recv['original_amount'] ?? 0), 2);
        }
        if ($amount <= 0) {
            return ['ok' => false, 'error' => 'El monto a cobrar es 0.'];
        }

        $accStmt = $pdo->prepare(
            "SELECT * FROM accounts WHERE id = :id AND status = 'active' LIMIT 1"
        );
        $accStmt->execute(['id' => $accountId]);
        $account = $accStmt->fetch();
        if (!$account) {
            return ['ok' => false, 'error' => 'Cuenta inválida o inactiva.'];
        }
        if ((int) $account['entity_id'] !== (int) $recv['entity_id']) {
            return ['ok' => false, 'error' => 'La cuenta debe pertenecer a quien cobra.'];
        }
        if ((string) $account['currency_code'] !== (string) $recv['currency_code']) {
            return ['ok' => false, 'error' => 'La moneda de la cuenta no coincide con el cobrable.'];
        }

        $when = $occurredAt ?: date('Y-m-d H:i:s');
        $when = str_replace('T', ' ', $when);
        if (strlen($when) === 16) {
            $when .= ':00';
        }

        $user = Auth::user();
        $desc = 'Cobro: ' . (string) $recv['debtor_name'];

        $pdo->beginTransaction();
        try {
            $pdo->prepare(
                'INSERT INTO transactions
                 (entity_id, account_id, type, occurred_at, currency_code, amount, description,
                  origin, metadata_json, created_by)
                 VALUES
                 (:entity_id, :account_id, \'collection\', :occurred_at, :currency_code, :amount, :description,
                  \'manual\', :metadata_json, :created_by)'
            )->execute([
                'entity_id' => (int) $recv['entity_id'],
                'account_id' => $accountId,
                'occurred_at' => $when,
                'currency_code' => (string) $recv['currency_code'],
                'amount' => $amount,
                'description' => mb_substr($desc, 0, 255),
                'metadata_json' => json_encode([
                    'receivable_id' => $receivableId,
                    'debtor_name' => $recv['debtor_name'],
                ], JSON_UNESCAPED_UNICODE),
                'created_by' => $user['id'] ?? null,
            ]);
            $txId = (int) $pdo->lastInsertId();

            $pdo->prepare(
                'UPDATE accounts
                 SET balance = ROUND(balance + :a, 2), updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id'
            )->execute(['a' => $amount, 'id' => $accountId]);

            $balStmt = $pdo->prepare('SELECT balance, balance_held FROM accounts WHERE id = ?');
            $balStmt->execute([$accountId]);
            $bal = $balStmt->fetch();
            if ($bal) {
                $pdo->prepare(
                    'INSERT INTO account_balances (account_id, captured_at, balance, balance_held, source)
                     VALUES (?, NOW(), ?, ?, \'system\')'
                )->execute([
                    $accountId,
                    $bal['balance'],
                    $bal['balance_held'],
                ]);
            }

            $pdo->prepare(
                "UPDATE receivables
                 SET status = 'paid',
                     outstanding_amount = 0,
                     notes = CASE
                       WHEN notes IS NULL OR notes = '' THEN :note
                       ELSE CONCAT(notes, '\n', :note2)
                     END
                 WHERE id = :id"
            )->execute([
                'note' => 'Pagado → cuenta #' . $accountId . ' (' . $account['name'] . ')',
                'note2' => 'Pagado → cuenta #' . $accountId . ' (' . $account['name'] . ')',
                'id' => $receivableId,
            ]);

            $pdo->commit();
            AuditService::log(
                'update',
                'receivable',
                $receivableId,
                'Cobro pagado a cuenta #' . $accountId . ' · tx #' . $txId,
                $recv,
                null
            );
            return ['ok' => true];
        } catch (Throwable $e) {
            $pdo->rollBack();
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /** @return array<string,string> */
    public static function statusLabels(): array
    {
        return [
            'current' => 'Al día',
            'overdue' => 'Vencido',
            'paid' => 'Pago',
            'uncollectible' => 'Incobrable',
            'cancelled' => 'Cancelado',
        ];
    }
}
