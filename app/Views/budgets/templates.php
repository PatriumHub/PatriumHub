<?php
$groups = $groups ?? [];
$hide = hide_amounts();
$typeLabel = static fn (string $type): string => $type === 'person' ? 'Persona' : 'Empresa';
$palette = ['#047857', '#3b82f6', '#10b981', '#ef4444', '#f97316', '#06b6d4', '#8b5cf6', '#b45309', '#0e7490', '#64748b', '#15803d', '#dc2626'];
$chartsPayload = [];
?>
<section class="page">
    <div class="page-head">
        <div>
            <h1>Presupuestos por grupos</h1>
            <p class="lede">Todos los gastos fijos, agrupados por persona o empresa</p>
        </div>
        <div class="actions">
            <a class="btn" href="<?= e(url('/presupuestos/plantillas/nueva')) ?>">+ Gasto</a>
            <a class="btn ghost" href="<?= e(url('/presupuestos')) ?>">Volver al mes</a>
        </div>
    </div>
    <?php require __DIR__ . '/../partials/alerts.php'; ?>

    <?php if ($groups === []): ?>
        <?php
        $message = 'Sin gastos. Ej.: Alquiler, Luz, Internet asignados a una persona o empresa.';
        $ctaHref = '/presupuestos/plantillas/nueva';
        $ctaLabel = 'Crear gasto';
        require __DIR__ . '/../partials/empty.php';
        ?>
    <?php else: ?>
        <?php foreach ($groups as $gi => $group): ?>
            <?php
            $groupTotals = $group['totals'] ?? [];
            if ($groupTotals === []) {
                $groupTotals = ['ARS' => (float) ($group['total'] ?? 0)];
            }
            $currency = (string) array_key_first($groupTotals);
            $labels = [];
            $values = [];
            foreach ($group['items'] as $row) {
                $rowCur = strtoupper(trim((string) ($row['currency_code'] ?? 'ARS'))) ?: 'ARS';
                if ($rowCur !== $currency) {
                    continue;
                }
                $amt = abs((float) ($row['amount'] ?? 0));
                if ($amt < 0.005) {
                    continue;
                }
                $labels[] = (string) ($row['name'] ?? 'Gasto');
                $values[] = round($amt, 2);
            }
            $chartId = 'bg-mix-' . (int) $gi;
            $colors = [];
            foreach ($labels as $i => $_) {
                $colors[] = $palette[$i % count($palette)];
            }
            $chartsPayload[] = [
                'id' => $chartId,
                'labels' => $labels,
                'values' => $values,
                'currency' => $currency,
                'colors' => $colors,
            ];
            ?>
            <div class="panel mt bg-group">
                <div class="page-head" style="margin:0 0 0.85rem">
                    <div>
                        <h2 style="margin:0"><?= e($group['entity_name']) ?></h2>
                        <p class="muted" style="margin:0.3rem 0 0">
                            <?= e($typeLabel((string) $group['entity_type'])) ?>
                            · <?= count($group['items']) ?> gasto(s)
                        </p>
                    </div>
                    <div class="actions">
                        <?php foreach ($groupTotals as $curCode => $curTotal): ?>
                            <strong><?= e(money($curTotal, (string) $curCode)) ?></strong>
                        <?php endforeach; ?>
                        <a class="btn ghost" href="<?= e(url('/presupuestos/plantillas/nueva?entity_id=' . (int) $group['entity_id'])) ?>">+ Gasto</a>
                        <form method="post" action="<?= e(url('/presupuestos/plantillas/grupo/' . (int) $group['entity_id'] . '/eliminar')) ?>" class="inline" onsubmit="return confirm(<?= json_encode('¿Eliminar todo el grupo de gastos de ' . $group['entity_name'] . "?\nSe sacan todos los gastos: pendientes se borran; pagos ya hechos se mantienen.", JSON_UNESCAPED_UNICODE) ?>);">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn danger">Eliminar grupo</button>
                        </form>
                    </div>
                </div>
                <div class="bg-group-body">
                    <div class="bg-group-table">
                        <div class="table-scroll">
                            <table class="table" id="bg-table-<?= (int) $gi ?>">
                                <thead>
                                <tr>
                                    <th class="th-sort" data-sort="text" data-col="0" scope="col">Nombre <span class="sort-ind" aria-hidden="true"></span></th>
                                    <th class="th-sort" data-sort="text" data-col="1" scope="col">Categoría <span class="sort-ind" aria-hidden="true"></span></th>
                                    <th class="th-sort num" data-sort="num" data-col="2" scope="col">Monto <span class="sort-ind" aria-hidden="true"></span></th>
                                    <th class="th-sort num" data-sort="num" data-col="3" scope="col">% <span class="sort-ind" aria-hidden="true"></span></th>
                                    <th class="th-sort num" data-sort="num" data-col="4" scope="col">Día vto. <span class="sort-ind" aria-hidden="true"></span></th>
                                    <th class="th-sort" data-sort="text" data-col="5" scope="col">Cuenta sugerida <span class="sort-ind" aria-hidden="true"></span></th>
                                    <th class="th-sort" data-sort="text" data-col="6" scope="col">Estado <span class="sort-ind" aria-hidden="true"></span></th>
                                    <th></th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php
                                $groupTotal = array_sum($values);
                                foreach ($group['items'] as $row):
                                    $rowCur = strtoupper(trim((string) ($row['currency_code'] ?? 'ARS'))) ?: 'ARS';
                                    $amt = abs((float) ($row['amount'] ?? 0));
                                    $sameCur = $rowCur === $currency;
                                    $pct = ($sameCur && $groupTotal > 0.005) ? round(($amt / $groupTotal) * 100, 1) : 0;
                                    $statusLabel = !empty($row['is_active']) ? 'Activo' : 'Inactivo';
                                    ?>
                                    <tr>
                                        <td data-sort="<?= e(mb_strtolower((string) $row['name'])) ?>"><?= e($row['name']) ?></td>
                                        <td data-sort="<?= e(mb_strtolower((string) ($row['category_name'] ?? ''))) ?>"><?= e($row['category_name'] ?? '—') ?></td>
                                        <td class="num" data-sort="<?= e((string) $amt) ?>"><?= e(money($row['amount'], $row['currency_code'])) ?></td>
                                        <td class="num" data-sort="<?= e((string) $pct) ?>"><?= ($sameCur && $amt >= 0.005) ? e(number_format($pct, 1, ',', '.') . '%') : '—' ?></td>
                                        <td class="num" data-sort="<?= e((string) (int) $row['due_day']) ?>"><?= (int) $row['due_day'] ?></td>
                                        <td data-sort="<?= e(mb_strtolower((string) ($row['account_name'] ?? ''))) ?>"><?= e($row['account_name'] ?? '—') ?></td>
                                        <td data-sort="<?= e(mb_strtolower($statusLabel)) ?>"><?= e($statusLabel) ?></td>
                                        <td class="actions">
                                            <div class="row-icon-actions">
                                                <?= icon_action_link(url('/presupuestos/plantillas/' . (int) $row['id'] . '/editar'), 'edit', 'Editar') ?>
                                                <form method="post" action="<?= e(url('/presupuestos/plantillas/' . (int) $row['id'] . '/eliminar')) ?>" onsubmit="return confirm('¿Sacar este gasto del grupo? Se borran pendientes; los pagos ya hechos se mantienen y no se regenera.');">
                                                    <?= csrf_field() ?>
                                                    <?= icon_action_button('trash', 'Eliminar', 'danger') ?>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="bg-group-charts">
                        <div class="bg-group-chart chart-card">
                            <h2>Composición</h2>
                            <p class="chart-hint">% por gasto · <?= e($currency) ?></p>
                            <?php if ($hide): ?>
                                <div class="chart-empty">Cifras ocultas</div>
                            <?php elseif ($values === []): ?>
                                <div class="chart-empty">Sin montos para graficar</div>
                            <?php else: ?>
                                <div class="chart-wrap short"><canvas id="<?= e($chartId) ?>"></canvas></div>
                            <?php endif; ?>
                        </div>
                        <div class="bg-group-chart chart-card">
                            <h2>Ranking</h2>
                            <p class="chart-hint">De mayor a menor %</p>
                            <?php if ($hide): ?>
                                <div class="chart-empty">Cifras ocultas</div>
                            <?php elseif ($values === []): ?>
                                <div class="chart-empty">Sin montos para graficar</div>
                            <?php else: ?>
                                <div class="chart-wrap short"><canvas id="<?= e($chartId) ?>-rank"></canvas></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</section>
