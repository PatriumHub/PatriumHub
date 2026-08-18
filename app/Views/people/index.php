<?php
$canWrite = !empty($canWrite);
$totals = $totals ?? ['count' => 0, 'active' => 0, 'inactive' => 0, 'with_document' => 0, 'countries' => 0];
$charts = $charts ?? ['byType' => ['labels' => [], 'values' => []], 'byEntity' => ['labels' => [], 'values' => []]];
$hide = hide_amounts();
?>
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
        <div class="stat-row">
            <div class="stat tone-amber">
                <span class="stat-label">Personas</span>
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
            <div class="stat tone-teal">
                <span class="stat-label">Con documento</span>
                <span class="stat-value"><?= (int) $totals['with_document'] ?></span>
                <span class="stat-hint muted"><?= (int) $totals['countries'] ?> país(es)</span>
            </div>
        </div>

        <?php
        $titleType = 'Estado';
        $titleEntity = 'Por país';
        $hintType = 'Activas vs inactivas';
        $hintEntity = 'Cantidad de personas';
        $unitLabel = 'count';
        $currency = 'ARS';
        require __DIR__ . '/../partials/list_charts.php';
        ?>

        <div class="panel mt">
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
