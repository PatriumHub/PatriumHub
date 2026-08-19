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

        $currency = preferred_list_currency($rows, 'outstanding_amount', 'ARS');
        $statusLabels = ReceivableService::statusLabels();

        $pending = 0.0;
        $overdue = 0.0;
        $current = 0.0;
        $openCount = 0;
        $overdueCount = 0;
        $byStatus = [];
        $byEntity = [];
        foreach ($rows as $row) {
            $st = (string) ($row['status'] ?? 'current');
            $rowCurrency = strtoupper(trim((string) ($row['currency_code'] ?? '')));
            if ($rowCurrency !== $currency) {
                continue;
            }
            $amt = (float) ($row['outstanding_amount'] ?? 0);
            $stLabel = $statusLabels[$st] ?? $st;
            $byStatus[$stLabel] = ($byStatus[$stLabel] ?? 0.0) + $amt;
            if ($st === 'overdue' || $st === 'current') {
                $pending += $amt;
                $openCount++;
                $ename = (string) ($row['entity_name'] ?? 'Sin entidad');
                $byEntity[$ename] = ($byEntity[$ename] ?? 0.0) + $amt;
            }
            if ($st === 'overdue') {
                $overdue += $amt;
                $overdueCount++;
            } elseif ($st === 'current') {
                $current += $amt;
            }
        }
        arsort($byStatus);
        arsort($byEntity);

        view('receivables/index', [
            'title' => 'Cuentas por cobrar',
            'rows' => $rows,
            'currency' => $currency,
            'currencies' => Catalog::currencies(),
            'statusLabels' => $statusLabels,
            'totals' => [
                'pending' => $pending,
                'overdue' => $overdue,
                'current' => $current,
                'open_count' => $openCount,
                'overdue_count' => $overdueCount,
                'count' => count($rows),
            ],
            'charts' => [
                'byType' => [
                    'labels' => array_keys($byStatus),
                    'values' => array_values($byStatus),
                ],
                'byEntity' => [
                    'labels' => array_keys($byEntity),
                    'values' => array_values($byEntity),
                ],
            ],
            'success' => flash('success'),
            'error' => flash('error'),
        ]);
    }

    public static function create(): void
    {
        Auth::requireAdminArea();
        ReceivableService::ensurePaidStatus();
        $entityId = request_int('entity_id');
        $returnTo = self::requestReturnTo();
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
            'returnTo' => $returnTo,
            'error' => flash('error'),
        ]);
    }

    public static function store(): void
    {
        Auth::requireAdminArea();
        require_csrf();
        ReceivableService::ensurePaidStatus();
        $returnTo = self::postedReturnTo();
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
            redirect(self::pathWithReturn('/cobrables/nueva' . ($entityId ? '?entity_id=' . $entityId : ''), $returnTo));
        }
        $outstanding = post_float('outstanding_amount');
        $originalPosted = post_string('original_amount');
        $original = ($originalPosted === null || $originalPosted === '')
            ? $outstanding
            : post_float('original_amount', $outstanding);
        $status = post_string('status', 'current');
        if ($status === 'paid') {
            flash('error', 'Para marcar como pago, guardá el cobrable y usá “Cobrar / marcar pago”.');
            redirect(self::pathWithReturn('/cobrables/nueva' . ($entityId ? '?entity_id=' . $entityId : ''), $returnTo));
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
        redirect($returnTo !== '' ? $returnTo : '/cobrables');
    }

    public static function edit(string $id): void
    {
        Auth::requireAdminArea();
        ReceivableService::ensurePaidStatus();
        $item = self::find((int) $id);
        $returnTo = self::requestReturnTo();
        if (!$item) {
            flash('error', 'Registro no encontrado.');
            redirect($returnTo !== '' ? $returnTo : '/cobrables');
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
            'returnTo' => $returnTo,
            'error' => flash('error'),
        ]);
    }

    public static function update(string $id): void
    {
        Auth::requireAdminArea();
        require_csrf();
        ReceivableService::ensurePaidStatus();
        $recvId = (int) $id;
        $returnTo = self::postedReturnTo();
        $before = self::find($recvId);
        if (!$before) {
            flash('error', 'Registro no encontrado.');
            redirect($returnTo !== '' ? $returnTo : '/cobrables');
        }

        $status = post_string('status', 'current');
        if ($status === 'paid' && ($before['status'] ?? '') !== 'paid') {
            $accountId = request_int('deposit_account_id');
            if (!$accountId) {
                flash('error', 'Elegí a qué cuenta sumar el cobro.');
                redirect(self::pathWithReturn('/cobrables/' . $recvId . '/editar', $returnTo));
            }
            $result = ReceivableService::markPaid($recvId, $accountId);
            if (!$result['ok']) {
                flash('error', $result['error'] ?? 'No se pudo marcar como pago.');
                redirect(self::pathWithReturn('/cobrables/' . $recvId . '/editar', $returnTo));
            }
            flash('success', 'Marcado como pago y acreditado en la cuenta.');
            redirect($returnTo !== '' ? $returnTo : '/cobrables');
        }

        $entityId = request_int('entity_id');
        $debtor = post_string('debtor_name');
        if (!$entityId || $debtor === '') {
            flash('error', 'Entidad acreedora y deudor son obligatorios.');
            redirect(self::pathWithReturn('/cobrables/' . $recvId . '/editar', $returnTo));
        }
        if ($status === 'paid') {
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
        redirect($returnTo !== '' ? $returnTo : '/cobrables');
    }

    public static function payForm(string $id): void
    {
        Auth::requireAdminArea();
        ReceivableService::ensurePaidStatus();
        $item = self::find((int) $id);
        $returnTo = self::requestReturnTo();
        if (!$item) {
            flash('error', 'Registro no encontrado.');
            redirect($returnTo !== '' ? $returnTo : '/cobrables');
        }
        if (($item['status'] ?? '') === 'paid') {
            flash('error', 'Ya está marcado como pago.');
            redirect($returnTo !== '' ? $returnTo : '/cobrables');
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
            'returnTo' => $returnTo,
            'error' => flash('error'),
        ]);
    }

    public static function pay(string $id): void
    {
        Auth::requireAdminArea();
        require_csrf();
        $recvId = (int) $id;
        $returnTo = self::postedReturnTo();
        $accountId = request_int('deposit_account_id');
        if (!$accountId) {
            flash('error', 'Elegí la cuenta donde sumar el cobro.');
            redirect(self::pathWithReturn('/cobrables/' . $recvId . '/pagar', $returnTo));
        }
        $result = ReceivableService::markPaid(
            $recvId,
            $accountId,
            post_string('occurred_at') ?: null
        );
        if (!$result['ok']) {
            flash('error', $result['error'] ?? 'No se pudo registrar el pago.');
            redirect(self::pathWithReturn('/cobrables/' . $recvId . '/pagar', $returnTo));
        }
        flash('success', 'Cobro acreditado en la cuenta.');
        redirect($returnTo !== '' ? $returnTo : '/cobrables');
    }

    private static function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM receivables WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private static function requestReturnTo(): string
    {
        return safe_return_path((string) ($_GET['return_to'] ?? ''), '');
    }

    private static function postedReturnTo(): string
    {
        return safe_return_path(post_string('return_to'), '');
    }

    private static function pathWithReturn(string $path, string $returnTo): string
    {
        if ($returnTo === '') {
            return $path;
        }
        $sep = str_contains($path, '?') ? '&' : '?';
        return $path . $sep . 'return_to=' . rawurlencode($returnTo);
    }
}
