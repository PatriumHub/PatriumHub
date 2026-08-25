<?php
$totals = $totals ?? ['count' => 0, 'owners' => 0, 'companies' => 0, 'avg_pct' => 0];
$charts = $charts ?? ['byType' => ['labels' => [], 'values' => []], 'byEntity' => ['labels' => [], 'values' => []]];
$hide = hide_amounts();
?>
<section class="page">
    <div class="page-head">
        <div>
            <h1>Participaciones</h1>
            <p class="lede">Ownerships persona → empresa. El patrimonio personal usa el % sobre la valuación de la empresa.</p>
        </div>
        <a class="btn" href="<?= e(url('/participaciones/nueva')) ?>">Nueva participación</a>
    </div>
    <?php require __DIR__ . '/../partials/alerts.php'; ?>
    <?php if (empty($rows)): ?>
        <div class="panel empty"><p>No hay participaciones. Cargá ownerships para que el patrimonio personal sume valor de empresas sin duplicar activos internos.</p></div>
    <?php else: ?>
        <div class="stat-row">
            <div class="stat tone-amber">
                <span class="stat-label">Participaciones</span>
                <span class="stat-value"><?= (int) $totals['count'] ?></span>
            </div>
            <div class="stat tone-teal">
                <span class="stat-label">Dueños</span>
                <span class="stat-value"><?= (int) $totals['owners'] ?></span>
            </div>
            <div class="stat tone-blue">
                <span class="stat-label">Empresas</span>
                <span class="stat-value"><?= (int) $totals['companies'] ?></span>
            </div>
            <div class="stat tone-violet">
                <span class="stat-label">% promedio</span>
                <span class="stat-value"><?= e(number_format((float) $totals['avg_pct'], 1, ',', '.')) ?>%</span>
            </div>
        </div>

        <?php
        $titleType = '% por dueño';
        $titleEntity = '% por empresa';
        $hintType = 'Suma de porcentajes de ownership';
        $hintEntity = 'Suma de porcentajes por empresa';
        $unitLabel = 'pct';
        $currency = 'ARS';
        require __DIR__ . '/../partials/list_charts.php';
        ?>

        <div class="panel mt">
            <table class="table">
                <thead>
                <tr>
                    <th>Dueño</th>
                    <th>Empresa</th>
                    <th class="num">%</th>
                    <th>Notas</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><?= e($row['owner_name']) ?></td>
                        <td>
                            <a href="<?= e(url('/empresas/' . $row['company_entity_id'] . '?tab=participaciones')) ?>">
                                <?= e($row['company_name']) ?>
                            </a>
                        </td>
                        <td class="num"><?= e(number_format((float) $row['ownership_pct'], 2, ',', '.')) ?>%</td>
                        <td><?= e($row['notes'] ?? '—') ?></td>
                        <td class="actions">
                            <div class="row-icon-actions">
                                <?= icon_action_link(url('/participaciones/' . $row['id'] . '/editar'), 'edit', 'Editar') ?>
                                <form method="post" action="<?= e(url('/participaciones/' . $row['id'] . '/eliminar')) ?>" onsubmit="return confirm('¿Eliminar participación?');">
                                    <?= csrf_field() ?>
                                    <?= icon_action_button('trash', 'Eliminar', 'danger') ?>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
