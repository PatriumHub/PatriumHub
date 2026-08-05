<section class="page">
    <div class="page-head"><h1><?= e($title) ?></h1><a class="btn ghost" href="<?= e(url('/inventario')) ?>">Volver</a></div>
    <?php require __DIR__ . '/../partials/alerts.php'; ?>
    <?php $i = $item; $isEdit = !empty($i['id']); ?>
    <form method="post" action="<?= e(url($isEdit ? '/inventario/' . $i['id'] : '/inventario')) ?>" class="panel form-grid">
        <?= csrf_field() ?>
        <label>Empresa *
            <select name="entity_id" required>
                <option value="">—</option>
                <?php foreach ($entities as $ent): ?>
                    <option value="<?= (int) $ent['id'] ?>"<?= selected($i['entity_id'] ?? '', $ent['id']) ?>><?= e($ent['name']) ?></option>
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
        <label>Valor de stock *
            <input name="stock_value" required value="<?= e((string) ($i['stock_value'] ?? '0')) ?>">
        </label>
        <label>Unidades (opcional)
            <input name="units_total" value="<?= e((string) ($i['units_total'] ?? '0')) ?>">
        </label>
        <input type="hidden" name="name" value="<?= e($i['name'] ?? 'Inventario manual') ?>">
        <input type="hidden" name="products_count" value="<?= e((string) ($i['products_count'] ?? '0')) ?>">
        <label class="check"><input type="checkbox" name="include_in_net_worth" value="1"<?= checked($i['include_in_net_worth'] ?? 1) ?>> Incluir en patrimonio</label>
        <label class="full">Notas<textarea name="notes" rows="2"><?= e($i['notes'] ?? '') ?></textarea></label>
        <div class="full"><button type="submit">Guardar</button></div>
    </form>
</section>
