<?php
$hide = hide_amounts();
$personRows = $summary['by_entity'] ?? [];
$person = $personRows[0] ?? null;
$personId = $person ? (int) ($person['entity_id'] ?? 0) : 0;

$ownAssets = (float) ($summary['accounts'] ?? 0)
    + (float) ($summary['assets'] ?? 0)
    + (float) ($summary['properties'] ?? 0)
    + (float) ($summary['receivables'] ?? 0)
    + (float) ($summary['inventories'] ?? 0);
$participations = (float) ($summary['participations'] ?? 0);
$net = (float) ($summary['net_worth'] ?? 0);
$liq = (float) ($summary['liquidity'] ?? 0);
$liqOwn = (float) ($summary['liquidity_own'] ?? 0);
$liqParts = (float) ($summary['liquidity_from_participations'] ?? 0);
$liabilities = (float) ($summary['liabilities'] ?? 0);

$partDetails = [];
foreach ($personRows as $row) {
    foreach ($row['participation_details'] ?? [] as $d) {
        $partDetails[] = $d;
    }
}
usort($partDetails, static fn ($a, $b) => ((float) ($b['attributable'] ?? 0)) <=> ((float) ($a['attributable'] ?? 0)));

$ownLabels = [];
$ownValues = [];
$ownIds = [];
$ownLiq = [];
$ownPct = [];
foreach ($partDetails as $d) {
    $ownLabels[] = (string) ($d['company_name'] ?? 'Empresa');
    $ownValues[] = (float) ($d['attributable'] ?? 0);
    $ownIds[] = (int) ($d['company_entity_id'] ?? 0);
    $ownLiq[] = (float) ($d['attributable_liquidity'] ?? 0);
    $ownPct[] = (float) ($d['ownership_pct'] ?? 0);
}

$evolLabels = [];
$evolNet = [];
$evolLiq = [];
foreach ($history ?? [] as $h) {
    $evolLabels[] = substr((string) ($h['captured_at'] ?? ''), 0, 10);
    $evolNet[] = (float) ($h['net_worth'] ?? 0);
    $evolLiq[] = (float) ($h['liquidity'] ?? 0);
}

$flowIn = (float) ($flow['income'] ?? 0);
$flowOut = (float) ($flow['expense'] ?? 0);

$chartPayload = [
    'hide' => $hide,
    'currency' => $currency,
    'entityId' => $personId ?: null,
    'baseUrl' => base_path() . '/index.php',
    'mode' => 'personal',
    'wealthSplit' => [
        'labels' => ['Propios', 'Participaciones'],
        'values' => [$ownAssets, $participations],
    ],
    'ownerships' => [
        'labels' => $ownLabels,
        'values' => $ownValues,
        'liquidity' => $ownLiq,
        'ids' => $ownIds,
        'pcts' => $ownPct,
    ],
    'liquiditySources' => [
        'labels' => ['Caja propia', 'Vía empresas'],
        'values' => [$liqOwn, $liqParts],
    ],
    'evolution' => [
        'labels' => $evolLabels,
        'net' => $evolNet,
        'liquidity' => $evolLiq,
        'assets' => [],
        'liabilities' => [],
        'simple' => true,
    ],
    'flow' => [
        'labels' => ['Ingresos', 'Egresos'],
        'values' => [$flowIn, $flowOut],
    ],
];

$hasWealth = ($ownAssets + $participations) > 0;
$hasOwnerships = array_sum($ownValues) > 0;
$hasLiqSources = ($liqOwn + $liqParts) > 0;
$hasEvolution = $evolLabels !== [];
$hasFlow = ($flowIn + $flowOut) > 0;
$liqPct = $net > 0 ? min(100, round(($liq / $net) * 100)) : 0;

