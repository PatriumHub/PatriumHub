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
            <table class="table">
                <thead><tr><th>Fecha</th><th>Entidad</th><th>Tipo</th><th>Cuenta</th><th>Destino</th><th>Categoría</th><th>Descripción</th><th class="num">Importe</th></tr></thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><?= e(substr((string)$row['occurred_at'], 0, 16)) ?></td>
                        <td><?= e($row['entity_name']) ?></td>
                        <td><span class="badge"><?= e(movement_type_label((string) $row['type'])) ?></span></td>
                        <td><?= e($row['account_name'] ?? '—') ?></td>
                        <td><?= e($row['counterparty_name'] ?? '—') ?></td>
                        <td><?= e($row['category_name'] ?? '—') ?></td>
                        <td><?= e($row['description'] ?? '—') ?></td>
                        <td class="num"><?= money_flow($row['amount'], $row['currency_code'], (string) $row['type']) ?></td>
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
    <?php endif; ?>
</section>
