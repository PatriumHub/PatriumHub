<?php $canWrite = !empty($canWrite); ?>
<section class="page">
    <div class="page-head">
        <div>
            <h1>Empresas</h1>
            <p class="lede"><?= $canWrite ? 'Entidades empresariales' : 'Empresas asignadas a tu usuario (solo lectura)' ?></p>
        </div>
        <?php if ($canWrite): ?>
            <a class="btn" href="<?= e(url('/empresas/nueva')) ?>">Nueva empresa</a>
        <?php endif; ?>
    </div>
    <?php require __DIR__ . '/../partials/alerts.php'; ?>
    <?php if (empty($rows)): ?>
        <div class="panel empty">
            <p><?= $canWrite ? 'No hay empresas cargadas.' : 'No tenés empresas asignadas. Pedile a un administrador que te dé acceso.' ?></p>
        </div>
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
                            <?php if ($canWrite): ?>
                                · <a href="<?= e(url('/empresas/' . $row['id'] . '/editar')) ?>">Editar</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