$exportQ = array_filter([
    'mode' => 'personal',
    'currency' => $currency,
], static fn ($v) => $v !== null && $v !== '');
?>
<section class="page personal-dash">
    <div class="page-head">
        <div>
            <h1>Personal</h1>
            <p class="lede">Tu patrimonio: lo propio más tu % en cada empresa</p>
        </div>
        <div class="actions">
            <a class="btn ghost" href="<?= e(url('/dashboard/export?' . http_build_query($exportQ))) ?>">Exportar CSV</a>
            <form method="post" action="<?= e(url('/dashboard/snapshot')) ?>" class="inline">
                <?= csrf_field() ?>
                <input type="hidden" name="mode" value="personal">
                <input type="hidden" name="currency" value="<?= e($currency) ?>">
                <button type="submit" class="btn ghost">Snapshot</button>
            </form>
        </div>
    </div>
    <?php require __DIR__ . '/../partials/alerts.php'; ?>

    <div class="preset-row">
        <a class="preset" href="<?= e(url('/dashboard?preset=consolidated&mode=consolidated&currency=' . urlencode($currency))) ?>">Consolidado</a>
        <a class="preset is-active" href="<?= e(url('/dashboard?preset=personal&mode=personal&currency=' . urlencode($currency))) ?>">Personal</a>
        <a class="preset" href="<?= e(url('/dashboard?preset=liquidity&mode=consolidated&currency=' . urlencode($currency))) ?>">Liquidez</a>
    </div>

    <form method="get" class="filters panel personal-filters" action="<?= e(form_action()) ?>">
        <?= route_field('/dashboard') ?>
        <input type="hidden" name="preset" value="personal">
        <input type="hidden" name="mode" value="personal">
        <label>
            Moneda
            <select name="currency" onchange="this.form.submit()">
                <?php foreach ($currencies as $c): ?>
                    <option value="<?= e($c['code']) ?>"<?= selected($currency, $c['code']) ?>><?= e($c['code']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Desde <input type="date" name="from" value="<?= e($from ?? '') ?>"></label>
        <label>Hasta <input type="date" name="to" value="<?= e($to ?? '') ?>"></label>
        <div class="filter-actions">
            <button type="submit">Aplicar</button>
        </div>
    </form>

    <?php if (empty($personRows)): ?>
        <div class="alert alert-error">No hay personas activas. Creá una en Personas.</div>
    <?php else: ?>

    <div class="personal-hero" data-skeleton-target>
        <div class="personal-hero-main">
            <span class="stat-label">Patrimonio neto</span>
            <div class="personal-hero-value"><?= $hide ? '••••' : e(money($net, $currency)) ?></div>
            <?php if ($person): ?>
                <p class="muted personal-hero-sub">
                    <a href="<?= e(url('/personas/' . $personId . '?tab=resumen&currency=' . urlencode($currency))) ?>"><?= e((string) $person['name']) ?></a>
                    · <?= e($currency) ?>
                </p>
            <?php endif; ?>
        </div>
        <div class="personal-hero-side">
            <div class="personal-meter">
                <div class="personal-meter-head">
                    <span>Liquidez del neto</span>
                    <strong><?= $hide ? '—' : ($liqPct . '%') ?></strong>
                </div>
                <div class="personal-meter-track" aria-hidden="true">
                    <span style="width:<?= (int) $liqPct ?>%"></span>
                </div>
                <p class="muted personal-meter-note">
                    <?= $hide ? 'Cifras ocultas' : e(money($liq, $currency)) ?> disponibles
                    <?php if (!$hide && $liqParts > 0): ?>
                        · <?= e(money($liqParts, $currency)) ?> vía empresas
                    <?php endif; ?>
                </p>
            </div>
        </div>
    </div>

    <div class="stat-row personal-kpis" data-skeleton-target>
        <div class="stat tone-blue">
            <span class="stat-label">Propios</span>
            <span class="stat-value"><?= $hide ? '••••' : e(money($ownAssets, $currency)) ?></span>
        </div>
        <div class="stat tone-amber">
            <span class="stat-label">Participaciones</span>
            <span class="stat-value"><?= $hide ? '••••' : e(money($participations, $currency)) ?></span>
        </div>
        <div class="stat tone-green">
            <span class="stat-label">Liquidez</span>
            <span class="stat-value"><?= $hide ? '••••' : e(money($liq, $currency)) ?></span>
        </div>
        <div class="stat tone-rose">
            <span class="stat-label">Pasivos</span>
            <span class="stat-value"><?= $hide ? '••••' : e(money($liabilities, $currency)) ?></span>
        </div>
    </div>

    <div class="charts-grid personal-charts-top">
        <div class="chart-card tone-amber">
            <h2>De dónde viene</h2>
            <p class="chart-hint">Propios vs valor de tus % en empresas</p>
            <?php if ($hasWealth && !$hide): ?>
                <div class="chart-wrap"><canvas id="chartWealthSplit"></canvas></div>
            <?php elseif ($hide): ?>
                <div class="chart-empty">Cifras ocultas</div>
            <?php else: ?>
                <div class="chart-empty">Sin patrimonio para graficar.</div>
            <?php endif; ?>
        </div>
        <div class="chart-card tone-green">
            <h2>Liquidez</h2>
            <p class="chart-hint">Caja a tu nombre + % de caja de empresas</p>
            <?php if ($hasLiqSources && !$hide): ?>
                <div class="chart-wrap"><canvas id="chartLiqSources"></canvas></div>
            <?php elseif ($hide): ?>
                <div class="chart-empty">Cifras ocultas</div>
            <?php else: ?>
                <div class="chart-empty">Sin liquidez registrada.</div>
            <?php endif; ?>
        </div>
    </div>

    <div class="chart-card tone-violet personal-ownership-card mt">
        <h2>Tus empresas</h2>
        <p class="chart-hint">Valor atribuido = % × valuación de cada empresa</p>
        <?php if ($hasOwnerships && !$hide): ?>
            <div class="chart-wrap tall"><canvas id="chartOwnerships"></canvas></div>
        <?php elseif ($hide): ?>
            <div class="chart-empty">Cifras ocultas</div>
        <?php else: ?>
            <div class="chart-empty">Sin participaciones. Cargalas en Personas → Participaciones.</div>
        <?php endif; ?>

        <?php if ($partDetails !== []): ?>
            <table class="table personal-parts-table">
                <thead>
                <tr>
                    <th>Empresa</th>
                    <th class="num">%</th>
                    <th class="num">Valor</th>
                    <th class="num">Liquidez</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($partDetails as $d): ?>
                    <tr>
                        <td>
                            <a href="<?= e(url('/empresas/' . (int) $d['company_entity_id'] . '?tab=resumen&currency=' . urlencode($currency))) ?>">
                                <?= e((string) $d['company_name']) ?>
                            </a>
                        </td>
                        <td class="num"><?= e(number_format((float) $d['ownership_pct'], 0, ',', '.')) ?>%</td>
                        <td class="num"><?= $hide ? '••••' : e(money($d['attributable'] ?? 0, $currency)) ?></td>
                        <td class="num"><?= $hide ? '••••' : e(money($d['attributable_liquidity'] ?? 0, $currency)) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div class="charts-grid mt">
        <div class="chart-card">
            <h2>Evolución</h2>
            <p class="chart-hint">Patrimonio neto y liquidez en el tiempo</p>
            <?php if ($hasEvolution && !$hide): ?>
                <div class="chart-wrap tall"><canvas id="chartEvolution"></canvas></div>
            <?php elseif ($hide): ?>
                <div class="chart-empty">Cifras ocultas</div>
            <?php else: ?>
                <div class="chart-empty">Sin historial. Usá “Snapshot” arriba.</div>
            <?php endif; ?>
        </div>
        <div class="chart-card">
            <h2>Flujo personal</h2>
            <p class="chart-hint">
                Movimientos de tus cuentas
                <?php if (($from ?? '') !== '' || ($to ?? '') !== ''): ?>
                    · <?= e(trim(($from ?? '…') . ' → ' . ($to ?? '…'))) ?>
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

    <div class="panel mt">
        <div class="page-head" style="margin:0 0 0.75rem">
            <h2 style="margin:0">Últimos movimientos</h2>
            <?php if ($personId): ?>
                <a class="btn ghost" href="<?= e(url('/movimientos?entity_id=' . $personId)) ?>">Ver todos</a>
            <?php endif; ?>
        </div>
        <?php if (empty($movements)): ?>
            <p class="muted">Sin movimientos personales en el filtro.</p>
        <?php else: ?>
            <table class="table">
                <thead>
                <tr>
                    <th>Fecha</th><th>Tipo</th><th>Descripción</th><th class="num">Importe</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach (array_slice($movements, 0, 8) as $m): ?>
                    <tr>
                        <td><?= e(substr((string) $m['occurred_at'], 0, 16)) ?></td>
                        <td><span class="badge"><?= e(movement_type_label((string) $m['type'])) ?></span></td>
                        <td><?= e($m['description'] ?? '—') ?></td>
                        <td class="num"><?= money_flow($m['amount'], $m['currency_code'], (string) $m['type']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <?php endif; ?>
</section>
<script type="application/json" id="dashboard-charts"><?= json_encode($chartPayload, JSON_UNESCAPED_UNICODE) ?></script>
