<?php
$currency = $currency ?? 'USD';
$currencies = $currencies ?? [];
$typeLabels = $typeLabels ?? [];
$totals = $totals ?? [
    'value' => 0, 'owned_value' => 0, 'homes' => 0, 'count' => 0, 'active' => 0, 'in_net_worth' => 0,
];
$charts = $charts ?? [
    'byType' => ['labels' => [], 'values' => []],
    'byEntity' => ['labels' => [], 'values' => []],
];
$chartUnit = $chartUnit ?? 'money';
$hide = hide_amounts();
?>
<section class="page">
    <div class="page-head">
        <div><h1>Propiedades</h1><p class="lede">Inmuebles y valuaciones</p></div>
        <a class="btn" href="<?= e(url('/propiedades/nueva')) ?>">Nueva propiedad</a>
    </div>
    <?php require __DIR__ . '/../partials/alerts.php'; ?>

    <form method="get" class="filters inline-filters" action="<?= e(form_action()) ?>">
        <?= route_field('/propiedades') ?>
        <label>Moneda (totales / gráficos)
            <select name="currency" onchange="this.form.submit()">
                <?php foreach ($currencies as $c): ?>
                    <option value="<?= e($c['code']) ?>"<?= selected($currency, $c['code']) ?>><?= e($c['code']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
    </form>

    <?php if (empty($rows)): ?>
        <?php $message = 'Sin propiedades.'; $ctaHref = '/propiedades/nueva'; $ctaLabel = 'Cargá tu primera propiedad'; require __DIR__ . '/../partials/empty.php'; ?>
    <?php else: ?>
        <div class="stat-row">
            <div class="stat tone-green">
                <span class="stat-label">Valor total</span>
                <span class="stat-value"><?= $hide ? '••••' : e(money($totals['value'], $currency)) ?></span>
                <span class="stat-hint muted">En <?= e($currency) ?></span>
            </div>
            <div class="stat tone-teal">
                <span class="stat-label">Según tu %</span>
                <span class="stat-value"><?= $hide ? '••••' : e(money($totals['owned_value'], $currency)) ?></span>
                <span class="stat-hint muted">Ajustado por ownership</span>
            </div>
            <div class="stat tone-blue">
                <span class="stat-label">Casas y deptos</span>
                <span class="stat-value"><?= $hide ? '••••' : e(money($totals['homes'], $currency)) ?></span>
            </div>
            <div class="stat tone-amber">
                <span class="stat-label">Propiedades</span>
                <span class="stat-value"><?= (int) $totals['count'] ?></span>
                <span class="stat-hint muted"><?= (int) $totals['active'] ?> activas · <?= (int) $totals['in_net_worth'] ?> en neto</span>
            </div>
        </div>

        <?php
        $titleType = $chartUnit === 'count' ? 'Cantidad por tipo' : 'Valor por tipo';
        $titleEntity = $chartUnit === 'count' ? 'Cantidad por entidad' : 'Valor por entidad';
        $hintType = $chartUnit === 'count'
            ? 'Sin montos en ' . $currency . ' — se muestra cantidad'
            : ('Casa, depto, terreno, etc. (' . $currency . ')');
        $hintEntity = $chartUnit === 'count'
            ? 'Propiedades por persona/empresa'
            : 'Cómo se reparte entre personas/empresas';
        $unitLabel = $chartUnit;
        require __DIR__ . '/../partials/list_charts.php';
        ?>

        <div class="panel mt">
            <table class="table">
                <thead><tr><th>Nombre</th><th>Entidad</th><th>Tipo</th><th class="num">Valor</th><th>%</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <?php
                    $ptype = (string) ($row['property_type'] ?? '');
                    $ptypeLabel = $typeLabels[$ptype] ?? $ptype;
                    ?>
                    <tr>
                        <td><?= e($row['name']) ?></td>
                        <td><?= e($row['entity_name']) ?></td>
                        <td><?= e($ptypeLabel) ?></td>
                        <td class="num"><?= e(money($row['current_value'], $row['currency_code'])) ?></td>
                        <td><?= e(number_format((float)$row['ownership_pct'], 2, ',', '.')) ?>%</td>
                        <td class="actions">
                            <a href="<?= e(url('/propiedades/' . $row['id'] . '/editar')) ?>">Editar</a>
                            <form method="post" action="<?= e(url('/propiedades/' . (int) $row['id'] . '/eliminar')) ?>" class="inline" onsubmit="return confirm('¿Eliminar esta propiedad? No se puede deshacer.');">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn danger sm">Eliminar</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
