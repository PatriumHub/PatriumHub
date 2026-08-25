<?php

declare(strict_types=1);

final class BudgetController
{
    public static function index(): void
    {
        Auth::requireAdminArea();
        $period = BudgetService::normalizePeriod((string) ($_GET['period'] ?? date('Y-m')));
        $entityId = request_int('entity_id');
        $currency = $_GET['currency'] ?? 'ARS';

        BudgetService::ensurePeriod($period);
        $items = BudgetService::itemsForPeriod($period, $entityId);
        $totals = BudgetService::periodTotals($period, $currency, $entityId);

        view('budgets/index', [
            'title' => 'Presupuestos',
            'period' => $period,
            'entityId' => $entityId,
            'currency' => $currency,
            'items' => $items,
            'totals' => $totals,
            'entities' => Catalog::entities(),
            'currencies' => Catalog::currencies(),
            'accounts' => Database::pdo()->query(
                "SELECT a.id, a.name, a.entity_id, a.currency_code, e.name AS entity_name
                 FROM accounts a
                 INNER JOIN entities e ON e.id = a.entity_id
                 WHERE a.status = 'active'
                 ORDER BY e.name, a.name"
            )->fetchAll(),
            'success' => flash('success'),
            'error' => flash('error'),
        ]);
    }

    public static function templates(): void
    {
        Auth::requireAdminArea();
        $rows = BudgetService::templates(null);
        $grouped = [];
        foreach ($rows as $row) {
            $eid = (int) $row['entity_id'];
            if (!isset($grouped[$eid])) {
                $grouped[$eid] = [
                    'entity_id' => $eid,
                    'entity_name' => (string) ($row['entity_name'] ?? ''),
                    'entity_type' => (string) ($row['entity_type'] ?? 'company'),
                    'items' => [],
                    'total' => 0.0,
                ];
            }
            $grouped[$eid]['items'][] = $row;
            $grouped[$eid]['total'] += (float) ($row['amount'] ?? 0);
        }

        view('budgets/templates', [
            'title' => 'Gastos agrupados',
            'groups' => array_values($grouped),
            'success' => flash('success'),
            'error' => flash('error'),
        ]);
    }

    public static function createTemplate(): void
    {
        Auth::requireAdminArea();
        view('budgets/template_form', [
            'title' => 'Nuevo gasto',
            'item' => [
                'entity_id' => request_int('entity_id'),
                'currency_code' => 'ARS',
                'amount' => 0,
                'due_day' => 10,
                'frequency' => 'monthly',
                'is_active' => 1,
                'include_in_net_worth' => 1,
            ],
            'entities' => Catalog::entities(),
            'accounts' => Database::pdo()->query(
                "SELECT a.id, a.name, a.entity_id, e.name AS entity_name
                 FROM accounts a
                 INNER JOIN entities e ON e.id = a.entity_id
                 WHERE a.status = 'active'
                 ORDER BY e.name, a.name"
            )->fetchAll(),
            'currencies' => Catalog::currencies(),
            'categories' => Catalog::transactionCategories(),
            'error' => flash('error'),
        ]);
    }

    public static function storeTemplate(): void
    {
        Auth::requireAdminArea();
        require_csrf();
        $payload = self::templatePayload();
        if ($payload === null) {
            flash('error', 'Entidad y nombre son obligatorios. El monto puede ser 0.');
            redirect('/presupuestos/plantillas/nueva');
        }

        Database::pdo()->prepare(
            'INSERT INTO budget_templates
             (entity_id, name, amount, currency_code, frequency, due_day, category_id, suggested_account_id,
              is_active, include_in_net_worth, notes)
             VALUES
             (:entity_id, :name, :amount, :currency_code, :frequency, :due_day, :category_id, :suggested_account_id,
              :is_active, :include_in_net_worth, :notes)'
        )->execute($payload);
        $id = (int) Database::pdo()->lastInsertId();

        $tpl = BudgetService::findTemplate($id);
        if ($tpl) {
            BudgetService::ensureItemForTemplate($tpl, date('Y-m'));
        }

        AuditService::log('create', 'budget_template', $id, 'Alta gasto: ' . $payload['name']);
        flash('success', 'Gasto creado. Ya figura en este mes.');
        redirect('/presupuestos');
    }

