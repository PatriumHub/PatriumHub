<?php
$hide = hide_amounts();
$preset = $preset ?? '';
$byCurrency = $byCurrency ?? ['labels' => [], 'values' => []];
$ranking = $ranking ?? ['labels' => [], 'values' => [], 'ids' => []];

$compositionLabels = ['Cuentas', 'Activos varios', 'Propiedades', 'Cobrables', 'Inventario', 'Participaciones'];
$compositionRoutes = ['/cuentas', '/activos', '/propiedades', '/cobrables', '/inventario', '/participaciones'];
$compositionValues = [
    (float) ($summary['accounts'] ?? 0),
    (float) ($summary['assets'] ?? 0),
    (float) ($summary['properties'] ?? 0),
    (float) ($summary['receivables'] ?? 0),
    (float) ($summary['inventories'] ?? 0),
    (float) ($summary['participations'] ?? 0),
];
$entityLabels = [];
$entityValues = [];
$entityIds = [];
$entityTypes = [];
foreach ($summary['by_entity'] ?? [] as $row) {
    $entityLabels[] = (string) ($row['name'] ?? '');
    $entityValues[] = (float) ($row['net_worth'] ?? 0);
    $entityIds[] = (int) ($row['entity_id'] ?? $row['id'] ?? 0);
    $entityTypes[] = (string) ($row['type'] ?? 'company');
}
$evolLabels = [];
$evolNet = [];
$evolAssets = [];
$evolLiab = [];
$evolLiq = [];
foreach ($history ?? [] as $h) {
    $evolLabels[] = substr((string) ($h['captured_at'] ?? ''), 0, 10);
    $evolNet[] = (float) ($h['net_worth'] ?? 0);
    $evolAssets[] = (float) ($h['total_assets'] ?? 0);
    $evolLiab[] = (float) ($h['liabilities'] ?? 0);
    $evolLiq[] = (float) ($h['liquidity'] ?? 0);
}
$liq = (float) ($summary['liquidity'] ?? 0);
$net = (float) ($summary['net_worth'] ?? 0);
$illiquid = max(0, $net - $liq);

$exportQ = array_filter([
    'mode' => $mode,
    'entity_id' => $entityId,
    'currency' => $currency,
], static fn ($v) => $v !== null && $v !== '');

$wooSales = $wooSales ?? ['rows' => [], 'total_sales' => 0, 'total_orders' => 0, 'labels' => [], 'sales' => [], 'orders' => []];

