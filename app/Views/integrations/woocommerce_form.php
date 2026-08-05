<section class="page">
    <div class="page-head">
        <div>
            <h1><?= e($title) ?></h1>
            <p class="lede">REST API de solo lectura. Consumer Key/Secret se guardan cifrados en la BD desde este menú — no uses .env.</p>
        </div>
        <a class="btn ghost" href="<?= e(url('/integraciones')) ?>">Volver</a>
    </div>
    <?php require __DIR__ . '/../partials/alerts.php'; ?>
    <?php
    $i = $item;
    $cfg = $i['config'] ?? [];
    $isEdit = !empty($i['id']);
    $action = $isEdit ? '/integraciones/' . $i['id'] : '/integraciones/woocommerce';
    ?>
    <form method="post" action="<?= e(url($action)) ?>" class="panel form-grid">
        <?= csrf_field() ?>
        <label>Empresa *
            <select name="entity_id" required>
                <option value="">—</option>
                <?php foreach ($entities as $ent): ?>
                    <option value="<?= (int) $ent['id'] ?>"<?= selected($i['entity_id'] ?? '', $ent['id']) ?>><?= e($ent['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Nombre de la conexión *
            <input name="name" required value="<?= e($i['name'] ?? '') ?>" placeholder="WC HomeSpot">
        </label>
        <label class="full">URL de la tienda *
            <input name="store_url" required value="<?= e($cfg['store_url'] ?? '') ?>" placeholder="https://mitienda.com">
        </label>
        <label>Consumer Key <?= $isEdit ? '' : '*' ?>
            <input name="consumer_key" <?= $isEdit ? '' : 'required' ?> autocomplete="off" placeholder="<?= !empty($hasKey) ? '•••••••• (dejar vacío para no cambiar)' : 'ck_...' ?>">
        </label>
        <label>Consumer Secret <?= $isEdit ? '' : '*' ?>
            <input name="consumer_secret" <?= $isEdit ? '' : 'required' ?> autocomplete="off" placeholder="<?= !empty($hasSecret) ? '•••••••• (dejar vacío para no cambiar)' : 'cs_...' ?>">
        </label>
        <label>Versión API
            <input name="api_version" value="<?= e($cfg['api_version'] ?? 'wc/v3') ?>">
        </label>
        <label>Moneda stock/ventas
            <select name="currency_code">
                <?php foreach ($currencies as $c): ?>
                    <option value="<?= e($c['code']) ?>"<?= selected($cfg['currency_code'] ?? 'ARS', $c['code']) ?>><?= e($c['code']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Umbral stock bajo
            <input name="low_stock_threshold" value="<?= e((string) ($cfg['low_stock_threshold'] ?? 5)) ?>">
        </label>
        <?php if ($isEdit): ?>
            <label>Estado
                <select name="status">
                    <?php foreach (['active', 'disabled', 'error'] as $s): ?>
                        <option value="<?= $s ?>"<?= selected($i['status'] ?? 'active', $s) ?>><?= $s ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        <?php endif; ?>
        <label class="check"><input type="checkbox" name="sync_auto" value="1"<?= checked($cfg['sync_auto'] ?? true) ?>> Sync automático (cron)</label>
        <label class="check"><input type="checkbox" name="sync_products" value="1"<?= checked($cfg['sync_products'] ?? true) ?>> Importar productos / stock</label>
        <label class="check"><input type="checkbox" name="sync_orders" value="1"<?= checked($cfg['sync_orders'] ?? true) ?>> Importar pedidos / ventas</label>
        <div class="full"><button type="submit">Guardar</button></div>
    </form>
</section>
