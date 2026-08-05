<?php

declare(strict_types=1);

final class LiabilityController
{
    public static function index(): void
    {
        Auth::requireLogin();
        $rows = Database::pdo()->query(
            "SELECT l.*, e.name AS entity_name
             FROM liabilities l
             INNER JOIN entities e ON e.id = l.entity_id
             ORDER BY l.status, l.name"
        )->fetchAll();

        BudgetService::ensurePeriod(date('Y-m'));
        $budgetPending = Database::pdo()->query(
            "SELECT i.*, e.name AS entity_name, e.type AS entity_type
             FROM budget_items i
             INNER JOIN entities e ON e.id = i.entity_id
             WHERE i.status = 'pending' AND i.include_in_net_worth = 1
             ORDER BY e.type, e.name, i.due_date, i.name"
        )->fetchAll();

        $budgetGroups = [];
        foreach ($budgetPending as $row) {
            $eid = (int) $row['entity_id'];
            if (!isset($budgetGroups[$eid])) {
                $budgetGroups[$eid] = [
                    'entity_id' => $eid,
                    'entity_name' => (string) ($row['entity_name'] ?? ''),
                    'entity_type' => (string) ($row['entity_type'] ?? 'company'),
                    'items' => [],
                    'total' => 0.0,
                    'currency_code' => (string) ($row['currency_code'] ?? 'ARS'),
                ];
            }
            $budgetGroups[$eid]['items'][] = $row;
            $budgetGroups[$eid]['total'] += (float) ($row['amount'] ?? 0);
        }

        view('liabilities/index', [
            'title' => 'Pasivos',
            'rows' => $rows,
            'budgetGroups' => array_values($budgetGroups),
            'success' => flash('success'),
            'error' => flash('error'),
        ]);
    }

    public static function create(): void
    {
        Auth::requireLogin();
        view('liabilities/form', [
            'title' => 'Nuevo pasivo',
            'item' => [
                'entity_id' => request_int('entity_id'),
                'liability_type' => 'loan',
                'currency_code' => 'ARS',
                'status' => 'open',
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
        $name = post_string('name');
        if (!$entityId || $name === '') {
            flash('error', 'Entidad y nombre son obligatorios.');
            redirect('/pasivos/nuevo');
        }
        $amount = post_float('outstanding_amount', post_float('original_amount'));
        Database::pdo()->prepare(
            'INSERT INTO liabilities
             (entity_id, name, liability_type, creditor_name, creditor_entity_id, currency_code,
              original_amount, outstanding_amount, due_date, status, include_in_net_worth, notes)
             VALUES
             (:entity_id, :name, :liability_type, :creditor_name, :creditor_entity_id, :currency_code,
              :original_amount, :outstanding_amount, :due_date, :status, :include_in_net_worth, :notes)'
        )->execute(self::payload($entityId, $name, $amount));
        $id = (int) Database::pdo()->lastInsertId();
        AuditService::log('create', 'liability', $id, 'Alta pasivo: ' . $name);
        flash('success', 'Pasivo creado.');
        redirect('/pasivos');
    }

    public static function edit(string $id): void
    {
        Auth::requireLogin();
        $item = self::find((int) $id);
        if (!$item) {
            flash('error', 'Pasivo no encontrado.');
            redirect('/pasivos');
        }
        view('liabilities/form', [
            'title' => 'Editar pasivo',
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
        $liabId = (int) $id;
        $before = self::find($liabId);
        if (!$before) {
            flash('error', 'Pasivo no encontrado.');
            redirect('/pasivos');
        }
        $entityId = request_int('entity_id');
        $name = post_string('name');
        if (!$entityId || $name === '') {
            flash('error', 'Entidad y nombre son obligatorios.');
            redirect('/pasivos/' . $liabId . '/editar');
        }
        $payload = self::payload($entityId, $name, post_float('original_amount'));
        $payload['id'] = $liabId;
        Database::pdo()->prepare(
            'UPDATE liabilities SET entity_id = :entity_id, name = :name, liability_type = :liability_type,
             creditor_name = :creditor_name, creditor_entity_id = :creditor_entity_id,
             currency_code = :currency_code, original_amount = :original_amount,
             outstanding_amount = :outstanding_amount, due_date = :due_date, status = :status,
             include_in_net_worth = :include_in_net_worth, notes = :notes
             WHERE id = :id'
        )->execute($payload);
        AuditService::log('update', 'liability', $liabId, 'Edición pasivo: ' . $name, $before, self::find($liabId));
        flash('success', 'Pasivo actualizado.');
        redirect('/pasivos');
    }

    private static function payload(int $entityId, string $name, float $original): array
    {
        $outstanding = post_float('outstanding_amount', $original);
        $originalPosted = post_string('original_amount');
        $originalAmount = ($originalPosted === null || $originalPosted === '')
            ? $outstanding
            : post_float('original_amount', $outstanding);

        $creditorEntityId = request_int('creditor_entity_id');
        $creditorName = post_string('creditor_name');
        if ($creditorName === '' && $creditorEntityId) {
            $stmt = Database::pdo()->prepare('SELECT name FROM entities WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $creditorEntityId]);
            $creditorName = (string) ($stmt->fetchColumn() ?: '');
        }

        return [
            'entity_id' => $entityId,
            'name' => $name,
            'liability_type' => post_string('liability_type', 'other'),
            'creditor_name' => $creditorName !== '' ? $creditorName : null,
            'creditor_entity_id' => $creditorEntityId,
            'currency_code' => post_string('currency_code', 'ARS'),
            'original_amount' => $originalAmount,
            'outstanding_amount' => $outstanding,
            'due_date' => post_string('due_date') ?: null,
            'status' => post_string('status', 'open'),
            'include_in_net_worth' => bool_post('include_in_net_worth'),
            'notes' => post_string('notes') ?: null,
        ];
    }

    private static function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM liabilities WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}
