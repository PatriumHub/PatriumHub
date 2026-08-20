<?php
$period = $period ?? date('Y-m');
$entityId = $entityId ?? null;
$currency = $currency ?? 'ARS';
$items = $items ?? [];
$totals = $totals ?? ['pending' => 0, 'paid' => 0, 'skipped' => 0, 'count_pending' => 0];
$accounts = $accounts ?? [];
$today = date('Y-m-d');
?>
<section class="page">
    <div class="page-head">
        <div>
            <h1>Presupuestos</h1>
            <p class="lede">Costos fijos del mes. Pendientes = pasivo. Al pagar se crea el egreso.</p>
        </div>
        <div class="actions">
            <a class="btn" href="<?= e(url('/presupuestos/plantillas/nueva')) ?>">+ Gasto</a>
            <a class="btn ghost" href="<?= e(url('/presupuestos/plantillas')) ?>">Gastos agrupados</a>
        </div>
    </div>
    <?php require __DIR__ . '/../partials/alerts.php'; ?>

    <form method="get" class="filters panel" action="<?= e(form_action()) ?>">
        <?= route_field('/presupuestos') ?>
        <label>Período
            <input type="month" name="period" value="<?= e($period) ?>">
        </label>
        <label>Entidad
            <select name="entity_id">
                <option value="">Todas</option>
                <?php foreach ($entities as $ent): ?>
                    <option value="<?= (int) $ent['id'] ?>"<?= selected($entityId, $ent['id']) ?>><?= e($ent['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Moneda (totales)
            <select name="currency">
                <?php foreach ($currencies as $c): ?>
                    <option value="<?= e($c['code']) ?>"<?= selected($currency, $c['code']) ?>><?= e($c['code']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <div class="filter-actions">
            <button type="submit">Ver</button>
        </div>
    </form>

    <div class="stat-row">
        <div class="stat tone-rose">
            <span class="stat-label">Pendiente (pasivo)</span>
            <span class="stat-value"><?= e(money($totals['pending'], $currency)) ?></span>
            <span class="stat-hint muted"><?= (int) $totals['count_pending'] ?> ítem(s)</span>
        </div>
        <div class="stat tone-green">
            <span class="stat-label">Pagado</span>
            <span class="stat-value"><?= e(money($totals['paid'], $currency)) ?></span>
        </div>
        <div class="stat tone-blue">
            <span class="stat-label">Omitido</span>
            <span class="stat-value"><?= e(money($totals['skipped'], $currency)) ?></span>
        </div>
        <div class="stat">
            <span class="stat-label">Período</span>
            <span class="stat-value" style="font-size:1.25rem"><?= e($period) ?></span>
        </div>
    </div>

    <?php if ($items === []): ?>
        <?php
        $message = 'Sin ítems en este período. Creá gastos activos; se generan el 1º de cada mes (o al entrar acá).';
        $ctaHref = '/presupuestos/plantillas/nueva';
        $ctaLabel = 'Crear gasto';
        require __DIR__ . '/../partials/empty.php';
        ?>
    <?php else: ?>
        <div class="panel mt">
            <table class="table" id="budget-items-table">
                <thead>
                <tr>
                    <th class="th-sort" data-sort="text" data-col="0" scope="col">Concepto <span class="sort-ind" aria-hidden="true"></span></th>
                    <th class="th-sort" data-sort="text" data-col="1" scope="col">Entidad <span class="sort-ind" aria-hidden="true"></span></th>
                    <th class="th-sort" data-sort="text" data-col="2" scope="col">Vence <span class="sort-ind" aria-hidden="true"></span></th>
                    <th class="th-sort num" data-sort="num" data-col="3" scope="col">Importe <span class="sort-ind" aria-hidden="true"></span></th>
                    <th class="th-sort" data-sort="status" data-col="4" scope="col">Estado <span class="sort-ind" aria-hidden="true"></span></th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($items as $row): ?>
                    <?php
                    $status = (string) ($row['status'] ?? 'pending');
                    $due = (string) ($row['due_date'] ?? '');
                    $overdue = $status === 'pending' && $due !== '' && $due < $today;
                    $statusOrder = match ($status) {
                        'pending' => 0,
                        'paid' => 1,
                        default => 2,
                    };
                    ?>
                    <tr data-status="<?= e($status) ?>">
                        <td data-sort="<?= e(mb_strtolower((string) $row['name'])) ?>">
                            <?= e($row['name']) ?>
                            <?php if ($overdue): ?>
                                <span class="badge" style="margin-left:0.35rem">Vencido</span>
                            <?php endif; ?>
                        </td>
                        <td data-sort="<?= e(mb_strtolower((string) $row['entity_name'])) ?>"><?= e($row['entity_name']) ?></td>
                        <td data-sort="<?= e($due) ?>"><?= e($due) ?></td>
                        <td class="num" data-sort="<?= e((string) (float) $row['amount']) ?>"><?= e(money($row['amount'], $row['currency_code'])) ?></td>
                        <td data-sort="<?= (int) $statusOrder ?>">
                            <?php if ($status === 'pending'): ?>
                                <span class="badge">Pendiente</span>
                            <?php elseif ($status === 'paid'): ?>
                                <span class="badge badge-ok">Pagado</span>
                            <?php else: ?>
                                <span class="badge">Omitido</span>
                            <?php endif; ?>
                        </td>
                        <td class="actions">
                            <div class="row-actions">
                                <?php if ($status === 'pending'): ?>
                                    <details class="pay-drop">
                                        <summary class="btn sm danger">Pagar</summary>
                                        <div class="pay-menu">
                                            <form method="post" action="<?= e(url('/presupuestos/items/' . (int) $row['id'] . '/pagar')) ?>" class="form-grid" style="gap:0.5rem;padding:0;border:0;box-shadow:none;background:transparent">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="period" value="<?= e($period) ?>">
                                                <label style="margin:0">Cuenta
                                                    <select name="account_id">
                                                        <option value="">— (opcional si monto es 0) —</option>
                                                        <?php foreach ($accounts as $acc): ?>
                                                            <?php if ((int) $acc['entity_id'] !== (int) $row['entity_id']) continue; ?>
                                                            <option value="<?= (int) $acc['id'] ?>"<?= selected($row['account_id'] ?? '', $acc['id']) ?>>
                                                                <?= e($acc['name']) ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </label>
                                                <label style="margin:0">Importe
                                                    <input name="amount" type="number" step="0.01" min="0" value="<?= e((string) $row['amount']) ?>">
                                                </label>
                                                <label style="margin:0">Fecha
                                                    <input name="occurred_at" type="datetime-local" value="<?= e(date('Y-m-d\TH:i')) ?>">
                                                </label>
                                                <button type="submit" class="btn sm" style="margin-top:0.25rem">Liquidar</button>
                                            </form>
                                        </div>
                                    </details>
                                    <form method="post" action="<?= e(url('/presupuestos/items/' . (int) $row['id'] . '/omitir')) ?>" onsubmit="return confirm('¿Omitir este ítem? Deja de contar como pasivo.');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="period" value="<?= e($period) ?>">
                                        <button type="submit" class="btn sm ghost">Omitir</button>
                                    </form>
                                    <form method="post" action="<?= e(url('/presupuestos/items/' . (int) $row['id'] . '/eliminar')) ?>" onsubmit="return confirm('¿Eliminar este gasto? Se saca del mes y deja de regenerarse.');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="period" value="<?= e($period) ?>">
                                        <?php if ($entityId): ?><input type="hidden" name="entity_id" value="<?= (int) $entityId ?>"><?php endif; ?>
                                        <button type="submit" class="btn danger sm">Eliminar</button>
                                    </form>
                                <?php elseif ($status === 'paid'): ?>
                                    <form method="post" action="<?= e(url('/presupuestos/items/' . (int) $row['id'] . '/revertir')) ?>" onsubmit="return confirm('¿Revertir el pago? Se anula el egreso y vuelve a pendiente.');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="period" value="<?= e($period) ?>">
                                        <?php if ($entityId): ?><input type="hidden" name="entity_id" value="<?= (int) $entityId ?>"><?php endif; ?>
                                        <button type="submit" class="btn sm ghost">Revertir</button>
                                    </form>
                                    <span class="slot-empty" aria-hidden="true"></span>
                                    <form method="post" action="<?= e(url('/presupuestos/items/' . (int) $row['id'] . '/eliminar')) ?>" onsubmit="return confirm('¿Eliminar? Se revierte el pago (si hay egreso), se saca del mes y deja de regenerarse.');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="period" value="<?= e($period) ?>">
                                        <?php if ($entityId): ?><input type="hidden" name="entity_id" value="<?= (int) $entityId ?>"><?php endif; ?>
                                        <button type="submit" class="btn danger sm">Eliminar</button>
                                    </form>
                                <?php else: ?>
                                    <span class="slot-empty" aria-hidden="true"></span>
                                    <span class="slot-empty" aria-hidden="true"></span>
                                    <form method="post" action="<?= e(url('/presupuestos/items/' . (int) $row['id'] . '/eliminar')) ?>" onsubmit="return confirm('¿Eliminar este gasto omitido? Deja de regenerarse.');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="period" value="<?= e($period) ?>">
                                        <?php if ($entityId): ?><input type="hidden" name="entity_id" value="<?= (int) $entityId ?>"><?php endif; ?>
                                        <button type="submit" class="btn danger sm">Eliminar</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <script>
        (() => {
          const table = document.getElementById('budget-items-table');
          if (!table) return;
          const tbody = table.tBodies[0];
          const headers = [...table.querySelectorAll('th.th-sort')];
          let activeCol = null;
          let asc = true;

          const valueOf = (row, col, type) => {
            const raw = row.cells[col]?.dataset.sort ?? '';
            if (type === 'num' || type === 'status') {
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
                  cmp = String(av).localeCompare(String(bv), 'es', { sensitivity: 'base', numeric: true });
                }
                return asc ? cmp : -cmp;
              });
              rows.forEach((r) => tbody.appendChild(r));
            });
          });
        })();
        </script>
    <?php endif; ?>
</section>
