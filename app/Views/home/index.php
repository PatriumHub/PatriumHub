<section class="page">
    <div class="page-head">
        <div>
            <h1>Inicio</h1>
            <p class="lede">Patrimonio consolidado (sin dobles por ownership)</p>
        </div>
        <form method="get" class="filters inline-filters" action="<?= e(form_action()) ?>">
            <?= route_field('/') ?>
            <label>
                Moneda
                <select name="currency" onchange="this.form.submit()">
                    <?php foreach ($currencies as $c): ?>
                        <option value="<?= e($c['code']) ?>"<?= selected($currency, $c['code']) ?>><?= e($c['code']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </form>
    </div>

    <?php require __DIR__ . '/../partials/alerts.php'; ?>
    <?php
    $chips = filter_chips([
        'currency' => $currency !== 'ARS' ? ('Moneda: ' . $currency) : null,
    ], array_filter(['currency' => $currency]), '/');
    require __DIR__ . '/../partials/filter_chips.php';
    ?>

    <?php $count = 4; require __DIR__ . '/../partials/skeleton_stats.php'; ?>
    <div class="stat-row" data-skeleton-target>
        <div class="stat accent">
            <span class="stat-label">Patrimonio neto</span>
            <span class="stat-value"><?= e(money($summary['net_worth'], $currency)) ?></span>
        </div>
        <div class="stat tone-green">
            <span class="stat-label">Activos totales</span>
            <span class="stat-value"><?= e(money($summary['total_assets'], $currency)) ?></span>
        </div>
        <div class="stat tone-rose">
            <span class="stat-label">Pasivos</span>
            <span class="stat-value"><?= e(money($summary['liabilities'], $currency)) ?></span>
        </div>
        <div class="stat tone-teal">
            <span class="stat-label">Liquidez</span>
            <span class="stat-value"><?= e(money($summary['liquidity'], $currency)) ?></span>
        </div>
    </div>

    <div class="grid-2">
        <div class="panel">
            <h2>Composición</h2>
            <ul class="plain">
                <li>Cuentas: <strong><?= e(money($summary['accounts'], $currency)) ?></strong></li>
                <li>Activos: <strong><?= e(money($summary['assets'], $currency)) ?></strong></li>
                <li>Propiedades: <strong><?= e(money($summary['properties'], $currency)) ?></strong></li>
                <li>Cobrables: <strong><?= e(money($summary['receivables'], $currency)) ?></strong></li>
                <li>Inventario: <strong><?= e(money($summary['inventories'], $currency)) ?></strong></li>
                <?php if (($summary['internal_netting'] ?? 0) > 0): ?>
                    <li>Neto deudas internas: <strong><?= e(money($summary['internal_netting'], $currency)) ?></strong></li>
                <?php endif; ?>
            </ul>
        </div>
        <div class="panel">
            <h2>Catálogo</h2>
            <ul class="plain">
                <li>Personas: <strong><?= (int) $counts['people'] ?></strong></li>
                <li>Empresas: <strong><?= (int) $counts['companies'] ?></strong></li>
                <li>Cuentas: <strong><?= (int) $counts['accounts'] ?></strong></li>
                <li>Integraciones: <strong><?= (int) $counts['integrations'] ?></strong></li>
            </ul>
            <p class="mt">
                <a class="btn" href="<?= e(url('/dashboard')) ?>">Dashboard</a>
                <a class="btn ghost" href="<?= e(url('/participaciones')) ?>">Participaciones</a>
                <a class="btn ghost" href="<?= e(url('/cuentas')) ?>">Cuentas</a>
            </p>
        </div>
    </div>

    <?php if (!empty($summary['by_entity'])): ?>
    <div class="panel mt">
        <h2>Por entidad</h2>
        <table class="table">
            <thead>
            <tr>
                <th>Entidad</th>
                <th class="num">Activos</th>
                <th class="num">Pasivos</th>
                <th class="num">Neto</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($summary['by_entity'] as $row): ?>
                <tr>
                    <td><?= e($row['name']) ?></td>
                    <td class="num"><?= e(money($row['total_assets'], $currency)) ?></td>
                    <td class="num"><?= e(money($row['liabilities'], $currency)) ?></td>
                    <td class="num"><strong><?= e(money($row['net_worth'], $currency)) ?></strong></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div class="panel mt empty">
        <p>Todavía no hay entidades. Empezá creando una <a href="<?= e(url('/personas/nueva')) ?>">persona</a> o una <a href="<?= e(url('/empresas/nueva')) ?>">empresa</a>.</p>
    </div>
    <?php endif; ?>
</section>
