<section class="page">
    <div class="page-head"><h1><?= e($title) ?></h1><a class="btn ghost" href="<?= e(url('/cobrables')) ?>">Volver</a></div>
    <?php require __DIR__ . '/../partials/alerts.php'; ?>
    <?php $i = $item; $isEdit = !empty($i['id']); ?>
    <form method="post" action="<?= e(url($isEdit ? '/cobrables/' . $i['id'] : '/cobrables')) ?>" class="panel form-grid">
        <?= csrf_field() ?>
        <label>Quién cobra (entidad) *
            <select name="entity_id" required>
                <option value="">—</option>
                <?php foreach ($entities as $ent): ?>
                    <option value="<?= (int) $ent['id'] ?>"<?= selected($i['entity_id'] ?? '', $ent['id']) ?>><?= e($ent['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Deudor *
            <input name="debtor_name" required value="<?= e($i['debtor_name'] ?? '') ?>" placeholder="Nombre de quien debe">
        </label>
        <label>Si es entidad interna
            <select name="debtor_entity_id">
                <option value="">— externo —</option>
                <?php foreach ($entities as $ent): ?>
                    <option value="<?= (int) $ent['id'] ?>"<?= selected($i['debtor_entity_id'] ?? '', $ent['id']) ?>><?= e($ent['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Moneda
            <select name="currency_code">
                <?php foreach ($currencies as $c): ?>
                    <option value="<?= e($c['code']) ?>"<?= selected($i['currency_code'] ?? 'ARS', $c['code']) ?>><?= e($c['code']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Saldo a cobrar *
            <input name="outstanding_amount" required value="<?= e((string) ($i['outstanding_amount'] ?? $i['original_amount'] ?? '0')) ?>">
        </label>
        <?php if ($isEdit): ?>
            <label>Monto original
                <input name="original_amount" value="<?= e((string) ($i['original_amount'] ?? '0')) ?>">
            </label>
            <label>Estado
                <select name="status">
                    <?php foreach (['current' => 'Al día', 'overdue' => 'Vencido', 'uncollectible' => 'Incobrable', 'cancelled' => 'Cancelado'] as $s => $label): ?>
                        <option value="<?= $s ?>"<?= selected($i['status'] ?? 'current', $s) ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        <?php else: ?>
            <input type="hidden" name="original_amount" value="">
            <input type="hidden" name="status" value="current">
        <?php endif; ?>
        <label>Vencimiento
            <input type="date" name="due_date" value="<?= e($i['due_date'] ?? '') ?>">
        </label>
        <label class="check"><input type="checkbox" name="include_in_net_worth" value="1"<?= checked($i['include_in_net_worth'] ?? 1) ?>> Incluir en patrimonio</label>
        <label class="full">Notas<textarea name="notes" rows="2"><?= e($i['notes'] ?? '') ?></textarea></label>
        <div class="full"><button type="submit">Guardar</button></div>
    </form>
</section>
