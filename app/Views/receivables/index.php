<?php
$currency = $currency ?? 'ARS';
$currencies = $currencies ?? [];
$totals = $totals ?? [
    'pending' => 0, 'overdue' => 0, 'current' => 0, 'open_count' => 0, 'overdue_count' => 0, 'count' => 0,
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
$statusLabels = $statusLabels ?? [
    'current' => 'Al día',
    'overdue' => 'Vencido',
    'paid' => 'Pago',
    'uncollectible' => 'Incobrable',
    'cancelled' => 'Cancelado',
];
?>
<section class="page">
    <div class="page-head">
        <div><h1>Cuentas por cobrar</h1><p class="lede">Dinero que te deben</p></div>
        <a class="btn" href="<?= e(url('/cobrables/nueva')) ?>">Nueva</a>
    </div>
    <?php require __DIR__ . '/../partials/alerts.php'; ?>

    <form method="get" class="filters inline-filters" action="<?= e(form_action()) ?>">
        <?= route_field('/cobrables') ?>
        <label>Moneda (totales / gráficos)
            <select name="currency" onchange="this.form.submit()">
                <?php foreach ($currencies as $c): ?>
                    <option value="<?= e($c['code']) ?>"<?= selected($currency, $c['code']) ?>><?= e($c['code']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
    </form>

    <?php if (empty($rows)): ?>
        <?php $message = 'Sin cobrables.'; $ctaHref = '/cobrables/nueva'; $ctaLabel = 'Registrar cobrable'; require __DIR__ . '/../partials/empty.php'; ?>
    <?php else: ?>
        <div class="stat-row">
            <div class="stat tone-green">
                <span class="stat-label">Valor total</span>
                <span class="stat-value"><?= $hide ? '••••' : e(money($totals['pending'], $currency)) ?></span>
                <span class="stat-hint muted"><?= (int) $totals['open_count'] ?> abierto(s) · <?= e($currency) ?></span>
            </div>
            <div class="stat tone-rose">
                <span class="stat-label">Vencido</span>
                <span class="stat-value"><?= $hide ? '••••' : e(money($totals['overdue'], $currency)) ?></span>
                <span class="stat-hint muted"><?= (int) $totals['overdue_count'] ?> caso(s)</span>
            </div>
            <div class="stat tone-teal">
                <span class="stat-label">Al día</span>
                <span class="stat-value"><?= $hide ? '••••' : e(money($totals['current'], $currency)) ?></span>
            </div>
            <div class="stat tone-amber">
                <span class="stat-label">Cobrables</span>
                <span class="stat-value"><?= (int) $totals['count'] ?></span>
            </div>
        </div>

        <div class="charts-grid mt">
            <div class="chart-card tone-amber">
                <h2>Pendiente por estado</h2>
                <p class="chart-hint">Distribución en <?= e($currency) ?></p>
                <?php if ($hasByType && !$hide): ?>
                    <div class="chart-wrap"><canvas id="chartListByType"></canvas></div>
                <?php elseif ($hide): ?>
                    <div class="chart-empty">Cifras ocultas</div>
                <?php else: ?>
                    <div class="chart-empty">Sin montos para graficar en <?= e($currency) ?>.</div>
                <?php endif; ?>
            </div>
            <div class="chart-card tone-blue">
                <h2>Abierto por entidad</h2>
                <p class="chart-hint">Al día + vencido</p>
                <?php if ($hasByEntity && !$hide): ?>
                    <div class="chart-wrap"><canvas id="chartListByEntity"></canvas></div>
                <?php elseif ($hide): ?>
                    <div class="chart-empty">Cifras ocultas</div>
                <?php else: ?>
                    <div class="chart-empty">Sin montos por entidad en <?= e($currency) ?>.</div>
                <?php endif; ?>
            </div>
        </div>

        <div class="panel mt">
            <table class="table">
                <thead><tr><th>Deudor</th><th>Acreedor</th><th class="num">Pendiente</th><th>Vence</th><th>Estado</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <?php $st = (string) ($row['status'] ?? 'current'); ?>
                    <tr>
                        <td><?= e($row['debtor_name']) ?></td>
                        <td><?= e($row['entity_name']) ?></td>
                        <td class="num"><?= e(money($row['outstanding_amount'], $row['currency_code'])) ?></td>
                        <td><?= e($row['due_date'] ?? '—') ?></td>
                        <td><span class="badge"><?= e($statusLabels[$st] ?? $st) ?></span></td>
                        <td class="actions">
                            <a href="<?= e(url('/cobrables/' . $row['id'] . '/editar')) ?>">Editar</a>
                            <?php if ($st !== 'paid'): ?>
                                · <a href="<?= e(url('/cobrables/' . $row['id'] . '/pagar')) ?>">Cobrar</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <script type="application/json" id="list-charts"><?= json_encode($chartPayload, JSON_UNESCAPED_UNICODE) ?></script>
    <?php endif; ?>
</section>
