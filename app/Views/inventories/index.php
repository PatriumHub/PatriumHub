<section class="page">
    <div class="page-head">
        <div><h1>Inventario</h1><p class="lede">Valor de stock manual</p></div>
        <a class="btn" href="<?= e(url('/inventario/nuevo')) ?>">Nuevo inventario</a>
    </div>
    <?php require __DIR__ . '/../partials/alerts.php'; ?>
    <?php if (empty($rows)): ?>
        <?php $message = 'Sin inventarios.'; $ctaHref = '/inventario/nuevo'; $ctaLabel = 'Crear inventario'; require __DIR__ . '/../partials/empty.php'; ?>
    <?php else: ?>
        <div class="panel">
            <table class="table">
                <thead><tr><th>Nombre</th><th>Entidad</th><th>Fuente</th><th class="num">Valor</th><th class="num">Unidades</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><?= e($row['name']) ?></td>
                        <td><?= e($row['entity_name']) ?></td>
                        <td><?= e($row['source']) ?></td>
                        <td class="num"><?= e(money($row['stock_value'], $row['currency_code'])) ?></td>
                        <td class="num"><?= e(number_format((float)$row['units_total'], 2, ',', '.')) ?></td>
                        <td class="actions"><a href="<?= e(url('/inventario/' . $row['id'] . '/editar')) ?>">Editar</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