$chartPayload = [
    'hide' => $hide,
    'currency' => $currency,
    'entityId' => $entityId,
    'baseUrl' => base_path() . '/index.php',
    'composition' => [
        'labels' => $compositionLabels,
        'values' => $compositionValues,
        'routes' => $compositionRoutes,
    ],
    'entities' => [
        'labels' => $entityLabels,
        'values' => $entityValues,
        'ids' => $entityIds,
        'types' => $entityTypes,
    ],
    'evolution' => [
        'labels' => $evolLabels,
        'net' => $evolNet,
        'assets' => $evolAssets,
        'liabilities' => $evolLiab,
        'liquidity' => $evolLiq,
    ],
    'flow' => [
        'labels' => ['Ingresos', 'Egresos'],
        'values' => [
            (float) ($flow['income'] ?? 0),
            (float) ($flow['expense'] ?? 0),
        ],
    ],
    'currencySplit' => $byCurrency,
    'ranking' => $ranking,
    'liquidity' => [
        'labels' => ['Liquidez', 'Resto neto'],
        'values' => [$liq, $illiquid],
    ],
    'wooSales' => [
        'labels' => $wooSales['labels'] ?? [],
        'sales' => $wooSales['sales'] ?? [],
        'orders' => $wooSales['orders'] ?? [],
    ],
];
$hasComposition = array_sum($compositionValues) > 0;
$hasEntities = $entityValues !== [];
$hasEvolution = $evolLabels !== [];
$hasFlow = array_sum($chartPayload['flow']['values'] ?? []) > 0;
$hasCurrency = ($byCurrency['labels'] ?? []) !== [];
$hasRanking = ($ranking['labels'] ?? []) !== [];
$hasLiquidity = ($liq + $illiquid) > 0;
$hasWooSales = ($wooSales['labels'] ?? []) !== [];
$focusLiquidity = $preset === 'liquidity';
$modeLede = match (true) {
    $focusLiquidity => 'Vista de liquidez (consolidado): cash y cuentas líquidas vs el resto del patrimonio',
    $mode === 'entity' && $entityId => 'Detalle de una entidad',
    default => 'Todas las entidades, sin doble conteo por participaciones',
};
?>
<section class="page">
    <div class="page-head">
        <div>
            <h1>Dashboard</h1>
            <p class="lede"><?= e($modeLede) ?></p>
        </div>
        <div class="actions">
            <a class="btn ghost" href="<?= e(url('/dashboard/export?' . http_build_query($exportQ))) ?>">Exportar serie CSV</a>
            <form method="post" action="<?= e(url('/dashboard/snapshot')) ?>" class="inline">
                <?= csrf_field() ?>
                <input type="hidden" name="mode" value="<?= e($mode) ?>">
                <input type="hidden" name="entity_id" value="<?= e((string) ($entityId ?? '')) ?>">
                <input type="hidden" name="currency" value="<?= e($currency) ?>">
                <button type="submit" class="btn ghost">Capturar snapshot</button>
            </form>
        </div>
    </div>
    <?php require __DIR__ . '/../partials/alerts.php'; ?>

    <div class="preset-row">
        <a class="preset<?= $preset === 'consolidated' || ($preset === '' && $mode === 'consolidated' && !$entityId && !$focusLiquidity) ? ' is-active' : '' ?>"
           href="<?= e(url('/dashboard?preset=consolidated&mode=consolidated&currency=' . urlencode($currency))) ?>">Consolidado</a>
        <a class="preset<?= $preset === 'personal' || $mode === 'personal' ? ' is-active' : '' ?>"
           href="<?= e(url('/dashboard?preset=personal&mode=personal&currency=' . urlencode($currency))) ?>">Personal</a>
        <a class="preset<?= $focusLiquidity ? ' is-active' : '' ?>"
           href="<?= e(url('/dashboard?preset=liquidity&mode=consolidated&currency=' . urlencode($currency))) ?>">Liquidez</a>
    </div>

    <form method="get" class="filters panel" action="<?= e(form_action()) ?>">
        <?= route_field('/dashboard') ?>
        <label>
            Modo
            <select name="mode">
                <option value="consolidated"<?= selected($mode, 'consolidated') ?>>Consolidado</option>
                <option value="personal"<?= selected($mode, 'personal') ?>>Personal</option>
                <option value="entity"<?= selected($mode, 'entity') ?>>Entidad</option>
            </select>
        </label>
        <label>
            Entidad
            <select name="entity_id">
                <option value="">Todas</option>
                <?php foreach ($entities as $ent): ?>
                    <option value="<?= (int) $ent['id'] ?>"<?= selected($entityId, $ent['id']) ?>>
                        <?= e($ent['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            Moneda
            <select name="currency">
                <?php foreach ($currencies as $c): ?>
                    <option value="<?= e($c['code']) ?>"<?= selected($currency, $c['code']) ?>><?= e($c['code']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Desde <input type="date" name="from" value="<?= e($from ?? '') ?>"></label>
        <label>Hasta <input type="date" name="to" value="<?= e($to ?? '') ?>"></label>
        <div class="filter-actions">
            <button type="submit">Aplicar</button>
            <a class="btn ghost" href="<?= e(url('/dashboard')) ?>">Limpiar</a>
        </div>
    </form>
    <?php
    $entityName = '';
    if ($entityId) {
        foreach ($entities as $ent) {
            if ((int) $ent['id'] === (int) $entityId) {
                $entityName = $ent['name'];
                break;
            }
        }
    }
    $modeLabels = ['consolidated' => 'Consolidado', 'personal' => 'Personal', 'entity' => 'Entidad'];
    $baseQ = array_filter([
        'mode' => $mode, 'entity_id' => $entityId, 'currency' => $currency,
        'from' => $from, 'to' => $to, 'preset' => $preset ?: null,
    ], static fn ($v) => $v !== null && $v !== '');
    $chips = filter_chips([
        'mode' => 'Modo: ' . ($modeLabels[$mode] ?? $mode) . ($focusLiquidity ? ' · Liquidez' : ''),
        'entity_id' => $entityName !== '' ? ('Entidad: ' . $entityName) : null,
        'currency' => $currency !== 'ARS' ? ('Moneda: ' . $currency) : null,
        'from' => $from ? ('Desde: ' . $from) : null,
        'to' => $to ? ('Hasta: ' . $to) : null,
    ], $baseQ, '/dashboard');
    require __DIR__ . '/../partials/filter_chips.php';
    ?>

    <?php if ($mode === 'personal' && empty($summary['by_entity'])): ?>
        <div class="alert alert-error">No hay personas activas. Creá una en Personas para ver el modo personal.</div>
    <?php endif; ?>

    <?php if (!empty($summary['note']) && !$focusLiquidity): ?>
        <p class="muted" style="margin:0"><?= e((string) $summary['note']) ?></p>
    <?php endif; ?>

    <?php
    $liqVal = (float) ($summary['liquidity'] ?? 0);
    $liqTone = $liqVal > 0 ? 'tone-green' : 'tone-rose';
    ?>
    <div class="stat-row" data-skeleton-target>
        <?php if ($focusLiquidity): ?>
            <div class="stat <?= e($liqTone) ?>">
                <span class="stat-label">Liquidez</span>
                <span class="stat-value"><?= e(money($summary['liquidity'], $currency)) ?></span>
            </div>
            <div class="stat accent">
                <span class="stat-label">Patrimonio neto</span>
                <span class="stat-value"><?= e(money($summary['net_worth'], $currency)) ?></span>
            </div>
            <div class="stat tone-blue">
                <span class="stat-label">Resto (no líquido)</span>
                <span class="stat-value"><?= e(money(max(0, (float) $summary['net_worth'] - (float) $summary['liquidity']), $currency)) ?></span>
            </div>
            <div class="stat tone-rose">
                <span class="stat-label">Pasivos</span>
                <span class="stat-value"><?= e(money($summary['liabilities'], $currency)) ?></span>
            </div>
        <?php else: ?>
            <div class="stat accent">
                <span class="stat-label">Patrimonio neto</span>
                <span class="stat-value"><?= e(money($summary['net_worth'], $currency)) ?></span>
            </div>
            <div class="stat tone-blue">
                <span class="stat-label">Activos</span>
                <span class="stat-value"><?= e(money($summary['total_assets'], $currency)) ?></span>
            </div>
            <div class="stat tone-rose">
                <span class="stat-label">Pasivos</span>
                <span class="stat-value"><?= e(money($summary['liabilities'], $currency)) ?></span>
            </div>
            <div class="stat <?= e($liqTone) ?>">
                <span class="stat-label">Liquidez</span>
                <span class="stat-value"><?= e(money($summary['liquidity'], $currency)) ?></span>
                <?php if ($mode === 'personal' && (float) ($summary['liquidity_from_participations'] ?? 0) > 0): ?>
                    <span class="stat-hint muted">Incluye <?= e(money($summary['liquidity_from_participations'], $currency)) ?> vía % empresas</span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php
    if ($focusLiquidity) {
        $items = [
            ['label' => 'Liquidez / activos', 'value' => (float) ($summary['liquidity'] ?? 0), 'tone' => $liqTone],
            ['label' => 'Neto / activos', 'value' => (float) ($summary['net_worth'] ?? 0), 'tone' => 'accent'],
            ['label' => 'Resto / activos', 'value' => max(0.0, (float) ($summary['net_worth'] ?? 0) - (float) ($summary['liquidity'] ?? 0)), 'tone' => 'tone-blue'],
            ['label' => 'Pasivos / activos', 'value' => (float) ($summary['liabilities'] ?? 0), 'tone' => 'tone-rose'],
        ];
    }
    require __DIR__ . '/../partials/stat_pct_of_assets.php';
    ?>

    <div class="charts-grid">
        <?php if ($focusLiquidity): ?>
            <div class="chart-card accent-border">
                <h2>Liquidez vs patrimonio</h2>
                <p class="chart-hint">Cuánto del neto está en efectivo / cuentas líquidas</p>
                <?php if ($hasLiquidity && !$hide): ?>
                    <div class="chart-wrap"><canvas id="chartLiquidity"></canvas></div>
                <?php elseif ($hide): ?>
                    <div class="chart-empty">Cifras ocultas</div>
                <?php else: ?>
                    <div class="chart-empty">Sin datos de liquidez.</div>
                <?php endif; ?>
            </div>
            <div class="chart-card">
                <h2>Composición de activos</h2>
                <p class="chart-hint">Contexto del consolidado</p>
                <?php if ($hasComposition && !$hide): ?>
                    <div class="chart-wrap"><canvas id="chartComposition"></canvas></div>
                <?php elseif ($hide): ?>
                    <div class="chart-empty">Cifras ocultas — mostrá montos para ver el gráfico</div>
                <?php else: ?>
                    <div class="chart-empty">Sin activos para graficar.</div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="chart-card">
                <h2>Composición de activos</h2>
                <p class="chart-hint">Clic en un segmento para ir al listado</p>
                <?php if ($hasComposition && !$hide): ?>
                    <div class="chart-wrap"><canvas id="chartComposition"></canvas></div>
                <?php elseif ($hide): ?>
                    <div class="chart-empty">Cifras ocultas — mostrá montos para ver el gráfico</div>
                <?php else: ?>
                    <div class="chart-empty">Sin activos para graficar.</div>
                <?php endif; ?>
            </div>
            <div class="chart-card">
                <h2>Liquidez vs patrimonio</h2>
                <?php if ($hasLiquidity && !$hide): ?>
                    <div class="chart-wrap"><canvas id="chartLiquidity"></canvas></div>
                <?php elseif ($hide): ?>
                    <div class="chart-empty">Cifras ocultas</div>
                <?php else: ?>
                    <div class="chart-empty">Sin datos de liquidez.</div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="charts-grid">
        <div class="chart-card">
            <h2>Por moneda (bruto)</h2>
            <p class="chart-hint">Suma en moneda original, sin convertir</p>
            <?php if ($hasCurrency && !$hide): ?>
                <div class="chart-wrap"><canvas id="chartCurrency"></canvas></div>
            <?php elseif ($hide): ?>
                <div class="chart-empty">Cifras ocultas</div>
            <?php else: ?>
                <div class="chart-empty">Sin saldos por moneda.</div>
            <?php endif; ?>
        </div>
        <div class="chart-card">
            <h2>Distribución por entidad</h2>
            <p class="chart-hint">Clic para abrir la entidad</p>
            <?php if ($hasEntities && !$hide): ?>
                <div class="chart-wrap"><canvas id="chartEntities"></canvas></div>
            <?php elseif ($hide): ?>
                <div class="chart-empty">Cifras ocultas</div>
            <?php else: ?>
                <div class="chart-empty">Sin entidades con patrimonio en este filtro.</div>
            <?php endif; ?>
        </div>
    </div>

    <div class="charts-grid">
        <div class="chart-card">
            <h2>Evolución patrimonial</h2>
            <p class="chart-hint">Línea gruesa = patrimonio neto · verde = activos · rojo = pasivos</p>
            <?php if ($hasEvolution && !$hide): ?>
                <div class="chart-wrap tall"><canvas id="chartEvolution"></canvas></div>
            <?php elseif ($hide): ?>
                <div class="chart-empty">Cifras ocultas</div>
            <?php else: ?>
                <div class="chart-empty">Sin historial. Usá “Capturar snapshot”.</div>
            <?php endif; ?>
        </div>
        <div class="chart-card">
            <h2>Ingresos y egresos</h2>
            <p class="chart-hint">
                Rango
                <?php if (($from ?? '') !== '' || ($to ?? '') !== ''): ?>
                    <?= e(trim(($from ?? '…') . ' → ' . ($to ?? '…'))) ?>
                <?php else: ?>
                    sin límite de fechas
                <?php endif; ?>
            </p>
            <?php if ($hasFlow && !$hide): ?>
                <div class="chart-wrap"><canvas id="chartFlow"></canvas></div>
            <?php elseif ($hide): ?>
                <div class="chart-empty">Cifras ocultas</div>
            <?php else: ?>
                <div class="chart-empty">Sin ingresos/egresos en el rango.</div>
            <?php endif; ?>
        </div>
    </div>

    <div class="charts-grid">
        <div class="chart-card">
            <h2>Variación por entidad (snapshots)</h2>
            <p class="chart-hint">Clic para filtrar el dashboard</p>
            <?php if ($hasRanking && !$hide): ?>
                <div class="chart-wrap"><canvas id="chartRanking"></canvas></div>
            <?php elseif ($hide): ?>
                <div class="chart-empty">Cifras ocultas</div>
            <?php else: ?>
                <div class="chart-empty">
                    <p style="margin:0 0 0.75rem">Todavía no hay snapshots por entidad.</p>
                    <form method="post" action="<?= e(url('/dashboard/snapshot')) ?>" class="inline">
                        <?= csrf_field() ?>
                        <input type="hidden" name="mode" value="consolidated">
                        <input type="hidden" name="currency" value="<?= e($currency) ?>">
                        <button type="submit" class="btn sm">Capturar baseline ahora</button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
        <div class="chart-card">
            <h2>Vistas guardadas</h2>
            <?php if (empty($savedViews)): ?>
                <p class="muted">Ninguna aún.</p>
            <?php else: ?>
                <ul class="plain">
                    <?php foreach ($savedViews as $sv): ?>
                        <li>
                            <a href="<?= e(url('/dashboard?view=' . $sv['id'])) ?>"><?= e($sv['name']) ?></a>
                            <form method="post" action="<?= e(url('/dashboard/vistas/' . $sv['id'] . '/eliminar')) ?>" class="inline">
                                <?= csrf_field() ?>
                                <button type="submit" class="linkish danger" aria-label="Eliminar vista">×</button>
                            </form>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <h3 class="mt">Guardar filtros</h3>
            <form method="post" action="<?= e(url('/dashboard/vistas')) ?>" class="inline-filters">
                <?= csrf_field() ?>
                <input type="hidden" name="mode" value="<?= e($mode) ?>">
                <input type="hidden" name="entity_id" value="<?= e((string) ($entityId ?? '')) ?>">
                <input type="hidden" name="currency" value="<?= e($currency) ?>">
                <input type="hidden" name="from" value="<?= e($from ?? '') ?>">
                <input type="hidden" name="to" value="<?= e($to ?? '') ?>">
                <label>Nombre
                    <input name="name" required maxlength="120" placeholder="Ej. Personal ARS">
                </label>
                <button type="submit" class="mt">Guardar vista</button>
            </form>
        </div>
    </div>

    <div class="charts-grid">
        <div class="chart-card">
            <h2>Ventas WooCommerce</h2>
            <p class="chart-hint">Pedidos sync por mes (no son movimientos manuales)</p>
            <?php if ($hasWooSales && !$hide): ?>
                <div class="chart-wrap"><canvas id="chartWooSales"></canvas></div>
            <?php elseif ($hide): ?>
                <div class="chart-empty">Cifras ocultas</div>
            <?php else: ?>
                <div class="chart-empty">Sin ventas WC. Conectá WooCommerce e intentá “Sincronizar ahora”.</div>
            <?php endif; ?>
        </div>
        <div class="panel">
            <h2>Detalle ventas WC</h2>
            <?php if ($hasWooSales): ?>
                <p class="muted" style="margin-top:0">
                    Total visible: <strong><?= e(money($wooSales['total_sales'], $currency)) ?></strong>
                    · <?= (int) $wooSales['total_orders'] ?> pedidos
                </p>
                <table class="table">
                    <thead>
                    <tr><th>Mes</th><th>Tienda / entidad</th><th class="num">Pedidos</th><th class="num">Ventas</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($wooSales['rows'] as $sr): ?>
                        <tr>
                            <td><?= e(substr((string) $sr['period_start'], 0, 7)) ?></td>
                            <td>
                                <?= e($sr['integration_name'] ?? 'WC') ?>
                                <span class="muted">· <?= e($sr['entity_name'] ?? '') ?></span>
                            </td>
                            <td class="num"><?= (int) $sr['orders_count'] ?></td>
                            <td class="num"><?= e(money($sr['sales_total'], $sr['currency_code'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="muted">Todavía no hay `sales_metrics`. Corré sync en Integraciones.</p>
            <?php endif; ?>
        </div>
    </div>

    <div class="panel mt">
        <h2>Últimos movimientos</h2>
        <?php if (empty($movements)): ?>
            <p class="muted">Sin movimientos en el filtro.</p>
        <?php else: ?>
            <table class="table">
                <thead>
                <tr>
                    <th>Fecha</th><th>Entidad</th><th>Tipo</th><th>Descripción</th><th class="num">Importe</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($movements as $m): ?>
                    <tr>
                        <td><?= e(substr((string) $m['occurred_at'], 0, 16)) ?></td>
                        <td><?= e($m['entity_name']) ?></td>
                        <td><span class="badge"><?= e(movement_type_label((string) $m['type'])) ?></span></td>
                        <td><?= e($m['description'] ?? '—') ?></td>
                        <td class="num"><?= money_flow($m['amount'], $m['currency_code'], (string) $m['type']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</section>
<script type="application/json" id="dashboard-charts"><?= json_encode($chartPayload, JSON_UNESCAPED_UNICODE) ?></script>
