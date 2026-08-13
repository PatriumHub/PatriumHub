<?php

declare(strict_types=1);

final class ReceivableController
{
    public static function index(): void
    {
        Auth::requireAdminArea();
        ReceivableService::ensurePaidStatus();
        $rows = Database::pdo()->query(
            "SELECT r.*, e.name AS entity_name
             FROM receivables r
             INNER JOIN entities e ON e.id = r.entity_id
             ORDER BY FIELD(r.status,'overdue','current','paid','uncollectible','cancelled'),
                      r.due_date IS NULL, r.due_date"
        )->fetchAll();
        view('receivables/index', [
            'title' => 'Cuentas por cobrar',
            'rows' => $rows,
            'statusLabels' => ReceivableService::statusLabels(),
            'success' => flash('success'),
            'error' => flash('error'),
        ]);
    }

    public static function create(): void
    {
        Auth::requireAdminArea();
        ReceivableService::ensurePaidStatus();
        $entityId = request_int('entity_id');
        view('receivables/form', [
            'title' => 'Nueva cuenta por cobrar',
            'item' => [
                'entity_id' => $entityId,
                'currency_code' => 'ARS',
                'status' => 'current',
                'include_in_net_worth' => 1,
                'original_amount' => 0,
                'outstanding_amount' => 0,
            ],
            'entities' => Catalog::entities(),
            'currencies' => Catalog::currencies(),
            'accounts' => $entityId ? ReceivableService::accountsForEntity($entityId) : [],
            'statusLabels' => ReceivableService::statusLabels(),
            'error' => flash('error'),
        ]);
    }

