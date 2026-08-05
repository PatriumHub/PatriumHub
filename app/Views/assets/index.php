<section class="page">
    <div class="page-head">
        <div><h1>Activos varios</h1><p class="lede">Vehículos, equipos, inversiones y más</p></div>
        <a class="btn" href="<?= e(url('/activos/nuevo')) ?>">Nuevo activo</a>
    </div>
    <?php require __DIR__ . '/../partials/alerts.php'; ?>
    <?php if (empty($rows)): ?>
        <?php
        $message = 'Sin activos cargados.';
        $ctaHref = '/activos/nuevo';
        $ctaLabel = 'Creá tu primer activo';
        require __DIR__ . '/../partials/empty.php';
        ?>
    <?php else: ?>
        <div class="panel">
            <table class="table">
                <thead><tr><th>Nombre</th><th>Entidad</th><th>Cuenta</th><th>Categoría</th><th class="num">Valor</th><th>%</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><?= e($row['name']) ?></td>
                        <td><?= e($row['entity_name']) ?></td>
                        <td><?= e($row['account_name'] ?? '—') ?></td>
                        <td><?= e($row['category']) ?></td>
                        <td class="num"><?= e(money($row['current_value'], $row['currency_code'])) ?></td>
                        <td><?= ($row['entity_type'] ?? '') === 'company'
                            ? '—'
                            : e(number_format((float) $row['ownership_pct'], 2, ',', '.')) . '%' ?></td>
                        <td class="actions"><a href="<?= e(url('/activos/' . $row['id'] . '/editar')) ?>">Editar</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