    public static function editTemplate(string $id): void
    {
        Auth::requireAdminArea();
        $item = BudgetService::findTemplate((int) $id);
        if (!$item) {
            flash('error', 'Gasto no encontrado.');
            redirect('/presupuestos/plantillas');
        }
        view('budgets/template_form', [
            'title' => 'Editar gasto',
            'item' => $item,
            'entities' => Catalog::entities(),
            'accounts' => Database::pdo()->query(
                "SELECT a.id, a.name, a.entity_id, e.name AS entity_name
                 FROM accounts a
                 INNER JOIN entities e ON e.id = a.entity_id
                 WHERE a.status = 'active'
                 ORDER BY e.name, a.name"
            )->fetchAll(),
            'currencies' => Catalog::currencies(),
            'categories' => Catalog::transactionCategories(),
            'error' => flash('error'),
        ]);
    }

    public static function updateTemplate(string $id): void
    {
        Auth::requireAdminArea();
        require_csrf();
        $tplId = (int) $id;
        $before = BudgetService::findTemplate($tplId);
        if (!$before) {
            flash('error', 'Gasto no encontrado.');
            redirect('/presupuestos/plantillas');
        }
        $payload = self::templatePayload();
        if ($payload === null) {
            flash('error', 'Entidad y nombre son obligatorios. El monto puede ser 0.');
            redirect('/presupuestos/plantillas/' . $tplId . '/editar');
        }
        $payload['id'] = $tplId;
        Database::pdo()->prepare(
            'UPDATE budget_templates
             SET entity_id = :entity_id, name = :name, amount = :amount, currency_code = :currency_code,
                 frequency = :frequency, due_day = :due_day, category_id = :category_id,
                 suggested_account_id = :suggested_account_id,
                 is_active = :is_active, include_in_net_worth = :include_in_net_worth, notes = :notes
             WHERE id = :id'
        )->execute($payload);

        // Propaga categoría a ítems pendientes (los ya pagados no se tocan).
        Database::pdo()->prepare(
            "UPDATE budget_items
             SET category_id = :c, updated_at = CURRENT_TIMESTAMP
             WHERE template_id = :t AND status = 'pending'"
        )->execute([
            'c' => $payload['category_id'],
            't' => $tplId,
        ]);

        AuditService::log('update', 'budget_template', $tplId, 'Edición gasto', $before, BudgetService::findTemplate($tplId));
        flash('success', 'Gasto actualizado. La categoría se aplica a ítems pendientes.');
        redirect('/presupuestos/plantillas');
    }

    public static function payItem(string $id): void
    {
        Auth::requireAdminArea();
        require_csrf();
        $itemId = (int) $id;
        $period = BudgetService::normalizePeriod((string) ($_POST['period'] ?? date('Y-m')));
        $accountId = request_int('account_id');
        $amount = post_float('amount');
        $occurred = post_string('occurred_at') ?: null;
        $note = post_string('description') ?: null;
        $item = BudgetService::findItem($itemId);
        $payAmount = $amount > 0 || (isset($_POST['amount']) && $_POST['amount'] !== '')
            ? abs($amount)
            : (float) ($item['amount'] ?? 0);

        if ($payAmount > 0 && !$accountId) {
            flash('error', 'Elegí la cuenta de pago.');
            redirect('/presupuestos?period=' . urlencode($period));
        }

        try {
            if ($payAmount <= 0) {
                // Monto 0: cierra el ítem sin movimiento.
                BudgetService::payItemZero($itemId, $occurred, $note);
            } else {
                BudgetService::payItem(
                    $itemId,
                    $accountId,
                    $payAmount,
                    $occurred,
                    $note
                );
            }
            flash('success', 'Gasto liquidado' . ($payAmount > 0 ? ': egreso registrado.' : '.'));
        } catch (Throwable $e) {
            flash('error', $e->getMessage());
        }
        redirect('/presupuestos?period=' . urlencode($period));
    }

