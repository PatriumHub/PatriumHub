<?php

declare(strict_types=1);

final class PeopleController
{
    public static function index(): void
    {
        Auth::requireLogin();
        $rows = Database::pdo()->query(
            "SELECT e.id, e.name, e.country, e.is_active, e.updated_at,
                    p.first_name, p.last_name, p.document_id
             FROM entities e
             INNER JOIN people p ON p.entity_id = e.id
             WHERE e.type = 'person'
             ORDER BY e.name"
        )->fetchAll();
        $allowed = UserService::allowedEntityIds();
        if ($allowed !== null) {
            $set = array_fill_keys($allowed, true);
            $rows = array_values(array_filter(
                $rows,
                static fn (array $r): bool => isset($set[(int) $r['id']])
            ));
        }

        $active = 0;
        $inactive = 0;
        $withDoc = 0;
        $countries = [];
        $byCountry = [];
        foreach ($rows as $row) {
            if ((int) ($row['is_active'] ?? 0)) {
                $active++;
            } else {
                $inactive++;
            }
            if (trim((string) ($row['document_id'] ?? '')) !== '') {
                $withDoc++;
            }
            $c = trim((string) ($row['country'] ?? ''));
            if ($c === '') {
                $c = 'Sin país';
            } else {
                $countries[$c] = true;
            }
            $byCountry[$c] = ($byCountry[$c] ?? 0) + 1;
        }
        arsort($byCountry);

        view('people/index', [
            'title' => 'Personas',
            'rows' => $rows,
            'canWrite' => Auth::canWrite(),
            'totals' => [
                'count' => count($rows),
                'active' => $active,
                'inactive' => $inactive,
                'with_document' => $withDoc,
                'countries' => count($countries),
            ],
            'charts' => [
                'byType' => [
                    'labels' => ['Activas', 'Inactivas'],
                    'values' => [$active, $inactive],
                ],
                'byEntity' => [
                    'labels' => array_keys($byCountry),
                    'values' => array_values($byCountry),
                ],
            ],
            'success' => flash('success'),
            'error' => flash('error'),
        ]);
    }

    public static function create(): void
    {
        Auth::requireWrite();
        view('people/form', [
            'title' => 'Nueva persona',
            'person' => null,
            'error' => flash('error'),
        ]);
    }

