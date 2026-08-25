<?php
$canWrite = !empty($canWrite);
$totals = $totals ?? ['count' => 0, 'active' => 0, 'inactive' => 0, 'with_industry' => 0, 'models' => 0];
$charts = $charts ?? ['byType' => ['labels' => [], 'values' => []], 'byEntity' => ['labels' => [], 'values' => []]];
$hide = hide_amounts();
?>
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
        <div class="stat-row">
            <div class="stat tone-amber">
                <span class="stat-label">Empresas</span>
                <span class="stat-value"><?= (int) $totals['count'] ?></span>
            </div>
            <div class="stat tone-green">
                <span class="stat-label">Activas</span>
                <span class="stat-value"><?= (int) $totals['active'] ?></span>
            </div>
            <div class="stat tone-rose">
                <span class="stat-label">Inactivas</span>
                <span class="stat-value"><?= (int) $totals['inactive'] ?></span>
            </div>
            <div class="stat tone-blue">
                <span class="stat-label">Con rubro</span>
                <span class="stat-value"><?= (int) $totals['with_industry'] ?></span>
                <span class="stat-hint muted"><?= (int) $totals['models'] ?> modelo(s)</span>
            </div>
        </div>

        <?php
        $titleType = 'Estado';
        $titleEntity = 'Por rubro';
        $hintType = 'Activas vs inactivas';
        $hintEntity = 'Cantidad de empresas';
        $unitLabel = 'count';
        $currency = 'ARS';
        require __DIR__ . '/../partials/list_charts.php';
        ?>

        <div class="panel mt">
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
                            <div class="row-icon-actions">
                                <?= icon_action_link(url('/empresas/' . $row['id']), 'eye', 'Ver') ?>
                                <?php if ($canWrite): ?>
                                    <?= icon_action_link(url('/empresas/' . $row['id'] . '/editar'), 'edit', 'Editar') ?>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
