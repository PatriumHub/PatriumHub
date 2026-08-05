<section class="page">
    <div class="page-head">
        <div><h1>Propiedades</h1><p class="lede">Inmuebles y valuaciones</p></div>
        <a class="btn" href="<?= e(url('/propiedades/nueva')) ?>">Nueva propiedad</a>
    </div>
    <?php require __DIR__ . '/../partials/alerts.php'; ?>
    <?php if (empty($rows)): ?>
        <?php $message = 'Sin propiedades.'; $ctaHref = '/propiedades/nueva'; $ctaLabel = 'Cargá tu primera propiedad'; require __DIR__ . '/../partials/empty.php'; ?>
    <?php else: ?>
        <div class="panel">
            <table class="table">
                <thead><tr><th>Nombre</th><th>Entidad</th><th>Tipo</th><th class="num">Valor</th><th>%</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><?= e($row['name']) ?></td>
                        <td><?= e($row['entity_name']) ?></td>
                        <td><?= e($row['property_type']) ?></td>
                        <td class="num"><?= e(money($row['current_value'], $row['currency_code'])) ?></td>
                        <td><?= e(number_format((float)$row['ownership_pct'], 2, ',', '.')) ?>%</td>
                        <td class="actions"><a href="<?= e(url('/propiedades/' . $row['id'] . '/editar')) ?>">Editar</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
