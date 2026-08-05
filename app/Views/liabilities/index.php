<?php
$budgetGroups = $budgetGroups ?? [];
$hasRows = !empty($rows);
$hasBudgets = $budgetGroups !== [];
$typeLabel = static fn (string $type): string => $type === 'person' ? 'Persona' : 'Empresa';
?>
<section class="page">
    <div class="page-head">
        <div>
            <h1>Pasivos</h1>
            <p class="lede">Deudas clásicas + presupuestos pendientes del mes (también bajan el neto)</p>
        </div>
        <div class="actions">
            <a class="btn" href="<?= e(url('/pasivos/nuevo')) ?>">Nuevo pasivo</a>
        </div>
    </div>
    <?php require __DIR__ . '/../partials/alerts.php'; ?>

    <?php if (!$hasRows && !$hasBudgets): ?>
        <?php $message = 'Sin pasivos ni presupuestos pendientes.'; $ctaHref = '/pasivos/nuevo'; $ctaLabel = 'Registrar pasivo'; require __DIR__ . '/../partials/empty.php'; ?>
    <?php else: ?>

        <?php if ($hasBudgets): ?>
            <div class="panel mt">
                <div class="page-head" style="margin:0 0 1rem">
                    <div>
                        <h2 style="margin:0">Presupuestos pendientes</h2>
                        <p class="muted" style="margin:0.35rem 0 0">Total por persona/empresa. El detalle se liquida en Presupuestos.</p>
                    </div>
                    <a class="btn ghost" href="<?= e(url('/presupuestos')) ?>">Ir a Presupuestos</a>
                </div>
                <table class="table">
                    <thead>
                    <tr>
                        <th>Entidad</th>
                        <th>Tipo</th>
                        <th>Gastos</th>
                        <th class="num">Total pendiente</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($budgetGroups as $group): ?>
                        <tr>
                            <td><?= e($group['entity_name']) ?></td>
                            <td><?= e($typeLabel((string) $group['entity_type'])) ?></td>
                            <td><?= count($group['items']) ?></td>
                            <td class="num"><?= e(money($group['total'], $group['currency_code'])) ?></td>
                            <td class="actions">
                                <a href="<?= e(url('/presupuestos?entity_id=' . (int) $group['entity_id'])) ?>">Ver</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <div class="panel mt">
            <h2 style="margin-top:0">Deudas y obligaciones</h2>
            <?php if (!$hasRows): ?>
                <p class="muted">Sin pasivos clásicos (préstamos, tarjetas, etc.).
                    <a href="<?= e(url('/pasivos/nuevo')) ?>">Registrar uno</a>
                </p>
            <?php else: ?>
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
            <?php endif; ?>
        </div>

    <?php endif; ?>
</section>
