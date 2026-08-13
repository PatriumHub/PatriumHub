<?php $canWrite = !empty($canWrite); ?>
<section class="page">
    <div class="page-head">
        <div>
            <h1>Personas</h1>
            <p class="lede"><?= $canWrite ? 'Entidades personales del patrimonio' : 'Personas asignadas a tu usuario (solo lectura)' ?></p>
        </div>
        <?php if ($canWrite): ?>
            <a class="btn" href="<?= e(url('/personas/nueva')) ?>">Nueva persona</a>
        <?php endif; ?>
    </div>
    <?php require __DIR__ . '/../partials/alerts.php'; ?>
    <?php if (empty($rows)): ?>
        <div class="panel empty">
            <p><?= $canWrite ? 'No hay personas cargadas.' : 'No tenés personas asignadas. Pedile a un administrador que te dé acceso.' ?></p>
        </div>
    <?php else: ?>
        <div class="panel">
            <table class="table">
                <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Documento</th>
                    <th>País</th>
                    <th>Estado</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><a href="<?= e(url('/personas/' . $row['id'])) ?>"><?= e($row['name']) ?></a></td>
                        <td><?= e($row['document_id'] ?? '—') ?></td>
                        <td><?= e($row['country'] ?? '—') ?></td>
                        <td><?= (int) $row['is_active'] ? 'Activa' : 'Inactiva' ?></td>
                        <td class="actions">
                            <a href="<?= e(url('/personas/' . $row['id'])) ?>">Ficha</a>
                            <?php if ($canWrite): ?>
                                · <a href="<?= e(url('/personas/' . $row['id'] . '/editar')) ?>">Editar</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
