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
        <div class="panel">
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
                            <a href="<?= e(url('/participaciones/' . $row['id'] . '/editar')) ?>">Editar</a>
                            <form method="post" action="<?= e(url('/participaciones/' . $row['id'] . '/eliminar')) ?>" class="inline" onsubmit="return confirm('¿Eliminar participación?');">
                                <?= csrf_field() ?>
                                <button type="submit" class="linkish">Eliminar</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
