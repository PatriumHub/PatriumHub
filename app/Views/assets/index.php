<?php
$currency = $currency ?? 'ARS';
$currencies = $currencies ?? [];
$totals = $totals ?? [
    'value' => 0, 'count' => 0, 'active' => 0, 'in_net_worth' => 0,
    'top_category' => '—', 'top_category_value' => 0,
];
$charts = $charts ?? [
    'byType' => ['labels' => [], 'values' => []],
    'byEntity' => ['labels' => [], 'values' => []],
];
$hide = hide_amounts();
?>
<section class="page">
    <div class="page-head">
        <div><h1>Activos varios</h1><p class="lede">Vehículos, equipos, inversiones y más</p></div>
        <a class="btn" href="<?= e(url('/activos/nuevo')) ?>">Nuevo activo</a>
    </div>
    <?php require __DIR__ . '/../partials/alerts.php'; ?>

    <form method="get" class="filters inline-filters" action="<?= e(form_action()) ?>">
        <?= route_field('/activos') ?>
        <label>Moneda (totales / gráficos)
            <select name="currency" onchange="this.form.submit()">
                <?php foreach ($currencies as $c): ?>
                    <option value="<?= e($c['code']) ?>"<?= selected($currency, $c['code']) ?>><?= e($c['code']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
    </form>

    <?php if (empty($rows)): ?>
        <?php
        $message = 'Sin activos cargados.';
        $ctaHref = '/activos/nuevo';
        $ctaLabel = 'Creá tu primer activo';
        require __DIR__ . '/../partials/empty.php';
        ?>
    <?php else: ?>
        <div class="stat-row">
            <div class="stat tone-green">
                <span class="stat-label">Valor total</span>
                <span class="stat-value"><?= $hide ? '••••' : e(money($totals['value'], $currency)) ?></span>
                <span class="stat-hint muted">En <?= e($currency) ?></span>
            </div>
            <div class="stat tone-teal">
                <span class="stat-label"><?= e((string) $totals['top_category']) ?></span>
                <span class="stat-value"><?= $hide ? '••••' : e(money($totals['top_category_value'], $currency)) ?></span>
                <span class="stat-hint muted">Categoría principal</span>
            </div>
            <div class="stat tone-blue">
                <span class="stat-label">En patrimonio</span>
                <span class="stat-value"><?= (int) $totals['in_net_worth'] ?></span>
            </div>
            <div class="stat tone-amber">
                <span class="stat-label">Activos</span>
                <span class="stat-value"><?= (int) $totals['count'] ?></span>
                <span class="stat-hint muted"><?= (int) $totals['active'] ?> activos</span>
            </div>
        </div>

        <?php
        $titleType = 'Valor por categoría';
        $titleEntity = 'Valor por titular';
        $hintType = 'Distribución en ' . $currency;
        $hintEntity = 'Reparto según % de co-titulares';
        $unitLabel = 'money';
        require __DIR__ . '/../partials/list_charts.php';
        ?>

        <div class="panel mt">
            <table class="table">
                <thead><tr><th>Nombre</th><th>Titulares</th><th>Cuenta</th><th>Categoría</th><th class="num">Valor</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><?= e($row['name']) ?></td>
                        <td><?= e($row['owners_label'] ?? $row['entity_name']) ?></td>
                        <td><?= e($row['account_name'] ?? '—') ?></td>
                        <td><?= e($row['category']) ?></td>
                        <td class="num"><?= e(money($row['current_value'], $row['currency_code'])) ?></td>
                        <td class="actions col-actions">
                            <div class="row-icon-actions">
                                <?= icon_action_link(url('/activos/' . $row['id'] . '/editar'), 'edit', 'Editar') ?>
                                <form method="post" action="<?= e(url('/activos/' . (int) $row['id'] . '/eliminar')) ?>" onsubmit="return confirm('¿Eliminar este activo? No se puede deshacer.');">
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
