<section class="page">
    <div class="page-head">
        <div><h1>Cuentas por cobrar</h1><p class="lede">Dinero que te deben</p></div>
        <a class="btn" href="<?= e(url('/cobrables/nueva')) ?>">Nueva</a>
    </div>
    <?php require __DIR__ . '/../partials/alerts.php'; ?>
    <?php
    $statusLabels = $statusLabels ?? [
        'current' => 'Al día',
        'overdue' => 'Vencido',
        'paid' => 'Pago',
        'uncollectible' => 'Incobrable',
        'cancelled' => 'Cancelado',
    ];
    ?>
    <?php if (empty($rows)): ?>
        <?php $message = 'Sin cobrables.'; $ctaHref = '/cobrables/nueva'; $ctaLabel = 'Registrar cobrable'; require __DIR__ . '/../partials/empty.php'; ?>
    <?php else: ?>
        <div class="panel">
            <table class="table">
                <thead><tr><th>Deudor</th><th>Acreedor</th><th class="num">Pendiente</th><th>Vence</th><th>Estado</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <?php $st = (string) ($row['status'] ?? 'current'); ?>
                    <tr>
                        <td><?= e($row['debtor_name']) ?></td>
                        <td><?= e($row['entity_name']) ?></td>
                        <td class="num"><?= e(money($row['outstanding_amount'], $row['currency_code'])) ?></td>
                        <td><?= e($row['due_date'] ?? '—') ?></td>
                        <td><span class="badge"><?= e($statusLabels[$st] ?? $st) ?></span></td>
                        <td class="actions">
                            <a href="<?= e(url('/cobrables/' . $row['id'] . '/editar')) ?>">Editar</a>
                            <?php if ($st !== 'paid'): ?>
                                · <a href="<?= e(url('/cobrables/' . $row['id'] . '/pagar')) ?>">Cobrar</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
