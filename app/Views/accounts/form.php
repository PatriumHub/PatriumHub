<section class="page">
    <div class="page-head">
        <h1><?= e($title) ?></h1>
        <a class="btn ghost" href="<?= e(url('/cuentas')) ?>">Volver</a>
    </div>
    <?php require __DIR__ . '/../partials/alerts.php'; ?>
    <?php $a = $account; $isEdit = !empty($a['id']); ?>
    <form method="post" action="<?= e(url($isEdit ? '/cuentas/' . $a['id'] : '/cuentas')) ?>" class="panel form-grid">
        <?= csrf_field() ?>
        <label>Entidad *
            <select name="entity_id" required>
                <option value="">—</option>
                <?php foreach ($entities as $ent): ?>
                    <option value="<?= (int) $ent['id'] ?>"<?= selected($a['entity_id'] ?? '', $ent['id']) ?>><?= e($ent['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Nombre *
            <input name="name" required value="<?= e($a['name'] ?? '') ?>" placeholder="<?= e(($a['type'] ?? '') === 'mercadopago' ? 'Ej. MP Homespot' : 'Ej. Galicia ARS') ?>">
        </label>
        <label>Tipo
            <select name="type">
                <?php
                $types = [
                    'bank' => 'Banco', 'checking' => 'Cuenta corriente', 'savings' => 'Caja de ahorro',
                    'wallet' => 'Billetera', 'mercadopago' => 'Mercado Pago', 'cash' => 'Efectivo',
                    'broker' => 'Broker', 'virtual' => 'Virtual', 'other' => 'Otro',
                ];
                foreach ($types as $t => $label): ?>
                    <option value="<?= $t ?>"<?= selected($a['type'] ?? 'bank', $t) ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Moneda
            <select name="currency_code">
                <?php foreach ($currencies as $c): ?>
                    <option value="<?= e($c['code']) ?>"<?= selected($a['currency_code'] ?? 'ARS', $c['code']) ?>><?= e($c['code']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Saldo
            <input name="balance" value="<?= e((string) ($a['balance'] ?? '0')) ?>">
        </label>
        <?php if ($isEdit): ?>
            <label>Retenido / pendiente
                <input name="balance_held" value="<?= e((string) ($a['balance_held'] ?? '0')) ?>">
            </label>
            <label>Estado
                <select name="status">
                    <option value="active"<?= selected($a['status'] ?? 'active', 'active') ?>>Activa</option>
                    <option value="closed"<?= selected($a['status'] ?? '', 'closed') ?>>Cerrada</option>
                </select>
            </label>
        <?php else: ?>
            <input type="hidden" name="balance_held" value="0">
            <input type="hidden" name="status" value="active">
        <?php endif; ?>
        <label class="check"><input type="checkbox" name="include_in_net_worth" value="1"<?= checked($a['include_in_net_worth'] ?? 1) ?>> Incluir en patrimonio</label>
        <label class="full">Notas<textarea name="notes" rows="2"><?= e($a['notes'] ?? '') ?></textarea></label>
        <div class="full"><button type="submit">Guardar</button></div>
    </form>
</section>
