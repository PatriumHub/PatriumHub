<?php
$groups = $groups ?? [];
$typeLabel = static fn (string $type): string => $type === 'person' ? 'Persona' : 'Empresa';
?>
<section class="page">
    <div class="page-head">
        <div>
            <h1>Gastos agrupados</h1>
            <p class="lede">Todos los gastos fijos, agrupados por persona o empresa</p>
        </div>
        <div class="actions">
            <a class="btn" href="<?= e(url('/presupuestos/plantillas/nueva')) ?>">+ Gasto</a>
            <a class="btn ghost" href="<?= e(url('/presupuestos')) ?>">Volver al mes</a>
        </div>
    </div>
    <?php require __DIR__ . '/../partials/alerts.php'; ?>

    <?php if ($groups === []): ?>
        <?php
        $message = 'Sin gastos. Ej.: Alquiler, Luz, Internet asignados a una persona o empresa.';
        $ctaHref = '/presupuestos/plantillas/nueva';
        $ctaLabel = 'Crear gasto';
        require __DIR__ . '/../partials/empty.php';
        ?>
    <?php else: ?>
        <?php foreach ($groups as $group): ?>
            <div class="panel mt">
                <div class="page-head" style="margin:0 0 0.85rem">
                    <div>
                        <h2 style="margin:0"><?= e($group['entity_name']) ?></h2>
                        <p class="muted" style="margin:0.3rem 0 0">
                            <?= e($typeLabel((string) $group['entity_type'])) ?>
                            · <?= count($group['items']) ?> gasto(s)
                        </p>
                    </div>
                    <div class="actions">
                        <strong><?= e(money($group['total'], $group['items'][0]['currency_code'] ?? 'ARS')) ?></strong>
                        <a class="btn ghost" href="<?= e(url('/presupuestos/plantillas/nueva?entity_id=' . (int) $group['entity_id'])) ?>">+ Gasto</a>
                    </div>
                </div>
                <table class="table">
                    <thead>
                    <tr>
                        <th>Nombre</th>
                        <th class="num">Monto</th>
                        <th class="num">Día vto.</th>
                        <th>Cuenta sugerida</th>
                        <th>Estado</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($group['items'] as $row): ?>
                        <tr>
                            <td><?= e($row['name']) ?></td>
                            <td class="num"><?= e(money($row['amount'], $row['currency_code'])) ?></td>
                            <td class="num"><?= (int) $row['due_day'] ?></td>
                            <td><?= e($row['account_name'] ?? '—') ?></td>
                            <td><?= !empty($row['is_active']) ? 'Activo' : 'Inactivo' ?></td>
                            <td class="actions">
                                <a href="<?= e(url('/presupuestos/plantillas/' . (int) $row['id'] . '/editar')) ?>">Editar</a>
                                <form method="post" action="<?= e(url('/presupuestos/plantillas/' . (int) $row['id'] . '/eliminar')) ?>" class="inline" onsubmit="return confirm('¿Sacar este gasto del grupo? Se borran pendientes; los pagos ya hechos se mantienen y no se regenera.');">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="linkish">Eliminar</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</section>
