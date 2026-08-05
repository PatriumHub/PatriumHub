<section class="page">
    <div class="page-head">
        <div>
            <h1><?= e($title) ?></h1>
            <p class="lede">Empresa del patrimonio</p>
        </div>
        <a class="btn ghost" href="<?= e(url('/empresas')) ?>">Volver</a>
    </div>
    <?php require __DIR__ . '/../partials/alerts.php'; ?>
    <?php $c = $company; $isEdit = !empty($c['id']); ?>
    <form method="post" action="<?= e(url($isEdit ? '/empresas/' . $c['id'] : '/empresas')) ?>" class="panel form-grid">
        <?= csrf_field() ?>
        <label>Nombre *
            <input name="name" required value="<?= e($c['name'] ?? '') ?>" placeholder="Ej. HomeSpot">
        </label>
        <label>Razón social
            <input name="legal_name" value="<?= e($c['legal_name'] ?? '') ?>" placeholder="Opcional, si difiere del nombre">
        </label>
        <label>CUIT
            <input name="tax_id" value="<?= e($c['tax_id'] ?? '') ?>">
        </label>
        <label>Rubro
            <input name="industry" value="<?= e($c['industry'] ?? '') ?>">
        </label>
        <label>País
            <input name="country" value="<?= e($c['country'] ?? '') ?>">
        </label>
        <?php if (!$isEdit): ?>
            <label>Dueño (persona)
                <select name="owner_entity_id">
                    <option value="">— opcional —</option>
                    <?php foreach ($people as $p): ?>
                        <option value="<?= (int) $p['id'] ?>"><?= e($p['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>% del dueño
                <input name="ownership_pct" type="number" step="0.01" min="0.01" max="100" value="100">
            </label>
        <?php else: ?>
            <label class="check"><input type="checkbox" name="is_active" value="1"<?= checked($c['is_active'] ?? 1) ?>> Activa</label>
        <?php endif; ?>
        <label class="full">Notas
            <textarea name="notes" rows="2"><?= e($c['notes'] ?? '') ?></textarea>
        </label>
        <div class="full"><button type="submit">Guardar</button></div>
    </form>
</section>
