<?php

declare(strict_types=1);

final class DashboardController
{
    public static function index(): void
    {
        Auth::requireLogin();
        $user = Auth::user();

        $viewId = request_int('view');
        if ($viewId) {
            $saved = SavedViewService::find($viewId, (int) $user['id']);
            if ($saved) {
                $f = SavedViewService::filters($saved);
                $_GET = array_merge($_GET, array_filter([
                    'mode' => $f['mode'] ?? null,
                    'entity_id' => $f['entity_id'] ?? null,
                    'currency' => $f['currency'] ?? null,
                    'from' => $f['from'] ?? null,
                    'to' => $f['to'] ?? null,
                ], static fn ($v) => $v !== null && $v !== ''));
            }
        }

        $preset = $_GET['preset'] ?? '';
        if ($preset === 'personal') {
            $_GET['mode'] = 'personal';
            unset($_GET['entity_id']);
        } elseif ($preset === 'consolidated') {
            $_GET['mode'] = 'consolidated';
            unset($_GET['entity_id']);
        } elseif ($preset === 'liquidity') {
            $_GET['mode'] = 'consolidated';
            unset($_GET['entity_id']);
        }

        $currency = $_GET['currency'] ?? 'ARS';
        $entityId = request_int('entity_id');
        // Default 30 días: el 1° del mes "este mes" deja ingresos en 0 y solo muestra egresos del día.
        if (array_key_exists('from', $_GET) || array_key_exists('to', $_GET)) {
            $from = trim((string) ($_GET['from'] ?? '')) ?: null;
            $to = trim((string) ($_GET['to'] ?? '')) ?: null;
        } else {
            $from = date('Y-m-d', strtotime('-29 days'));
            $to = date('Y-m-d');
        }
        $mode = $_GET['mode'] ?? ($entityId ? 'entity' : 'consolidated');
        if (!in_array($mode, ['entity', 'personal', 'consolidated'], true)) {
            $mode = 'consolidated';
        }

        $ids = $entityId ? [$entityId] : null;
        if ($mode === 'entity' && $entityId) {
            $summary = PatrimonioService::forEntity($entityId, $currency);
            // Normalizar forma de summary con by_entity para la vista
            $summary = [
                'currency' => $currency,
                'mode' => $summary['mode'] ?? 'entity',
                'accounts' => (float) ($summary['accounts'] ?? 0),
                'assets' => (float) ($summary['assets'] ?? 0),
                'properties' => (float) ($summary['properties'] ?? 0),
                'receivables' => (float) ($summary['receivables'] ?? 0),
                'inventories' => (float) ($summary['inventories'] ?? 0),
                'participations' => (float) ($summary['participations'] ?? 0),
                'liabilities' => (float) ($summary['liabilities'] ?? 0),
                'liquidity' => (float) ($summary['liquidity'] ?? 0),
                'liquidity_own' => (float) ($summary['liquidity_own'] ?? 0),
                'liquidity_from_participations' => (float) ($summary['liquidity_from_participations'] ?? 0),
                'internal_netting' => (float) ($summary['internal_netting'] ?? 0),
                'total_assets' => (float) ($summary['total_assets'] ?? 0),
                'net_worth' => (float) ($summary['net_worth'] ?? 0),
                'by_entity' => [array_merge($summary, [
                    'entity_id' => $entityId,
                    'name' => $summary['name'] ?? '',
                    'type' => $summary['type'] ?? 'company',
                ])],
                'participation_details' => $summary['participation_details'] ?? [],
                'note' => $summary['note'] ?? null,
            ];
        } else {
            $summary = PatrimonioService::summarize($ids, $currency, $mode);
        }

        // Alcance de listados/gráficos secundarios según modo (antes personal ≈ consolidado).
        $scopeEntityIds = self::scopeEntityIds($mode, $entityId);

        $historyScope = $mode === 'entity' ? (($summary['mode'] ?? '') === 'personal' ? 'personal' : 'entity') : $mode;
        $history = SnapshotService::history($historyScope, $entityId, $currency, 60);

        $movements = self::recentMovements($scopeEntityIds, $from, $to);
        $flow = self::flowTotals($scopeEntityIds, $currency, $from, $to);
        $byCurrency = DashboardAnalyticsService::byCurrency($scopeEntityIds);
        $ranking = DashboardAnalyticsService::entityRanking($currency);
        $alerts = DashboardAnalyticsService::alerts(
            $mode === 'entity' ? $entityId : null
        );
        if ($mode === 'personal' && $scopeEntityIds !== null) {
            $alerts = array_values(array_filter(
                $alerts,
                static function (array $a) use ($scopeEntityIds): bool {
                    // Alertas de integración: solo si la entidad está en el alcance personal.
                    if (($a['type'] ?? '') !== 'integration' || empty($a['href'])) {
                        return true;
                    }
                    if (!preg_match('#/integraciones/(\d+)#', (string) $a['href'], $m)) {
                        return true;
                    }
                    $int = IntegrationService::find((int) $m[1]);
                    return $int && in_array((int) $int['entity_id'], $scopeEntityIds, true);
                }
            ));
        }
        $wooSales = DashboardAnalyticsService::wooSalesSeries(
            ($mode === 'entity' && $entityId) ? $entityId : null,
            $currency,
            12
        );
        // En personal no tiene sentido el gráfico WC de empresas.
        if ($mode === 'personal') {
            $wooSales = ['rows' => [], 'total_sales' => 0, 'total_orders' => 0, 'labels' => [], 'sales' => [], 'orders' => []];
        }

        $entitiesForFilter = $mode === 'personal'
            ? Catalog::entities('person')
            : Catalog::entities();

        view($mode === 'personal' ? 'dashboard/personal' : 'dashboard/index', [
            'title' => $mode === 'personal' ? 'Personal' : 'Dashboard',
            'summary' => $summary,
            'currency' => $currency,
            'entityId' => $entityId,
            'mode' => $mode,
            'preset' => $preset,
            'from' => $from,
            'to' => $to,
            'entities' => $entitiesForFilter,
            'currencies' => Catalog::currencies(),
            'movements' => $movements,
            'flow' => $flow,
            'byCurrency' => $byCurrency,
            'ranking' => $ranking,
            'alerts' => $alerts,
            'wooSales' => $wooSales,
            'history' => $history,
            'savedViews' => SavedViewService::listForUser((int) $user['id']),
            'success' => flash('success'),
            'error' => flash('error'),
        ]);
    }

