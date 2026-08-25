<?php
$currency = $currency ?? 'ARS';
$currencies = $currencies ?? [];
$totals = $totals ?? [
    'value' => 0, 'units' => 0, 'in_net_worth' => 0, 'entities' => 0, 'count' => 0,
];
$charts = $charts ?? [
    'byType' => ['labels' => [], 'values' => []],
    'byEntity' => ['labels' => [], 'values' => []],
];
$hide = hide_amounts();
$hasByType = array_sum($charts['byType']['values'] ?? []) > 0.009;
$hasByEntity = array_sum($charts['byEntity']['values'] ?? []) > 0.009;
$chartPayload = [
    'hide' => $hide,
    'currency' => $currency,
    'byType' => $charts['byType'],
    'byEntity' => $charts['byEntity'],
];
?>
<section class="page">
    <div class="page-head">
        <div><h1>Inventario</h1><p class="lede">Valor de stock manual</p></div>
        <a class="btn" href="<?= e(url('/inventario/nuevo')) ?>">Nuevo inventario</a>
    </div>
    <?php require __DIR__ . '/../partials/alerts.php'; ?>

    <form method="get" class="filters inline-filters" action="<?= e(form_action()) ?>">
        <?= route_field('/inventario') ?>
        <label>Moneda (totales / gráficos)
            <select name="currency" onchange="this.form.submit()">
                <?php foreach ($currencies as $c): ?>
                    <option value="<?= e($c['code']) ?>"<?= selected($currency, $c['code']) ?>><?= e($c['code']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
    </form>

    <?php if (empty($rows)): ?>
        <?php $message = 'Sin inventarios.'; $ctaHref = '/inventario/nuevo'; $ctaLabel = 'Crear inventario'; require __DIR__ . '/../partials/empty.php'; ?>
    <?php else: ?>
        <div class="stat-row">
            <div class="stat tone-green">
                <span class="stat-label">Valor total</span>
                <span class="stat-value"><?= $hide ? '••••' : e(money($totals['value'], $currency)) ?></span>
                <span class="stat-hint muted">Stock en <?= e($currency) ?></span>
            </div>
            <div class="stat tone-teal">
                <span class="stat-label">Unidades</span>
                <span class="stat-value"><?= e(number_format((float) $totals['units'], 0, ',', '.')) ?></span>
            </div>
            <div class="stat tone-blue">
                <span class="stat-label">En patrimonio</span>
                <span class="stat-value"><?= (int) $totals['in_net_worth'] ?></span>
                <span class="stat-hint muted"><?= (int) $totals['entities'] ?> entidad(es)</span>
            </div>
            <div class="stat tone-amber">
                <span class="stat-label">Inventarios</span>
                <span class="stat-value"><?= (int) $totals['count'] ?></span>
            </div>
        </div>

        <div class="charts-grid mt">
            <div class="chart-card tone-amber">
                <h2>Valor por fuente</h2>
                <p class="chart-hint">Manual u otras (<?= e($currency) ?>)</p>
                <?php if ($hasByType && !$hide): ?>
                    <div class="chart-wrap"><canvas id="chartListByType"></canvas></div>
                <?php elseif ($hide): ?>
                    <div class="chart-empty">Cifras ocultas</div>
                <?php else: ?>
                    <div class="chart-empty">Sin valores para graficar en <?= e($currency) ?>.</div>
                <?php endif; ?>
            </div>
            <div class="chart-card tone-blue">
                <h2>Valor por entidad</h2>
                <p class="chart-hint">Stock por empresa</p>
                <?php if ($hasByEntity && !$hide): ?>
                    <div class="chart-wrap"><canvas id="chartListByEntity"></canvas></div>
                <?php elseif ($hide): ?>
                    <div class="chart-empty">Cifras ocultas</div>
                <?php else: ?>
                    <div class="chart-empty">Sin valores por entidad en <?= e($currency) ?>.</div>
                <?php endif; ?>
            </div>
        </div>

        <div class="panel mt">
            <table class="table">
                <thead><tr><th>Nombre</th><th>Entidad</th><th>Fuente</th><th class="num">Valor</th><th class="num">Unidades</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><?= e($row['name']) ?></td>
                        <td><?= e($row['entity_name']) ?></td>
                        <td><?= e($row['source']) ?></td>
                        <td class="num"><?= e(money($row['stock_value'], $row['currency_code'])) ?></td>
                        <td class="num"><?= e(number_format((float)$row['units_total'], 2, ',', '.')) ?></td>
                        <td class="actions"><?= icon_action_link(url('/inventario/' . $row['id'] . '/editar'), 'edit', 'Editar') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <script type="application/json" id="list-charts"><?= json_encode($chartPayload, JSON_UNESCAPED_UNICODE) ?></script>
    <?php endif; ?>
</section>
