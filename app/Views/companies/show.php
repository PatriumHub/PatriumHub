<?php
$isServices = !empty($isServices) || (($company['business_model'] ?? 'services') === 'services');
$tabs = [
    'resumen' => 'Resumen',
    'cuentas' => 'Cuentas',
];
if ($isServices) {
    $tabs['clientes'] = 'Clientes';
}
$tabs += [
    'estados-proyeccion' => 'Estados y proyección',
    'activos' => 'Activos varios',
    'pasivos' => 'Pasivos',
    'propiedades' => 'Propiedades',
    'inventario' => 'Inventario',
    'cobrables' => 'Cobrables',
    'movimientos' => 'Movimientos',
    'integraciones' => 'Integraciones',
    'participaciones' => 'Participaciones',
    'valuacion' => 'Valuación',
];
$companyPath = '/empresas/' . (int) $company['id'];
$canWrite = !empty($canWrite);
$companyClients = $companyClients ?? [];
$valuations = $valuations ?? [];
$valuation = $valuation ?? ['value' => 0, 'source' => '', 'is_manual' => false, 'auto_value' => 0];
$health = $health ?? ['status' => 'warn', 'label' => '—', 'reasons' => []];
$net = (float) ($summary['net_worth'] ?? 0);
?>
<section class="page">
    <div class="page-head">
        <div>
            <h1><?= e($company['name']) ?></h1>
            <p class="lede"><?= e($company['legal_name'] ?? 'Empresa') ?><?= $company['industry'] ? ' · ' . e($company['industry']) : '' ?><?= $canWrite ? '' : ' · Solo lectura' ?></p>
        </div>
        <div class="actions">
            <?php if ($canWrite): ?>
            <form method="post" action="<?= e(url('/dashboard/snapshot')) ?>" class="inline">
                <?= csrf_field() ?>
                <input type="hidden" name="mode" value="entity">
                <input type="hidden" name="entity_id" value="<?= (int) $company['id'] ?>">
                <input type="hidden" name="currency" value="<?= e($currency) ?>">
                <input type="hidden" name="redirect_to" value="<?= e($companyPath . '?tab=' . urlencode((string) $tab) . '&currency=' . urlencode($currency)) ?>">
                <button type="submit" class="btn ghost">Capturar snapshot</button>
            </form>
            <a class="btn ghost" href="<?= e(url($companyPath . '/editar')) ?>">Editar</a>
            <?php endif; ?>
            <a class="btn ghost" href="<?= e(url('/empresas')) ?>">Volver</a>
        </div>
    </div>
    <?php require __DIR__ . '/../partials/alerts.php'; ?>

    <nav class="tabs">
        <?php foreach ($tabs as $key => $label): ?>
            <a class="<?= $tab === $key ? 'is-active' : '' ?>"
               href="<?= e(url($companyPath . '?tab=' . urlencode($key) . '&currency=' . urlencode($currency))) ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
    </nav>

    <?php if ($tab === 'clientes'): ?>
        <?php
        $clientsUi = $clientsUi ?? [
            'rows' => [], 'sort' => 'name', 'dir' => 'asc',
            'stats' => ['active' => 0, 'inactive' => 0, 'total_all' => 0],
        ];
        $cSort = (string) ($clientsUi['sort'] ?? 'name');
        $cDir = (string) ($clientsUi['dir'] ?? 'asc');
        $cStats = $clientsUi['stats'] ?? ['active' => 0, 'inactive' => 0, 'total_all' => 0];
        $sortUrl = static function (string $col) use ($companyPath, $currency, $cSort, $cDir): string {
            $nextDir = ($cSort === $col && $cDir === 'asc') ? 'desc' : 'asc';
            $q = ['tab' => 'clientes', 'currency' => $currency, 'sort' => $col, 'dir' => $nextDir];
            return url($companyPath . '?' . http_build_query($q));
        };
        $sortMark = static function (string $col) use ($cSort, $cDir): string {
            if ($cSort !== $col) {
                return '';
            }
            return $cDir === 'desc' ? ' ↓' : ' ↑';
        };
        ?>
        <div class="panel mt">
            <div class="page-head">
                <div>
                    <h2>Clientes</h2>
                    <p class="muted" style="margin:0">
                        Lista y montos desde <strong>Estados y proyección</strong>.
                        Acá completás contacto, contrato PDF, docs y estado.
                    </p>
                </div>
                <?php if ($canWrite): ?>
                    <a class="btn" href="<?= e(url($companyPath . '?tab=estados-proyeccion')) ?>">Ir a proyección</a>
                <?php endif; ?>
            </div>

            <div class="stat-row cols-3" style="margin:1.25rem 0 1rem">
                <div class="stat tone-green">
                    <span class="stat-label">Clientes activos</span>
                    <span class="stat-value"><?= (int) ($cStats['active'] ?? 0) ?></span>
                </div>
                <div class="stat tone-rose">
                    <span class="stat-label">Clientes inactivos</span>
                    <span class="stat-value"><?= (int) ($cStats['inactive'] ?? 0) ?></span>
                </div>
                <div class="stat tone-blue">
                    <span class="stat-label">Total desde el comienzo</span>
                    <span class="stat-value"><?= (int) ($cStats['total_all'] ?? 0) ?></span>
                </div>
            </div>

            <?php if ($companyClients === []): ?>
                <p class="muted">
                    No hay clientes con nombre en Estados y proyección.
                    <?php if ($canWrite): ?>
                        Agregá filas de cliente ahí y guardá el plan.
                    <?php endif; ?>
                </p>
            <?php else: ?>
                <table class="table">
                    <thead>
                    <tr>
                        <th><a class="th-sort" href="<?= e($sortUrl('name')) ?>">Cliente<?= e($sortMark('name')) ?></a></th>
                        <th><a class="th-sort" href="<?= e($sortUrl('status')) ?>">Estado<?= e($sortMark('status')) ?></a></th>
                        <th><a class="th-sort" href="<?= e($sortUrl('contact')) ?>">Contacto<?= e($sortMark('contact')) ?></a></th>
                        <th>Email</th>
                        <th>Teléfono</th>
                        <th class="num"><a class="th-sort" href="<?= e($sortUrl('total')) ?>">Total<?= e($sortMark('total')) ?></a></th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($companyClients as $cl): ?>
                        <?php $active = (int) ($cl['is_active'] ?? 1) === 1; ?>
                        <tr>
                            <td>
                                <?php if (!empty($cl['id'])): ?>
                                    <a href="<?= e(url($companyPath . '/clientes/' . (int) $cl['id'])) ?>"><?= e($cl['name']) ?></a>
                                <?php else: ?>
                                    <?= e($cl['name']) ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge <?= $active ? 'badge-ok' : 'badge-danger' ?>">
                                    <?= $active ? 'Activo' : 'Inactivo' ?>
                                </span>
                            </td>
                            <td><?= e(($cl['contact_name'] ?? '') !== '' ? $cl['contact_name'] : '—') ?></td>
                            <td><?= e(($cl['email'] ?? '') !== '' ? $cl['email'] : '—') ?></td>
                            <td><?= e(($cl['phone'] ?? '') !== '' ? $cl['phone'] : '—') ?></td>
                            <td class="num"><?= e(money($cl['value_total'] ?? 0, $currency)) ?></td>
                            <td class="actions">
                                <?php if (!empty($cl['id'])): ?>
                                    <div class="row-icon-actions">
                                        <?= icon_action_link(url($companyPath . '/clientes/' . (int) $cl['id']), 'eye', 'Ver') ?>
                                        <?php if ($canWrite): ?>
                                            <?= icon_action_link(url($companyPath . '/clientes/' . (int) $cl['id'] . '/editar'), 'edit', 'Ficha') ?>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

    <?php elseif ($tab === 'estados-proyeccion'): ?>
        <?php
        $fp = $financialPlan ?? null;
        $fpWorkbook = $fp['workbook'] ?? ['version' => 2, 'activeYear' => 0, 'years' => []];
        $fpMonths = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
        ?>
        <?php
        $fpTemplate = (($fpWorkbook['template'] ?? 'services') === 'products') ? 'products' : 'services';
        $fpIsProducts = $fpTemplate === 'products';
        ?>
        <div class="mt" id="fp-root"
             data-save-url="<?= e(url($companyPath . '/estados-proyeccion')) ?>"
             data-csrf="<?= e(csrf_token()) ?>"
             data-template="<?= e($fpTemplate) ?>"
             data-readonly="<?= $canWrite ? '0' : '1' ?>">

            <details class="fp-fold panel" id="fp-fold-compare" open>
                <summary class="fp-fold-summary">
                    <div>
                        <h2 style="margin:0">Comparativa por año</h2>
                        <p class="muted" style="margin:0.2rem 0 0">Línea y barras por mes; torta anual.</p>
                    </div>
                </summary>
                <div class="fp-fold-body">
                    <div id="fp-all-years-kpis" class="fp-all-kpis" aria-live="polite"></div>

                    <div class="fp-chart-toolbar">
                        <div class="fp-chart-tabs" id="fp-chart-tabs" role="tablist">
                            <button type="button" class="fp-sheet-tab is-active" data-chart="line">Línea</button>
                            <button type="button" class="fp-sheet-tab" data-chart="bar">Barras</button>
                            <button type="button" class="fp-sheet-tab" data-chart="doughnut">Torta</button>
                        </div>
                        <label class="fp-pie-year-wrap" id="fp-pie-year-wrap">
                            Año
                            <select id="fp-pie-year" class="fp-rate-input" style="width:auto;min-width:5.5rem"></select>
                        </label>
                    </div>
                    <div class="fp-chart-wrap">
                        <canvas id="fp-compare-chart" aria-label="Comparativa por mes"></canvas>
                    </div>
                    <p class="muted fp-chart-hint" id="fp-chart-hint">Pasá el mouse para ver montos. En la leyenda podés ocultar series.</p>

                    <div class="fp-section-head mt">
                        <div>
                            <h3 style="margin:0">Comparativa entre años</h3>
                            <p class="muted" style="margin:0.2rem 0 0">Ganancia neta, egresos y ahorro anuales de todos los años.</p>
                        </div>
                        <div class="fp-chart-tabs" id="fp-years-chart-tabs" role="tablist">
                            <button type="button" class="fp-sheet-tab is-active" data-years-chart="bar">Barras</button>
                            <button type="button" class="fp-sheet-tab" data-years-chart="line">Línea</button>
                        </div>
                    </div>
                    <div class="fp-chart-wrap fp-chart-wrap-sm">
                        <canvas id="fp-years-chart" aria-label="Comparativa entre años"></canvas>
                    </div>
                </div>
            </details>

            <details class="fp-fold panel mt" id="fp-fold-detail" open>
                <summary class="fp-fold-summary">
                    <div>
                        <h2 style="margin:0">Detalle del año</h2>
                        <p class="muted" style="margin:0.2rem 0 0"><?= $canWrite ? 'Ingresos, egresos y resumen mensual editables.' : 'Ingresos, egresos y resumen mensual (solo lectura).' ?></p>
                    </div>
                </summary>
                <div class="fp-fold-body">
                    <div class="fp-toolbar">
                        <div id="fp-year-tabs" class="fp-sheet-tabs" style="margin:0"></div>
                        <div class="fp-toolbar-actions">
                            <label class="fp-rate-label">Ahorro
                                <input type="number" id="fp-savings-rate" min="0" max="100" step="0.5" class="fp-rate-input"<?= $canWrite ? '' : ' disabled' ?>> %
                            </label>
                            <span id="fp-status" class="fp-status"></span>
                            <?php if ($canWrite): ?>
                            <button type="button" class="btn ghost" id="fp-add-year">+ Año</button>
                            <button type="button" class="btn" id="fp-save">Guardar</button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div id="fp-kpis" class="fp-kpis"></div>

                    <div id="fp-clients-section" class="<?= $fpIsProducts ? 'is-hidden' : '' ?>">
                        <div class="fp-section-head mt">
                            <div>
                                <h3 style="margin:0">Ingresos por cliente</h3>
                                <p class="muted" style="margin:0.2rem 0 0">Montos mensuales proyectados o reales.</p>
                            </div>
                            <?php if ($canWrite): ?>
                            <button type="button" class="btn ghost" id="fp-add-client">+ Cliente</button>
                            <?php endif; ?>
                        </div>
                        <div class="fp-table-scroll">
                            <table class="table fp-edit-table">
                                <thead>
                                <tr>
                                    <th>Cliente</th>
                                    <?php foreach ($fpMonths as $m): ?><th class="num"><?= e($m) ?></th><?php endforeach; ?>
                                    <th class="num">Total</th>
                                    <th></th>
                                </tr>
                                </thead>
                                <tbody id="fp-clients-body"></tbody>
                            </table>
                        </div>
                    </div>

                    <div id="fp-products-income-section" class="<?= $fpIsProducts ? '' : 'is-hidden' ?>">
                        <div class="fp-section-head mt">
                            <div>
                                <h3 style="margin:0">Ingresos del mes</h3>
                                <p class="muted" style="margin:0.2rem 0 0">Total de ingresos por mes (sin desglose por cliente).</p>
                            </div>
                        </div>
                        <div class="fp-table-scroll">
                            <table class="table fp-edit-table">
                                <thead>
                                <tr>
                                    <th>Concepto</th>
                                    <?php foreach ($fpMonths as $m): ?><th class="num"><?= e($m) ?></th><?php endforeach; ?>
                                    <th class="num">Total</th>
                                </tr>
                                </thead>
                                <tbody id="fp-products-income-body"></tbody>
                            </table>
                        </div>
                    </div>

                    <div class="fp-section-head mt">
                        <div>
                            <h3 style="margin:0">Egresos / costos</h3>
                            <p class="muted" style="margin:0.2rem 0 0">Salarios, proveedores y costos fijos o variables.</p>
                        </div>
                        <?php if ($canWrite): ?>
                        <button type="button" class="btn ghost" id="fp-add-expense">+ Costo</button>
                        <?php endif; ?>
                    </div>
                    <div class="fp-table-scroll">
                        <table class="table fp-edit-table">
                            <thead>
                            <tr>
                                <th>Concepto</th>
                                <?php foreach ($fpMonths as $m): ?><th class="num"><?= e($m) ?></th><?php endforeach; ?>
                                <th class="num">Total</th>
                                <th></th>
                            </tr>
                            </thead>
                            <tbody id="fp-expenses-body"></tbody>
                        </table>
                    </div>

                    <div class="fp-section-head mt">
                        <div>
                            <h3 style="margin:0">Resumen mensual</h3>
                            <p class="muted" style="margin:0.2rem 0 0">Se calcula solo; no hace falta editarlo.</p>
                        </div>
                        <label class="fp-split-label">Dividir ganancia entre
                            <input type="number" id="fp-profit-split-n" class="fp-split-input" min="1" max="99" step="1" value="1"<?= $canWrite ? '' : ' disabled' ?>>
                            personas
                        </label>
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
                            <tbody id="fp-summary-body"></tbody>
                        </table>
                    </div>
                    <p id="fp-net-month-count" class="fp-net-month-count" hidden></p>
                    <p class="fp-hint">
                        Ctrl+S guarda.
                        <?php if (!empty($fp['updated_at'])): ?>
                            Último guardado: <?= e((string) $fp['updated_at']) ?>
                        <?php endif; ?>
                    </p>
                </div>
            </details>

            <div class="pp-block-burden mt-lg">
                <div class="cp-block-head">
                    <div>
                        <h2>Carga de egresos</h2>
                        <p class="muted">Egresos y ahorro sobre ingresos; el verde es el disponible neto (ganancia)</p>
                    </div>
                </div>
                <div class="pp-charts pp-charts-burden">
                    <div class="chart-card pp-burden-total">
                        <h2>Egresos vs ingresos</h2>
                        <p class="chart-hint">Total del año activo (tras meta de ahorro)</p>
                        <div class="burden-total-layout">
                            <div class="chart-doughnut">
                                <div class="chart-wrap short"><canvas id="fp-burden-total"></canvas></div>
                                <div class="chart-doughnut-center">
                                    <strong id="fp-burden-kpi-value">—</strong>
                                    <span>egresos</span>
                                </div>
                            </div>
                            <ul class="burden-legend" id="fp-burden-legend" aria-live="polite"></ul>
                        </div>
                    </div>
                    <div class="chart-card pp-burden-months">
                        <h2>Mes a mes</h2>
                        <p class="chart-hint">Egresos + ahorro + disponible neto</p>
                        <div class="chart-wrap short"><canvas id="fp-burden-months"></canvas></div>
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
                        <div class="chart-wrap tall"><canvas id="fp-burden-categories"></canvas></div>
                    </div>
                </div>
            </div>

            <div class="pp-block-daily mt-lg">
                <div class="cp-block-head">
                    <div>
                        <h2>Gasto diario máximo</h2>
                        <p class="muted">Disponible neto (ganancia) de cada mes ÷ días · referencia = promedio mensual ÷ 30</p>
                    </div>
                    <div class="pp-year-kpi is-accent cp-savings-kpi" id="fp-daily-kpi">
                        <span class="pp-year-kpi-label">Referencia ÷ 30</span>
                        <strong class="pp-year-kpi-value" id="fp-daily-kpi-value">—</strong>
                        <span class="pp-year-kpi-hint">Promedio mensual ÷ 30</span>
                    </div>
                </div>
                <div class="pp-daily-month-cards" id="fp-daily-month-cards" aria-live="polite"></div>
                <div class="pp-charts pp-charts-daily">
                    <div class="chart-card pp-daily-chart-wide">
                        <h2>Mes a mes</h2>
                        <p class="chart-hint">Disponible neto del mes ÷ días · línea = referencia ÷ 30</p>
                        <div class="chart-wrap short"><canvas id="fp-daily-months"></canvas></div>
                    </div>
                </div>
            </div>

            <script type="application/json" id="fp-workbook"><?= json_encode($fpWorkbook, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?></script>
            <?php /* Cargar DESPUÉS de Chart.js (defer al final del layout) para que los gráficos monten bien */ ?>
            <script>
            (function () {
              var src = <?= json_encode(url('/assets/js/financial-plan.js') . '?v=' . (int) @filemtime(dirname(__DIR__, 3) . '/assets/js/financial-plan.js'), JSON_UNESCAPED_SLASHES) ?>;
              function loadFp() {
                if (document.querySelector('script[data-fp-plan]')) return;
                var s = document.createElement('script');
                s.src = src;
                s.dataset.fpPlan = '1';
                document.body.appendChild(s);
              }
              function tryLoad() {
                if (window.Chart) loadFp();
                else window.addEventListener('load', loadFp);
              }
              if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', tryLoad);
              } else {
                tryLoad();
              }
            })();
            </script>
        </div>

    <?php elseif ($tab === 'participaciones'): ?>
        <div class="panel mt">
            <div class="page-head">
                <div>
                    <h2>Dueños / participaciones</h2>
                    <p class="muted" style="margin:0">Quién es dueño y en qué %. La valuación de la empresa está en su pestaña.</p>
                </div>
                <?php if ($canWrite): ?>
                <a class="btn" href="<?= e(url('/participaciones/nueva?company_entity_id=' . $company['id'])) ?>">+ Dueño</a>
                <?php endif; ?>
            </div>
            <?php if (empty($ownerships)): ?>
                <p class="muted">Sin dueños cargados. El patrimonio personal no atribuirá valor de esta empresa.</p>
            <?php else: ?>
                <table class="table">
                    <thead><tr><th>Dueño</th><th class="num">%</th><?php if ($canWrite): ?><th></th><?php endif; ?></tr></thead>
                    <tbody>
                    <?php foreach ($ownerships as $o): ?>
                        <tr>
                            <td><?= e($o['owner_name']) ?></td>
                            <td class="num"><?= e(number_format((float) $o['ownership_pct'], 2, ',', '.')) ?>%</td>
                            <?php if ($canWrite): ?>
                            <td class="actions"><?= icon_action_link(url('/participaciones/' . $o['id'] . '/editar'), 'edit', 'Editar') ?></td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
            <p class="mt"><a href="<?= e(url($companyPath . '?tab=valuacion&currency=' . urlencode($currency))) ?>">Ver valuación de la empresa →</a></p>
        </div>

    <?php elseif ($tab === 'valuacion'): ?>
        <?php
        $sv = $salesValuation ?? [
            'wc_sales' => 0, 'mp_income' => 0, 'total_sales' => 0, 'multiple' => 1,
            'suggested' => 0, 'book_value' => $net, 'months' => 12, 'has_sales' => false,
        ];
        ?>
        <div class="company-hero mt">
            <div class="company-hero-main">
                <span class="stat-label">Valuación vigente</span>
                <div class="company-hero-value"><?= e(money($valuation['value'], $currency)) ?></div>
                <p class="muted"><?= e($valuation['source']) ?><?= !empty($valuation['valued_at']) ? ' · ' . e($valuation['valued_at']) : '' ?></p>
            </div>
            <div class="company-hero-main">
                <span class="stat-label">Sugerida por ventas (<?= (int) $sv['months'] ?>m × <?= e(number_format((float) $sv['multiple'], 2, ',', '.')) ?>)</span>
                <div class="company-hero-value" style="font-size:clamp(1.6rem,3vw,2.4rem)"><?= e(money($sv['suggested'], $currency)) ?></div>
                <p class="muted">No se suma al patrimonio: es criterio de negocio para participaciones</p>
            </div>
        </div>
        <div class="grid-2">
            <div class="panel">
                <h2>Automática (patrimonio neto)</h2>
                <p class="muted">Activos − pasivos en <?= e($currency) ?>. Es el default si no hay override.</p>
                <ul class="plain">
                    <li>Patrimonio neto: <strong><?= e(money($net, $currency)) ?></strong></li>
                    <li>Valuación auto: <strong><?= e(money($valuation['auto_value'] ?? $net, $currency)) ?></strong></li>
                </ul>
                <?php if ($canWrite): ?>
                <form method="post" action="<?= e(url('/valuaciones')) ?>" class="mt">
                    <?= csrf_field() ?>
                    <input type="hidden" name="company_entity_id" value="<?= (int) $company['id'] ?>">
                    <input type="hidden" name="currency_code" value="<?= e($currency) ?>">
                    <input type="hidden" name="method" value="patrimonio_neto">
                    <button type="submit" class="btn ghost">Usar a patrimonio neto</button>
                </form>
                <?php endif; ?>
            </div>
            <div class="panel">
                <h2>Por múltiplo de ventas</h2>
                <p class="muted">WC + Mercado Pago (últimos 12 meses). La caja no es venta: es saldo de efectivo.</p>
                <ul class="plain">
                    <li>Ventas WC: <strong><?= e(money($sv['wc_sales'], $currency)) ?></strong></li>
                    <li>Ventas MP: <strong><?= e(money($sv['mp_income'], $currency)) ?></strong></li>
                    <li>Total 12m: <strong><?= e(money($sv['total_sales'], $currency)) ?></strong></li>
                    <li>Sugerido: <strong><?= e(money($sv['suggested'], $currency)) ?></strong></li>
                </ul>
                <?php if (!$sv['has_sales']): ?>
                    <p class="muted mt">Sin ventas sync en esta moneda. Conectá WC/MP y sincronizá.</p>
                <?php endif; ?>
                <?php if ($canWrite): ?>
                <form method="post" action="<?= e(url('/valuaciones')) ?>" class="form-grid mt" style="padding:0;box-shadow:none;border:0;background:transparent">
                    <?= csrf_field() ?>
                    <input type="hidden" name="company_entity_id" value="<?= (int) $company['id'] ?>">
                    <input type="hidden" name="currency_code" value="<?= e($currency) ?>">
                    <input type="hidden" name="method" value="multiplo_ventas">
                    <label>Múltiplo
                        <input name="sales_multiple" type="number" step="0.1" min="0" value="<?= e((string) $sv['multiple']) ?>">
                    </label>
                    <div class="form-actions" style="align-items:end">
                        <button type="submit" class="btn" <?= $sv['has_sales'] ? '' : 'disabled' ?>>Aplicar como valuación</button>
                    </div>
                </form>
                <?php endif; ?>
            </div>
        </div>
        <?php if ($canWrite): ?>
        <div class="panel mt">
            <h2>Override manual</h2>
            <p class="muted">Cualquier número fijo; las participaciones usarán este valor.</p>
            <form method="post" action="<?= e(url('/valuaciones')) ?>" class="form-grid" style="padding:0;box-shadow:none;border:0;background:transparent">
                <?= csrf_field() ?>
                <input type="hidden" name="company_entity_id" value="<?= (int) $company['id'] ?>">
                <label>Fecha
                    <input type="date" name="valued_at" value="<?= e(date('Y-m-d')) ?>" required>
                </label>
                <label>Moneda
                    <select name="currency_code">
                        <?php foreach ($currencies as $c): ?>
                            <option value="<?= e($c['code']) ?>"<?= selected($currency, $c['code']) ?>><?= e($c['code']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="span-2">Valor total *
                    <input name="total_value" required value="">
                </label>
                <input type="hidden" name="method" value="manual">
                <label class="span-2">Notas
                    <textarea name="notes" rows="2"></textarea>
                </label>
                <div class="form-actions span-2"><button type="submit">Guardar override</button></div>
            </form>
        </div>
        <?php endif; ?>
        <?php if (!empty($valuations)): ?>
            <div class="panel mt">
                <h2>Historial</h2>
                <table class="table">
                    <thead><tr><th>Fecha</th><th>Método</th><th class="num">Valor</th><th>Notas</th></tr></thead>
                    <tbody>
                    <?php foreach ($valuations as $v):
                        $methodLabel = match ((string) ($v['method'] ?? '')) {
                            'patrimonio_neto' => 'Automática',
                            'multiplo_ventas' => 'Múltiplo ventas',
                            'manual' => 'Manual',
                            default => (string) ($v['method'] ?? '—'),
                        };
                        ?>
                        <tr>
                            <td><?= e($v['valued_at']) ?></td>
                            <td><?= e($methodLabel) ?></td>
                            <td class="num"><?= e(money($v['total_value'], $v['currency_code'])) ?></td>
                            <td class="muted"><?= e($v['notes'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

    <?php elseif ($tab === 'resumen'): ?>
        <?php
        $hide = hide_amounts();
        $extras = $companyExtras ?? [
            'cash' => 0, 'en_caja' => 0, 'mp' => 0, 'inventory' => 0, 'sales' => 0,
            'mp_sales' => 0, 'cash_sales' => 0, 'sales_total' => 0, 'orders' => 0,
        ];
        $wc = is_array($salesMetrics ?? null) && isset($salesMetrics['rows'])
            ? $salesMetrics
            : ['labels' => [], 'sales' => [], 'orders' => [], 'rows' => [], 'total_sales' => 0, 'total_orders' => 0];
        // Resumen: histórico completo (no el recorte de 12m del gráfico WC).
        $wcSales = (float) ($extras['sales'] ?? 0);
        $mpSales = (float) ($extras['mp_sales'] ?? 0);
        $salesTotal = (float) ($extras['sales_total'] ?? ($wcSales + $mpSales));
        $companyAccounts = $companyAccounts ?? [];
        $enCajaSeries = $enCajaSeries ?? ['labels' => [], 'sales' => [], 'total_sales' => 0.0];
        $compLabels = ['Cuentas', 'Activos varios', 'Propiedades', 'Cobrables', 'Inventario'];
        $compValues = [
            (float) ($summary['accounts'] ?? 0),
            (float) ($summary['assets'] ?? 0),
            (float) ($summary['properties'] ?? 0),
            (float) ($summary['receivables'] ?? 0),
            (float) ($summary['inventories'] ?? 0),
        ];
        $companyChart = [
            'hide' => $hide,
            'entityId' => (int) $company['id'],
            'baseUrl' => base_path() . '/index.php',
            'composition' => [
                'labels' => $compLabels,
                'values' => $compValues,
                'routes' => ['/cuentas', '/activos', '/propiedades', '/cobrables', '/inventario'],
            ],
            'companyExtras' => [
                'labels' => ['Bancos/líquido', 'Saldo MP', 'Stock'],
                'values' => [
                    (float) $extras['cash'],
                    (float) $extras['mp'],
                    (float) $extras['inventory'],
                ],
            ],
            'salesSplit' => [
                'labels' => ['WooCommerce'],
                'values' => [$wcSales],
            ],
            'liquidity' => [
                'labels' => ['Liquidez', 'Resto neto'],
                'values' => [
                    (float) ($summary['liquidity'] ?? 0),
                    max(0, $net - (float) ($summary['liquidity'] ?? 0)),
                ],
            ],
            'wooSales' => [
                'labels' => $wc['labels'],
                'sales' => $wc['sales'],
                'orders' => $wc['orders'],
            ],
            'mpSales' => ['labels' => [], 'sales' => []],
            'cashSales' => $enCajaSeries,
        ];
        $hasWcSales = ($wc['labels'] ?? []) !== [];
        $hasEnCajaChart = array_sum(array_map('abs', $companyChart['cashSales']['sales'] ?? [])) > 0.009;
        $hasSalesSplit = $wcSales > 0.009;
        $healthClass = match ($health['status'] ?? 'warn') {
            'ok' => 'is-ok',
            'bad' => 'is-bad',
            default => 'is-warn',
        };
        ?>
        <form method="get" class="filters inline-filters mt" action="<?= e(form_action()) ?>">
            <?= route_field($companyPath) ?>
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
            <div class="company-hero-main">
                <span class="stat-label">Patrimonio neto</span>
                <div class="company-hero-value"><?= e(money($net, $currency)) ?></div>
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

        <div class="company-section">
            <div class="stat-row">
                <div class="stat tone-blue">
                    <span class="stat-label">Activos</span>
                    <span class="stat-value"><?= e(money($summary['total_assets'] ?? 0, $currency)) ?></span>
                </div>
                <div class="stat tone-rose">
                    <span class="stat-label">Pasivos</span>
                    <span class="stat-value"><?= e(money($summary['liabilities'] ?? 0, $currency)) ?></span>
                </div>
                <div class="stat tone-teal">
                    <span class="stat-label">Liquidez</span>
                    <span class="stat-value"><?= e(money($summary['liquidity'] ?? 0, $currency)) ?></span>
                </div>
                <div class="stat tone-violet">
                    <span class="stat-label">Valuación</span>
                    <span class="stat-value"><?= e(money($valuation['value'], $currency)) ?></span>
                </div>
            </div>
        </div>

        <div class="company-section">
            <div class="stat-row cols-3">
                <div class="stat tone-green">
                    <span class="stat-label">Bancos / líquido</span>
                    <span class="stat-value"><?= e(money($extras['cash'], $currency)) ?></span>
                </div>
                <div class="stat tone-blue">
                    <span class="stat-label">Saldo Mercado Pago</span>
                    <span class="stat-value"><?= e(money($extras['mp'], $currency)) ?></span>
                </div>
                <div class="stat tone-violet">
                    <span class="stat-label">Stock</span>
                    <span class="stat-value"><?= e(money($extras['inventory'], $currency)) ?></span>
                </div>
            </div>
        </div>

        <div class="company-section">
            <div class="stat-row cols-3">
                <div class="stat tone-amber">
                    <span class="stat-label">Ventas WooCommerce</span>
                    <span class="stat-value" style="font-size:1.25rem"><?= e(money($wcSales, $currency)) ?></span>
                </div>
                <div class="stat tone-teal">
                    <span class="stat-label">Pedidos e-commerce</span>
                    <span class="stat-value" style="font-size:1.25rem"><?= (int) ($extras['orders'] ?? 0) ?></span>
                </div>
                <div class="stat tone-blue">
                    <span class="stat-label">Cuentas</span>
                    <span class="stat-value" style="font-size:1.1rem">
                        <a href="<?= e(url($companyPath . '?tab=cuentas&currency=' . urlencode($currency))) ?>">Ver saldos →</a>
                    </span>
                </div>
            </div>
        </div>

        <?php if ($canWrite): ?>
        <div class="company-section panel tone-coral">
            <div class="page-head" style="margin-bottom:0.75rem">
                <h2 style="margin:0">Movimiento de caja</h2>
            </div>
            <p class="muted" style="margin-top:0">
                Ingreso o egreso asociado a una cuenta. El saldo y el patrimonio se actualizan en esa cuenta (Bancos / líquido, MP, etc.).
            </p>
            <?php if ($companyAccounts === []): ?>
                <p class="muted">
                    No hay cuentas en esta empresa.
                    <a href="<?= e(url('/cuentas/nueva?entity_id=' . (int) $company['id'] . '&type=cash')) ?>">Creá una cuenta Efectivo/Caja</a>
                    u otra cuenta para asociar el movimiento.
                </p>
            <?php else: ?>
            <?php
            $preferredCashId = 0;
            foreach ($companyAccounts as $acc) {
                if (($acc['type'] ?? '') === 'cash' && ($acc['currency_code'] ?? '') === $currency) {
                    $preferredCashId = (int) $acc['id'];
                    break;
                }
            }
            ?>
            <form method="post" action="<?= e(url($companyPath . '/caja')) ?>" class="filters inline-filters" style="margin:0">
                <?= csrf_field() ?>
                <input type="hidden" name="currency_code" value="<?= e($currency) ?>">
                <label>Tipo
                    <select name="tx_type" required>
                        <option value="income">Ingreso</option>
                        <option value="expense">Egreso</option>
                    </select>
                </label>
                <label>Cuenta *
                    <select name="account_id" required>
                        <option value="">— elegir —</option>
                        <?php foreach ($companyAccounts as $acc): ?>
                            <option value="<?= (int) $acc['id'] ?>"<?= selected($preferredCashId, (int) $acc['id']) ?>>
                                <?= e($acc['name']) ?>
                                · <?= e($acc['currency_code']) ?>
                                <?= ($acc['type'] ?? '') === 'cash' ? ' · efectivo' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Importe
                    <input name="amount" type="number" step="0.01" min="0.01" required placeholder="0,00">
                </label>
                <label>Fecha
                    <input name="occurred_at" type="datetime-local" value="<?= e(date('Y-m-d\TH:i')) ?>" required>
                </label>
                <label>Nota
                    <input name="description" placeholder="Opcional">
                </label>
                <div class="form-actions" style="align-items:end">
                    <button type="submit">Registrar</button>
                </div>
            </form>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="charts-grid company-section">
            <div class="chart-card tone-amber">
                <h2>Ventas WooCommerce</h2>
                <?php if ($hasWcSales && !$hide): ?>
                    <div class="chart-wrap"><canvas id="chartWooSales"></canvas></div>
                <?php elseif ($hide): ?>
                    <div class="chart-empty">Cifras ocultas</div>
                <?php else: ?>
                    <div class="chart-empty">Sin ventas sync.</div>
                <?php endif; ?>
            </div>
            <div class="chart-card tone-coral">
                <h2>Flujo en caja</h2>
                <p class="chart-hint">Neto mensual de movimientos En caja</p>
                <?php if ($hasEnCajaChart && !$hide): ?>
                    <div class="chart-wrap"><canvas id="chartCashSales"></canvas></div>
                <?php elseif ($hide): ?>
                    <div class="chart-empty">Cifras ocultas</div>
                <?php else: ?>
                    <div class="chart-empty">Sin movimientos de caja aún.</div>
                <?php endif; ?>
            </div>
        </div>

        <div class="charts-grid company-section">
            <div class="chart-card tone-violet">
                <h2>Composición</h2>
                <?php if (array_sum($compValues) > 0 && !$hide): ?>
                    <div class="chart-wrap"><canvas id="chartComposition"></canvas></div>
                <?php elseif ($hide): ?>
                    <div class="chart-empty">Cifras ocultas</div>
                <?php else: ?>
                    <div class="chart-empty">Sin activos para graficar.</div>
                <?php endif; ?>
            </div>
            <div class="chart-card tone-green">
                <h2>Liquidez vs patrimonio</h2>
                <?php if (!$hide && ($net > 0 || (float) ($summary['liquidity'] ?? 0) > 0)): ?>
                    <div class="chart-wrap"><canvas id="chartLiquidity"></canvas></div>
                <?php else: ?>
                    <div class="chart-empty">Sin datos.</div>
                <?php endif; ?>
            </div>
        </div>

        <div class="charts-grid company-section">
            <div class="chart-card tone-teal">
                <h2>Operativo</h2>
                <?php if (!$hide && array_sum($companyChart['companyExtras']['values']) > 0): ?>
                    <div class="chart-wrap"><canvas id="chartCompanyExtras"></canvas></div>
                <?php else: ?>
                    <div class="chart-empty">Sin datos operativos aún.</div>
                <?php endif; ?>
            </div>
        </div>
        <script type="application/json" id="company-charts"><?= json_encode($companyChart, JSON_UNESCAPED_UNICODE) ?></script>

    <?php else: ?>
        <div class="page-head mt">
            <h2><?= e($tabs[$tab] ?? $tab) ?></h2>
            <?php
            $returnToCobrables = $companyPath . '?tab=cobrables&currency=' . urlencode($currency);
            $newLinks = [
                'cuentas' => '/cuentas/nueva?entity_id=' . $company['id'],
                'activos' => '/activos/nuevo?entity_id=' . $company['id'],
                'pasivos' => '/pasivos/nuevo?entity_id=' . $company['id'],
                'propiedades' => '/propiedades/nueva?entity_id=' . $company['id'],
                'inventario' => '/inventario/nuevo?entity_id=' . $company['id'],
                'cobrables' => '/cobrables/nueva?entity_id=' . $company['id'] . '&return_to=' . rawurlencode($returnToCobrables),
                'movimientos' => '/movimientos/nuevo?entity_id=' . $company['id'],
                'integraciones' => '/integraciones/woocommerce/nueva?entity_id=' . $company['id'],
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
                        <?php if ($tab === 'inventario'): ?><th>Nombre</th><th>Fuente</th><th class="num">Valor</th><th class="num">Unidades</th><?php endif; ?>
                        <?php if ($tab === 'cobrables'): ?>
                            <th class="th-sort" data-sort="text" data-col="0" scope="col">Deudor <span class="sort-ind" aria-hidden="true"></span></th>
                            <th class="th-sort num" data-sort="num" data-col="1" scope="col">Pendiente <span class="sort-ind" aria-hidden="true"></span></th>
                            <th class="th-sort" data-sort="text" data-col="2" scope="col">Vence <span class="sort-ind" aria-hidden="true"></span></th>
                            <th class="th-sort" data-sort="num" data-col="3" scope="col">Estado <span class="sort-ind" aria-hidden="true"></span></th>
                            <?php if ($canWrite): ?><th></th><?php endif; ?>
                        <?php endif; ?>
                        <?php if ($tab === 'movimientos'): ?><th>Fecha</th><th>Tipo</th><th>Cuenta</th><th class="num">Importe</th><?php endif; ?>
                        <?php if ($tab === 'integraciones'): ?><th>Nombre</th><th>Proveedor</th><th>Estado</th><th>Última sync</th><?php if ($canWrite): ?><th></th><?php endif; ?><?php endif; ?>
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
                            <?php elseif ($tab === 'inventario'): ?>
                                <td><?php if ($canWrite): ?><a href="<?= e(url('/inventario/' . $r['id'] . '/editar')) ?>"><?= e($r['name']) ?></a><?php else: ?><?= e($r['name']) ?><?php endif; ?></td>
                                <td><?= e($r['source']) ?></td>
                                <td class="num"><?= e(money($r['stock_value'], $r['currency_code'])) ?></td>
                                <td class="num"><?= e(number_format((float) $r['units_total'], 2, ',', '.')) ?></td>
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
                            <?php elseif ($tab === 'integraciones'): ?>
                                <td><?php if ($canWrite): ?><a href="<?= e(url('/integraciones/' . $r['id'])) ?>"><?= e($r['name']) ?></a><?php else: ?><?= e($r['name']) ?><?php endif; ?></td>
                                <td><?= e($r['provider']) ?></td>
                                <td><?= e($r['status']) ?></td>
                                <td><?= e($r['last_sync_at'] ?? 'Nunca') ?></td>
                                <?php if ($canWrite): ?>
                                <td class="actions"><?= icon_action_link(url('/integraciones/' . $r['id']), 'eye', 'Ver') ?></td>
                                <?php endif; ?>
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
                    $pagerRoute = $companyPath;
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
