<?php
$tabs = [
    'resumen' => 'Resumen',
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
            <div class="company-hero-main">
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
            <div class="stat">
                <span class="stat-label">Activos propios</span>
                <span class="stat-value"><?= e(money(
                    (float) ($summary['accounts'] ?? 0)
                    + (float) ($summary['assets'] ?? 0)
                    + (float) ($summary['properties'] ?? 0)
                    + (float) ($summary['receivables'] ?? 0),
                    $currency
                )) ?></span>
            </div>
            <div class="stat">
                <span class="stat-label">Participaciones</span>
                <span class="stat-value"><?= e(money($summary['participations'] ?? 0, $currency)) ?></span>
            </div>
            <div class="stat">
                <span class="stat-label">Pasivos</span>
                <span class="stat-value"><?= e(money($summary['liabilities'] ?? 0, $currency)) ?></span>
            </div>
            <div class="stat">
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

    <?php else: ?>
        <div class="page-head mt">
            <h2><?= e($tabs[$tab] ?? $tab) ?></h2>
            <?php
            $newLinks = [
                'cuentas' => '/cuentas/nueva?entity_id=' . $person['id'],
                'activos' => '/activos/nuevo?entity_id=' . $person['id'],
                'pasivos' => '/pasivos/nuevo?entity_id=' . $person['id'],
                'propiedades' => '/propiedades/nueva?entity_id=' . $person['id'],
                'cobrables' => '/cobrables/nueva?entity_id=' . $person['id'],
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
                <table class="table">
                    <thead>
                    <tr>
                        <?php if ($tab === 'cuentas'): ?><th>Nombre</th><th>Tipo</th><th>Moneda</th><th class="num">Saldo</th><?php endif; ?>
                        <?php if ($tab === 'activos'): ?><th>Nombre</th><th>Cuenta</th><th>Categoría</th><th class="num">Valor</th><?php endif; ?>
                        <?php if ($tab === 'pasivos'): ?><th>Nombre</th><th>Tipo</th><th class="num">Saldo</th><th>Estado</th><?php endif; ?>
                        <?php if ($tab === 'propiedades'): ?><th>Nombre</th><th>Tipo</th><th class="num">Valor</th><?php endif; ?>
                        <?php if ($tab === 'cobrables'): ?><th>Deudor</th><th class="num">Pendiente</th><th>Estado</th><?php if ($canWrite): ?><th></th><?php endif; ?><?php endif; ?>
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
                                $returnTo = '/personas/' . (int) $person['id'] . '?tab=cobrables&currency=' . urlencode($currency);
                                ?>
                                <td><?php if ($canWrite): ?><a href="<?= e(url('/cobrables/' . $r['id'] . '/editar')) ?>"><?= e($r['debtor_name']) ?></a><?php else: ?><?= e($r['debtor_name']) ?><?php endif; ?></td>
                                <td class="num"><?= e(money($r['outstanding_amount'], $r['currency_code'])) ?></td>
                                <td><span class="badge"><?= e($stLabels[$st] ?? $st) ?></span></td>
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
