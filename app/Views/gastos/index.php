<?php
/** @var array $analysis */
/** @var list<array> $entities */
/** @var list<array> $currencies */
/** @var string $currency */
/** @var string $preset */
/** @var string $from */
/** @var string $to */
/** @var int|null $entityId */
/** @var string $entityFilter */
$entityFilter = $entityFilter ?? ($entityId ? (string) $entityId : '');
$hide = hide_amounts();
$people = array_values(array_filter($entities, static fn (array $e): bool => ($e['type'] ?? '') === 'person'));
$companies = array_values(array_filter($entities, static fn (array $e): bool => ($e['type'] ?? '') === 'company'));

$k = $analysis['kpis'] ?? [];
$fmtPct = static function (?float $v): string {
    if ($v === null) {
        return '—';
    }
    return number_format($v, 1, ',', '.') . '%';
};
$deltaClass = static function (float $v): string {
    if (abs($v) < 0.01) {
        return '';
    }
    return $v > 0 ? 'err-text' : 'ok-text';
};
$presets = [
    '30d' => 'Últimos 30 días',
    'month' => 'Mes actual',
    '90d' => 'Últimos 90 días',
    'ytd' => 'Año en curso',
    'year' => 'Año completo',
    'custom' => 'Personalizado',
];
$chartPayload = [
    'hide' => $hide,
    'currency' => $currency,
    'byEntity' => $analysis['by_entity'] ?? ['labels' => [], 'values' => []],
    'byCategory' => $analysis['by_category'] ?? ['labels' => [], 'values' => []],
    'byType' => $analysis['by_type'] ?? ['labels' => [], 'values' => []],
    'byAccount' => $analysis['by_account'] ?? ['labels' => [], 'values' => []],
    'byMonth' => $analysis['by_month'] ?? ['labels' => [], 'expense' => [], 'income' => []],
    'byCategoryMonth' => $analysis['by_category_month'] ?? ['labels' => [], 'month_totals' => [], 'series' => []],
    'splitBudget' => $analysis['split_budget'] ?? ['labels' => [], 'values' => []],
];
?>
<section class="page gastos-page">
    <div class="page-head">
        <div>
            <h1>Gastos</h1>
            <p class="lede">Análisis de egresos reales, presupuesto y proyección · filtrá por persona, empresa o todas.</p>
        </div>
        <form method="get" class="filters inline-filters" action="<?= e(form_action()) ?>">
            <?= route_field('/gastos') ?>
            <label>Período
                <select name="preset" onchange="this.form.submit()">
                    <?php foreach ($presets as $val => $label): ?>
                        <option value="<?= e($val) ?>"<?= selected($preset, $val) ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <?php if ($preset === 'custom'): ?>
                <label>Desde
                    <input type="date" name="from" value="<?= e($from) ?>" onchange="this.form.submit()">
                </label>
                <label>Hasta
                    <input type="date" name="to" value="<?= e($to) ?>" onchange="this.form.submit()">
                </label>
            <?php else: ?>
                <input type="hidden" name="from" value="<?= e($from) ?>">
                <input type="hidden" name="to" value="<?= e($to) ?>">
            <?php endif; ?>
            <label>Entidad
                <select name="entity_id" onchange="this.form.submit()">
                    <option value=""<?= selected($entityFilter, '') ?>>Todas</option>
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
        </form>
    </div>

    <?php require __DIR__ . '/../partials/alerts.php'; ?>

    <p class="muted cp-note">
        <?= e($from) ?> → <?= e($to) ?>
        · <?= (int) ($analysis['days'] ?? 0) ?> día(s)
        · presupuestos del mes <?= e((string) ($analysis['budget_period'] ?? '')) ?>
        · proyección <?= e((string) ($analysis['proj_year'] ?? '')) ?>.
    </p>

    <?php if (!empty($analysis['alerts'])): ?>
        <div class="gastos-alerts">
            <?php foreach ($analysis['alerts'] as $al): ?>
                <div class="alert alert-<?= e(($al['level'] ?? '') === 'warn' ? 'warn' : 'success') ?>">
                    <?= e((string) ($al['message'] ?? '')) ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="stat-row">
        <div class="stat tone-rose">
            <span class="stat-label">Total gastado</span>
            <span class="stat-value"><?= $hide ? '••••' : e(money($k['expense'] ?? 0, $currency)) ?></span>
            <span class="stat-hint muted"><?= (int) ($k['tx_count'] ?? 0) ?> movimiento(s)</span>
        </div>
        <div class="stat tone-coral">
            <span class="stat-label">Promedio diario</span>
            <span class="stat-value"><?= $hide ? '••••' : e(money($k['avg_daily'] ?? 0, $currency)) ?></span>
            <span class="stat-hint muted">Semanal <?= $hide ? '••••' : e(money($k['avg_weekly'] ?? 0, $currency)) ?></span>
        </div>
        <div class="stat tone-amber">
            <span class="stat-label">Vs período anterior</span>
            <span class="stat-value <?= e($deltaClass((float) ($k['delta_prev'] ?? 0))) ?>">
                <?= $hide ? '••••' : e((($k['delta_prev'] ?? 0) >= 0 ? '+' : '') . money($k['delta_prev'] ?? 0, $currency)) ?>
            </span>
            <span class="stat-hint muted"><?= e($fmtPct($k['delta_prev_pct'] ?? null)) ?></span>
        </div>
        <div class="stat tone-violet">
            <span class="stat-label">Vs mismo lapso año ant.</span>
            <span class="stat-value <?= e($deltaClass((float) ($k['delta_yoy'] ?? 0))) ?>">
                <?= $hide ? '••••' : e((($k['delta_yoy'] ?? 0) >= 0 ? '+' : '') . money($k['delta_yoy'] ?? 0, $currency)) ?>
            </span>
            <span class="stat-hint muted"><?= e($fmtPct($k['delta_yoy_pct'] ?? null)) ?></span>
        </div>
    </div>

    <div class="stat-row mt">
        <div class="stat tone-blue">
            <span class="stat-label">Carga de egresos</span>
            <span class="stat-value"><?= $hide ? '••••' : e($fmtPct($k['burden_pct'] ?? null)) ?></span>
            <span class="stat-hint muted">Gastos ÷ ingresos del período</span>
        </div>
        <div class="stat tone-teal">
            <span class="stat-label">Ingresos del período</span>
            <span class="stat-value"><?= $hide ? '••••' : e(money($k['income'] ?? 0, $currency)) ?></span>
            <span class="stat-hint muted">Disponible est. <?= $hide ? '••••' : e(money($k['available_est'] ?? 0, $currency)) ?></span>
        </div>
        <div class="stat tone-green">
            <span class="stat-label">Presupuesto del mes</span>
            <span class="stat-value"><?= $hide ? '••••' : e(money($k['budget_planned'] ?? 0, $currency)) ?></span>
            <span class="stat-hint muted">Pagado <?= e($fmtPct($k['budget_exec_pct'] ?? null)) ?></span>
        </div>
        <div class="stat tone-amber">
            <span class="stat-label">Pendiente / omitido</span>
            <span class="stat-value"><?= $hide ? '••••' : e(money($k['budget_pending'] ?? 0, $currency)) ?></span>
            <span class="stat-hint muted">Omitido <?= $hide ? '••••' : e(money($k['budget_skipped'] ?? 0, $currency)) ?></span>
        </div>
    </div>

    <div class="stat-row mt">
        <div class="stat tone-violet">
            <span class="stat-label">Proyectado (prorrateo)</span>
            <span class="stat-value"><?= $hide ? '••••' : e(money($k['projected_period'] ?? 0, $currency)) ?></span>
            <span class="stat-hint muted">Año <?= e((string) ($analysis['proj_year'] ?? '')) ?>: <?= $hide ? '••••' : e(money($k['projected_annual'] ?? 0, $currency)) ?></span>
        </div>
        <div class="stat tone-rose">
            <span class="stat-label">Real vs proyectado</span>
            <span class="stat-value <?= e($deltaClass((float) ($k['vs_proj_delta'] ?? 0))) ?>">
                <?= $hide ? '••••' : e((($k['vs_proj_delta'] ?? 0) >= 0 ? '+' : '') . money($k['vs_proj_delta'] ?? 0, $currency)) ?>
            </span>
            <span class="stat-hint muted"><?= e($fmtPct($k['vs_proj_pct'] ?? null)) ?></span>
        </div>
        <div class="stat tone-blue">
            <span class="stat-label">Ticket promedio</span>
            <span class="stat-value"><?= $hide ? '••••' : e(money($k['ticket_avg'] ?? 0, $currency)) ?></span>
            <span class="stat-hint muted">Mediana <?= $hide ? '••••' : e(money($k['ticket_median'] ?? 0, $currency)) ?></span>
        </div>
        <div class="stat tone-coral">
            <span class="stat-label">Concentración top 3</span>
            <span class="stat-value"><?= $hide ? '••••' : e($fmtPct($k['concentration_top3_pct'] ?? null)) ?></span>
            <span class="stat-hint muted">Máx. movimiento <?= $hide ? '••••' : e(money($k['ticket_max'] ?? 0, $currency)) ?></span>
        </div>
    </div>

    <div class="stat-row cols-3 mt">
        <div class="stat tone-teal">
            <span class="stat-label">Personas</span>
            <span class="stat-value"><?= $hide ? '••••' : e(money($k['people_expense'] ?? 0, $currency)) ?></span>
        </div>
        <div class="stat tone-blue">
            <span class="stat-label">Empresas</span>
            <span class="stat-value"><?= $hide ? '••••' : e(money($k['company_expense'] ?? 0, $currency)) ?></span>
        </div>
        <div class="stat tone-amber">
            <span class="stat-label">De presupuesto / libres</span>
            <span class="stat-value" style="font-size:1.15rem">
                <?= $hide ? '••••' : e(money($k['budget_linked'] ?? 0, $currency)) ?>
                <span class="muted">/</span>
                <?= $hide ? '••••' : e(money($k['budget_free'] ?? 0, $currency)) ?>
            </span>
        </div>
    </div>

    <div class="charts-grid mt">
        <div class="chart-card tone-rose">
            <h2>Tendencia mensual</h2>
            <p class="chart-hint">Ingresos vs egresos en el rango</p>
            <?php if (!$hide && !empty($analysis['by_month']['labels'])): ?>
                <div class="chart-wrap flow-xl"><canvas id="chartGastosMonth"></canvas></div>
            <?php elseif ($hide): ?>
                <div class="chart-empty">Cifras ocultas</div>
            <?php else: ?>
                <div class="chart-empty">Sin movimientos en el rango.</div>
            <?php endif; ?>
        </div>
        <div class="chart-card tone-blue">
            <h2>Por entidad</h2>
            <p class="chart-hint">Ranking de gasto</p>
            <?php if (!$hide && !empty($analysis['by_entity']['labels'])): ?>
                <div class="chart-wrap gastos-hbar"><canvas id="chartGastosEntity"></canvas></div>
            <?php elseif ($hide): ?>
                <div class="chart-empty">Cifras ocultas</div>
            <?php else: ?>
                <div class="chart-empty">Sin datos por entidad.</div>
            <?php endif; ?>
        </div>
    </div>

    <?php
    $catMonth = $analysis['by_category_month'] ?? [];
    $hasCatMonth = !empty($catMonth['labels']);
    ?>
    <div class="chart-card tone-violet mt">
        <h2>Gastos por categoría · mes a mes</h2>
        <p class="chart-hint">
            Año <?= e((string) ($catMonth['year'] ?? substr((string) $to, 0, 4))) ?>
            · los 12 meses en barras horizontales · apilado por categoría · tooltip con monto y % del mes
        </p>
        <?php if (!$hide && $hasCatMonth): ?>
            <div class="chart-wrap flow-xl gastos-cat-month"><canvas id="chartGastosCategoryMonth"></canvas></div>
        <?php elseif ($hide): ?>
            <div class="chart-empty">Cifras ocultas</div>
        <?php else: ?>
            <div class="chart-empty">Sin egresos por categoría en el año.</div>
        <?php endif; ?>
    </div>

    <div class="charts-grid mt">
        <div class="chart-card tone-violet">
            <h2>Por categoría</h2>
            <p class="chart-hint">Composición del gasto (total del período)</p>
            <?php if (!$hide && !empty($analysis['by_category']['labels'])): ?>
                <div class="chart-wrap gastos-hbar"><canvas id="chartGastosCategory"></canvas></div>
            <?php elseif ($hide): ?>
                <div class="chart-empty">Cifras ocultas</div>
            <?php else: ?>
                <div class="chart-empty">Sin categorías (o sin egresos).</div>
            <?php endif; ?>
        </div>
        <div class="chart-card tone-amber">
            <h2>Por cuenta</h2>
            <p class="chart-hint">De dónde salió la plata</p>
            <?php if (!$hide && !empty($analysis['by_account']['labels'])): ?>
                <div class="chart-wrap gastos-hbar"><canvas id="chartGastosAccount"></canvas></div>
            <?php elseif ($hide): ?>
                <div class="chart-empty">Cifras ocultas</div>
            <?php else: ?>
                <div class="chart-empty">Sin egresos por cuenta.</div>
            <?php endif; ?>
        </div>
    </div>

    <div class="charts-grid mt">
        <div class="chart-card tone-teal">
            <h2>Tipo de movimiento</h2>
            <p class="chart-hint">Egreso / pago</p>
            <?php if (!$hide && !empty($analysis['by_type']['labels'])): ?>
                <div class="chart-wrap"><canvas id="chartGastosType"></canvas></div>
            <?php elseif ($hide): ?>
                <div class="chart-empty">Cifras ocultas</div>
            <?php else: ?>
                <div class="chart-empty">Sin datos.</div>
            <?php endif; ?>
        </div>
        <div class="chart-card tone-green">
            <h2>Presupuesto vs libres</h2>
            <p class="chart-hint">Egresos ligados a plantilla vs resto</p>
            <?php if (!$hide && array_sum($analysis['split_budget']['values'] ?? []) > 0.009): ?>
                <div class="chart-wrap"><canvas id="chartGastosSplit"></canvas></div>
            <?php elseif ($hide): ?>
                <div class="chart-empty">Cifras ocultas</div>
            <?php else: ?>
                <div class="chart-empty">Sin egresos para partir.</div>
            <?php endif; ?>
        </div>
    </div>

    <div class="grid-2 mt">
        <div class="panel">
            <div class="page-head" style="margin-bottom:0.75rem">
                <h2 style="margin:0;font-size:1.1rem">Por entidad</h2>
            </div>
            <?php if (empty($analysis['by_entity']['rows'])): ?>
                <p class="muted">Sin egresos por entidad en el rango.</p>
            <?php else: ?>
                <div class="table-scroll">
                    <table class="table">
                        <thead>
                        <tr>
                            <th>Entidad</th>
                            <th>Tipo</th>
                            <th class="num">Gasto</th>
                            <th class="num">% del total</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php
                        $totalExp = max(0.009, (float) ($k['expense'] ?? 0));
                        foreach ($analysis['by_entity']['rows'] as $row):
                            $pct = round(((float) $row['amount'] / $totalExp) * 1000) / 10;
                            ?>
                            <tr>
                                <td><?= e((string) $row['name']) ?></td>
                                <td><?= e(($row['type'] ?? '') === 'company' ? 'Empresa' : 'Persona') ?></td>
                                <td class="num"><?= $hide ? '••••' : e(money($row['amount'], $currency)) ?></td>
                                <td class="num"><?= $hide ? '••••' : e(number_format($pct, 1, ',', '.') . '%') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <div class="panel">
            <div class="page-head" style="margin-bottom:0.75rem">
                <h2 style="margin:0;font-size:1.1rem">Top conceptos</h2>
            </div>
            <?php if (empty($analysis['top_concepts'])): ?>
                <p class="muted">Sin conceptos en el rango.</p>
            <?php else: ?>
                <div class="table-scroll">
                    <table class="table">
                        <thead>
                        <tr>
                            <th>Descripción</th>
                            <th class="num">Veces</th>
                            <th class="num">Total</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($analysis['top_concepts'] as $row): ?>
                            <tr>
                                <td><?= e((string) $row['name']) ?></td>
                                <td class="num"><?= (int) $row['count'] ?></td>
                                <td class="num"><?= $hide ? '••••' : e(money($row['amount'], $currency)) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script type="application/json" id="gastos-charts"><?= json_encode($chartPayload, JSON_UNESCAPED_UNICODE) ?></script>
</section>