    public static function exportSeries(): void
    {
        Auth::requireLogin();
        $currency = $_GET['currency'] ?? 'ARS';
        $entityId = request_int('entity_id');
        $mode = $_GET['mode'] ?? 'consolidated';
        if (!in_array($mode, ['entity', 'personal', 'consolidated'], true)) {
            $mode = 'consolidated';
        }
        $historyScope = $mode === 'entity' ? 'entity' : $mode;
        $history = SnapshotService::history($historyScope, $entityId, $currency, 365);
        $rows = [];
        foreach ($history as $h) {
            $rows[] = [
                $h['captured_at'],
                $h['scope'],
                $h['currency_code'],
                $h['net_worth'],
                $h['total_assets'],
                $h['liabilities'],
                $h['participations'],
            ];
        }
        ExportService::csv('dashboard-serie.csv', [
            'Fecha', 'Scope', 'Moneda', 'Neto', 'Activos', 'Pasivos', 'Participaciones',
        ], $rows);
    }

    public static function captureSnapshot(): void
    {
        Auth::requireLogin();
        require_csrf();

        $currency = post_string('currency', 'ARS') ?: 'ARS';
        $mode = post_string('mode', 'consolidated') ?: 'consolidated';
        $entityId = request_int('entity_id');
        $redirectTo = post_string('redirect_to') ?: '';

        if ($entityId) {
            $row = PatrimonioService::forEntity($entityId, $currency);
            $scope = (($row['mode'] ?? '') === 'personal') ? 'personal' : 'entity';
            $id = SnapshotService::capture($scope, $row + ['currency' => $currency], $entityId);
            AuditService::log('create', 'net_worth_snapshot', $id, "Snapshot entidad {$entityId}");
            flash('success', 'Snapshot de la entidad capturado.');
        } else {
            // Consolidado/personal: también baseline por cada persona/empresa (sirve al ranking).
            $ids = SnapshotService::captureDaily($currency);
            AuditService::log(
                'create',
                'net_worth_snapshot',
                $ids[0] ?? 0,
                'Snapshot diario: ' . count($ids) . ' filas'
            );
            flash('success', 'Snapshots capturados: consolidado, personal y cada entidad activa.');
        }

        if ($redirectTo !== '' && str_starts_with($redirectTo, '/')) {
            redirect($redirectTo);
        }

        $q = http_build_query(array_filter([
            'mode' => $mode,
            'entity_id' => $entityId,
            'currency' => $currency,
        ]));
        redirect('/dashboard' . ($q ? ('?' . $q) : ''));
    }