<?php if (!$hide && $chartsPayload !== []): ?>
<script type="application/json" id="bg-charts-data"><?= json_encode(['hide' => false, 'charts' => $chartsPayload], JSON_UNESCAPED_UNICODE) ?></script>
<script>
(function () {
  function wipe(el) {
    if (!el || typeof Chart === 'undefined') return;
    var existing = typeof Chart.getChart === 'function' ? Chart.getChart(el) : null;
    if (existing) existing.destroy();
    delete el.dataset.ready;
  }

  function boot(force) {
    if (typeof Chart === 'undefined') return false;
    var raw = document.getElementById('bg-charts-data');
    if (!raw) return true;
    var payload;
    try { payload = JSON.parse(raw.textContent || '{}'); } catch (_) { return true; }
    var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    var tick = isDark ? '#94a3b8' : '#64748b';
    var tickStrong = isDark ? '#e8eef6' : '#1a202c';
    var grid = isDark ? 'rgba(148, 163, 184, 0.15)' : 'rgba(15, 23, 42, 0.08)';
    (payload.charts || []).forEach(function (c) {
      var el = document.getElementById(c.id);
      var rankEl = document.getElementById(c.id + '-rank');
      if (force) {
        wipe(el);
        wipe(rankEl);
      }
      if (!el || el.dataset.ready === '1') return;
      var vals = (c.values || []).map(Number);
      var total = vals.reduce(function (a, b) { return a + Math.max(0, b); }, 0);
      if (total <= 0) return;
      var cur = (c.currency || 'ARS').toUpperCase();
      function money(v) {
        try {
          return cur + ' ' + new Intl.NumberFormat('es-AR', { maximumFractionDigits: 0 }).format(Math.round(v || 0));
        } catch (_) {
          return cur + ' ' + String(Math.round(v || 0));
        }
      }
      var baseLabels = c.labels || [];
      var labels = baseLabels.map(function (name, i) {
        var pct = Math.round((vals[i] / total) * 1000) / 10;
        return name + ' (' + pct.toLocaleString('es-AR') + '%)';
      });
      new Chart(el, {
        type: 'doughnut',
        data: {
          labels: labels,
          datasets: [{
            data: vals,
            backgroundColor: c.colors || [],
            borderWidth: 0
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          cutout: '58%',
          plugins: {
            legend: {
              position: 'bottom',
              labels: { color: tick, boxWidth: 10, font: { size: 11 }, padding: 10 }
            },
            tooltip: {
              callbacks: {
                label: function (ctx) {
                  var v = Number(ctx.raw) || 0;
                  var pct = total > 0 ? Math.round((v / total) * 1000) / 10 : 0;
                  var base = baseLabels[ctx.dataIndex] || ctx.label || '';
                  return ' ' + base + ': ' + money(v) + ' (' + pct.toLocaleString('es-AR') + '%)';
                }
              }
            }
          }
        }
      });
      el.dataset.ready = '1';

      if (!rankEl || rankEl.dataset.ready === '1') return;
      var ranked = baseLabels.map(function (name, i) {
        return {
          name: name,
          value: vals[i],
          pct: Math.round((vals[i] / total) * 1000) / 10,
          color: (c.colors && c.colors[i]) || '#047857'
        };
      }).sort(function (a, b) { return b.pct - a.pct; });
      new Chart(rankEl, {
        type: 'bar',
        data: {
          labels: ranked.map(function (r) { return r.name; }),
          datasets: [{
            label: '% del total',
            data: ranked.map(function (r) { return r.pct; }),
            backgroundColor: ranked.map(function (r) { return r.color; }),
            borderRadius: 4,
            borderSkipped: false
          }]
        },
        options: {
          indexAxis: 'y',
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: { display: false },
            tooltip: {
              callbacks: {
                label: function (ctx) {
                  var r = ranked[ctx.dataIndex];
                  if (!r) return '';
                  return ' ' + r.pct.toLocaleString('es-AR') + '% · ' + money(r.value);
                }
              }
            }
          },
          scales: {
            x: {
              beginAtZero: true,
              max: 100,
              ticks: {
                color: tick,
                callback: function (v) { return v + '%'; }
              },
              grid: { color: grid }
            },
            y: {
              ticks: { color: tickStrong, font: { size: 11 } },
              grid: { display: false }
            }
          }
        }
      });
      rankEl.dataset.ready = '1';
    });
    return true;
  }
  window.addEventListener('patrium:theme', function () { boot(true); });
  if (boot()) return;
  window.addEventListener('load', function () { boot(); });
  var n = 0;
  var t = setInterval(function () {
    n += 1;
    if (boot() || n > 40) clearInterval(t);
  }, 100);
})();
</script>
<?php endif; ?>
<script>
(() => {
  document.querySelectorAll('table[id^="bg-table-"]').forEach((table) => {
    const tbody = table.tBodies[0];
    if (!tbody) return;
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
        if (activeCol === col) {
          asc = !asc;
        } else {
          activeCol = col;
          asc = true;
        }
        headers.forEach((h) => h.classList.remove('is-asc', 'is-desc'));
        th.classList.add(asc ? 'is-asc' : 'is-desc');

        const rows = [...tbody.rows];
        rows.sort((a, b) => {
          const av = valueOf(a, col, type);
          const bv = valueOf(b, col, type);
          let cmp = 0;
          if (typeof av === 'number' && typeof bv === 'number') {
            cmp = av - bv;
          } else {
            cmp = String(av).localeCompare(String(bv), 'es', { sensitivity: 'base' });
          }
          return asc ? cmp : -cmp;
        });
        rows.forEach((r) => tbody.appendChild(r));
      });
    });
  });
})();
</script>
