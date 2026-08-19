<?php
/** @var array $agg */
/** @var array $reality */
/** @var string $currency */
/** @var list<array{code:string,name:string,symbol:string}> $currencies */
$hide = hide_amounts();
$totals = $agg['totals'] ?? [];
$year = (string) ($agg['year'] ?? date('Y'));
$scope = (string) ($agg['scope'] ?? 'all');
$years = $agg['years'] ?? [$year];
$people = $agg['people'] ?? [];
$companies = $agg['companies'] ?? [];
$counts = $agg['counts'] ?? [];
$chartPayload = [
    'months' => $agg['months'] ?? [],
    'currency' => $currency,
    'hide' => $hide,
    'charts' => $agg['charts'] ?? [],
];
$scopeLabels = [
    'all' => 'Todas',
    'people' => 'Personas',
    'companies' => 'Empresas',
];
?>
<section class="page">
    <div class="page-head">
        <div>
            <h1>Proyecciones</h1>
            <p class="lede">Vista consolidada de flujo proyectado — solo lectura. La carga se hace en cada persona o empresa.</p>
        </div>
        <form method="get" class="filters inline-filters" action="<?= e(form_action()) ?>">
            <?= route_field('/proyecciones') ?>
            <label>
                Año
                <select name="year" onchange="this.form.submit()">
                    <?php foreach ($years as $y): ?>
                        <option value="<?= e($y) ?>"<?= selected($year, $y) ?>><?= e($y) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                Alcance
                <select name="scope" onchange="this.form.submit()">
                    <?php foreach ($scopeLabels as $val => $label): ?>
                        <option value="<?= e($val) ?>"<?= selected($scope, $val) ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                Moneda
                <select name="currency" onchange="this.form.submit()">
                    <?php foreach ($currencies as $c): ?>
                        <option value="<?= e($c['code']) ?>"<?= selected($currency, $c['code']) ?>><?= e($c['code']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </form>
    </div>

    <?php require __DIR__ . '/../partials/alerts.php'; ?>

    <p class="muted cp-note">
        Sumatoria de planillas del año <strong><?= e($year) ?></strong>
        · <?= (int) ($counts['with_year'] ?? 0) ?> con datos
        · <?= (int) ($counts['people'] ?? 0) ?> personas / <?= (int) ($counts['companies'] ?? 0) ?> empresas.
        Si una persona incluye utilidades de empresas, puede haber solapamiento.
    </p>

    <div class="pp-year-kpis cp-kpis">
        <div class="pp-year-kpi is-ok">
            <span class="pp-year-kpi-label">Ingresos consolidados</span>
            <strong class="pp-year-kpi-value"><?= e(money($totals['income_annual'] ?? 0, $currency)) ?></strong>
            <span class="pp-year-kpi-hint">Suma de todas las planillas</span>
        </div>
        <div class="pp-year-kpi is-danger">
            <span class="pp-year-kpi-label">Egresos consolidados</span>
            <strong class="pp-year-kpi-value"><?= e(money($totals['expense_annual'] ?? 0, $currency)) ?></strong>
            <span class="pp-year-kpi-hint">Gastos proyectados del año</span>
        </div>
        <div class="pp-year-kpi<?= ((float) ($totals['balance_annual'] ?? 0) >= 0) ? ' is-ok' : ' is-danger' ?>">
            <span class="pp-year-kpi-label">Balance anual</span>
            <strong class="pp-year-kpi-value"><?= e(money($totals['balance_annual'] ?? 0, $currency)) ?></strong>
            <span class="pp-year-kpi-hint">Ingresos − egresos</span>
        </div>
        <div class="pp-year-kpi<?= ((float) ($totals['profit_annual'] ?? 0) >= 0) ? ' is-accent' : ' is-danger' ?>">
            <span class="pp-year-kpi-label">Promedio mensual</span>
            <strong class="pp-year-kpi-value"><?= e(money(((float) ($totals['profit_annual'] ?? 0)) / 12, $currency)) ?></strong>
            <span class="pp-year-kpi-hint">Disponible neto anual ÷ 12</span>
        </div>
    </div>

    <div class="pp-board-grid cp-reality">
        <div class="pp-tile pp-tile-slate">
            <span class="pp-tile-label">Patrimonio neto real</span>
            <strong class="pp-tile-value"><?= e(money($reality['net_worth'] ?? 0, $currency)) ?></strong>
            <span class="pp-tile-hint">Consolidado hoy</span>
        </div>
        <div class="pp-tile pp-tile-teal">
            <span class="pp-tile-label">Liquidez real</span>
            <strong class="pp-tile-value"><?= e(money($reality['liquidity'] ?? 0, $currency)) ?></strong>
            <span class="pp-tile-hint">Cuentas y efectivo</span>
        </div>
        <div class="pp-tile pp-tile-amber">
            <span class="pp-tile-label">Pasivos reales</span>
            <strong class="pp-tile-value"><?= e(money($reality['liabilities'] ?? 0, $currency)) ?></strong>
            <span class="pp-tile-hint">Deudas abiertas</span>
        </div>
        <div class="pp-tile<?= ((float) ($reality['net_worth'] ?? 0) + (float) ($totals['profit_annual'] ?? 0) >= 0) ? ' pp-tile-mint' : ' pp-tile-rose' ?>">
            <span class="pp-tile-label">Neto si se cumple el año</span>
            <strong class="pp-tile-value"><?= e(money(((float) ($reality['net_worth'] ?? 0)) + ((float) ($totals['profit_annual'] ?? 0)), $currency)) ?></strong>
            <span class="pp-tile-hint">Neto real + disponible</span>
        </div>
    </div>

    <div class="cp-block">
        <div class="cp-block-head">
            <h2>Flujo del año</h2>
            <p class="muted">Ingresos, egresos y balance consolidado</p>
        </div>
        <div class="pp-charts cp-charts">
            <div class="chart-card pp-chart-flow">
                <h2>Flujo consolidado</h2>
                <p class="chart-hint">Mes a mes</p>
                <?php if ($hide): ?>
                    <div class="chart-empty">Cifras ocultas</div>
                <?php else: ?>
                    <div class="chart-wrap tall"><canvas id="cp-flow-chart"></canvas></div>
                <?php endif; ?>
            </div>
            <div class="chart-card pp-chart-mix">
                <h2>Composición</h2>
                <p class="chart-hint">Ingresos vs egresos</p>
                <?php if ($hide): ?>
                    <div class="chart-empty">Cifras ocultas</div>
                <?php else: ?>
                    <div class="chart-wrap short"><canvas id="cp-mix-chart"></canvas></div>
                <?php endif; ?>
            </div>
            <div class="chart-card pp-chart-cum">
                <h2>Personas vs empresas</h2>
                <p class="chart-hint">Ingresos y egresos por grupo</p>
                <?php if ($hide): ?>
                    <div class="chart-empty">Cifras ocultas</div>
                <?php else: ?>
                    <div class="chart-wrap short"><canvas id="cp-groups-chart"></canvas></div>
                <?php endif; ?>
            </div>
            <div class="chart-card pp-chart-break">
                <h2>Por entidad</h2>
                <p class="chart-hint">Ingresos y egresos de cada planilla</p>
                <?php if ($hide): ?>
                    <div class="chart-empty">Cifras ocultas</div>
                <?php else: ?>
                    <div class="chart-wrap tall"><canvas id="cp-entity-chart"></canvas></div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="cp-block cp-block-savings">
        <div class="cp-block-head">
            <div>
                <h2>Ahorro proyectado</h2>
                <p class="muted">Según la meta de ahorro de cada planilla</p>
            </div>
            <div class="pp-year-kpi is-ok cp-savings-kpi">
                <span class="pp-year-kpi-label">Total del año</span>
                <strong class="pp-year-kpi-value"><?= e(money($totals['savings_annual'] ?? 0, $currency)) ?></strong>
            </div>
        </div>
        <div class="pp-charts cp-charts-savings">
            <div class="chart-card">
                <h2>Mes a mes</h2>
                <p class="chart-hint">Ahorro consolidado</p>
                <?php if ($hide): ?>
                    <div class="chart-empty">Cifras ocultas</div>
                <?php else: ?>
                    <div class="chart-wrap short"><canvas id="cp-savings-chart"></canvas></div>
                <?php endif; ?>
            </div>
            <div class="chart-card">
                <h2>Acumulado</h2>
                <p class="chart-hint">Suma progresiva del año</p>
                <?php if ($hide): ?>
                    <div class="chart-empty">Cifras ocultas</div>
                <?php else: ?>
                    <div class="chart-wrap short"><canvas id="cp-savings-cum-chart"></canvas></div>
                <?php endif; ?>
            </div>
            <div class="chart-card">
                <h2>Por entidad</h2>
                <p class="chart-hint">Quién aporta al ahorro</p>
                <?php if ($hide): ?>
                    <div class="chart-empty">Cifras ocultas</div>
                <?php else: ?>
                    <div class="chart-wrap short"><canvas id="cp-savings-mix-chart"></canvas></div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="grid-2 mt cp-tables">
        <div class="panel">
            <h2>Personas</h2>
            <?php if ($people === []): ?>
                <p class="muted">Sin personas en el alcance.</p>
            <?php else: ?>
                <div class="table-scroll">
                    <table class="table">
                        <thead>
                        <tr>
                            <th>Persona</th>
                            <th class="num">Ingresos</th>
                            <th class="num">Egresos</th>
                            <th class="num">Ahorro</th>
                            <th class="num">Balance</th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($people as $row): ?>
                            <?php $s = $row['stats']; ?>
                            <tr>
                                <td>
                                    <?= e($row['name']) ?>
                                    <?php if (!$row['has_plan']): ?>
                                        <span class="badge">Sin planilla</span>
                                    <?php elseif (!$row['has_year']): ?>
                                        <span class="badge">Sin <?= e($year) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="num"><?= e(money($s['income_annual'] ?? 0, $currency)) ?></td>
                                <td class="num"><?= e(money($s['expense_annual'] ?? 0, $currency)) ?></td>
                                <td class="num"><?= e(money($s['savings_annual'] ?? 0, $currency)) ?></td>
                                <td class="num"><strong><?= e(money($s['balance_annual'] ?? 0, $currency)) ?></strong></td>
                                <td class="actions"><a href="<?= e(url($row['href'])) ?>">Ver</a></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        <div class="panel">
            <h2>Empresas</h2>
            <?php if ($companies === []): ?>
                <p class="muted">Sin empresas en el alcance.</p>
            <?php else: ?>
                <div class="table-scroll">
                    <table class="table">
                        <thead>
                        <tr>
                            <th>Empresa</th>
                            <th class="num">Ingresos</th>
                            <th class="num">Egresos</th>
                            <th class="num">Ahorro</th>
                            <th class="num">Balance</th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($companies as $row): ?>
                            <?php $s = $row['stats']; ?>
                            <tr>
                                <td>
                                    <?= e($row['name']) ?>
                                    <?php if (!$row['has_plan']): ?>
                                        <span class="badge">Sin planilla</span>
                                    <?php elseif (!$row['has_year']): ?>
                                        <span class="badge">Sin <?= e($year) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="num"><?= e(money($s['income_annual'] ?? 0, $currency)) ?></td>
                                <td class="num"><?= e(money($s['expense_annual'] ?? 0, $currency)) ?></td>
                                <td class="num"><?= e(money($s['savings_annual'] ?? 0, $currency)) ?></td>
                                <td class="num"><strong><?= e(money($s['balance_annual'] ?? 0, $currency)) ?></strong></td>
                                <td class="actions"><a href="<?= e(url($row['href'])) ?>">Ver</a></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<script type="application/json" id="cp-charts-data"><?= json_encode($chartPayload, JSON_UNESCAPED_UNICODE) ?></script>
<script>
(function () {
  function boot() {
    var s = document.createElement('script');
    s.src = <?= json_encode(url('/assets/js/consolidated-projections.js') . '?v=' . (int) @filemtime(dirname(__DIR__, 3) . '/assets/js/consolidated-projections.js'), JSON_UNESCAPED_SLASHES) ?>;
    document.body.appendChild(s);
  }
  if (window.Chart) boot();
  else window.addEventListener('load', boot);
})();
</script>
