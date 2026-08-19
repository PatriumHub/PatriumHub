<?php
$tabs = [
    'resumen' => 'Resumen',
    'proyecciones' => 'Proyecciones',
    'cuentas' => 'Cuentas',
    'activos' => 'Activos varios',
    'pasivos' => 'Pasivos',
    'propiedades' => 'Propiedades',
    'cobrables' => 'Cobrables',
    'movimientos' => 'Movimientos',
    'participaciones' => 'Participaciones',
];
$personPath = '/personas/' . (int) $person['id'];
$canWrite = !empty($canWrite);
$net = (float) ($summary['net_worth'] ?? 0);
$health = $health ?? ['status' => 'warn', 'label' => '—', 'reasons' => []];
$parts = $summary['participation_details'] ?? [];
$personPlan = $personPlan ?? null;
$planStats = $planStats ?? null;
$planCompare = $planCompare ?? null;
$fpMonths = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
?>
<section class="page">
    <div class="page-head">
        <div>
            <h1><?= e($person['name']) ?></h1>
            <p class="lede">
                Persona
                <?= !empty($person['document_id']) ? ' · Doc. ' . e($person['document_id']) : '' ?>
                <?= !empty($person['country']) ? ' · ' . e($person['country']) : '' ?>
                <?= $canWrite ? '' : ' · Solo lectura' ?>
            </p>
        </div>
        <div class="actions">
            <?php if ($canWrite): ?>
            <form method="post" action="<?= e(url('/dashboard/snapshot')) ?>" class="inline">
                <?= csrf_field() ?>
                <input type="hidden" name="mode" value="entity">
                <input type="hidden" name="entity_id" value="<?= (int) $person['id'] ?>">
                <input type="hidden" name="currency" value="<?= e($currency) ?>">
                <input type="hidden" name="redirect_to" value="<?= e($personPath . '?tab=' . urlencode((string) $tab) . '&currency=' . urlencode($currency)) ?>">
                <button type="submit" class="btn ghost">Capturar snapshot</button>
            </form>
            <a class="btn ghost" href="<?= e(url($personPath . '/editar')) ?>">Editar</a>
            <?php endif; ?>
            <a class="btn ghost" href="<?= e(url('/personas')) ?>">Volver</a>
        </div>
    </div>
    <?php require __DIR__ . '/../partials/alerts.php'; ?>

    <nav class="tabs">
        <?php foreach ($tabs as $key => $label): ?>
            <a class="<?= $tab === $key ? 'is-active' : '' ?>"
               href="<?= e(url($personPath . '?tab=' . urlencode($key) . '&currency=' . urlencode($currency))) ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
    </nav>

    <?php if ($tab === 'participaciones'): ?>
        <div class="panel mt">
            <div class="page-head">
                <div>
                    <h2>Empresas donde participa</h2>
                    <p class="muted" style="margin:0">Tu % de la empresa × valuación completa. El activo de la empresa cuenta al 100%; tu parte sale de la participación.</p>
                </div>
                <?php if ($canWrite): ?>
                <a class="btn" href="<?= e(url('/participaciones/nueva?owner_entity_id=' . $person['id'])) ?>">+ Participación</a>
                <?php endif; ?>
            </div>
            <?php if (empty($ownerships)): ?>
                <p class="muted">Sin participaciones. El patrimonio personal solo suma activos propios.</p>
            <?php else: ?>
                <table class="table">
                    <thead>
                    <tr><th>Empresa</th><th class="num">%</th><th class="num">Valor atribuido</th><th class="num">Liquidez atribuida</th><?php if ($canWrite): ?><th></th><?php endif; ?></tr>
                    </thead>
                    <tbody>
                    <?php
                    $valueByCompany = [];
                    foreach ($parts as $p) {
                        $valueByCompany[(int) ($p['company_entity_id'] ?? 0)] = $p;
                    }
                    foreach ($ownerships as $o):
                        $detail = $valueByCompany[(int) $o['company_entity_id']] ?? null;
                        ?>
                        <tr>
                            <td>
                                <a href="<?= e(url('/empresas/' . $o['company_entity_id'] . '?tab=resumen&currency=' . urlencode($currency))) ?>">
                                    <?= e($o['company_name']) ?>
                                </a>
                            </td>
                            <td class="num"><?= e(number_format((float) $o['ownership_pct'], 2, ',', '.')) ?>%</td>
                            <td class="num">
                                <?= $detail
                                    ? e(money($detail['attributable'] ?? 0, $currency))
                                    : '—' ?>
                            </td>
                            <td class="num">
                                <?= $detail
                                    ? e(money($detail['attributable_liquidity'] ?? 0, $currency))
                                    : '—' ?>
                            </td>
                            <?php if ($canWrite): ?>
                            <td class="actions">
                                <a href="<?= e(url('/participaciones/' . $o['id'] . '/editar')) ?>">Editar</a>
                            </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

    <?php elseif ($tab === 'resumen'): ?>
        <?php
        $hide = hide_amounts();
        $healthClass = match ($health['status'] ?? 'warn') {
            'ok' => 'is-ok',
            'bad' => 'is-bad',
            default => 'is-warn',
        };
        $compLabels = ['Cuentas', 'Activos varios', 'Propiedades', 'Cobrables', 'Participaciones'];
        $compValues = [
            (float) ($summary['accounts'] ?? 0),
            (float) ($summary['assets'] ?? 0),
            (float) ($summary['properties'] ?? 0),
            (float) ($summary['receivables'] ?? 0),
            (float) ($summary['participations'] ?? 0),
        ];
        $chartPayload = [
            'hide' => $hide,
            'entityId' => (int) $person['id'],
            'baseUrl' => base_path() . '/index.php',
            'composition' => [
                'labels' => $compLabels,
                'values' => $compValues,
                'routes' => ['/cuentas', '/activos', '/propiedades', '/cobrables', '/participaciones'],
            ],
            'liquidity' => [
                'labels' => ['Liquidez', 'Resto neto'],
                'values' => [
                    (float) ($summary['liquidity'] ?? 0),
                    max(0, $net - (float) ($summary['liquidity'] ?? 0)),
                ],
            ],
        ];
        ?>
        <form method="get" class="filters inline-filters mt" action="<?= e(form_action()) ?>">
            <?= route_field($personPath) ?>
            <input type="hidden" name="tab" value="resumen">
            <label>Moneda
                <select name="currency" onchange="this.form.submit()">
                    <?php foreach ($currencies as $c): ?>
                        <option value="<?= e($c['code']) ?>"<?= selected($currency, $c['code']) ?>><?= e($c['code']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </form>

        <div class="company-hero">
            <div class="company-hero-main accent">
                <span class="stat-label">Patrimonio personal</span>
                <div class="company-hero-value"><?= e(money($net, $currency)) ?></div>
                <p class="muted" style="margin:0.4rem 0 0">Propios + participaciones en empresas</p>
            </div>
            <div class="company-health <?= e($healthClass) ?>">
                <span class="company-health-label"><?= e($health['label']) ?></span>
                <ul>
                    <?php foreach (($health['reasons'] ?? []) as $reason): ?>
                        <li><?= e($reason) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>

        <div class="stat-row">
            <div class="stat tone-green">
                <span class="stat-label">Activos propios</span>
                <span class="stat-value"><?= e(money(
                    (float) ($summary['accounts'] ?? 0)
                    + (float) ($summary['assets'] ?? 0)
                    + (float) ($summary['properties'] ?? 0)
                    + (float) ($summary['receivables'] ?? 0),
                    $currency
                )) ?></span>
            </div>
            <div class="stat tone-amber">
                <span class="stat-label">Participaciones</span>
                <span class="stat-value"><?= e(money($summary['participations'] ?? 0, $currency)) ?></span>
            </div>
            <div class="stat tone-rose">
                <span class="stat-label">Pasivos</span>
                <span class="stat-value"><?= e(money($summary['liabilities'] ?? 0, $currency)) ?></span>
            </div>
            <div class="stat tone-teal">
                <span class="stat-label">Liquidez</span>
                <span class="stat-value"><?= e(money($summary['liquidity'] ?? 0, $currency)) ?></span>
                <?php if ((float) ($summary['liquidity_from_participations'] ?? 0) > 0): ?>
                    <span class="stat-hint muted">Incluye <?= e(money($summary['liquidity_from_participations'], $currency)) ?> vía % empresas</span>
                <?php endif; ?>
            </div>
        </div>

        <div class="charts-grid">
            <div class="chart-card">
                <h2>Composición</h2>
                <?php if (array_sum($compValues) > 0 && !$hide): ?>
                    <div class="chart-wrap"><canvas id="chartComposition"></canvas></div>
                <?php elseif ($hide): ?>
                    <div class="chart-empty">Cifras ocultas</div>
                <?php else: ?>
                    <div class="chart-empty">Sin activos para graficar.</div>
                <?php endif; ?>
            </div>
            <div class="chart-card">
                <h2>Liquidez vs patrimonio</h2>
                <?php if (!$hide && ($net > 0 || (float) ($summary['liquidity'] ?? 0) > 0)): ?>
                    <div class="chart-wrap"><canvas id="chartLiquidity"></canvas></div>
                <?php else: ?>
                    <div class="chart-empty">Sin datos.</div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($planStats && $planCompare): ?>
            <?php
            $ps = $planStats;
            $pc = $planCompare;
            $yearName = (string) ($ps['name'] ?? '');
            ?>
            <div class="panel mt pp-resumen-card">
                <div class="page-head">
                    <div>
                        <h2 style="margin:0">Proyección <?= $yearName !== '' ? e($yearName) : (string) date('Y') ?></h2>
                        <p class="muted" style="margin:0.25rem 0 0">Año en curso · flujo planificado vs tu patrimonio real</p>
                    </div>
                    <a class="btn ghost" href="<?= e(url($personPath . '?tab=proyecciones&currency=' . urlencode($currency))) ?>">Abrir proyecciones</a>
                </div>
                <div class="stat-row cols-3" style="margin:1rem 0 0">
                    <div class="stat tone-green">
                        <span class="stat-label">Ingresos proyectados</span>
                        <span class="stat-value"><?= e(money($ps['income_annual'] ?? 0, $currency)) ?></span>
                    </div>
                    <div class="stat tone-rose">
                        <span class="stat-label">Egresos proyectados</span>
                        <span class="stat-value"><?= e(money($ps['expense_annual'] ?? 0, $currency)) ?></span>
                    </div>
                    <div class="stat tone-teal">
                        <span class="stat-label">Balance del año</span>
                        <span class="stat-value"><?= e(money($ps['balance_annual'] ?? 0, $currency)) ?></span>
                    </div>
                </div>
                <p class="muted" style="margin:0.85rem 0 0">
                    Neto real <?= e(money($pc['real']['net_worth'] ?? 0, $currency)) ?>
                    · Liquidez <?= e(money($pc['real']['liquidity'] ?? 0, $currency)) ?>
                    · Promedio mensual <?= e(money($pc['insights']['monthly_avg'] ?? (($pc['projected']['profit_annual'] ?? 0) / 12), $currency)) ?>
                </p>
            </div>
        <?php endif; ?>

        <?php if (!empty($ownerships)): ?>
            <div class="panel mt">
                <h2>Participaciones</h2>
                <ul class="plain">
                    <?php foreach ($ownerships as $o): ?>
                        <li>
                            <a href="<?= e(url('/empresas/' . $o['company_entity_id'])) ?>"><?= e($o['company_name']) ?></a>
                            — <strong><?= e(number_format((float) $o['ownership_pct'], 2, ',', '.')) ?>%</strong>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <p class="mt"><a href="<?= e(url($personPath . '?tab=participaciones&currency=' . urlencode($currency))) ?>">Ver detalle →</a></p>
            </div>
        <?php endif; ?>
        <script type="application/json" id="company-charts"><?= json_encode($chartPayload, JSON_UNESCAPED_UNICODE) ?></script>

    <?php elseif ($tab === 'proyecciones'): ?>
        <?php
        $pp = $personPlan ?? PersonFinancialPlanService::ensureForPerson((int) $person['id']);
        $realityPayload = [
            'net_worth' => (float) ($summary['net_worth'] ?? 0),
            'liquidity' => (float) ($summary['liquidity'] ?? 0),
            'liabilities' => (float) ($summary['liabilities'] ?? 0),
            'own_assets' => (float) ($summary['accounts'] ?? 0)
                + (float) ($summary['assets'] ?? 0)
                + (float) ($summary['properties'] ?? 0)
                + (float) ($summary['receivables'] ?? 0),
            'participations' => (float) ($summary['participations'] ?? 0),
        ];
        ?>
        <div id="pp-root"
             class="mt"
             data-readonly="<?= $canWrite ? '0' : '1' ?>"
             data-save-url="<?= e(url($personPath . '/proyecciones')) ?>"
             data-csrf="<?= e(csrf_token()) ?>"
             data-currency="<?= e($currency) ?>">
            <div class="pp-board">
                <div class="pp-board-head">
                    <div>
                        <h2>Proyecciones</h2>
                        <p>Ingresos y gastos mes a mes, contrastados con tu patrimonio real.</p>
                    </div>
                    <?php if ($canWrite): ?>
                        <form method="post" action="<?= e(url($personPath . '/proyecciones/reset')) ?>" class="inline"
                              onsubmit="return confirm('¿Restablecer la proyección a la plantilla vacía?');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="currency" value="<?= e($currency) ?>">
                            <button type="submit" class="btn ghost">Restablecer</button>
                        </form>
                    <?php endif; ?>
                </div>

                <div class="pp-board-focus">
                    <div class="pp-focus pp-focus-net">
                        <span class="pp-kicker">Neto actual</span>
                        <strong class="pp-focus-value"><?= e(money($realityPayload['net_worth'], $currency)) ?></strong>
                        <span class="pp-focus-note">Patrimonio real hoy</span>
                    </div>
                    <div class="pp-focus pp-focus-out">
                        <span class="pp-kicker">Egresos del año</span>
                        <strong class="pp-focus-value" id="pp-expense-hero">—</strong>
                        <span class="pp-focus-note" id="pp-expense-hero-hint">Promedio mensual —</span>
                    </div>
                </div>

                <div class="pp-board-grid">
                    <div class="pp-tile pp-tile-teal">
                        <span class="pp-tile-label">Liquidez</span>
                        <strong class="pp-tile-value"><?= e(money($realityPayload['liquidity'], $currency)) ?></strong>
                    </div>
                    <div class="pp-tile pp-tile-green">
                        <span class="pp-tile-label">Activos propios</span>
                        <strong class="pp-tile-value"><?= e(money($realityPayload['own_assets'], $currency)) ?></strong>
                    </div>
                    <div class="pp-tile pp-tile-amber">
                        <span class="pp-tile-label">Pasivos</span>
                        <strong class="pp-tile-value"><?= e(money($realityPayload['liabilities'], $currency)) ?></strong>
                    </div>
                    <div class="pp-board-insights" id="pp-insight-dynamic"></div>
                </div>
            </div>

            <div class="pp-year-bar">
                <div id="pp-year-tabs" class="fp-sheet-tabs"></div>
                <div class="fp-toolbar-actions">
                    <label class="fp-rate-label">Meta de ahorro
                        <input type="number" id="pp-savings-rate" min="0" max="100" step="0.5" class="fp-rate-input"<?= $canWrite ? '' : ' disabled' ?>> %
                    </label>
                    <span id="pp-status" class="fp-status"></span>
                    <?php if ($canWrite): ?>
                        <button type="button" class="btn ghost" id="pp-add-year">+ Año</button>
                        <button type="button" class="btn" id="pp-save">Guardar</button>
                    <?php endif; ?>
                </div>
            </div>

            <div id="pp-kpis" class="pp-year-kpis" aria-live="polite"></div>

            <div class="pp-charts">
                <div class="chart-card pp-chart-flow">
                    <h2>Flujo del año</h2>
                    <p class="chart-hint">Ingresos, egresos y balance mes a mes</p>
                    <div class="chart-wrap flow-xl"><canvas id="pp-flow-chart"></canvas></div>
                </div>
                <div class="chart-card pp-chart-cum">
                    <h2>Acumulado</h2>
                    <p class="chart-hint">Balance acumulado mes a mes</p>
                    <div class="chart-wrap short"><canvas id="pp-cum-chart"></canvas></div>
                </div>
                <div class="chart-card pp-chart-break">
                    <h2>Desglose por concepto</h2>
                    <p class="chart-hint">Totales anuales de cada línea de ingreso y gasto</p>
                    <div class="chart-wrap tall"><canvas id="pp-break-chart"></canvas></div>
                </div>
            </div>

            <div class="panel mt">
                <div class="fp-section-head">
                    <div>
                        <h3 style="margin:0">Ingresos mensuales</h3>
                        <p class="muted" style="margin:0.2rem 0 0">Sueldos, honorarios, rentas y otros — pasados o proyectados.</p>
                    </div>
                    <?php if ($canWrite): ?>
                        <button type="button" class="btn ghost" id="pp-add-income">+ Ingreso</button>
                    <?php endif; ?>
                </div>
                <div class="fp-table-scroll">
                    <table class="table fp-edit-table">
                        <thead>
                        <tr>
                            <th>Concepto</th>
                            <?php foreach ($fpMonths as $m): ?><th class="num"><?= e($m) ?></th><?php endforeach; ?>
                            <th class="num">Total</th>
                            <?php if ($canWrite): ?><th></th><?php endif; ?>
                        </tr>
                        </thead>
                        <tbody id="pp-income-body"></tbody>
                    </table>
                </div>

                <div class="fp-section-head mt">
                    <div>
                        <h3 style="margin:0">Egresos / costos</h3>
                        <p class="muted" style="margin:0.2rem 0 0">Gastos fijos y variables del mes.</p>
                    </div>
                    <?php if ($canWrite): ?>
                        <button type="button" class="btn ghost" id="pp-add-expense">+ Gasto</button>
                    <?php endif; ?>
                </div>
                <div class="fp-table-scroll">
                    <table class="table fp-edit-table">
                        <thead>
                        <tr>
                            <th>Concepto</th>
                            <?php foreach ($fpMonths as $m): ?><th class="num"><?= e($m) ?></th><?php endforeach; ?>
                            <th class="num">Total</th>
                            <?php if ($canWrite): ?><th></th><?php endif; ?>
                        </tr>
                        </thead>
                        <tbody id="pp-expenses-body"></tbody>
                    </table>
                </div>

                <div class="fp-section-head mt">
                    <div>
                        <h3 style="margin:0">Resumen mensual</h3>
                        <p class="muted" style="margin:0.2rem 0 0">Se calcula solo.</p>
                    </div>
                </div>
                <div class="fp-table-scroll">
                    <table class="table fp-summary-table">
                        <thead>
                        <tr>
                            <th></th>
                            <?php foreach ($fpMonths as $m): ?><th class="num"><?= e($m) ?></th><?php endforeach; ?>
                            <th class="num">Anual</th>
                        </tr>
                        </thead>
                        <tbody id="pp-summary-body"></tbody>
                    </table>
                </div>
                <p class="fp-hint">Ctrl+S guarda.
                    <?php if (!empty($pp['updated_at'])): ?>
                        Último guardado: <?= e((string) $pp['updated_at']) ?>
                    <?php endif; ?>
                </p>
            </div>

            <div class="pp-block-burden mt-lg">
                <div class="cp-block-head">
                    <div>
                        <h2>Carga de egresos</h2>
                        <p class="muted">Egresos y ahorro sobre ingresos; el verde es el disponible neto</p>
                    </div>
                </div>
                <div class="pp-charts pp-charts-burden">
                    <div class="chart-card pp-burden-total">
                        <h2>Egresos vs ingresos</h2>
                        <p class="chart-hint">Total del año (tras meta de ahorro)</p>
                        <div class="burden-total-layout">
                            <div class="chart-doughnut">
                                <div class="chart-wrap short"><canvas id="pp-burden-total"></canvas></div>
                                <div class="chart-doughnut-center" id="pp-burden-kpi">
                                    <strong id="pp-burden-kpi-value">—</strong>
                                    <span>egresos</span>
                                </div>
                            </div>
                            <ul class="burden-legend" id="pp-burden-legend" aria-live="polite"></ul>
                        </div>
                    </div>
                    <div class="chart-card pp-burden-months">
                        <h2>Mes a mes</h2>
                        <p class="chart-hint">Egresos + ahorro + disponible neto</p>
                        <div class="chart-wrap short"><canvas id="pp-burden-months"></canvas></div>
                        <p class="burden-color-key" aria-hidden="true">
                            <span><i class="swatch is-high"></i> Egresos ≥ 50%</span>
                            <span><i class="swatch is-low"></i> Egresos &lt; 50%</span>
                            <span><i class="swatch is-savings"></i> Ahorro</span>
                            <span><i class="swatch is-rest"></i> Disponible neto</span>
                        </p>
                    </div>
                    <div class="chart-card pp-burden-categories">
                        <h2>Por categoría</h2>
                        <p class="chart-hint">Cada egreso como % de los ingresos del año</p>
                        <div class="chart-wrap tall"><canvas id="pp-burden-categories"></canvas></div>
                    </div>
                </div>
            </div>

            <div class="pp-block-daily mt-lg">
                <div class="cp-block-head">
                    <div>
                        <h2>Gasto diario máximo</h2>
                        <p class="muted">Disponible neto de cada mes (tras ahorro) ÷ días · referencia = promedio mensual ÷ 30</p>
                    </div>
                    <div class="pp-year-kpi is-accent cp-savings-kpi" id="pp-daily-kpi">
                        <span class="pp-year-kpi-label">Referencia ÷ 30</span>
                        <strong class="pp-year-kpi-value" id="pp-daily-kpi-value">—</strong>
                        <span class="pp-year-kpi-hint">Promedio mensual ÷ 30</span>
                    </div>
                </div>
                <div class="pp-daily-month-cards" id="pp-daily-month-cards" aria-live="polite"></div>
                <div class="pp-charts pp-charts-daily">
                    <div class="chart-card pp-daily-chart-wide">
                        <h2>Mes a mes</h2>
                        <p class="chart-hint">Disponible neto del mes ÷ días · línea = referencia ÷ 30</p>
                        <div class="chart-wrap short"><canvas id="pp-daily-months"></canvas></div>
                    </div>
                </div>
            </div>
        </div>
        <script type="application/json" id="pp-workbook"><?= json_encode($pp['workbook'] ?? [], JSON_UNESCAPED_UNICODE) ?></script>
        <script type="application/json" id="pp-reality"><?= json_encode($realityPayload, JSON_UNESCAPED_UNICODE) ?></script>
        <script>
          (function () {
            function load() {
              var s = document.createElement('script');
              s.src = <?= json_encode(url('/assets/js/person-financial-plan.js') . '?v=' . (int) @filemtime(dirname(__DIR__, 3) . '/assets/js/person-financial-plan.js'), JSON_UNESCAPED_SLASHES) ?>;
              document.body.appendChild(s);
            }
            if (window.Chart) load();
            else window.addEventListener('load', load);
          })();
        </script>

    <?php else: ?>
        <div class="page-head mt">
            <h2><?= e($tabs[$tab] ?? $tab) ?></h2>
            <?php
            $returnToCobrables = '/personas/' . (int) $person['id'] . '?tab=cobrables&currency=' . urlencode($currency);
            $newLinks = [
                'cuentas' => '/cuentas/nueva?entity_id=' . $person['id'],
                'activos' => '/activos/nuevo?entity_id=' . $person['id'],
                'pasivos' => '/pasivos/nuevo?entity_id=' . $person['id'],
                'propiedades' => '/propiedades/nueva?entity_id=' . $person['id'],
                'cobrables' => '/cobrables/nueva?entity_id=' . $person['id'] . '&return_to=' . rawurlencode($returnToCobrables),
                'movimientos' => '/movimientos/nuevo?entity_id=' . $person['id'],
            ];
            if ($canWrite && isset($newLinks[$tab])): ?>
                <a class="btn" href="<?= e(url($newLinks[$tab])) ?>">Agregar</a>
            <?php endif; ?>
        </div>
        <div class="panel">
            <?php if (empty($tabRows)): ?>
                <p class="muted">Sin registros en esta pestaña.</p>
            <?php else: ?>
                <table class="table"<?= $tab === 'cobrables' ? ' id="entity-cobrables-table"' : '' ?>>
                    <thead>
                    <tr>
                        <?php if ($tab === 'cuentas'): ?><th>Nombre</th><th>Tipo</th><th>Moneda</th><th class="num">Saldo</th><?php endif; ?>
                        <?php if ($tab === 'activos'): ?><th>Nombre</th><th>Cuenta</th><th>Categoría</th><th class="num">Valor</th><?php endif; ?>
                        <?php if ($tab === 'pasivos'): ?><th>Nombre</th><th>Tipo</th><th class="num">Saldo</th><th>Estado</th><?php endif; ?>
                        <?php if ($tab === 'propiedades'): ?><th>Nombre</th><th>Tipo</th><th class="num">Valor</th><?php endif; ?>
                        <?php if ($tab === 'cobrables'): ?>
                            <th class="th-sort" data-sort="text" data-col="0" scope="col">Deudor <span class="sort-ind" aria-hidden="true"></span></th>
                            <th class="th-sort num" data-sort="num" data-col="1" scope="col">Pendiente <span class="sort-ind" aria-hidden="true"></span></th>
                            <th class="th-sort" data-sort="text" data-col="2" scope="col">Vence <span class="sort-ind" aria-hidden="true"></span></th>
                            <th class="th-sort" data-sort="num" data-col="3" scope="col">Estado <span class="sort-ind" aria-hidden="true"></span></th>
                            <?php if ($canWrite): ?><th></th><?php endif; ?>
                        <?php endif; ?>
                        <?php if ($tab === 'movimientos'): ?><th>Fecha</th><th>Tipo</th><th>Cuenta</th><th class="num">Importe</th><?php endif; ?>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($tabRows as $r): ?>
                        <tr>
                            <?php if ($tab === 'cuentas'): ?>
                                <td><?php if ($canWrite): ?><a href="<?= e(url('/cuentas/' . $r['id'] . '/editar')) ?>"><?= e($r['name']) ?></a><?php else: ?><?= e($r['name']) ?><?php endif; ?></td>
                                <td><?= e($r['type'] === 'mercadopago' ? 'Mercado Pago' : $r['type']) ?></td>
                                <td><?= e($r['currency_code']) ?></td>
                                <td class="num"><?= e(money($r['balance'], $r['currency_code'])) ?></td>
                            <?php elseif ($tab === 'activos'): ?>
                                <td><?php if ($canWrite): ?><a href="<?= e(url('/activos/' . $r['id'] . '/editar')) ?>"><?= e($r['name']) ?></a><?php else: ?><?= e($r['name']) ?><?php endif; ?></td>
                                <td><?= e($r['account_name'] ?? '—') ?></td>
                                <td><?= e($r['category']) ?></td>
                                <td class="num"><?= e(money($r['current_value'], $r['currency_code'])) ?></td>
                            <?php elseif ($tab === 'pasivos'): ?>
                                <td><?php if ($canWrite): ?><a href="<?= e(url('/pasivos/' . $r['id'] . '/editar')) ?>"><?= e($r['name']) ?></a><?php else: ?><?= e($r['name']) ?><?php endif; ?></td>
                                <td><?= e($r['liability_type']) ?></td>
                                <td class="num"><?= e(money($r['outstanding_amount'], $r['currency_code'])) ?></td>
                                <td><?= e($r['status']) ?></td>
                            <?php elseif ($tab === 'propiedades'): ?>
                                <td><?php if ($canWrite): ?><a href="<?= e(url('/propiedades/' . $r['id'] . '/editar')) ?>"><?= e($r['name']) ?></a><?php else: ?><?= e($r['name']) ?><?php endif; ?></td>
                                <td><?= e($r['property_type']) ?></td>
                                <td class="num"><?= e(money($r['current_value'], $r['currency_code'])) ?></td>
                            <?php elseif ($tab === 'cobrables'): ?>
                                <?php
                                $st = (string) ($r['status'] ?? 'current');
                                $stLabels = [
                                    'current' => 'Al día',
                                    'overdue' => 'Vencido',
                                    'paid' => 'Pago',
                                    'uncollectible' => 'Incobrable',
                                    'cancelled' => 'Cancelado',
                                ];
                                $stOrder = ['overdue' => 0, 'current' => 1, 'paid' => 2, 'uncollectible' => 3, 'cancelled' => 4];
                                $due = (string) ($r['due_date'] ?? '');
                                $returnTo = $returnToCobrables;
                                $editHref = '/cobrables/' . $r['id'] . '/editar?return_to=' . rawurlencode($returnTo);
                                ?>
                                <td data-sort="<?= e(mb_strtolower((string) $r['debtor_name'])) ?>"><?php if ($canWrite): ?><a href="<?= e(url($editHref)) ?>"><?= e($r['debtor_name']) ?></a><?php else: ?><?= e($r['debtor_name']) ?><?php endif; ?></td>
                                <td class="num" data-sort="<?= e((string) (float) $r['outstanding_amount']) ?>"><?= e(money($r['outstanding_amount'], $r['currency_code'])) ?></td>
                                <td data-sort="<?= e($due !== '' ? $due : '9999-99-99') ?>"><?= e($due !== '' ? $due : '—') ?></td>
                                <td data-sort="<?= (int) ($stOrder[$st] ?? 9) ?>"><span class="badge"><?= e($stLabels[$st] ?? $st) ?></span></td>
                                <?php if ($canWrite): ?>
                                <td class="actions">
                                    <?php if ($st !== 'paid'): ?>
                                        <a href="<?= e(url('/cobrables/' . $r['id'] . '/pagar?return_to=' . rawurlencode($returnTo))) ?>">Cobrar</a>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                                <?php endif; ?>
                            <?php elseif ($tab === 'movimientos'): ?>
                                <td><?= e(substr((string) $r['occurred_at'], 0, 16)) ?></td>
                                <td><?= e(movement_type_label((string) $r['type'])) ?></td>
                                <td><?= e($r['account_name'] ?? '—') ?></td>
                                <td class="num"><?= money_flow($r['amount'], $r['currency_code'], (string) $r['type']) ?></td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if ($tab === 'cobrables'): ?>
                <script>
                (() => {
                  const table = document.getElementById('entity-cobrables-table');
                  if (!table) return;
                  const tbody = table.tBodies[0];
                  const headers = [...table.querySelectorAll('th.th-sort')];
                  let activeCol = null;
                  let asc = true;
                  const valueOf = (row, col, type) => {
                    const raw = row.cells[col]?.dataset.sort ?? '';
                    if (type === 'num') {
                      const n = Number(raw);
                      return Number.isFinite(n) ? n : 0;
                    }
                    return String(raw);
                  };
                  headers.forEach((th) => {
                    th.style.cursor = 'pointer';
                    th.addEventListener('click', () => {
                      const col = Number(th.dataset.col);
                      const type = th.dataset.sort || 'text';
                      if (activeCol === col) asc = !asc;
                      else { activeCol = col; asc = true; }
                      headers.forEach((h) => h.classList.remove('is-asc', 'is-desc'));
                      th.classList.add(asc ? 'is-asc' : 'is-desc');
                      const rows = [...tbody.rows];
                      rows.sort((a, b) => {
                        const av = valueOf(a, col, type);
                        const bv = valueOf(b, col, type);
                        const cmp = typeof av === 'number' && typeof bv === 'number'
                          ? av - bv
                          : String(av).localeCompare(String(bv), 'es', { sensitivity: 'base', numeric: true });
                        return asc ? cmp : -cmp;
                      });
                      rows.forEach((r) => tbody.appendChild(r));
                    });
                  });
                })();
                </script>
                <?php endif; ?>
                <?php if ($tab === 'movimientos' && !empty($pagination)): ?>
                    <?php
                    $pagerRoute = '/personas/' . (int) $person['id'];
                    $pagerQuery = [
                        'tab' => 'movimientos',
                        'currency' => $currency,
                    ];
                    require __DIR__ . '/../partials/pagination.php';
                    ?>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</section>
