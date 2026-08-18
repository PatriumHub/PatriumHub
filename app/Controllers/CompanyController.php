<?php

declare(strict_types=1);

final class CompanyController
{
    public static function index(): void
    {
        Auth::requireLogin();
        CompanySchema::ensure();
        $rows = Database::pdo()->query(
            "SELECT e.id, e.name, e.country, e.is_active, e.updated_at,
                    c.legal_name, c.tax_id, c.industry, c.business_model
             FROM entities e
             INNER JOIN companies c ON c.entity_id = e.id
             WHERE e.type = 'company'
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
        $withIndustry = 0;
        $models = [];
        $byIndustry = [];
        $byModel = [];
        foreach ($rows as $row) {
            if ((int) ($row['is_active'] ?? 0)) {
                $active++;
            } else {
                $inactive++;
            }
            if (trim((string) ($row['industry'] ?? '')) !== '') {
                $withIndustry++;
            }
            $m = trim((string) ($row['business_model'] ?? ''));
            if ($m !== '') {
                $models[$m] = true;
                $byModel[$m] = ($byModel[$m] ?? 0) + 1;
            } else {
                $byModel['Sin modelo'] = ($byModel['Sin modelo'] ?? 0) + 1;
            }
            $ind = trim((string) ($row['industry'] ?? ''));
            if ($ind === '') {
                $ind = 'Sin rubro';
            }
            $byIndustry[$ind] = ($byIndustry[$ind] ?? 0) + 1;
        }
        arsort($byIndustry);
        arsort($byModel);

        view('companies/index', [
            'title' => 'Empresas',
            'rows' => $rows,
            'canWrite' => Auth::canWrite(),
            'totals' => [
                'count' => count($rows),
                'active' => $active,
                'inactive' => $inactive,
                'with_industry' => $withIndustry,
                'models' => count($models),
            ],
            'charts' => [
                'byType' => [
                    'labels' => ['Activas', 'Inactivas'],
                    'values' => [$active, $inactive],
                ],
                'byEntity' => [
                    'labels' => array_keys($byIndustry),
                    'values' => array_values($byIndustry),
                ],
            ],
            'success' => flash('success'),
            'error' => flash('error'),
        ]);
    }

    public static function create(): void
    {
        Auth::requireWrite();
        view('companies/form', [
            'title' => 'Nueva empresa',
            'company' => null,
            'people' => Catalog::entities('person'),
            'error' => flash('error'),
        ]);
    }

    public static function store(): void
    {
        Auth::requireWrite();
        require_csrf();

        CompanySchema::ensure();
        $name = post_string('name');
        if ($name === '') {
            flash('error', 'El nombre es obligatorio.');
            redirect('/empresas/nueva');
        }
        $businessModel = CompanySchema::normalizeModel(post_string('business_model', 'services'));

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $pdo->prepare(
                'INSERT INTO entities (type, name, display_name, country, notes, is_active)
                 VALUES (\'company\', :name, :display_name, :country, :notes, 1)'
            )->execute([
                'name' => $name,
                'display_name' => $name,
                'country' => post_string('country') ?: null,
                'notes' => post_string('notes') ?: null,
            ]);
            $id = (int) $pdo->lastInsertId();

            $pdo->prepare(
                'INSERT INTO companies (entity_id, legal_name, tax_id, industry, business_model, website)
                 VALUES (:id, :legal_name, :tax_id, :industry, :business_model, :website)'
            )->execute([
                'id' => $id,
                'legal_name' => post_string('legal_name') ?: null,
                'tax_id' => post_string('tax_id') ?: null,
                'industry' => post_string('industry') ?: null,
                'business_model' => $businessModel,
                'website' => null,
            ]);

            $ownerId = request_int('owner_entity_id');
            $pct = post_float('ownership_pct', 100.0);
            if ($ownerId) {
                $pdo->prepare(
                    'INSERT INTO ownerships (owner_entity_id, company_entity_id, ownership_pct)
                     VALUES (:owner, :company, :pct)'
                )->execute([
                    'owner' => $ownerId,
                    'company' => $id,
                    'pct' => $pct,
                ]);
            }

            $pdo->commit();
            try {
                FinancialPlanService::createForNewCompany($id, $name, $businessModel);
            } catch (Throwable $e) {
                // La empresa ya existe; el plan se crea al abrir el tab.
            }
            AuditService::log('create', 'company', $id, 'Alta empresa: ' . $name);
            flash('success', 'Empresa creada.');
            redirect('/empresas/' . $id);
        } catch (Throwable $e) {
            $pdo->rollBack();
            flash('error', 'No se pudo guardar: ' . $e->getMessage());
            redirect('/empresas/nueva');
        }
    }

    public static function show(string $id): void
    {
        Auth::requireLogin();
        CompanySchema::ensure();
        $company = self::find((int) $id);
        if (!$company) {
            flash('error', 'Empresa no encontrada.');
            redirect('/empresas');
        }
        UserService::requireEntityAccess((int) $company['id']);

        $tab = $_GET['tab'] ?? 'resumen';
        if ($tab === 'dashboard') {
            $tab = 'resumen';
        }
        $isServices = ($company['business_model'] ?? 'services') === 'services';
        $allowed = [
            'resumen', 'cuentas', 'activos', 'pasivos', 'propiedades', 'inventario',
            'cobrables', 'movimientos', 'integraciones', 'participaciones', 'valuacion',
            'estados-proyeccion',
        ];
        if ($isServices) {
            $allowed[] = 'clientes';
        }
        if (!in_array($tab, $allowed, true)) {
            $tab = 'resumen';
        }

        $entityId = (int) $company['id'];
        $currency = $_GET['currency'] ?? 'ARS';
        $summary = PatrimonioService::forEntity($entityId, $currency);

        $financialPlan = null;
        if ($tab === 'estados-proyeccion') {
            $financialPlan = FinancialPlanService::ensureForCompany($entityId, (string) $company['name']);
        }

        $companyClients = [];
        $clientsUi = [
            'rows' => [],
            'sort' => 'name',
            'dir' => 'asc',
            'stats' => ['active' => 0, 'inactive' => 0, 'total_all' => 0],
        ];
        if ($tab === 'clientes' && $isServices) {
            $clientsUi = CompanyClientService::listForUi(
                $entityId,
                (string) ($_GET['sort'] ?? 'name'),
                (string) ($_GET['dir'] ?? 'asc')
            );
            $companyClients = $clientsUi['rows'];
        }

        if ($tab === 'resumen' || $tab === 'valuacion') {
            ValuationService::refreshAuto($entityId, $currency);
        }

        $salesMetrics = DashboardAnalyticsService::wooSalesSeries($entityId, $currency, 12);
        $mpSalesSeries = ['labels' => [], 'sales' => [], 'total_sales' => 0.0];
        $enCajaSeries = ['labels' => [], 'sales' => [], 'total_sales' => 0.0];
        $companyAccounts = [];

        $valStmt = Database::pdo()->prepare(
            'SELECT * FROM business_valuations
             WHERE company_entity_id = :e
             ORDER BY valued_at DESC, id DESC
             LIMIT 20'
        );
        $valStmt->execute(['e' => $entityId]);

        $companyExtras = null;
        if ($tab === 'resumen') {
            $companyExtras = DashboardAnalyticsService::companyExtras($entityId, $currency);
            $mpSalesSeries = DashboardAnalyticsService::channelSalesSeries($entityId, $currency, 'mp', 12);
            $enCajaSeries = DashboardAnalyticsService::enCajaSeries($entityId, $currency, 12);
            $accStmt = Database::pdo()->prepare(
                "SELECT id, name, type, currency_code, balance
                 FROM accounts
                 WHERE entity_id = :e AND status = 'active'
                 ORDER BY type = 'cash' DESC, name"
            );
            $accStmt->execute(['e' => $entityId]);
            $companyAccounts = $accStmt->fetchAll();
        }

        $data = [
            'title' => $company['name'],
            'company' => $company,
            'tab' => $tab,
            'summary' => $summary,
            'currency' => $currency,
            'currencies' => Catalog::currencies(),
            'ownerships' => self::ownerships($entityId),
            'valuations' => $valStmt->fetchAll(),
            'valuation' => ValuationService::current($entityId, $currency),
            'salesValuation' => ValuationService::salesMultipleSuggestion(
                $entityId,
                $currency,
                isset($_GET['multiple']) ? (float) $_GET['multiple'] : 1.0
            ),
            'health' => ValuationService::health($summary, $entityId),
            'salesMetrics' => $salesMetrics,
            'mpSalesSeries' => $mpSalesSeries,
            'enCajaSeries' => $enCajaSeries,
            'companyAccounts' => $companyAccounts,
            'companyExtras' => $companyExtras,
            'financialPlan' => $financialPlan,
            'companyClients' => $companyClients,
            'clientsUi' => $clientsUi,
            'isServices' => $isServices,
            'canWrite' => Auth::canWrite(),
            'success' => flash('success'),
            'error' => flash('error'),
        ];

        $data['pagination'] = null;
        $data['tabRows'] = match ($tab) {
            'cuentas' => self::rows(
                "SELECT * FROM accounts WHERE entity_id = ? AND status = 'active' ORDER BY name",
                $entityId
            ),
            'activos' => self::rows(
                "SELECT a.*, ac.name AS account_name
                 FROM assets a
                 LEFT JOIN accounts ac ON ac.id = a.account_id
                 WHERE a.entity_id = ?
                 ORDER BY a.name",
                $entityId
            ),
            'pasivos' => self::rows("SELECT * FROM liabilities WHERE entity_id = ? ORDER BY name", $entityId),
            'propiedades' => self::rows("SELECT * FROM properties WHERE entity_id = ? ORDER BY name", $entityId),
            'inventario' => self::rows("SELECT * FROM inventories WHERE entity_id = ? ORDER BY name", $entityId),
            'cobrables' => self::rows("SELECT * FROM receivables WHERE entity_id = ? ORDER BY id DESC", $entityId),
            'movimientos' => self::pagedMovements($entityId, $data),
            'integraciones' => IntegrationService::forEntity($entityId),
            default => [],
        };

        view('companies/show', $data);
    }

    public static function edit(string $id): void
    {
        Auth::requireWrite();
        CompanySchema::ensure();
        $company = self::find((int) $id);
        if (!$company) {
            flash('error', 'Empresa no encontrada.');
            redirect('/empresas');
        }
        UserService::requireEntityAccess((int) $company['id']);
        view('companies/form', [
            'title' => 'Editar empresa',
            'company' => $company,
            'people' => Catalog::entities('person'),
            'deleteCounts' => EntityService::dependencyCounts((int) $company['id']),
            'error' => flash('error'),
        ]);
    }

    public static function destroy(string $id): void
    {
        Auth::requireWrite();
        require_csrf();
        $entityId = (int) $id;
        $company = self::find($entityId);
        if (!$company) {
            flash('error', 'Empresa no encontrada.');
            redirect('/empresas');
        }
        UserService::requireEntityAccess($entityId);

        $confirm = trim(post_string('confirm_name'));
        if ($confirm === '' || mb_strtolower($confirm) !== mb_strtolower((string) $company['name'])) {
            flash('error', 'Para eliminar, escribí el nombre exacto de la empresa.');
            redirect('/empresas/' . $entityId . '/editar');
        }

        $before = $company;
        $result = EntityService::delete($entityId, 'company');
        if (!$result['ok']) {
            flash('error', 'No se pudo eliminar: ' . ($result['error'] ?? 'error desconocido'));
            redirect('/empresas/' . $entityId . '/editar');
        }
        AuditService::log('delete', 'company', $entityId, 'Eliminación empresa: ' . ($before['name'] ?? ''), $before, null);
        flash('success', 'Empresa eliminada.');
        redirect('/empresas');
    }

    public static function update(string $id): void
    {
        Auth::requireWrite();
        require_csrf();
        CompanySchema::ensure();
        $entityId = (int) $id;
        $before = self::find($entityId);
        if (!$before) {
            flash('error', 'Empresa no encontrada.');
            redirect('/empresas');
        }
        UserService::requireEntityAccess($entityId);

        $name = post_string('name');
        if ($name === '') {
            flash('error', 'El nombre es obligatorio.');
            redirect('/empresas/' . $entityId . '/editar');
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
            // business_model solo se setea al crear; no se modifica en edición.
            $pdo->prepare(
                'UPDATE companies SET legal_name = :legal_name, tax_id = :tax_id,
                 industry = :industry WHERE entity_id = :id'
            )->execute([
                'legal_name' => post_string('legal_name') ?: null,
                'tax_id' => post_string('tax_id') ?: null,
                'industry' => post_string('industry') ?: null,
                'id' => $entityId,
            ]);
            $pdo->commit();
            AuditService::log('update', 'company', $entityId, 'Edición empresa: ' . $name, $before, self::find($entityId));
            flash('success', 'Empresa actualizada.');
            redirect('/empresas/' . $entityId);
        } catch (Throwable $e) {
            $pdo->rollBack();
            flash('error', 'No se pudo actualizar: ' . $e->getMessage());
            redirect('/empresas/' . $entityId . '/editar');
        }
    }

    public static function saveFinancialPlan(string $id): void
    {
        Auth::requireWrite();
        $entityId = (int) $id;
        $company = self::find($entityId);
        if (!$company) {
            self::jsonResponse(['ok' => false, 'error' => 'Empresa no encontrada.'], 404);
        }

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
            $plan = FinancialPlanService::save($entityId, $workbook);
            AuditService::log('update', 'company_financial_plan', $entityId, 'Estados y proyección actualizados');
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
        $company = self::find($entityId);
        if (!$company) {
            flash('error', 'Empresa no encontrada.');
            redirect('/empresas');
        }
        try {
            FinancialPlanService::reset($entityId, (string) $company['name']);
            AuditService::log('update', 'company_financial_plan', $entityId, 'Estados y proyección restablecidos');
            flash('success', 'Planilla restablecida a la plantilla.');
        } catch (Throwable $e) {
            flash('error', 'No se pudo restablecer: ' . $e->getMessage());
        }
        redirect('/empresas/' . $entityId . '?tab=estados-proyeccion');
    }

    /** @param array<string,mixed> $data */
    private static function jsonResponse(array $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function storeCashMovement(string $id): void
    {
        Auth::requireWrite();
        require_csrf();
        $entityId = (int) $id;
        $company = self::find($entityId);
        if (!$company) {
            flash('error', 'Empresa no encontrada.');
            redirect('/empresas');
        }

        $amount = abs(post_float('amount'));
        $currency = post_string('currency_code', 'ARS') ?: 'ARS';
        $accountId = request_int('account_id');
        $txType = post_string('tx_type', 'income');
        if (!in_array($txType, ['income', 'expense'], true)) {
            $txType = 'income';
        }
        $occurred = post_string('occurred_at') ?: date('Y-m-d H:i:s');
        $occurred = str_replace('T', ' ', $occurred);
        if (strlen($occurred) === 16) {
            $occurred .= ':00';
        }
        $note = post_string('description');
        $label = $txType === 'income' ? 'En caja · ingreso' : 'En caja · egreso';
        $description = $label . ($note !== '' ? (': ' . $note) : '');

        $redirectTo = '/empresas/' . $entityId . '?tab=resumen&currency=' . urlencode($currency);

        if ($amount <= 0) {
            flash('error', 'Ingresá un importe válido.');
            redirect($redirectTo);
        }
        if (!$accountId) {
            flash('error', 'Elegí la cuenta donde se asocia el movimiento de caja.');
            redirect($redirectTo);
        }

        $pdo = Database::pdo();
        $accStmt = $pdo->prepare(
            "SELECT id, currency_code FROM accounts
             WHERE id = :id AND entity_id = :e AND status = 'active'
             LIMIT 1"
        );
        $accStmt->execute(['id' => $accountId, 'e' => $entityId]);
        $account = $accStmt->fetch();
        if (!$account) {
            flash('error', 'La cuenta no pertenece a esta empresa o está inactiva.');
            redirect($redirectTo);
        }
        $currency = (string) ($account['currency_code'] ?: $currency);

        $delta = $txType === 'income' ? round($amount, 2) : -round($amount, 2);

        $pdo->beginTransaction();
        try {
            $user = Auth::user();
            $pdo->prepare(
                'INSERT INTO transactions
                 (entity_id, account_id, type, occurred_at, currency_code, amount, description,
                  origin, metadata_json, created_by)
                 VALUES
                 (:entity_id, :account_id, :type, :occurred_at, :currency_code, :amount, :description,
                  \'manual\', :metadata_json, :created_by)'
            )->execute([
                'entity_id' => $entityId,
                'account_id' => $accountId,
                'type' => $txType,
                'occurred_at' => $occurred,
                'currency_code' => $currency,
                'amount' => round($amount, 2),
                'description' => mb_substr($description, 0, 255),
                'metadata_json' => json_encode(['en_caja' => true], JSON_UNESCAPED_UNICODE),
                'created_by' => $user['id'] ?? null,
            ]);
            $txId = (int) $pdo->lastInsertId();
            $pdo->prepare(
                'UPDATE accounts SET balance = balance + :a, updated_at = CURRENT_TIMESTAMP WHERE id = :id'
            )->execute(['a' => $delta, 'id' => $accountId]);
            $pdo->commit();
            AuditService::log('create', 'transaction', $txId, $description . ' ' . $amount);
            flash('success', 'Movimiento de caja registrado en la cuenta. El patrimonio suma desde el saldo de esa cuenta.');
            redirect($redirectTo);
        } catch (Throwable $e) {
            $pdo->rollBack();
            flash('error', 'No se pudo registrar: ' . $e->getMessage());
            redirect($redirectTo);
        }
    }

    private static function find(int $id): ?array
    {
        CompanySchema::ensure();
        $stmt = Database::pdo()->prepare(
            "SELECT e.*, c.legal_name, c.tax_id, c.industry, c.business_model, c.website
             FROM entities e
             INNER JOIN companies c ON c.entity_id = e.id
             WHERE e.id = :id AND e.type = 'company'
             LIMIT 1"
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private static function ownerships(int $companyId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT o.*, e.name AS owner_name
             FROM ownerships o
             INNER JOIN entities e ON e.id = o.owner_entity_id
             WHERE o.company_entity_id = :id
             ORDER BY o.ownership_pct DESC'
        );
        $stmt->execute(['id' => $companyId]);
        return $stmt->fetchAll();
    }

    private static function rows(string $sql, int $entityId): array
    {
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute([$entityId]);
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
