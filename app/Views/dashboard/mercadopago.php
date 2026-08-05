<?php
$range = $range ?? '30d';
$accountId = $accountId ?? null;
$mpAccounts = $mpAccounts ?? [];
$hide = hide_amounts();
$baseQs = array_filter([
    'entity_id' => $entityId ?: null,
    'account_id' => $accountId ?: null,
    'currency' => $currency ?: null,
], static fn ($v) => $v !== null && $v !== '');
$rangeUrl = static function (string $r) use ($baseQs): string {
    return url('/dashboard/mercadopago?' . http_build_query(array_merge($baseQs, ['range' => $r])));
};
$periodLabel = match ($range) {
    'month' => 'este mes',
    '90d' => 'últimos 90 días',
    'all' => 'todo el historial',
    'custom' => (($from ?? '') !== '' || ($to ?? '') !== '')
        ? trim(($from ?? '…') . ' → ' . ($to ?? '…'))
        : 'personalizado',
    default => 'últimos 30 días',
};

$byAccountLabels = [];
$byAccountValues = [];
$byAccountIds = [];
$byEntityMap = [];
foreach ($mp['accounts'] ?? [] as $acc) {
    $bal = (float) ($acc['balance'] ?? 0);
    $byAccountLabels[] = (string) ($acc['name'] ?? 'Cuenta');
    $byAccountValues[] = $bal;
    $byAccountIds[] = (int) ($acc['id'] ?? 0);
    $ename = (string) ($acc['entity_name'] ?? 'Sin entidad');
    $byEntityMap[$ename] = ($byEntityMap[$ename] ?? 0.0) + $bal;
}
$byEntityLabels = array_keys($byEntityMap);
$byEntityValues = array_values($byEntityMap);

