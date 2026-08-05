<section class="page">
    <div class="page-head">
        <div><h1>Pasivos</h1><p class="lede">Deudas y obligaciones</p></div>
        <a class="btn" href="<?= e(url('/pasivos/nuevo')) ?>">Nuevo pasivo</a>
    </div>
    <?php require __DIR__ . '/../partials/alerts.php'; ?>
    <?php if (empty($rows)): ?>
        <?php $message = 'Sin pasivos.'; $ctaHref = '/pasivos/nuevo'; $ctaLabel = 'Registrar pasivo'; require __DIR__ . '/../partials/empty.php'; ?>
    <?php else: ?>
        <div class="panel">
            <table class="table">
                <thead><tr><th>Nombre</th><th>Entidad</th><th>Tipo</th><th class="num">Pendiente</th><th>Estado</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><?= e($row['name']) ?></td>
                        <td><?= e($row['entity_name']) ?></td>
                        <td><?= e($row['liability_type']) ?></td>
                        <td class="num"><?= e(money($row['outstanding_amount'], $row['currency_code'])) ?></td>
                        <td><span class="badge"><?= e($row['status']) ?></span></td>
                        <td class="actions"><a href="<?= e(url('/pasivos/' . $row['id'] . '/editar')) ?>">Editar</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
