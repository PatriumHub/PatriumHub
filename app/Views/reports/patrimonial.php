<?php
/** @var array $report */
/** @var list<array> $entities */
/** @var list<array> $currencies */
/** @var string $period */
/** @var string $currency */
/** @var string $entityFilter */
$hide = hide_amounts();
$people = array_values(array_filter($entities, static fn (array $e): bool => ($e['type'] ?? '') === 'person'));
$companies = array_values(array_filter($entities, static fn (array $e): bool => ($e['type'] ?? '') === 'company'));

$fig = $report['figures'];
$prev = $report['prev'];
$yoy = $report['yoy'];
$flow = $report['flow'];
$budget = $report['budget'];
$byEntity = $report['by_entity'];
$byCategory = $report['by_category'];

$assetGroups = $fig['assets'];
$liabilityGroups = $fig['liabilities'];
$assetsTotal = (float) $fig['total_assets'];
$liabilitiesTotal = (float) $fig['total_liabilities'];
$netting = (float) $fig['netting'];

$liquidity = 0.0;
foreach ($assetGroups as $g) {
    if ($g['group'] === 'Cuentas') {
        $liquidity = (float) $g['total'];
    }
}

$amount = static fn (float $v): string => $hide ? '••••' : money($v, $currency);
$share = static function (float $v) use ($assetsTotal): string {
    return $assetsTotal > 0.009
        ? number_format(($v / $assetsTotal) * 100, 1, ',', '.') . '%'
        : '—';
};
$signed = static function (?float $v) use ($amount): string {
    return $v === null ? '—' : ($v >= 0 ? '+' : '−') . $amount(abs($v));
};
$pct = static function (?float $v): string {
    return $v === null ? '—' : number_format($v, 1, ',', '.') . '%';
};
$deltaPct = static function (float $from, float $to): ?float {
    return abs($from) < 0.009 ? null : round((($to - $from) / abs($from)) * 1000) / 10;
};
$toneOf = static function (float $v): string {
    return abs($v) < 0.009 ? '' : ($v > 0 ? 'ok-text' : 'err-text');
};

