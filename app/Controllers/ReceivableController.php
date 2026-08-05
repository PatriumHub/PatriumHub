<?php

declare(strict_types=1);

final class ReceivableController
{
    public static function index(): void
    {
        Auth::requireLogin();
        $rows = Database::pdo()->query(
            "SELECT r.*, e.name AS entity_name
             FROM receivables r
             INNER JOIN entities e ON e.id = r.entity_id
             ORDER BY r.status, r.due_date IS NULL, r.due_date"
        )->fetchAll();
        view('receivables/index', [
            'title' => 'Cuentas por cobrar',
            'rows' => $rows,
            'success' => flash('success'),
            'error' => flash('error'),
        ]);
    }

    public static function create(): void
    {
        Auth::requireLogin();
        view('receivables/form', [
            'title' => 'Nueva cuenta por cobrar',
            'item' => [
                'entity_id' => request_int('entity_id'),
                'currency_code' => 'ARS',
                'status' => 'current',
                'include_in_net_worth' => 1,
                'original_amount' => 0,
                'outstanding_amount' => 0,
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
            'status' => post_string('status', 'current'),
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
        Auth::requireLogin();
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
            'error' => flash('error'),
        ]);
    }

    public static function update(string $id): void
    {
        Auth::requireLogin();
        require_csrf();
        $recvId = (int) $id;
        $before = self::find($recvId);
        if (!$before) {
            flash('error', 'Registro no encontrado.');
            redirect('/cobrables');
        }
        $entityId = request_int('entity_id');
        $debtor = post_string('debtor_name');
        if (!$entityId || $debtor === '') {
            flash('error', 'Entidad acreedora y deudor son obligatorios.');
            redirect('/cobrables/' . $recvId . '/editar');
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
            'outstanding_amount' => post_float('outstanding_amount'),
            'due_date' => post_string('due_date') ?: null,
            'status' => post_string('status', 'current'),
            'collection_probability' => $before['collection_probability'] ?? null,
            'include_in_net_worth' => bool_post('include_in_net_worth'),
            'notes' => post_string('notes') ?: null,
            'id' => $recvId,
        ]);
        AuditService::log('update', 'receivable', $recvId, 'Edición cobrable: ' . $debtor, $before, self::find($recvId));
        flash('success', 'Cuenta por cobrar actualizada.');
        redirect('/cobrables');
    }

    private static function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM receivables WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}