    public static function skipItem(string $id): void
    {
        Auth::requireAdminArea();
        require_csrf();
        $period = BudgetService::normalizePeriod((string) ($_POST['period'] ?? date('Y-m')));
        try {
            BudgetService::skipItem((int) $id, post_string('description') ?: null);
            flash('success', 'Ítem omitido. Deja de contar como pasivo.');
        } catch (Throwable $e) {
            flash('error', $e->getMessage());
        }
        redirect('/presupuestos?period=' . urlencode($period));
    }

    public static function revertItem(string $id): void
    {
        Auth::requireAdminArea();
        require_csrf();
        $period = BudgetService::normalizePeriod((string) ($_POST['period'] ?? date('Y-m')));
        $entityId = request_int('entity_id');
        try {
            BudgetService::revertItem((int) $id);
            flash('success', 'Pago revertido. El gasto vuelve a pendiente y el egreso se anuló.');
        } catch (Throwable $e) {
            flash('error', $e->getMessage());
        }
        $q = '/presupuestos?period=' . urlencode($period);
        if ($entityId) {
            $q .= '&entity_id=' . $entityId;
        }
        redirect($q);
    }

    public static function deleteItem(string $id): void
    {
        Auth::requireAdminArea();
        require_csrf();
        $period = BudgetService::normalizePeriod((string) ($_POST['period'] ?? date('Y-m')));
        $entityId = request_int('entity_id');
        try {
            BudgetService::deleteItem((int) $id);
            flash('success', 'Gasto eliminado del mes y desactivado (no se vuelve a generar).');
        } catch (Throwable $e) {
            flash('error', $e->getMessage());
        }
        $q = '/presupuestos?period=' . urlencode($period);
        if ($entityId) {
            $q .= '&entity_id=' . $entityId;
        }
        redirect($q);
    }

    public static function destroyTemplate(string $id): void
    {
        Auth::requireAdminArea();
        require_csrf();
        try {
            BudgetService::deleteTemplate((int) $id);
            flash('success', 'Gasto sacado del grupo. Pendientes borrados; pagos ya hechos se mantienen.');
        } catch (Throwable $e) {
            flash('error', $e->getMessage());
        }
        redirect('/presupuestos/plantillas');
    }

    public static function destroyTemplateGroup(string $entityId): void
    {
        Auth::requireAdminArea();
        require_csrf();
        $eid = (int) $entityId;
        try {
            $n = BudgetService::deleteTemplatesForEntity($eid);
            flash(
                'success',
                $n > 0
                    ? ('Grupo eliminado: ' . $n . ' gasto(s). Pendientes borrados; pagos ya hechos se mantienen.')
                    : 'El grupo no tenía gastos activos.'
            );
        } catch (Throwable $e) {
            flash('error', $e->getMessage());
        }
        redirect('/presupuestos/plantillas');
    }

    /** @return array<string,mixed>|null */
    private static function templatePayload(): ?array
    {
        $entityId = request_int('entity_id');
        $name = post_string('name');
        $amount = abs(post_float('amount'));
        if (!$entityId || $name === '' || $amount < 0) {
            return null;
        }
        $dueDay = (int) post_float('due_day', 1);
        $dueDay = max(1, min(31, $dueDay));
        $accountId = request_int('suggested_account_id');
        if ($accountId) {
            $stmt = Database::pdo()->prepare('SELECT entity_id FROM accounts WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $accountId]);
            if ((int) $stmt->fetchColumn() !== $entityId) {
                $accountId = null;
            }
        }

        $categoryId = request_int('category_id');
        if ($categoryId) {
            Catalog::ensureTransactionCategories();
            $chk = Database::pdo()->prepare('SELECT id FROM transaction_categories WHERE id = :id LIMIT 1');
            $chk->execute(['id' => $categoryId]);
            if (!$chk->fetchColumn()) {
                $categoryId = null;
            }
        }

        return [
            'entity_id' => $entityId,
            'name' => $name,
            'amount' => round($amount, 2),
            'currency_code' => post_string('currency_code', 'ARS') ?: 'ARS',
            'frequency' => 'monthly',
            'due_day' => $dueDay,
            'category_id' => $categoryId,
            'suggested_account_id' => $accountId,
            'is_active' => bool_post('is_active') ? 1 : 0,
            'include_in_net_worth' => bool_post('include_in_net_worth') ? 1 : 0,
            'notes' => post_string('notes') ?: null,
        ];
    }
}
