<?php
$budgetGroups = $budgetGroups ?? [];
$hasRows = !empty($rows);
$hasBudgets = $budgetGroups !== [];
$typeLabel = static fn (string $type): string => $type === 'person' ? 'Persona' : 'Empresa';
$currency = $currency ?? 'ARS';
$currencies = $currencies ?? [];
$typeLabels = $typeLabels ?? [];
$totals = $totals ?? [
    'open' => 0, 'open_count' => 0, 'budget' => 0, 'budget_items' => 0, 'count' => 0,
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
        <div>
            <h1>Pasivos</h1>
            <p class="lede">Deudas clásicas + presupuestos pendientes del mes actual y atrasados (también bajan el neto)</p>
        </div>
        <div class="actions">
            <a class="btn" href="<?= e(url('/pasivos/nuevo')) ?>">Nuevo pasivo</a>
        </div>
    </div>
    <?php require __DIR__ . '/../partials/alerts.php'; ?>

    <form method="get" class="filters inline-filters" action="<?= e(form_action()) ?>">
        <?= route_field('/pasivos') ?>
        <label>Moneda (totales / gráficos)
            <select name="currency" onchange="this.form.submit()">
                <?php foreach ($currencies as $c): ?>
                    <option value="<?= e($c['code']) ?>"<?= selected($currency, $c['code']) ?>><?= e($c['code']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
    </form>

    <?php if (!$hasRows && !$hasBudgets): ?>
        <?php $message = 'Sin pasivos ni presupuestos pendientes.'; $ctaHref = '/pasivos/nuevo'; $ctaLabel = 'Registrar pasivo'; require __DIR__ . '/../partials/empty.php'; ?>
    <?php else: ?>

        <div class="stat-row">
            <div class="stat tone-rose">
                <span class="stat-label">Valor total</span>
                <span class="stat-value"><?= $hide ? '••••' : e(money($totals['value'] ?? (($totals['open'] ?? 0) + ($totals['budget'] ?? 0)), $currency)) ?></span>
                <span class="stat-hint muted">Deudas + presupuestos · <?= e($currency) ?></span>
            </div>
            <div class="stat tone-coral">
                <span class="stat-label">Deudas abiertas</span>
                <span class="stat-value"><?= $hide ? '••••' : e(money($totals['open'], $currency)) ?></span>
                <span class="stat-hint muted"><?= (int) $totals['open_count'] ?> pasivo(s)</span>
            </div>
            <div class="stat tone-amber">
                <span class="stat-label">Presupuestos pendientes</span>
                <span class="stat-value"><?= $hide ? '••••' : e(money($totals['budget'], $currency)) ?></span>
                <span class="stat-hint muted"><?= (int) $totals['budget_items'] ?> ítem(s)</span>
            </div>
            <div class="stat tone-blue">
                <span class="stat-label">Pasivos cargados</span>
                <span class="stat-value"><?= (int) $totals['count'] ?></span>
            </div>
        </div>

        <div class="charts-grid mt">
            <div class="chart-card tone-amber">
                <h2>Deuda por tipo</h2>
                <p class="chart-hint">Incluye presupuestos pendientes (<?= e($currency) ?>)</p>
                <?php if ($hasByType && !$hide): ?>
                    <div class="chart-wrap"><canvas id="chartListByType"></canvas></div>
                <?php elseif ($hide): ?>
                    <div class="chart-empty">Cifras ocultas</div>
                <?php else: ?>
                    <div class="chart-empty">Sin montos para graficar en <?= e($currency) ?>.</div>
                <?php endif; ?>
            </div>
            <div class="chart-card tone-blue">
                <h2>Deuda por entidad</h2>
                <p class="chart-hint">Pasivos abiertos</p>
                <?php if ($hasByEntity && !$hide): ?>
                    <div class="chart-wrap"><canvas id="chartListByEntity"></canvas></div>
                <?php elseif ($hide): ?>
                    <div class="chart-empty">Cifras ocultas</div>
                <?php else: ?>
                    <div class="chart-empty">Sin montos por entidad en <?= e($currency) ?>.</div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($hasBudgets): ?>
            <div class="panel mt">
                <div class="page-head" style="margin:0 0 1rem">
                    <div>
                        <h2 style="margin:0">Presupuestos pendientes</h2>
                        <p class="muted" style="margin:0.35rem 0 0">Total por persona/empresa. El detalle se liquida en Presupuestos.</p>
                    </div>
                    <a class="btn ghost" href="<?= e(url('/presupuestos')) ?>">Ir a Presupuestos</a>
                </div>
                <table class="table">
                    <thead>
                    <tr>
                        <th>Entidad</th>
                        <th>Tipo</th>
                        <th>Gastos</th>
                        <th class="num">Total pendiente</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($budgetGroups as $group): ?>
                        <tr>
                            <td><?= e($group['entity_name']) ?></td>
                            <td><?= e($typeLabel((string) $group['entity_type'])) ?></td>
                            <td><?= count($group['items']) ?></td>
                            <td class="num"><?= e(money($group['total'], $group['currency_code'])) ?></td>
                            <td class="actions">
                                <a href="<?= e(url('/presupuestos?entity_id=' . (int) $group['entity_id'])) ?>">Ver</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <div class="panel mt">
            <h2 style="margin-top:0">Deudas y obligaciones</h2>
            <?php if (!$hasRows): ?>
                <p class="muted">Sin pasivos clásicos (préstamos, tarjetas, etc.).
                    <a href="<?= e(url('/pasivos/nuevo')) ?>">Registrar uno</a>
                </p>
            <?php else: ?>
                <table class="table">
                    <thead><tr><th>Nombre</th><th>Entidad</th><th>Tipo</th><th class="num">Pendiente</th><th>Estado</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($rows as $row): ?>
                        <?php
                        $ltype = (string) ($row['liability_type'] ?? '');
                        $ltypeLabel = $typeLabels[$ltype] ?? $ltype;
                        ?>
                        <tr>
                            <td><?= e($row['name']) ?></td>
                            <td><?= e($row['entity_name']) ?></td>
                            <td><?= e($ltypeLabel) ?></td>
                            <td class="num"><?= e(money($row['outstanding_amount'], $row['currency_code'])) ?></td>
                            <td><span class="badge"><?= e($row['status']) ?></span></td>
                            <td class="actions"><a href="<?= e(url('/pasivos/' . $row['id'] . '/editar')) ?>">Editar</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <script type="application/json" id="list-charts"><?= json_encode($chartPayload, JSON_UNESCAPED_UNICODE) ?></script>

    <?php endif; ?>
</section>