    public static function store(): void
    {
        Auth::requireWrite();
        require_csrf();

        $first = post_string('first_name');
        $last = post_string('last_name');
        $name = trim($first . ' ' . $last);
        if ($first === '') {
            flash('error', 'El nombre es obligatorio.');
            redirect('/personas/nueva');
        }

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO entities (type, name, display_name, country, notes, is_active)
                 VALUES (\'person\', :name, :display_name, :country, :notes, 1)'
            );
            $stmt->execute([
                'name' => $name,
                'display_name' => $name,
                'country' => post_string('country') ?: null,
                'notes' => post_string('notes') ?: null,
            ]);
            $id = (int) $pdo->lastInsertId();

            $pdo->prepare(
                'INSERT INTO people (entity_id, first_name, last_name, document_id, birth_date)
                 VALUES (:id, :first_name, :last_name, :document_id, :birth_date)'
            )->execute([
                'id' => $id,
                'first_name' => $first,
                'last_name' => $last !== '' ? $last : null,
                'document_id' => post_string('document_id') ?: null,
                'birth_date' => null,
            ]);

            $pdo->commit();
            AuditService::log('create', 'person', $id, 'Alta persona: ' . $name);
            flash('success', 'Persona creada.');
            redirect('/personas/' . $id);
        } catch (Throwable $e) {
            $pdo->rollBack();
            flash('error', 'No se pudo guardar: ' . $e->getMessage());
            redirect('/personas/nueva');
        }
    }

    public static function show(string $id): void
    {
        Auth::requireLogin();
        $person = self::find((int) $id);
        if (!$person) {
            flash('error', 'Persona no encontrada.');
            redirect('/personas');
        }
        UserService::requireEntityAccess((int) $person['id']);

        $tab = $_GET['tab'] ?? 'resumen';
        $allowed = [
            'resumen', 'cuentas', 'activos', 'pasivos', 'propiedades',
            'cobrables', 'movimientos', 'participaciones', 'proyecciones',
        ];
        if (!in_array($tab, $allowed, true)) {
            $tab = 'resumen';
        }

        $entityId = (int) $person['id'];
        $currency = $_GET['currency'] ?? 'ARS';
        $summary = PatrimonioService::forEntity($entityId, $currency);

        $personPlan = null;
        $planStats = null;
        $planCompare = null;
        if ($tab === 'resumen' || $tab === 'proyecciones') {
            $personPlan = PersonFinancialPlanService::ensureForPerson($entityId);
            $yearIdx = $tab === 'resumen'
                ? PersonFinancialPlanService::yearIndexForCalendar($personPlan['workbook'])
                : null;
            $planStats = PersonFinancialPlanService::yearStats($personPlan['workbook'], $yearIdx);
            $planCompare = PersonFinancialPlanService::compareToReality($summary, $planStats);
        }

        $data = [
            'title' => $person['name'],
            'person' => $person,
            'tab' => $tab,
            'summary' => $summary,
            'currency' => $currency,
            'currencies' => Catalog::currencies(),
            'ownerships' => self::ownerships($entityId),
            'health' => ValuationService::health($summary, $entityId),
            'canWrite' => Auth::canWrite(),
            'personPlan' => $personPlan,
            'planStats' => $planStats,
            'planCompare' => $planCompare,
            'success' => flash('success'),
            'error' => flash('error'),
        ];

        $data['pagination'] = null;
        $data['tabRows'] = match ($tab) {
            'cuentas' => self::rows(
                "SELECT a.*
                 FROM accounts a
                 WHERE a.status = 'active'
                   AND (
                     a.entity_id = ?
                     OR EXISTS (
                       SELECT 1 FROM account_owners ao
                       WHERE ao.account_id = a.id AND ao.entity_id = ?
                     )
                   )
                 ORDER BY a.name",
                $entityId,
                $entityId
            ),
            'activos' => self::rows(
                'SELECT a.*, ac.name AS account_name
                 FROM assets a
                 LEFT JOIN accounts ac ON ac.id = a.account_id
                 WHERE a.entity_id = ?
                    OR EXISTS (
                      SELECT 1 FROM asset_owners ao
                      WHERE ao.asset_id = a.id AND ao.entity_id = ?
                    )
                 ORDER BY a.name',
                $entityId,
                $entityId
            ),
            'pasivos' => self::rows('SELECT * FROM liabilities WHERE entity_id = ? ORDER BY name', $entityId),
            'propiedades' => self::rows('SELECT * FROM properties WHERE entity_id = ? ORDER BY name', $entityId),
            'cobrables' => self::rows('SELECT * FROM receivables WHERE entity_id = ? ORDER BY id DESC', $entityId),
            'movimientos' => self::pagedMovements($entityId, $data),
            default => [],
        };

        view('people/show', $data);
    }

    public static function edit(string $id): void
    {
        Auth::requireWrite();
        $person = self::find((int) $id);
        if (!$person) {
            flash('error', 'Persona no encontrada.');
            redirect('/personas');
        }
        UserService::requireEntityAccess((int) $person['id']);
        view('people/form', [
            'title' => 'Editar persona',
            'person' => $person,
            'deleteCounts' => EntityService::dependencyCounts((int) $person['id']),
            'error' => flash('error'),
        ]);
    }

    public static function destroy(string $id): void
    {
        Auth::requireWrite();
        require_csrf();
        $entityId = (int) $id;
        $person = self::find($entityId);
        if (!$person) {
            flash('error', 'Persona no encontrada.');
            redirect('/personas');
        }
        UserService::requireEntityAccess($entityId);

        $confirm = trim(post_string('confirm_name'));
        if ($confirm === '' || mb_strtolower($confirm) !== mb_strtolower((string) $person['name'])) {
            flash('error', 'Para eliminar, escribí el nombre exacto de la persona.');
            redirect('/personas/' . $entityId . '/editar');
        }

        $before = $person;
        $result = EntityService::delete($entityId, 'person');
        if (!$result['ok']) {
            flash('error', 'No se pudo eliminar: ' . ($result['error'] ?? 'error desconocido'));
            redirect('/personas/' . $entityId . '/editar');
        }
        AuditService::log('delete', 'person', $entityId, 'Eliminación persona: ' . ($before['name'] ?? ''), $before, null);
        flash('success', 'Persona eliminada.');
        redirect('/personas');
    }

    public static function saveFinancialPlan(string $id): void
    {
        Auth::requireWrite();
        $entityId = (int) $id;
        $person = self::find($entityId);
        if (!$person) {
            self::jsonResponse(['ok' => false, 'error' => 'Persona no encontrada.'], 404);
        }
        UserService::requireEntityAccess($entityId);

        $raw = file_get_contents('php://input');
        $payload = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($payload)) {
            $payload = $_POST;
        }
        $token = $payload['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
        if (!verify_csrf(is_string($token) ? $token : null)) {
            self::jsonResponse(['ok' => false, 'error' => 'CSRF inválido.'], 403);
        }

        $workbook = $payload['workbook'] ?? null;
        if (!is_array($workbook)) {
            self::jsonResponse(['ok' => false, 'error' => 'Falta workbook.'], 422);
        }

        try {
            $plan = PersonFinancialPlanService::save($entityId, $workbook);
            AuditService::log('update', 'person_financial_plan', $entityId, 'Proyección personal actualizada');
            self::jsonResponse([
                'ok' => true,
                'workbook' => $plan['workbook'],
                'updated_at' => $plan['updated_at'],
            ]);
        } catch (Throwable $e) {
            self::jsonResponse(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public static function resetFinancialPlan(string $id): void
    {
        Auth::requireWrite();
        require_csrf();
        $entityId = (int) $id;
        $person = self::find($entityId);
        if (!$person) {
            flash('error', 'Persona no encontrada.');
            redirect('/personas');
        }
        UserService::requireEntityAccess($entityId);
        $currency = post_string('currency', 'ARS') ?: 'ARS';
        PersonFinancialPlanService::reset($entityId);
        AuditService::log('update', 'person_financial_plan', $entityId, 'Proyección personal restablecida');
        flash('success', 'Proyección restablecida.');
        redirect('/personas/' . $entityId . '?tab=proyecciones&currency=' . urlencode($currency));
    }

    public static function update(string $id): void
    {
        Auth::requireWrite();
        require_csrf();
        $entityId = (int) $id;
        $before = self::find($entityId);
        if (!$before) {
            flash('error', 'Persona no encontrada.');
            redirect('/personas');
        }

        $first = post_string('first_name');
        $last = post_string('last_name');
        $name = trim($first . ' ' . $last);
        if ($first === '') {
            flash('error', 'El nombre es obligatorio.');
            redirect('/personas/' . $entityId . '/editar');
        }

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $pdo->prepare(
                'UPDATE entities SET name = :name, display_name = :display_name, country = :country,
                 notes = :notes, is_active = :is_active WHERE id = :id'
            )->execute([
                'name' => $name,
                'display_name' => $name,
                'country' => post_string('country') ?: null,
                'notes' => post_string('notes') ?: null,
                'is_active' => bool_post('is_active'),
                'id' => $entityId,
            ]);
            $pdo->prepare(
                'UPDATE people SET first_name = :first_name, last_name = :last_name,
                 document_id = :document_id WHERE entity_id = :id'
            )->execute([
                'first_name' => $first,
                'last_name' => $last !== '' ? $last : null,
                'document_id' => post_string('document_id') ?: null,
                'id' => $entityId,
            ]);
            $pdo->commit();
            AuditService::log('update', 'person', $entityId, 'Edición persona: ' . $name, $before, self::find($entityId));
            flash('success', 'Persona actualizada.');
            redirect('/personas/' . $entityId);
        } catch (Throwable $e) {
            $pdo->rollBack();
            flash('error', 'No se pudo actualizar: ' . $e->getMessage());
            redirect('/personas/' . $entityId . '/editar');
        }
    }

    /** @param array<string,mixed> $data */
    private static function jsonResponse(array $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    private static function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare(
            "SELECT e.*, p.first_name, p.last_name, p.document_id, p.birth_date
             FROM entities e
             INNER JOIN people p ON p.entity_id = e.id
             WHERE e.id = :id AND e.type = 'person'
             LIMIT 1"
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** @return list<array<string,mixed>> */
    private static function ownerships(int $personId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT o.*, e.name AS company_name
             FROM ownerships o
             INNER JOIN entities e ON e.id = o.company_entity_id
             WHERE o.owner_entity_id = :id
             ORDER BY o.ownership_pct DESC'
        );
        $stmt->execute(['id' => $personId]);
        return $stmt->fetchAll();
    }

    /** @return list<array<string,mixed>> */
    private static function rows(string $sql, int ...$params): array
    {
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params === [] ? [] : $params);
        return $stmt->fetchAll();
    }

    /** @param array<string,mixed> $data */
    private static function pagedMovements(int $entityId, array &$data): array
    {
        $pdo = Database::pdo();
        $count = $pdo->prepare('SELECT COUNT(*) FROM transactions WHERE entity_id = ?');
        $count->execute([$entityId]);
        $pagination = Pagination::make((int) $count->fetchColumn(), null, 50);
        $data['pagination'] = $pagination;

        $stmt = $pdo->prepare(
            "SELECT t.*, a.name AS account_name
             FROM transactions t
             LEFT JOIN accounts a ON a.id = t.account_id
             WHERE t.entity_id = ?
             ORDER BY t.occurred_at DESC, t.id DESC
             LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}"
        );
        $stmt->execute([$entityId]);
        return $stmt->fetchAll();
    }
}
