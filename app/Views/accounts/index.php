<?php
$entityId = $entityId ?? null;
$currency = $currency ?? 'ARS';
$totals = $totals ?? ['balance' => 0, 'bank' => 0, 'bank_count' => 0, 'mp' => 0, 'mp_count' => 0, 'count' => 0];
$charts = $charts ?? [
    'byType' => ['labels' => [], 'values' => []],
    'byEntity' => ['labels' => [], 'values' => []],
    'byAccount' => ['labels' => [], 'values' => [], 'ids' => []],
    'mpByEntity' => ['labels' => [], 'values' => []],
];
$typeLabels = $typeLabels ?? [];
$hide = hide_amounts();
$hasByType = array_sum($charts['byType']['values'] ?? []) > 0.009;
$hasByEntity = array_sum($charts['byEntity']['values'] ?? []) > 0.009;
$hasMp = ($totals['mp_count'] ?? 0) > 0;
$hasMpByAccount = array_sum($charts['byAccount']['values'] ?? []) > 0.009;
$hasMpByEntity = array_sum($charts['mpByEntity']['values'] ?? []) > 0.009;
$chartPayload = [
    'hide' => $hide,
    'currency' => $currency,
    'baseUrl' => base_path() . '/index.php',
    'byType' => $charts['byType'],
    'byEntity' => $charts['byEntity'],
    'byAccount' => $charts['byAccount'],
    'mpByEntity' => $charts['mpByEntity'],
];
?>
<section class="page">
    <div class="page-head">
        <div>
            <h1>Cuentas</h1>
            <p class="lede">Bancos, Mercado Pago, billeteras, efectivo y más. Todo manual, un solo lugar.</p>
        </div>
        <div class="actions">
            <a class="btn ghost" href="<?= e(url('/cuentas/export' . ($entityId ? ('?entity_id=' . (int) $entityId) : ''))) ?>">Exportar CSV</a>
            <a class="btn" href="<?= e(url('/cuentas/nueva')) ?>">Nueva cuenta</a>
        </div>
    </div>
    <?php require __DIR__ . '/../partials/alerts.php'; ?>

    <form method="get" class="filters inline-filters" action="<?= e(form_action()) ?>">
        <?= route_field('/cuentas') ?>
        <label>Entidad
            <select name="entity_id" onchange="this.form.submit()">
                <option value="">Todas</option>
                <?php foreach ($entities as $ent): ?>
                    <option value="<?= (int) $ent['id'] ?>"<?= selected($entityId, $ent['id']) ?>><?= e($ent['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Moneda (totales / gráficos)
            <select name="currency" onchange="this.form.submit()">
                <?php foreach ($currencies as $c): ?>
                    <option value="<?= e($c['code']) ?>"<?= selected($currency, $c['code']) ?>><?= e($c['code']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
    </form>
    <?php
    $entityName = '';
    if ($entityId) {
        foreach ($entities as $ent) {
            if ((int) $ent['id'] === (int) $entityId) {
                $entityName = $ent['name'];
                break;
            }
        }
    }
    $chips = filter_chips(
        [
            'entity_id' => $entityName !== '' ? ('Entidad: ' . $entityName) : null,
            'currency' => $currency !== 'ARS' ? ('Moneda: ' . $currency) : null,
        ],
        array_filter(['entity_id' => $entityId, 'currency' => $currency !== 'ARS' ? $currency : null]),
        '/cuentas'
    );
    require __DIR__ . '/../partials/filter_chips.php';
    ?>

    <?php if (empty($rows)): ?>
        <?php
        $message = 'Sin cuentas todavía.';
        $ctaHref = '/cuentas/nueva';
        $ctaLabel = 'Creá tu primera cuenta';
        require __DIR__ . '/../partials/empty.php';
        ?>
    <?php else: ?>
        <div class="stat-row">
            <div class="stat tone-green">
                <span class="stat-label">Total en cuentas</span>
                <span class="stat-value"><?= $hide ? '••••' : e(money($totals['balance'], $currency)) ?></span>
            </div>
            <div class="stat tone-teal">
                <span class="stat-label">Cuentas bancarias</span>
                <span class="stat-value"><?= $hide ? '••••' : e(money($totals['bank'] ?? 0, $currency)) ?></span>
                <span class="stat-hint muted"><?= (int) ($totals['bank_count'] ?? 0) ?> cuenta(s)</span>
            </div>
            <div class="stat tone-blue">
                <span class="stat-label">Mercado Pago</span>
                <span class="stat-value"><?= $hide ? '••••' : e(money($totals['mp'], $currency)) ?></span>
                <span class="stat-hint muted"><?= (int) $totals['mp_count'] ?> cuenta(s)</span>
            </div>
            <div class="stat tone-amber">
                <span class="stat-label">Cuentas activas</span>
                <span class="stat-value"><?= (int) $totals['count'] ?></span>
            </div>
        </div>

        <div class="charts-grid mt">
            <div class="chart-card tone-amber">
                <h2>Saldo por tipo</h2>
                <p class="chart-hint">Banco, MP, efectivo, etc. (<?= e($currency) ?>)</p>
                <?php if ($hasByType && !$hide): ?>
                    <div class="chart-wrap"><canvas id="chartAccountsByType"></canvas></div>
                <?php elseif ($hide): ?>
                    <div class="chart-empty">Cifras ocultas</div>
                <?php else: ?>
                    <div class="chart-empty">Sin saldos para graficar en <?= e($currency) ?>.</div>
                <?php endif; ?>
            </div>
            <div class="chart-card tone-blue">
                <h2>Saldo por entidad</h2>
                <p class="chart-hint">Cómo se reparte entre personas/empresas</p>
                <?php if ($hasByEntity && !$hide): ?>
                    <div class="chart-wrap"><canvas id="chartAccountsByEntity"></canvas></div>
                <?php elseif ($hide): ?>
                    <div class="chart-empty">Cifras ocultas</div>
                <?php else: ?>
                    <div class="chart-empty">Sin saldos por entidad.</div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($hasMp): ?>
            <div class="charts-grid mt">
                <div class="chart-card tone-green">
                    <h2>Mercado Pago por cuenta</h2>
                    <p class="chart-hint">Clic para editar la cuenta</p>
                    <?php if ($hasMpByAccount && !$hide): ?>
                        <div class="chart-wrap"><canvas id="chartMpByAccount"></canvas></div>
                    <?php elseif ($hide): ?>
                        <div class="chart-empty">Cifras ocultas</div>
                    <?php else: ?>
                        <div class="chart-empty">Cuentas MP en 0.</div>
                    <?php endif; ?>
                </div>
                <div class="chart-card tone-violet">
                    <h2>Mercado Pago por entidad</h2>
                    <p class="chart-hint">Disponible MP agrupado</p>
                    <?php if ($hasMpByEntity && !$hide): ?>
                        <div class="chart-wrap"><canvas id="chartAccountsMpByEntity"></canvas></div>
                    <?php elseif ($hide): ?>
                        <div class="chart-empty">Cifras ocultas</div>
                    <?php else: ?>
                        <div class="chart-empty">Sin saldos MP por entidad.</div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="panel mt">
            <table class="table" id="accounts-table">
                <thead>
                <tr>
                    <th class="th-sort" data-sort="text" data-col="0" scope="col">Nombre <span class="sort-ind" aria-hidden="true"></span></th>
                    <th class="th-sort" data-sort="text" data-col="1" scope="col">Entidad <span class="sort-ind" aria-hidden="true"></span></th>
                    <th class="th-sort" data-sort="text" data-col="2" scope="col">Tipo <span class="sort-ind" aria-hidden="true"></span></th>
                    <th class="th-sort" data-sort="text" data-col="3" scope="col">Moneda <span class="sort-ind" aria-hidden="true"></span></th>
                    <th class="th-sort num" data-sort="num" data-col="4" scope="col">Saldo <span class="sort-ind" aria-hidden="true"></span></th>
                    <th>Patrimonio</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <?php
                    $type = (string) ($row['type'] ?? '');
                    $typeLabel = $typeLabels[$type] ?? $type;
                    ?>
                    <tr>
                        <td data-sort="<?= e(mb_strtolower((string) $row['name'])) ?>"><?= e($row['name']) ?></td>
                        <td data-sort="<?= e(mb_strtolower((string) $row['entity_name'])) ?>"><?= e($row['entity_name']) ?></td>
                        <td data-sort="<?= e(mb_strtolower($typeLabel)) ?>">
                            <?= e($typeLabel) ?>
                            <?php if ($type === 'mercadopago'): ?>
                                <span class="badge" style="margin-left:0.35rem">MP</span>
                            <?php endif; ?>
                        </td>
                        <td data-sort="<?= e((string) $row['currency_code']) ?>"><?= e($row['currency_code']) ?></td>
                        <td class="num" data-sort="<?= e((string) (float) $row['balance']) ?>">
                            <?= e(money($row['balance'], $row['currency_code'])) ?>
                            <?php if ($type === 'mercadopago' && (float) ($row['balance_held'] ?? 0) > 0.009): ?>
                                <div class="muted" style="font-size:0.8rem">retenido <?= e(money($row['balance_held'], $row['currency_code'])) ?></div>
                            <?php endif; ?>
                        </td>
                        <td><?= (int) $row['include_in_net_worth'] ? 'Sí' : 'No' ?></td>
                        <td class="actions">
                            <a href="<?= e(url('/cuentas/' . $row['id'] . '/editar')) ?>">Editar</a>
                            <a href="<?= e(url('/movimientos/nuevo?account_id=' . (int) $row['id'] . '&entity_id=' . (int) $row['entity_id'])) ?>">Movimiento</a>
                            <?php if (abs((float) ($row['balance'] ?? 0)) <= 0.009 && abs((float) ($row['balance_held'] ?? 0)) <= 0.009): ?>
                                <form method="post" action="<?= e(url('/cuentas/' . (int) $row['id'] . '/eliminar')) ?>" class="inline" onsubmit="return confirm('¿Eliminar esta cuenta? No se puede deshacer.');">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="linkish">Eliminar</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <script type="application/json" id="accounts-charts"><?= json_encode($chartPayload, JSON_UNESCAPED_UNICODE) ?></script>
        <script>
        (() => {
          const table = document.getElementById('accounts-table');
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
</section>