$categoryTotal = 0.0;
foreach ($byCategory as $row) {
    $categoryTotal += (float) $row['value'];
}
?>
<section class="page report-page">
    <div class="page-head no-print">
        <div>
            <h1>Reporte patrimonial</h1>
            <p class="lede">Cierre mensual por alcance · imprimí o guardá como PDF desde el navegador.</p>
        </div>
        <form method="get" class="filters inline-filters" action="<?= e(form_action()) ?>">
            <?= route_field('/reportes/patrimonial') ?>
            <label>Mes
                <input type="month" name="period" value="<?= e($period) ?>" max="<?= e(date('Y-m')) ?>" onchange="this.form.submit()">
            </label>
            <label>Alcance
                <select name="entity_id" onchange="this.form.submit()">
                    <option value=""<?= selected($entityFilter, '') ?>>Todas las entidades</option>
                    <option value="people"<?= selected($entityFilter, 'people') ?>>Todas las personas</option>
                    <option value="companies"<?= selected($entityFilter, 'companies') ?>>Todas las empresas</option>
                    <?php if ($people !== []): ?>
                        <optgroup label="Personas">
                            <?php foreach ($people as $ent): ?>
                                <option value="<?= (int) $ent['id'] ?>"<?= selected($entityFilter, (string) $ent['id']) ?>><?= e($ent['name']) ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endif; ?>
                    <?php if ($companies !== []): ?>
                        <optgroup label="Empresas">
                            <?php foreach ($companies as $ent): ?>
                                <option value="<?= (int) $ent['id'] ?>"<?= selected($entityFilter, (string) $ent['id']) ?>><?= e($ent['name']) ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endif; ?>
                </select>
            </label>
            <label>Moneda
                <select name="currency" onchange="this.form.submit()">
                    <?php foreach ($currencies as $c): ?>
                        <option value="<?= e($c['code']) ?>"<?= selected($currency, $c['code']) ?>><?= e($c['code']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button type="button" class="btn" id="report-print">Imprimir / PDF</button>
        </form>
    </div>

    <div class="no-print"><?php require __DIR__ . '/../partials/alerts.php'; ?></div>

    <?php if (!empty($report['is_future'])): ?>
        <div class="panel mt">
            <p class="muted">
                <strong><?= e((string) $report['period_label']) ?></strong> todavía no empezó.
                Elegí el mes en curso o uno anterior.
            </p>
        </div>
    <?php else: ?>
        <article class="report-doc">
            <header class="report-cover">
                <div>
                    <p class="report-kicker">Reporte patrimonial</p>
                    <h2 class="report-title"><?= e((string) $report['period_label']) ?></h2>
                    <p class="report-scope"><?= e((string) $report['scope_label']) ?> · <?= e($currency) ?></p>
                </div>
                <div class="report-meta">
                    <p>Corte: <strong><?= e((string) $report['cutoff']) ?></strong></p>
                    <p>Emitido: <?= e((string) $report['generated_at']) ?></p>
                    <?php if (!empty($report['is_current'])): ?>
                        <p class="report-flag">Preliminar · el mes no cerró</p>
                    <?php endif; ?>
                </div>
            </header>

            <section class="report-section">
                <h3>Resumen</h3>
                <div class="stat-row">
                    <div class="stat tone-green">
                        <span class="stat-label">Patrimonio neto</span>
                        <span class="stat-value"><?= e($amount((float) $fig['net_worth'])) ?></span>
                        <span class="stat-hint muted">Activo − pasivo</span>
                    </div>
                    <div class="stat tone-blue">
                        <span class="stat-label">Activos</span>
                        <span class="stat-value"><?= e($amount($assetsTotal)) ?></span>
                        <span class="stat-hint muted">Total valuado</span>
                    </div>
                    <div class="stat tone-rose">
                        <span class="stat-label">Pasivos</span>
                        <span class="stat-value"><?= e($amount($liabilitiesTotal)) ?></span>
                        <span class="stat-hint muted">Deudas y pendientes</span>
                    </div>
                    <div class="stat tone-teal">
                        <span class="stat-label">Liquidez en cuentas</span>
                        <span class="stat-value"><?= e($amount($liquidity)) ?></span>
                        <span class="stat-hint muted">Saldo de cuentas</span>
                    </div>
                </div>

                <table class="table mt">
                    <thead>
                        <tr>
                            <th scope="col">Comparativa de patrimonio neto</th>
                            <th scope="col" class="num">Importe</th>
                            <th scope="col" class="num">Variación</th>
                            <th scope="col" class="num">%</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><?= e((string) $report['period_label']) ?> (este reporte)</td>
                            <td class="num"><?= e($amount((float) $fig['net_worth'])) ?></td>
                            <td class="num">—</td>
                            <td class="num">—</td>
                        </tr>
                        <?php foreach ([
                            [$report['prev_period_label'], $prev, 'mes anterior'],
                            [$report['yoy_period_label'], $yoy, 'año anterior'],
                        ] as [$label, $ref, $caption]): ?>
                            <?php
                            $refNet = (float) $ref['net_worth'];
                            $diff = (float) $fig['net_worth'] - $refNet;
                            ?>
                            <tr>
                                <td><?= e((string) $label) ?> (<?= e($caption) ?>)</td>
                                <td class="num"><?= e($amount($refNet)) ?></td>
                                <td class="num <?= e($toneOf($diff)) ?>"><?= e($signed($diff)) ?></td>
                                <td class="num <?= e($toneOf($diff)) ?>"><?= e($pct($deltaPct($refNet, (float) $fig['net_worth']))) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <p class="muted">
                    La comparativa recalcula cada mes con el mismo criterio. Las clases que no guardan
                    histórico por línea (activos, propiedades, inventario, participaciones y deudas) entran
                    con su valor vigente en los tres meses, así que la variación refleja sobre todo
                    <strong>cuentas</strong>, <strong>cobrables</strong> y <strong>presupuestos pendientes</strong>.
                </p>
            </section>

            <section class="report-section">
                <h3>Composición y desglose del activo</h3>
                <table class="table">
                    <thead>
                        <tr>
                            <th scope="col">Clase / detalle</th>
                            <th scope="col">Entidad</th>
                            <th scope="col" class="num">Part.</th>
                            <th scope="col" class="num">Importe</th>
                            <th scope="col" class="num">% del activo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($assetGroups as $group): ?>
                            <tr class="row-group">
                                <th scope="rowgroup"><?= e((string) $group['group']) ?></th>
                                <td></td>
                                <td></td>
                                <td class="num"><strong><?= e($amount((float) $group['total'])) ?></strong></td>
                                <td class="num"><?= e($share((float) $group['total'])) ?></td>
                            </tr>
                            <?php if ($group['lines'] === []): ?>
                                <tr class="row-child">
                                    <td class="indent muted" colspan="5">Sin líneas</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($group['lines'] as $line): ?>
                                    <tr class="row-child">
                                        <td class="indent">
                                            <?= e((string) $line['label']) ?>
                                            <?php if (($line['note'] ?? '') !== ''): ?>
                                                <span class="line-note"><?= e((string) $line['note']) ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= e((string) $line['entity_name']) ?></td>
                                        <td class="num"><?= e(number_format((float) $line['pct'], 2, ',', '.')) ?>%</td>
                                        <td class="num"><?= e($amount((float) $line['amount'])) ?></td>
                                        <td class="num"><?= e($share((float) $line['amount'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        <?php if (abs($netting) > 0.009): ?>
                            <tr class="row-group">
                                <th scope="rowgroup">Neteo interno</th>
                                <td colspan="2" class="muted">Cobrables contra otra entidad del grupo</td>
                                <td class="num"><strong>−<?= e($amount($netting)) ?></strong></td>
                                <td class="num"></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th scope="row">Total del activo</th>
                            <td></td>
                            <td></td>
                            <td class="num"><strong><?= e($amount($assetsTotal)) ?></strong></td>
                            <td class="num">100%</td>
                        </tr>
                    </tfoot>
                </table>
            </section>

            <section class="report-section">
                <h3>Composición y desglose del pasivo</h3>
                <table class="table">
                    <thead>
                        <tr>
                            <th scope="col">Concepto / detalle</th>
                            <th scope="col">Entidad</th>
                            <th scope="col">Tipo</th>
                            <th scope="col">Vence</th>
                            <th scope="col" class="num">Importe</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($liabilityGroups as $group): ?>
                            <tr class="row-group">
                                <th scope="rowgroup"><?= e((string) $group['group']) ?></th>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td class="num"><strong><?= e($amount((float) $group['total'])) ?></strong></td>
                            </tr>
                            <?php if ($group['lines'] === []): ?>
                                <tr class="row-child">
                                    <td class="indent muted" colspan="5">Sin líneas</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($group['lines'] as $line): ?>
                                    <tr class="row-child">
                                        <td class="indent"><?= e((string) $line['label']) ?></td>
                                        <td><?= e((string) $line['entity_name']) ?></td>
                                        <td><?= e((string) ($line['detail'] ?? '')) ?></td>
                                        <td><?= e((string) ($line['due_date'] ?? '—')) ?></td>
                                        <td class="num"><?= e($amount((float) $line['amount'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        <?php if (abs($netting) > 0.009): ?>
                            <tr class="row-group">
                                <th scope="rowgroup">Neteo interno</th>
                                <td colspan="3" class="muted">Deudas con otra entidad del grupo</td>
                                <td class="num"><strong>−<?= e($amount($netting)) ?></strong></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th scope="row">Total del pasivo</th>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td class="num"><strong><?= e($amount($liabilitiesTotal)) ?></strong></td>
                        </tr>
                    </tfoot>
                </table>
            </section>

            <?php if ($byEntity !== []): ?>
                <section class="report-section">
                    <h3>Desglose por entidad</h3>
                    <table class="table">
                        <thead>
                            <tr>
                                <th scope="col">Entidad</th>
                                <th scope="col">Tipo</th>
                                <th scope="col" class="num">Activos</th>
                                <th scope="col" class="num">Pasivos</th>
                                <th scope="col" class="num">Patrimonio neto</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($byEntity as $row): ?>
                                <tr>
                                    <td><?= e((string) $row['name']) ?></td>
                                    <td><?= ($row['type'] ?? '') === 'person' ? 'Persona' : 'Empresa' ?></td>
                                    <td class="num"><?= e($amount((float) $row['total_assets'])) ?></td>
                                    <td class="num"><?= e($amount((float) $row['liabilities'])) ?></td>
                                    <td class="num"><strong><?= e($amount((float) $row['net_worth'])) ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <p class="muted">
                        Cada fila es la entidad por sí sola. Sumarlas puede diferir del total del reporte:
                        las participaciones solo cuentan en alcance personal y el neteo interno se descuenta
                        una sola vez.
                    </p>
                </section>
            <?php endif; ?>

            <section class="report-section">
                <h3>Flujo del mes</h3>
                <div class="stat-row">
                    <div class="stat tone-teal">
                        <span class="stat-label">Ingresos</span>
                        <span class="stat-value"><?= e($amount((float) $flow['income'])) ?></span>
                    </div>
                    <div class="stat tone-rose">
                        <span class="stat-label">Egresos y gastos</span>
                        <span class="stat-value"><?= e($amount((float) $flow['expense'])) ?></span>
                    </div>
                    <div class="stat <?= ((float) $flow['net']) >= 0 ? 'tone-green' : 'tone-coral' ?>">
                        <span class="stat-label">Resultado</span>
                        <span class="stat-value"><?= e($signed((float) $flow['net'])) ?></span>
                    </div>
                    <div class="stat tone-amber">
                        <span class="stat-label">Presupuesto planificado</span>
                        <span class="stat-value"><?= e($amount((float) $budget['planned'])) ?></span>
                        <span class="stat-hint muted">Pagado <?= e($amount((float) $budget['paid'])) ?></span>
                    </div>
                </div>
                <p class="muted">
                    Presupuesto del mes: pagado <?= e($amount((float) $budget['paid'])) ?> ·
                    pendiente <?= e($amount((float) $budget['pending'])) ?> ·
                    omitido <?= e($amount((float) $budget['skipped'])) ?>.
                </p>
            </section>

            <?php if ($byCategory !== []): ?>
                <section class="report-section">
                    <h3>Egresos y gastos por categoría</h3>
                    <p class="muted">En qué se va la plata este mes, de mayor a menor.</p>
                    <div class="report-chart">
                        <canvas id="report-cat-chart" height="<?= 40 + count($byCategory) * 26 ?>"></canvas>
                    </div>
                    <table class="table">
                        <thead>
                            <tr>
                                <th scope="col">Categoría</th>
                                <th scope="col" class="num">Importe</th>
                                <th scope="col" class="num">% del egreso</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($byCategory as $row): ?>
                                <tr>
                                    <td><?= e((string) $row['label']) ?></td>
                                    <td class="num"><?= e($amount((float) $row['value'])) ?></td>
                                    <td class="num">
                                        <?= $categoryTotal > 0.009
                                            ? e(number_format(((float) $row['value'] / $categoryTotal) * 100, 1, ',', '.') . '%')
                                            : '—' ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </section>
            <?php endif; ?>

            <section class="report-section report-notes">
                <h3>Notas metodológicas</h3>
                <ul>
                    <li>El total de cada clase es la <strong>suma de sus líneas</strong>: el reporte cierra consigo mismo.</li>
                    <li>El <strong>corte</strong> es el último día del mes, o hoy si el mes está en curso. Nada con fecha posterior entra, aunque ya esté cargado.</li>
                    <li>Las <strong>cuentas</strong> van al saldo al corte, reconstruido desde los movimientos.</li>
                    <li>Los <strong>cobrables</strong> son los exigibles al corte: las cuotas que vencen después no se cuentan todavía.</li>
                    <li>Activos, propiedades, inventario, participaciones y deudas van al <strong>valor vigente</strong>: de esas clases no se guarda histórico por línea.</li>
                    <li>Lo co-titularizado se computa <strong>prorrateado por porcentaje de titularidad</strong>.</li>
                    <li>Todo en <strong><?= e($currency) ?></strong>. El flujo sale de los movimientos por fecha de ocurrencia.</li>
                    <?php if (!empty($report['is_current'])): ?>
                        <li><strong>Preliminar:</strong> el mes está en curso, los números pueden cambiar hasta el cierre.</li>
                    <?php endif; ?>
                </ul>
            </section>
        </article>
    <?php endif; ?>
</section>

<?php
$palette = ['#047857', '#3b82f6', '#10b981', '#ef4444', '#f97316', '#06b6d4', '#8b5cf6', '#b45309', '#0e7490', '#64748b', '#15803d', '#dc2626'];
$catPayload = [
    'hide' => $hide,
    'currency' => $currency,
    'labels' => array_map(static fn (array $r): string => (string) $r['label'], $byCategory),
    'values' => array_map(static fn (array $r): float => (float) $r['value'], $byCategory),
    'colors' => $byCategory === [] ? [] : array_map(
        static fn (int $i): string => $palette[$i % count($palette)],
        range(0, count($byCategory) - 1)
    ),
];
?>
<script id="report-cat-data" type="application/json"><?= json_encode($catPayload, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<script>
(function () {
    var btn = document.getElementById('report-print');
    if (btn) {
        btn.addEventListener('click', function () { window.print(); });
    }

    var holder = document.getElementById('report-cat-data');
    var el = document.getElementById('report-cat-chart');
    if (!holder || !el) return;

    var data;
    try {
        data = JSON.parse(holder.textContent || '{}');
    } catch (err) {
        return;
    }
    var values = (data.values || []).map(Number);
    var total = values.reduce(function (a, b) { return a + Math.max(0, b); }, 0);
    if (total <= 0) return;

    var cur = (data.currency || 'ARS').toUpperCase();
    function money(v) {
        if (data.hide) return cur + ' ••••';
        try {
            return cur + ' ' + new Intl.NumberFormat('es-AR', { maximumFractionDigits: 0 }).format(Math.round(v || 0));
        } catch (err) {
            return cur + ' ' + String(Math.round(v || 0));
        }
    }

    var chart = null;
    function boot() {
        if (typeof Chart === 'undefined') return false;
        if (chart) {
            chart.destroy();
            chart = null;
        }
        var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        var tick = isDark ? '#cbd5e1' : '#475569';
        var tickStrong = isDark ? '#e8eef6' : '#1a202c';
        var grid = isDark ? 'rgba(148, 163, 184, 0.15)' : 'rgba(15, 23, 42, 0.08)';
        var pcts = values.map(function (v) { return Math.round((v / total) * 1000) / 10; });

        chart = new Chart(el, {
            type: 'bar',
            data: {
                labels: data.labels || [],
                datasets: [{
                    label: '% del gasto',
                    data: pcts,
                    backgroundColor: data.colors || [],
                    borderRadius: 4,
                    borderSkipped: false
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                animation: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function (ctx) {
                                var i = ctx.dataIndex;
                                return ' ' + pcts[i].toLocaleString('es-AR') + '% · ' + money(values[i]);
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        ticks: { color: tick, callback: function (v) { return v + '%'; } },
                        grid: { color: grid }
                    },
                    y: {
                        ticks: { color: tickStrong, font: { size: 11 } },
                        grid: { display: false }
                    }
                }
            }
        });
        return true;
    }

    window.addEventListener('patrium:theme', boot);
    if (!boot()) {
        window.addEventListener('load', boot);
    }
})();
</script>
