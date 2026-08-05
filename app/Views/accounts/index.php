<section class="page">
    <div class="page-head">
        <div>
            <h1>Cuentas</h1>
            <p class="lede">Bancos, billeteras, efectivo y más</p>
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
        <div class="panel">
            <table class="table">
                <thead>
                <tr><th>Nombre</th><th>Entidad</th><th>Tipo</th><th>Moneda</th><th class="num">Saldo</th><th>Patrimonio</th><th></th></tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><?= e($row['name']) ?></td>
                        <td><?= e($row['entity_name']) ?></td>
                        <td><?= e($row['type']) ?></td>
                        <td><?= e($row['currency_code']) ?></td>
                        <td class="num">
                            <?= e(money($row['balance'], $row['currency_code'])) ?>
                            <?php if ($row['type'] === 'mercadopago' && (float) ($row['balance_held'] ?? 0) > 0.009): ?>
                                <div class="muted" style="font-size:0.8rem">retenido <?= e(money($row['balance_held'], $row['currency_code'])) ?></div>
                            <?php endif; ?>
                        </td>
                        <td><?= (int) $row['include_in_net_worth'] ? 'Sí' : 'No' ?></td>
                        <td class="actions"><a href="<?= e(url('/cuentas/' . $row['id'] . '/editar')) ?>">Editar</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
