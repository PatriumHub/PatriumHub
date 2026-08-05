<section class="page">
    <div class="page-head">
        <h1><?= e($title) ?></h1>
        <a class="btn ghost" href="<?= e(url('/participaciones')) ?>">Volver</a>
    </div>
    <?php require __DIR__ . '/../partials/alerts.php'; ?>
    <?php $o = $item; $isEdit = !empty($o['id']); ?>
    <form method="post" action="<?= e(url($isEdit ? '/participaciones/' . $o['id'] : '/participaciones')) ?>" class="panel form-grid">
        <?= csrf_field() ?>
        <label>Dueño (persona) *
            <select name="owner_entity_id" required>
                <option value="">—</option>
                <?php foreach ($people as $p): ?>
                    <option value="<?= (int) $p['id'] ?>"<?= selected($o['owner_entity_id'] ?? '', $p['id']) ?>><?= e($p['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Empresa *
            <select name="company_entity_id" required>
                <option value="">—</option>
                <?php foreach ($companies as $c): ?>
                    <option value="<?= (int) $c['id'] ?>"<?= selected($o['company_entity_id'] ?? '', $c['id']) ?>><?= e($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>% participación *
            <input name="ownership_pct" type="number" step="0.01" min="0.01" max="100" required value="<?= e((string) ($o['ownership_pct'] ?? '100')) ?>">
        </label>
        <label class="span-2">Notas
            <textarea name="notes" rows="3"><?= e($o['notes'] ?? '') ?></textarea>
        </label>
        <div class="form-actions span-2">
            <button type="submit">Guardar</button>
        </div>
    </form>
</section>
