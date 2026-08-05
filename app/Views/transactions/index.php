<section class="page">
    <div class="page-head">
        <div><h1>Movimientos</h1><p class="lede">Ingresos, egresos, transferencias y ajustes</p></div>
        <div class="actions">
            <a class="btn ghost" href="<?= e(url('/movimientos/export' . ($entityId ? ('?entity_id=' . (int) $entityId) : ''))) ?>">Exportar CSV</a>
            <a class="btn" href="<?= e(url('/movimientos/nuevo')) ?>">Nuevo movimiento</a>
        </div>
    </div>
    <?php require __DIR__ . '/../partials/alerts.php'; ?>
    <form method="get" class="filters inline-filters" action="<?= e(form_action()) ?>">
        <?= route_field('/movimientos') ?>
        <label>Entidad
            <select name="entity_id" onchange="this.form.submit()">
                <option value="">Todas</option>
                <?php foreach ($entities as $ent): ?>
                    <option value="<?= (int)$ent['id'] ?>"<?= selected($entityId, $ent['id']) ?>><?= e($ent['name']) ?></option>
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
        ['entity_id' => $entityName !== '' ? ('Entidad: ' . $entityName) : null],
        array_filter(['entity_id' => $entityId]),
        '/movimientos'
    );
    require __DIR__ . '/../partials/filter_chips.php';
    ?>
    <?php if (empty($rows)): ?>
        <?php
        $message = 'Sin movimientos en este filtro.';
        $ctaHref = '/movimientos/nuevo';
        $ctaLabel = 'Registrar movimiento';
        require __DIR__ . '/../partials/empty.php';
        ?>
    <?php else: ?>
        <div class="panel">
            <table class="table" id="movements-table">
                <thead>
                <tr>
                    <th class="th-sort" data-sort="text" data-col="0" scope="col">Fecha <span class="sort-ind" aria-hidden="true"></span></th>
                    <th class="th-sort" data-sort="text" data-col="1" scope="col">Entidad <span class="sort-ind" aria-hidden="true"></span></th>
                    <th class="th-sort" data-sort="text" data-col="2" scope="col">Tipo <span class="sort-ind" aria-hidden="true"></span></th>
                    <th class="th-sort" data-sort="text" data-col="3" scope="col">Cuenta <span class="sort-ind" aria-hidden="true"></span></th>
                    <th class="th-sort" data-sort="text" data-col="4" scope="col">Destino <span class="sort-ind" aria-hidden="true"></span></th>
                    <th class="th-sort" data-sort="text" data-col="5" scope="col">Categoría <span class="sort-ind" aria-hidden="true"></span></th>
                    <th class="th-sort" data-sort="text" data-col="6" scope="col">Descripción <span class="sort-ind" aria-hidden="true"></span></th>
                    <th class="th-sort num" data-sort="num" data-col="7" scope="col">Importe <span class="sort-ind" aria-hidden="true"></span></th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <?php
                    $typeLabel = movement_type_label((string) $row['type']);
                    $signed = (float) $row['amount'];
                    if (in_array((string) $row['type'], ['expense', 'payment'], true)) {
                        $signed = -abs($signed);
                    } elseif (in_array((string) $row['type'], ['income', 'collection'], true)) {
                        $signed = abs($signed);
                    }
                    ?>
                    <tr>
                        <td data-sort="<?= e((string) $row['occurred_at']) ?>"><?= e(substr((string) $row['occurred_at'], 0, 16)) ?></td>
                        <td data-sort="<?= e(mb_strtolower((string) $row['entity_name'])) ?>"><?= e($row['entity_name']) ?></td>
                        <td data-sort="<?= e(mb_strtolower($typeLabel)) ?>"><span class="badge"><?= e($typeLabel) ?></span></td>
                        <td data-sort="<?= e(mb_strtolower((string) ($row['account_name'] ?? ''))) ?>"><?= e($row['account_name'] ?? '—') ?></td>
                        <td data-sort="<?= e(mb_strtolower((string) ($row['counterparty_name'] ?? ''))) ?>"><?= e($row['counterparty_name'] ?? '—') ?></td>
                        <td data-sort="<?= e(mb_strtolower((string) ($row['category_name'] ?? ''))) ?>"><?= e($row['category_name'] ?? '—') ?></td>
                        <td data-sort="<?= e(mb_strtolower((string) ($row['description'] ?? ''))) ?>"><?= e($row['description'] ?? '—') ?></td>
                        <td class="num" data-sort="<?= e((string) $signed) ?>"><?= money_flow($row['amount'], $row['currency_code'], (string) $row['type']) ?></td>
                        <td class="actions">
                            <a href="<?= e(url('/movimientos/' . (int) $row['id'] . '/editar')) ?>">Editar</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php
            $pagerRoute = '/movimientos';
            $pagerQuery = ['entity_id' => $entityId];
            require __DIR__ . '/../partials/pagination.php';
            ?>
        </div>
        <script>
        (() => {
          const table = document.getElementById('movements-table');
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
