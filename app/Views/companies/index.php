<section class="page">
    <div class="page-head">
        <div>
            <h1>Empresas</h1>
            <p class="lede">Entidades empresariales</p>
        </div>
        <a class="btn" href="<?= e(url('/empresas/nueva')) ?>">Nueva empresa</a>
    </div>
    <?php require __DIR__ . '/../partials/alerts.php'; ?>
    <?php if (empty($rows)): ?>
        <div class="panel empty"><p>No hay empresas cargadas.</p></div>
    <?php else: ?>
        <div class="panel">
            <table class="table">
                <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Razón social</th>
                    <th>Rubro</th>
                    <th>Estado</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><a href="<?= e(url('/empresas/' . $row['id'])) ?>"><?= e($row['name']) ?></a></td>
                        <td><?= e($row['legal_name'] ?? '—') ?></td>
                        <td><?= e($row['industry'] ?? '—') ?></td>
                        <td><?= (int) $row['is_active'] ? 'Activa' : 'Inactiva' ?></td>
                        <td class="actions">
                            <a href="<?= e(url('/empresas/' . $row['id'])) ?>">Ver</a>
                            <a href="<?= e(url('/empresas/' . $row['id'] . '/editar')) ?>">Editar</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