$chartPayload = [
    'hide' => $hide,
    'currency' => $currency,
    'baseUrl' => base_path() . '/index.php',
    'byAccount' => [
        'labels' => $byAccountLabels,
        'values' => $byAccountValues,
        'ids' => $byAccountIds,
    ],
    'byEntity' => [
        'labels' => $byEntityLabels,
        'values' => $byEntityValues,
    ],
];
$hasByAccount = array_sum($byAccountValues) > 0.009;
$hasByEntity = count($byEntityLabels) > 0 && array_sum($byEntityValues) > 0.009;
?>
<section class="page">
    <div class="page-head">
        <div>
            <h1>Mercado Pago</h1>
            <p class="lede">Saldos de tus cuentas MP. Los movimientos se cargan a mano sobre cada cuenta.</p>
        </div>
        <div class="actions">
            <a class="btn" href="<?= e(url('/cuentas/nueva?type=mercadopago')) ?>">+ Cuenta MP</a>
            <a class="btn ghost" href="<?= e(url('/movimientos/nuevo')) ?>">+ Movimiento</a>
        </div>
    </div>

    <div class="preset-row">
        <a class="preset<?= $range === '30d' ? ' is-active' : '' ?>" href="<?= e($rangeUrl('30d')) ?>">30 días</a>
        <a class="preset<?= $range === 'month' ? ' is-active' : '' ?>" href="<?= e($rangeUrl('month')) ?>">Este mes</a>
        <a class="preset<?= $range === '90d' ? ' is-active' : '' ?>" href="<?= e($rangeUrl('90d')) ?>">90 días</a>
        <a class="preset<?= $range === 'all' ? ' is-active' : '' ?>" href="<?= e($rangeUrl('all')) ?>">Todo</a>
    </div>

    <form method="get" class="filters panel" action="<?= e(form_action()) ?>">
        <?= route_field('/dashboard/mercadopago') ?>
        <label>
            Entidad
            <select name="entity_id">
                <option value="">Todas</option>
                <?php foreach ($entities as $ent): ?>
                    <option value="<?= (int) $ent['id'] ?>"<?= selected($entityId, $ent['id']) ?>><?= e($ent['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            Cuenta MP
            <select name="account_id">
                <option value="">Todas</option>
                <?php foreach ($mpAccounts as $accOpt): ?>
                    <option value="<?= (int) $accOpt['id'] ?>"<?= selected($accountId, $accOpt['id']) ?>>
                        <?= e(($accOpt['entity_name'] ?? '') . ' · ' . $accOpt['name']) ?>
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
        <label>Desde<input type="date" name="from" value="<?= e($from ?? '') ?>"></label>
        <label>Hasta<input type="date" name="to" value="<?= e($to ?? '') ?>"></label>
        <div class="filter-actions">
            <button type="submit">Aplicar</button>
            <a class="btn ghost" href="<?= e(url('/dashboard/mercadopago')) ?>">Limpiar</a>
        </div>
    </form>

    <p class="muted" style="margin:0">Movimientos del listado: <strong><?= e($periodLabel) ?></strong>. El saldo es el actual de las cuentas.</p>

    <div class="stat-row" style="grid-template-columns: repeat(2, 1fr); max-width: 640px;">
        <div class="stat accent">
            <span class="stat-label">Saldo disponible</span>
            <span class="stat-value"><?= $hide ? '••••' : e(money($mp['available'], $currency)) ?></span>
        </div>
        <div class="stat tone-blue">
            <span class="stat-label">Cuentas</span>
            <span class="stat-value"><?= (int) $mp['connections'] ?></span>
        </div>
    </div>

    <div class="charts-grid mt">
        <div class="chart-card tone-amber">
            <h2>Saldo por cuenta</h2>
            <p class="chart-hint">Clic para editar la cuenta</p>
            <?php if ($hasByAccount && !$hide): ?>
                <div class="chart-wrap"><canvas id="chartMpByAccount"></canvas></div>
            <?php elseif ($hide): ?>
                <div class="chart-empty">Cifras ocultas</div>
            <?php else: ?>
                <div class="chart-empty">Sin saldos MP para graficar.</div>
            <?php endif; ?>
        </div>
        <div class="chart-card tone-blue">
            <h2>Saldo por entidad</h2>
            <p class="chart-hint">Cómo se reparte el disponible</p>
            <?php if ($hasByEntity && !$hide): ?>
                <div class="chart-wrap"><canvas id="chartMpByEntity"></canvas></div>
            <?php elseif ($hide): ?>
                <div class="chart-empty">Cifras ocultas</div>
            <?php else: ?>
                <div class="chart-empty">Sin saldos por entidad.</div>
            <?php endif; ?>
        </div>
    </div>

    <div class="panel mt">
        <h2>Por cuenta</h2>
        <?php if (empty($mp['accounts'])): ?>
            <p class="muted">No hay cuentas Mercado Pago. Creá una con tipo “Mercado Pago” desde
                <a href="<?= e(url('/cuentas/nueva?type=mercadopago')) ?>">Cuentas</a>.
            </p>
        <?php else: ?>
            <table class="table">
                <thead>
                <tr>
                    <th>Cuenta</th>
                    <th>Entidad</th>
                    <th class="num">Disponible</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($mp['accounts'] as $acc): ?>
                    <tr>
                        <td><?= e($acc['name']) ?></td>
                        <td><?= e($acc['entity_name']) ?></td>
                        <td class="num"><?= $hide ? '••••' : e(money($acc['balance'], $acc['currency_code'])) ?></td>
                        <td class="actions">
                            <a href="<?= e(url('/cuentas/' . $acc['id'] . '/editar')) ?>">Editar</a>
                            <a href="<?= e(url('/movimientos/nuevo?account_id=' . $acc['id'] . '&entity_id=' . $acc['entity_id'])) ?>">Movimiento</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div class="panel mt">
        <h2>Últimos movimientos</h2>
        <?php if (empty($mp['movements'])): ?>
            <p class="muted">Sin movimientos en el filtro. Registralos sobre la cuenta MP.</p>
        <?php else: ?>
            <table class="table">
                <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Cuenta</th>
                    <th>Entidad</th>
                    <th>Tipo</th>
                    <th>Descripción</th>
                    <th class="num">Importe</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($mp['movements'] as $m): ?>
                    <tr>
                        <td><?= e(substr((string) $m['occurred_at'], 0, 16)) ?></td>
                        <td><?= e($m['account_name']) ?></td>
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
<script type="application/json" id="mp-dashboard-charts"><?= json_encode($chartPayload, JSON_UNESCAPED_UNICODE) ?></script>