    /** @deprecated Redirige a Cuentas (MP es cuenta manual como el resto). */
    public static function mercadopago(): void
    {
        Auth::requireLogin();
        $qs = [];
        if ($entityId = request_int('entity_id')) {
            $qs['entity_id'] = $entityId;
        }
        if (($currency = trim((string) ($_GET['currency'] ?? ''))) !== '') {
            $qs['currency'] = $currency;
        }
        redirect('/cuentas' . ($qs ? ('?' . http_build_query($qs)) : ''));
    }

    /** @return list<int>|null null = todas las entidades activas */
    private static function scopeEntityIds(string $mode, ?int $entityId): ?array
    {
        if ($mode === 'entity' && $entityId) {
            return [$entityId];
        }
        if ($mode === 'personal') {
            return array_map('intval', array_column(Catalog::entities('person'), 'id'));
        }
        return null;
    }

    /** @param list<int>|null $entityIds */
    private static function recentMovements(?array $entityIds, ?string $from, ?string $to): array
    {
        $sql = "SELECT t.*, e.name AS entity_name
                FROM transactions t
                INNER JOIN entities e ON e.id = t.entity_id
                WHERE 1=1";
        $params = [];
        if ($entityIds !== null) {
            if ($entityIds === []) {
                return [];
            }
            $ph = [];
            foreach (array_values($entityIds) as $i => $id) {
                $key = 'e' . $i;
                $ph[] = ':' . $key;
                $params[$key] = (int) $id;
            }
            $sql .= ' AND t.entity_id IN (' . implode(',', $ph) . ')';
        }
        if ($from) {
            $sql .= ' AND t.occurred_at >= :from';
            $params['from'] = $from . ' 00:00:00';
        }
        if ($to) {
            $sql .= ' AND t.occurred_at <= :to';
            $params['to'] = $to . ' 23:59:59';
        }
        $sql .= ' ORDER BY t.occurred_at DESC LIMIT 15';
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** @param list<int>|null $entityIds */
    private static function flowTotals(?array $entityIds, string $currency, ?string $from, ?string $to): array
    {
        if ($entityIds !== null && $entityIds === []) {
            return ['income' => 0.0, 'expense' => 0.0];
        }

        $sql = "SELECT
                    COALESCE(SUM(CASE WHEN type IN ('income','collection','loan','ingreso') THEN amount ELSE 0 END),0) AS income,
                    COALESCE(SUM(CASE WHEN type IN ('expense','payment','egreso') THEN amount ELSE 0 END),0) AS expense
                FROM transactions
                WHERE currency_code = :cur";
        $params = ['cur' => $currency];
        if ($entityIds !== null) {
            $ph = [];
            foreach (array_values($entityIds) as $i => $id) {
                $key = 'e' . $i;
                $ph[] = ':' . $key;
                $params[$key] = (int) $id;
            }
            $sql .= ' AND entity_id IN (' . implode(',', $ph) . ')';
        }
        if ($from) {
            $sql .= ' AND occurred_at >= :from';
            $params['from'] = $from . ' 00:00:00';
        }
        if ($to) {
            $sql .= ' AND occurred_at <= :to';
            $params['to'] = $to . ' 23:59:59';
        }
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch() ?: ['income' => 0, 'expense' => 0];
        return [
            'income' => (float) $row['income'],
            'expense' => (float) $row['expense'],
        ];
    }
}
