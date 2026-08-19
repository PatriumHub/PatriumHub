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
            <table class="table" id="receivables-table">
                <thead>
                <tr>
                    <th class="th-sort" data-sort="text" data-col="0" scope="col">Deudor <span class="sort-ind" aria-hidden="true"></span></th>
                    <th class="th-sort" data-sort="text" data-col="1" scope="col">Acreedor <span class="sort-ind" aria-hidden="true"></span></th>
                    <th class="th-sort num" data-sort="num" data-col="2" scope="col">Pendiente <span class="sort-ind" aria-hidden="true"></span></th>
                    <th class="th-sort" data-sort="text" data-col="3" scope="col">Vence <span class="sort-ind" aria-hidden="true"></span></th>
                    <th class="th-sort" data-sort="num" data-col="4" scope="col">Estado <span class="sort-ind" aria-hidden="true"></span></th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php
                $statusOrder = [
                    'overdue' => 0,
                    'current' => 1,
                    'paid' => 2,
                    'uncollectible' => 3,
                    'cancelled' => 4,
                ];
                foreach ($rows as $row):
                    $st = (string) ($row['status'] ?? 'current');
                    $due = (string) ($row['due_date'] ?? '');
                    ?>
                    <tr>
                        <td data-sort="<?= e(mb_strtolower((string) $row['debtor_name'])) ?>"><?= e($row['debtor_name']) ?></td>
                        <td data-sort="<?= e(mb_strtolower((string) $row['entity_name'])) ?>"><?= e($row['entity_name']) ?></td>
                        <td class="num" data-sort="<?= e((string) (float) $row['outstanding_amount']) ?>"><?= e(money($row['outstanding_amount'], $row['currency_code'])) ?></td>
                        <td data-sort="<?= e($due !== '' ? $due : '9999-99-99') ?>"><?= e($due !== '' ? $due : '—') ?></td>
                        <td data-sort="<?= (int) ($statusOrder[$st] ?? 9) ?>"><span class="badge"><?= e($statusLabels[$st] ?? $st) ?></span></td>
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
        <script>
        (() => {
          const table = document.getElementById('receivables-table');
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
            th.style.cursor = 'pointer';
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