    public static function store(): void
    {
        Auth::requireAdminArea();
        require_csrf();
        ReceivableService::ensurePaidStatus();
        $entityId = request_int('entity_id');
        $debtor = post_string('debtor_name');
        $debtorEntityId = request_int('debtor_entity_id');
        if ($debtor === '' && $debtorEntityId) {
            $stmt = Database::pdo()->prepare('SELECT name FROM entities WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $debtorEntityId]);
            $debtor = (string) ($stmt->fetchColumn() ?: '');
        }
        if (!$entityId || $debtor === '') {
            flash('error', 'Entidad acreedora y deudor son obligatorios.');
            redirect('/cobrables/nueva');
        }
        $outstanding = post_float('outstanding_amount');
        $originalPosted = post_string('original_amount');
        $original = ($originalPosted === null || $originalPosted === '')
            ? $outstanding
            : post_float('original_amount', $outstanding);
        $status = post_string('status', 'current');
        if ($status === 'paid') {
            flash('error', 'Para marcar como pago, guardá el cobrable y usá “Cobrar / marcar pago”.');
            redirect('/cobrables/nueva');
        }
        Database::pdo()->prepare(
            'INSERT INTO receivables
             (entity_id, debtor_name, debtor_entity_id, currency_code, original_amount, outstanding_amount,
              due_date, status, collection_probability, include_in_net_worth, notes)
             VALUES
             (:entity_id, :debtor_name, :debtor_entity_id, :currency_code, :original_amount, :outstanding_amount,
              :due_date, :status, :collection_probability, :include_in_net_worth, :notes)'
        )->execute([
            'entity_id' => $entityId,
            'debtor_name' => $debtor,
            'debtor_entity_id' => $debtorEntityId,
            'currency_code' => post_string('currency_code', 'ARS'),
            'original_amount' => $original,
            'outstanding_amount' => $outstanding,
            'due_date' => post_string('due_date') ?: null,
            'status' => $status,
            'collection_probability' => null,
            'include_in_net_worth' => bool_post('include_in_net_worth'),
            'notes' => post_string('notes') ?: null,
        ]);
        $id = (int) Database::pdo()->lastInsertId();
        AuditService::log('create', 'receivable', $id, 'Alta cobrable: ' . $debtor);
        flash('success', 'Cuenta por cobrar creada.');
        redirect('/cobrables');
    }

    public static function edit(string $id): void
    {
        Auth::requireAdminArea();
        ReceivableService::ensurePaidStatus();
        $item = self::find((int) $id);
        if (!$item) {
            flash('error', 'Registro no encontrado.');
            redirect('/cobrables');
        }
        view('receivables/form', [
            'title' => 'Editar cuenta por cobrar',
            'item' => $item,
            'entities' => Catalog::entities(),
            'currencies' => Catalog::currencies(),
            'accounts' => ReceivableService::accountsForEntity(
                (int) $item['entity_id'],
                (string) ($item['currency_code'] ?? 'ARS')
            ),
            'statusLabels' => ReceivableService::statusLabels(),
            'error' => flash('error'),
        ]);
    }

    public static function update(string $id): void
    {
        Auth::requireAdminArea();
        require_csrf();
        ReceivableService::ensurePaidStatus();
        $recvId = (int) $id;
        $before = self::find($recvId);
        if (!$before) {
            flash('error', 'Registro no encontrado.');
            redirect('/cobrables');
        }

        $status = post_string('status', 'current');
        if ($status === 'paid' && ($before['status'] ?? '') !== 'paid') {
            $accountId = request_int('deposit_account_id');
            if (!$accountId) {
                flash('error', 'Elegí a qué cuenta sumar el cobro.');
                redirect('/cobrables/' . $recvId . '/editar');
            }
            $result = ReceivableService::markPaid($recvId, $accountId);
            if (!$result['ok']) {
                flash('error', $result['error'] ?? 'No se pudo marcar como pago.');
                redirect('/cobrables/' . $recvId . '/editar');
            }
            // Still allow updating other fields that might have been posted? markPaid already set status.
            flash('success', 'Marcado como pago y acreditado en la cuenta.');
            redirect('/cobrables');
        }

        $entityId = request_int('entity_id');
        $debtor = post_string('debtor_name');
        if (!$entityId || $debtor === '') {
            flash('error', 'Entidad acreedora y deudor son obligatorios.');
            redirect('/cobrables/' . $recvId . '/editar');
        }
        if ($status === 'paid') {
            // already paid — don't re-credit; just update metadata
            $status = 'paid';
        }
        Database::pdo()->prepare(
            'UPDATE receivables SET entity_id = :entity_id, debtor_name = :debtor_name,
             debtor_entity_id = :debtor_entity_id, currency_code = :currency_code,
             original_amount = :original_amount, outstanding_amount = :outstanding_amount,
             due_date = :due_date, status = :status, collection_probability = :collection_probability,
             include_in_net_worth = :include_in_net_worth, notes = :notes
             WHERE id = :id'
        )->execute([
            'entity_id' => $entityId,
            'debtor_name' => $debtor,
            'debtor_entity_id' => request_int('debtor_entity_id'),
            'currency_code' => post_string('currency_code', 'ARS'),
            'original_amount' => post_float('original_amount', post_float('outstanding_amount')),
            'outstanding_amount' => $status === 'paid' ? 0.0 : post_float('outstanding_amount'),
            'due_date' => post_string('due_date') ?: null,
            'status' => $status,
            'collection_probability' => $before['collection_probability'] ?? null,
            'include_in_net_worth' => bool_post('include_in_net_worth'),
            'notes' => post_string('notes') ?: null,
            'id' => $recvId,
        ]);
        AuditService::log('update', 'receivable', $recvId, 'Edición cobrable: ' . $debtor, $before, self::find($recvId));
        flash('success', 'Cuenta por cobrar actualizada.');
        redirect('/cobrables');
    }

    public static function payForm(string $id): void
    {
        Auth::requireAdminArea();
        ReceivableService::ensurePaidStatus();
        $item = self::find((int) $id);
        if (!$item) {
            flash('error', 'Registro no encontrado.');
            redirect('/cobrables');
        }
        if (($item['status'] ?? '') === 'paid') {
            flash('error', 'Ya está marcado como pago.');
            redirect('/cobrables');
        }
        $entity = Catalog::entity((int) $item['entity_id']);
        view('receivables/pay', [
            'title' => 'Marcar pago',
            'item' => $item,
            'entity' => $entity,
            'accounts' => ReceivableService::accountsForEntity(
                (int) $item['entity_id'],
                (string) ($item['currency_code'] ?? 'ARS')
            ),
            'error' => flash('error'),
        ]);
    }

    public static function pay(string $id): void
    {
        Auth::requireAdminArea();
        require_csrf();
        $recvId = (int) $id;
        $accountId = request_int('deposit_account_id');
        if (!$accountId) {
            flash('error', 'Elegí la cuenta donde sumar el cobro.');
            redirect('/cobrables/' . $recvId . '/pagar');
        }
        $result = ReceivableService::markPaid(
            $recvId,
            $accountId,
            post_string('occurred_at') ?: null
        );
        if (!$result['ok']) {
            flash('error', $result['error'] ?? 'No se pudo registrar el pago.');
            redirect('/cobrables/' . $recvId . '/pagar');
        }
        flash('success', 'Cobro acreditado en la cuenta.');
        $back = post_string('return_to');
        redirect($back !== '' ? $back : '/cobrables');
    }

    private static function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM receivables WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}
