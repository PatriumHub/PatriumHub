<?php
$returnTo = safe_return_path((string) ($returnTo ?? ($_GET['return_to'] ?? '')), '');
$backUrl = $returnTo !== '' ? $returnTo : ('/cobrables/' . (int) $item['id'] . '/editar');
?>
<section class="page">
    <div class="page-head">
        <div>
            <h1>Marcar pago</h1>
            <p class="lede">¿A qué cuenta de <?= e($entity['name'] ?? 'la entidad') ?> sumamos el cobro?</p>
        </div>
        <a class="btn ghost" href="<?= e(url($backUrl)) ?>">Volver</a>
    </div>
    <?php require __DIR__ . '/../partials/alerts.php'; ?>

    <div class="panel">
        <ul class="plain">
            <li>Deudor: <strong><?= e($item['debtor_name']) ?></strong></li>
            <li>Pendiente: <strong><?= e(money($item['outstanding_amount'], $item['currency_code'])) ?></strong></li>
            <li>Moneda: <strong><?= e($item['currency_code']) ?></strong></li>
        </ul>
    </div>

    <?php if (empty($accounts)): ?>
        <div class="panel mt">
            <p class="muted">No hay cuentas activas en <?= e($item['currency_code']) ?> para esta entidad.
                <a href="<?= e(url('/cuentas/nueva?entity_id=' . (int) $item['entity_id'])) ?>">Crear cuenta</a>
            </p>
        </div>
    <?php else: ?>
        <form method="post" action="<?= e(url('/cobrables/' . (int) $item['id'] . '/pagar')) ?>" class="panel form-grid mt">
            <?= csrf_field() ?>
            <?php if ($returnTo !== ''): ?>
                <input type="hidden" name="return_to" value="<?= e($returnTo) ?>">
            <?php endif; ?>
            <label>Cuenta destino *
                <select name="deposit_account_id" required>
                    <option value="">— elegí —</option>
                    <?php foreach ($accounts as $acc): ?>
                        <option value="<?= (int) $acc['id'] ?>">
                            <?= e($acc['name']) ?>
                            (<?= e($acc['currency_code']) ?> · saldo <?= e(money($acc['balance'], $acc['currency_code'])) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Fecha del cobro
                <input type="datetime-local" name="occurred_at"
                       value="<?= e(date('Y-m-d\TH:i')) ?>">
            </label>
            <div class="full">
                <button type="submit">Confirmar pago</button>
            </div>
        </form>
    <?php endif; ?>
</section>
