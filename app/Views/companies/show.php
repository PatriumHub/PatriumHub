<?php
$tabs = [
    'resumen' => 'Resumen',
    'cuentas' => 'Cuentas',
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
$valuations = $valuations ?? [];
$valuation = $valuation ?? ['value' => 0, 'source' => '', 'is_manual' => false, 'auto_value' => 0];
$health = $health ?? ['status' => 'warn', 'label' => '—', 'reasons' => []];
$net = (float) ($summary['net_worth'] ?? 0);
?>
<section class="page">
    <div class="page-head">
        <div>
            <h1><?= e($company['name']) ?></h1>
            <p class="lede"><?= e($company['legal_name'] ?? 'Empresa') ?><?= $company['industry'] ? ' · ' . e($company['industry']) : '' ?></p>
        </div>
        <div class="actions">
            <form method="post" action="<?= e(url('/dashboard/snapshot')) ?>" class="inline">
                <?= csrf_field() ?>
                <input type="hidden" name="mode" value="entity">
                <input type="hidden" name="entity_id" value="<?= (int) $company['id'] ?>">
                <input type="hidden" name="currency" value="<?= e($currency) ?>">
                <input type="hidden" name="redirect_to" value="<?= e($companyPath . '?tab=' . urlencode((string) $tab) . '&currency=' . urlencode($currency)) ?>">
                <button type="submit" class="btn ghost">Capturar snapshot</button>
            </form>
            <a class="btn ghost" href="<?= e(url($companyPath . '/editar')) ?>">Editar</a>
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

    <?php if ($tab === 'participaciones'): ?>
        <div class="panel mt">
            <div class="page-head">
                <div>
                    <h2>Dueños / participaciones</h2>
                    <p class="muted" style="margin:0">Quién es dueño y en qué %. La valuación de la empresa está en su pestaña.</p>
                </div>
                <a class="btn" href="<?= e(url('/participaciones/nueva?company_entity_id=' . $company['id'])) ?>">+ Dueño</a>
            </div>
            <?php if (empty($ownerships)): ?>
                <p class="muted">Sin dueños cargados. El patrimonio personal no atribuirá valor de esta empresa.</p>
            <?php else: ?>
                <table class="table">
                    <thead><tr><th>Dueño</th><th class="num">%</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($ownerships as $o): ?>
                        <tr>
                            <td><?= e($o['owner_name']) ?></td>
                            <td class="num"><?= e(number_format((float) $o['ownership_pct'], 2, ',', '.')) ?>%</td>
                            <td class="actions"><a href="<?= e(url('/participaciones/' . $o['id'] . '/editar')) ?>">Editar</a></td>
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
                <form method="post" action="<?= e(url('/valuaciones')) ?>" class="mt">
                    <?= csrf_field() ?>
                    <input type="hidden" name="company_entity_id" value="<?= (int) $company['id'] ?>">
                    <input type="hidden" name="currency_code" value="<?= e($currency) ?>">
                    <input type="hidden" name="method" value="patrimonio_neto">
                    <button type="submit" class="btn ghost">Usar a patrimonio neto</button>
                </form>
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
            </div>
        </div>
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
                        <a href="<?= e(url('/cuentas?entity_id=' . (int) $company['id'] . '&currency=' . urlencode($currency))) ?>">Ver saldos →</a>
                    </span>
                </div>
            </div>
        </div>

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
            $newLinks = [
                'cuentas' => '/cuentas/nueva?entity_id=' . $company['id'],
                'activos' => '/activos/nuevo?entity_id=' . $company['id'],
                'pasivos' => '/pasivos/nuevo?entity_id=' . $company['id'],
                'propiedades' => '/propiedades/nueva?entity_id=' . $company['id'],
                'inventario' => '/inventario/nuevo?entity_id=' . $company['id'],
                'cobrables' => '/cobrables/nueva?entity_id=' . $company['id'],
                'movimientos' => '/movimientos/nuevo?entity_id=' . $company['id'],
                'integraciones' => '/integraciones/woocommerce/nueva?entity_id=' . $company['id'],
            ];
            if (isset($newLinks[$tab])): ?>
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
                        <?php if ($tab === 'inventario'): ?><th>Nombre</th><th>Fuente</th><th class="num">Valor</th><th class="num">Unidades</th><?php endif; ?>
                        <?php if ($tab === 'cobrables'): ?><th>Deudor</th><th class="num">Pendiente</th><th>Estado</th><?php endif; ?>
                        <?php if ($tab === 'movimientos'): ?><th>Fecha</th><th>Tipo</th><th>Cuenta</th><th class="num">Importe</th><?php endif; ?>
                        <?php if ($tab === 'integraciones'): ?><th>Nombre</th><th>Proveedor</th><th>Estado</th><th>Última sync</th><th></th><?php endif; ?>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($tabRows as $r): ?>
                        <tr>
                            <?php if ($tab === 'cuentas'): ?>
                                <td><a href="<?= e(url('/cuentas/' . $r['id'] . '/editar')) ?>"><?= e($r['name']) ?></a></td>
                                <td><?= e($r['type'] === 'mercadopago' ? 'Mercado Pago' : $r['type']) ?></td>
                                <td><?= e($r['currency_code']) ?></td>
                                <td class="num"><?= e(money($r['balance'], $r['currency_code'])) ?></td>
                            <?php elseif ($tab === 'activos'): ?>
                                <td><a href="<?= e(url('/activos/' . $r['id'] . '/editar')) ?>"><?= e($r['name']) ?></a></td>
                                <td><?= e($r['account_name'] ?? '—') ?></td>
                                <td><?= e($r['category']) ?></td>
                                <td class="num"><?= e(money($r['current_value'], $r['currency_code'])) ?></td>
                            <?php elseif ($tab === 'pasivos'): ?>
                                <td><a href="<?= e(url('/pasivos/' . $r['id'] . '/editar')) ?>"><?= e($r['name']) ?></a></td>
                                <td><?= e($r['liability_type']) ?></td>
                                <td class="num"><?= e(money($r['outstanding_amount'], $r['currency_code'])) ?></td>
                                <td><?= e($r['status']) ?></td>
                            <?php elseif ($tab === 'propiedades'): ?>
                                <td><a href="<?= e(url('/propiedades/' . $r['id'] . '/editar')) ?>"><?= e($r['name']) ?></a></td>
                                <td><?= e($r['property_type']) ?></td>
                                <td class="num"><?= e(money($r['current_value'], $r['currency_code'])) ?></td>
                            <?php elseif ($tab === 'inventario'): ?>
                                <td><a href="<?= e(url('/inventario/' . $r['id'] . '/editar')) ?>"><?= e($r['name']) ?></a></td>
                                <td><?= e($r['source']) ?></td>
                                <td class="num"><?= e(money($r['stock_value'], $r['currency_code'])) ?></td>
                                <td class="num"><?= e(number_format((float) $r['units_total'], 2, ',', '.')) ?></td>
                            <?php elseif ($tab === 'cobrables'): ?>
                                <td><a href="<?= e(url('/cobrables/' . $r['id'] . '/editar')) ?>"><?= e($r['debtor_name']) ?></a></td>
                                <td class="num"><?= e(money($r['outstanding_amount'], $r['currency_code'])) ?></td>
                                <td><?= e($r['status']) ?></td>
                            <?php elseif ($tab === 'movimientos'): ?>
                                <td><?= e(substr((string) $r['occurred_at'], 0, 16)) ?></td>
                                <td><?= e(movement_type_label((string) $r['type'])) ?></td>
                                <td><?= e($r['account_name'] ?? '—') ?></td>
                                <td class="num"><?= money_flow($r['amount'], $r['currency_code'], (string) $r['type']) ?></td>
                            <?php elseif ($tab === 'integraciones'): ?>
                                <td><a href="<?= e(url('/integraciones/' . $r['id'])) ?>"><?= e($r['name']) ?></a></td>
                                <td><?= e($r['provider']) ?></td>
                                <td><?= e($r['status']) ?></td>
                                <td><?= e($r['last_sync_at'] ?? 'Nunca') ?></td>
                                <td class="actions"><a href="<?= e(url('/integraciones/' . $r['id'])) ?>">Ver</a></td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
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
