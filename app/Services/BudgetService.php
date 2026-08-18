<?php

declare(strict_types=1);

/**
 * Presupuestos / costos fijos mensuales.
 * Plantillas → ítems por período (pending del mes actual/pasados cuentan como pasivo).
 */
final class BudgetService
{
    /** Asegura ítems del período para todas las plantillas activas. */
    public static function ensurePeriod(?string $periodYm = null): int
    {
        $periodYm = self::normalizePeriod($periodYm ?? date('Y-m'));
        $pdo = Database::pdo();
        $templates = $pdo->query(
            'SELECT * FROM budget_templates WHERE is_active = 1 ORDER BY id'
        )->fetchAll();
        $created = 0;
        foreach ($templates as $t) {
            if (self::ensureItemForTemplate($t, $periodYm)) {
                $created++;
            }
        }
        return $created;
    }

    /** @param array<string,mixed> $template */
    public static function ensureItemForTemplate(array $template, string $periodYm): bool
    {
        $periodYm = self::normalizePeriod($periodYm);
        $pdo = Database::pdo();
        $check = $pdo->prepare(
            'SELECT id FROM budget_items WHERE template_id = :t AND period_ym = :p LIMIT 1'
        );
        $check->execute(['t' => (int) $template['id'], 'p' => $periodYm]);
        if ($check->fetchColumn()) {
            return false;
        }

        $dueDay = max(1, min(31, (int) ($template['due_day'] ?? 1)));
        $dueDate = self::dueDateForPeriod($periodYm, $dueDay);

        $pdo->prepare(
            'INSERT INTO budget_items
             (template_id, entity_id, period_ym, name, amount, currency_code, due_date,
              status, include_in_net_worth, account_id, notes)
             VALUES
             (:template_id, :entity_id, :period_ym, :name, :amount, :currency_code, :due_date,
              \'pending\', :include_in_net_worth, :account_id, :notes)'
        )->execute([
            'template_id' => (int) $template['id'],
            'entity_id' => (int) $template['entity_id'],
            'period_ym' => $periodYm,
            'name' => (string) $template['name'],
            'amount' => round((float) $template['amount'], 2),
            'currency_code' => (string) ($template['currency_code'] ?? 'ARS'),
            'due_date' => $dueDate,
            'include_in_net_worth' => (int) ($template['include_in_net_worth'] ?? 1),
            'account_id' => $template['suggested_account_id'] !== null
                ? (int) $template['suggested_account_id']
                : null,
            'notes' => $template['notes'] ?? null,
        ]);
        return true;
    }

    public static function normalizePeriod(string $periodYm): string
    {
        if (preg_match('/^\d{4}-\d{2}$/', $periodYm)) {
            return $periodYm;
        }
        return date('Y-m');
    }

    public static function dueDateForPeriod(string $periodYm, int $dueDay): string
    {
        [$y, $m] = array_map('intval', explode('-', $periodYm));
        $last = (int) date('t', mktime(0, 0, 0, $m, 1, $y));
        $day = max(1, min($dueDay, $last));
        return sprintf('%04d-%02d-%02d', $y, $m, $day);
    }

    /** @return list<array<string,mixed>> */
    public static function templates(?int $entityId = null): array
    {
        $sql = "SELECT t.*, e.name AS entity_name, e.type AS entity_type, a.name AS account_name
                FROM budget_templates t
                INNER JOIN entities e ON e.id = t.entity_id
                LEFT JOIN accounts a ON a.id = t.suggested_account_id
                WHERE t.is_active = 1";
        $params = [];
        if ($entityId) {
            $sql .= ' AND t.entity_id = :e';
            $params['e'] = $entityId;
        }
        $sql .= ' ORDER BY e.type, e.name, t.name';
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function findTemplate(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM budget_templates WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** @return list<array<string,mixed>> */
    public static function itemsForPeriod(string $periodYm, ?int $entityId = null): array
    {
        $periodYm = self::normalizePeriod($periodYm);
        self::ensurePeriod($periodYm);

        $sql = "SELECT i.*, e.name AS entity_name, a.name AS account_name,
                       tx.id AS linked_tx_id
                FROM budget_items i
                INNER JOIN entities e ON e.id = i.entity_id
                LEFT JOIN accounts a ON a.id = i.account_id
                LEFT JOIN transactions tx ON tx.id = i.transaction_id
                WHERE i.period_ym = :p";
        $params = ['p' => $periodYm];
        if ($entityId) {
            $sql .= ' AND i.entity_id = :e';
            $params['e'] = $entityId;
        }
        $sql .= " ORDER BY FIELD(i.status, 'pending','paid','skipped'), i.due_date, e.name, i.name";
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function findItem(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM budget_items WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * @return array{pending:float,paid:float,skipped:float,count_pending:int}
     */
    public static function periodTotals(string $periodYm, string $currency, ?int $entityId = null): array
    {
        $periodYm = self::normalizePeriod($periodYm);
        $sql = "SELECT
                    COALESCE(SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END),0) AS pending,
                    COALESCE(SUM(CASE WHEN status = 'paid' THEN COALESCE(paid_amount, amount) ELSE 0 END),0) AS paid,
                    COALESCE(SUM(CASE WHEN status = 'skipped' THEN amount ELSE 0 END),0) AS skipped,
                    COALESCE(SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END),0) AS count_pending
                FROM budget_items
                WHERE period_ym = :p AND currency_code = :c";
        $params = ['p' => $periodYm, 'c' => $currency];
        if ($entityId) {
            $sql .= ' AND entity_id = :e';
            $params['e'] = $entityId;
        }
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch() ?: [];
        return [
            'pending' => (float) ($row['pending'] ?? 0),
            'paid' => (float) ($row['paid'] ?? 0),
            'skipped' => (float) ($row['skipped'] ?? 0),
            'count_pending' => (int) ($row['count_pending'] ?? 0),
        ];
    }

    /**
     * Liquida un ítem pendiente: crea egreso y deja de contar como pasivo.
     * @return int transaction id
     */
    public static function payItem(
        int $itemId,
        int $accountId,
        ?float $amount = null,
        ?string $occurredAt = null,
        ?string $note = null
    ): int {
        $pdo = Database::pdo();
        $item = self::findItem($itemId);
        if (!$item) {
            throw new RuntimeException('Ítem de presupuesto no encontrado.');
        }
        if (($item['status'] ?? '') !== 'pending') {
            throw new RuntimeException('El ítem ya no está pendiente.');
        }

        $payAmount = round($amount !== null ? abs($amount) : (float) $item['amount'], 2);
        if ($payAmount < 0) {
            throw new RuntimeException('Importe inválido.');
        }

        $acc = $pdo->prepare(
            "SELECT id, entity_id, currency_code, status FROM accounts WHERE id = :id LIMIT 1"
        );
        $acc->execute(['id' => $accountId]);
        $account = $acc->fetch();
        if (!$account || ($account['status'] ?? '') !== 'active') {
            throw new RuntimeException('Cuenta inválida o inactiva.');
        }
        if ((int) $account['entity_id'] !== (int) $item['entity_id']) {
            throw new RuntimeException('La cuenta debe pertenecer a la misma entidad del presupuesto.');
        }

        $occurred = $occurredAt ?: date('Y-m-d H:i:s');
        $occurred = str_replace('T', ' ', $occurred);
        if (strlen($occurred) === 16) {
            $occurred .= ':00';
        }

        $description = 'Presupuesto: ' . $item['name'];
        if ($note !== null && trim($note) !== '') {
            $description .= ' · ' . trim($note);
        }

        $user = Auth::user();
        $pdo->beginTransaction();
        try {
            $txId = null;
            if ($payAmount > 0) {
                $pdo->prepare(
                    'INSERT INTO transactions
                     (entity_id, account_id, type, occurred_at, currency_code, amount, description,
                      origin, metadata_json, created_by)
                     VALUES
                     (:entity_id, :account_id, \'expense\', :occurred_at, :currency_code, :amount, :description,
                      \'manual\', :metadata_json, :created_by)'
                )->execute([
                    'entity_id' => (int) $item['entity_id'],
                    'account_id' => $accountId,
                    'occurred_at' => $occurred,
                    'currency_code' => (string) $item['currency_code'],
                    'amount' => $payAmount,
                    'description' => mb_substr($description, 0, 255),
                    'metadata_json' => json_encode([
                        'budget_item_id' => $itemId,
                        'budget_template_id' => (int) $item['template_id'],
                        'period_ym' => $item['period_ym'],
                    ], JSON_UNESCAPED_UNICODE),
                    'created_by' => $user['id'] ?? null,
                ]);
                $txId = (int) $pdo->lastInsertId();

                $pdo->prepare(
                    'UPDATE accounts SET balance = balance - :a, updated_at = CURRENT_TIMESTAMP WHERE id = :id'
                )->execute(['a' => $payAmount, 'id' => $accountId]);
            }

            $pdo->prepare(
                "UPDATE budget_items
                 SET status = 'paid', account_id = :acc, transaction_id = :tx,
                     paid_amount = :paid, paid_at = :paid_at, updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id AND status = 'pending'"
            )->execute([
                'acc' => $accountId,
                'tx' => $txId,
                'paid' => $payAmount,
                'paid_at' => $occurred,
                'id' => $itemId,
            ]);

            $pdo->commit();
            AuditService::log('update', 'budget_item', $itemId, 'Pagado: ' . $payAmount, $item, self::findItem($itemId));
            return $txId ?? 0;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Cierra un ítem en 0 sin crear movimiento.
     */
    public static function payItemZero(int $itemId, ?string $occurredAt = null, ?string $note = null): void
    {
        $item = self::findItem($itemId);
        if (!$item) {
            throw new RuntimeException('Ítem de presupuesto no encontrado.');
        }
        if (($item['status'] ?? '') !== 'pending') {
            throw new RuntimeException('El ítem ya no está pendiente.');
        }

        $occurred = $occurredAt ?: date('Y-m-d H:i:s');
        $occurred = str_replace('T', ' ', $occurred);
        if (strlen($occurred) === 16) {
            $occurred .= ':00';
        }

        $notes = (string) ($item['notes'] ?? '');
        if ($note !== null && trim($note) !== '') {
            $notes = trim($notes . "\n" . trim($note));
        }

        Database::pdo()->prepare(
            "UPDATE budget_items
             SET status = 'paid', paid_amount = 0, paid_at = :paid_at, notes = :n,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id AND status = 'pending'"
        )->execute([
            'paid_at' => $occurred,
            'n' => $notes !== '' ? $notes : null,
            'id' => $itemId,
        ]);

        AuditService::log('update', 'budget_item', $itemId, 'Pagado en 0', $item, self::findItem($itemId));
    }

    public static function skipItem(int $itemId, ?string $note = null): void
    {
        $item = self::findItem($itemId);
        if (!$item) {
            throw new RuntimeException('Ítem de presupuesto no encontrado.');
        }
        if (($item['status'] ?? '') !== 'pending') {
            throw new RuntimeException('El ítem ya no está pendiente.');
        }

        $notes = (string) ($item['notes'] ?? '');
        if ($note !== null && trim($note) !== '') {
            $notes = trim($notes . "\nOmitido: " . trim($note));
        }

        Database::pdo()->prepare(
            "UPDATE budget_items
             SET status = 'skipped', notes = :n, updated_at = CURRENT_TIMESTAMP
             WHERE id = :id AND status = 'pending'"
        )->execute(['n' => $notes !== '' ? $notes : null, 'id' => $itemId]);

        AuditService::log('update', 'budget_item', $itemId, 'Omitido', $item, self::findItem($itemId));
    }

    /**
     * Revierte un pago: restaura saldo, borra el egreso y deja el ítem pendiente.
     */
    public static function revertItem(int $itemId): void
    {
        $pdo = Database::pdo();
        $item = self::findItem($itemId);
        if (!$item) {
            throw new RuntimeException('Ítem de presupuesto no encontrado.');
        }
        if (($item['status'] ?? '') !== 'paid') {
            throw new RuntimeException('Solo se puede revertir un gasto pagado.');
        }

        $pdo->beginTransaction();
        try {
            $txId = $item['transaction_id'] !== null ? (int) $item['transaction_id'] : 0;
            if ($txId > 0) {
                $txStmt = $pdo->prepare(
                    'SELECT id, account_id, amount, type FROM transactions WHERE id = :id LIMIT 1'
                );
                $txStmt->execute(['id' => $txId]);
                $tx = $txStmt->fetch();
                if ($tx) {
                    $accountId = (int) ($tx['account_id'] ?? 0);
                    $amount = round((float) ($tx['amount'] ?? 0), 2);
                    if ($accountId > 0 && $amount > 0 && ($tx['type'] ?? '') === 'expense') {
                        $pdo->prepare(
                            'UPDATE accounts SET balance = balance + :a, updated_at = CURRENT_TIMESTAMP WHERE id = :id'
                        )->execute(['a' => $amount, 'id' => $accountId]);
                    }
                    $pdo->prepare('DELETE FROM transactions WHERE id = :id')->execute(['id' => $txId]);
                }
            }

            $pdo->prepare(
                "UPDATE budget_items
                 SET status = 'pending', transaction_id = NULL, paid_amount = NULL, paid_at = NULL,
                     updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id AND status = 'paid'"
            )->execute(['id' => $itemId]);

            $pdo->commit();
            AuditService::log('update', 'budget_item', $itemId, 'Pago revertido', $item, self::findItem($itemId));
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Elimina un ítem del mes. Si estaba pagado, revierte el egreso antes.
     * Desactiva la plantilla para que no se regenere (gasto de una sola vez / sacar del grupo).
     */
    public static function deleteItem(int $itemId): void
    {
        $item = self::findItem($itemId);
        if (!$item) {
            throw new RuntimeException('Ítem de presupuesto no encontrado.');
        }

        if (($item['status'] ?? '') === 'paid') {
            self::revertItem($itemId);
            $item = self::findItem($itemId);
            if (!$item) {
                return;
            }
        }

        $templateId = (int) ($item['template_id'] ?? 0);
        Database::pdo()->prepare('DELETE FROM budget_items WHERE id = :id')->execute(['id' => $itemId]);

        if ($templateId > 0) {
            Database::pdo()->prepare(
                'UPDATE budget_templates SET is_active = 0, updated_at = CURRENT_TIMESTAMP WHERE id = :id'
            )->execute(['id' => $templateId]);
        }

        AuditService::log('delete', 'budget_item', $itemId, 'Eliminado del mes; gasto desactivado', $item, null);
    }

    /**
     * Saca el gasto del grupo: deja de regenerarse y borra pendientes/omitidos.
     * Los pagos ya liquidados se mantienen (historial).
     */
    public static function deleteTemplate(int $templateId): void
    {
        $tpl = self::findTemplate($templateId);
        if (!$tpl) {
            throw new RuntimeException('Gasto no encontrado.');
        }

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $pdo->prepare(
                "DELETE FROM budget_items
                 WHERE template_id = :t AND status IN ('pending', 'skipped')"
            )->execute(['t' => $templateId]);

            $pdo->prepare(
                'UPDATE budget_templates SET is_active = 0, updated_at = CURRENT_TIMESTAMP WHERE id = :id'
            )->execute(['id' => $templateId]);

            // Si no quedan ítems pagados, se puede borrar la plantilla del todo.
            $left = $pdo->prepare('SELECT COUNT(*) FROM budget_items WHERE template_id = :t');
            $left->execute(['t' => $templateId]);
            if ((int) $left->fetchColumn() === 0) {
                $pdo->prepare('DELETE FROM budget_templates WHERE id = :id')->execute(['id' => $templateId]);
            }

            $pdo->commit();
            AuditService::log('delete', 'budget_template', $templateId, 'Gasto eliminado del grupo', $tpl, null);
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
