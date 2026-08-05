<?php
$p = $person ?? [];
$isEdit = !empty($p['id']);
$first = (string) ($p['first_name'] ?? '');
$last = (string) ($p['last_name'] ?? '');
if ($first === '' && !empty($p['name'])) {
    $parts = preg_split('/\s+/', trim((string) $p['name']), 2) ?: [];
    $first = $parts[0] ?? '';
    $last = $last !== '' ? $last : ($parts[1] ?? '');
}
?>
<section class="page">
    <div class="page-head">
        <div>
            <h1><?= e($title) ?></h1>
            <p class="lede">Persona del patrimonio</p>
        </div>
        <a class="btn ghost" href="<?= e(url($isEdit ? '/personas/' . (int) $p['id'] : '/personas')) ?>">Volver</a>
    </div>
    <?php require __DIR__ . '/../partials/alerts.php'; ?>
    <form method="post" action="<?= e(url($isEdit ? '/personas/' . $p['id'] : '/personas')) ?>" class="panel form-grid">
        <?= csrf_field() ?>
        <label>Nombre *
            <input name="first_name" required value="<?= e($first) ?>" autocomplete="given-name">
        </label>
        <label>Apellido
            <input name="last_name" value="<?= e($last) ?>" autocomplete="family-name">
        </label>
        <label>Documento
            <input name="document_id" value="<?= e($p['document_id'] ?? '') ?>">
        </label>
        <label>País
            <input name="country" value="<?= e($p['country'] ?? '') ?>">
        </label>
        <?php if ($isEdit): ?>
            <label class="check"><input type="checkbox" name="is_active" value="1"<?= checked($p['is_active'] ?? 1) ?>> Activa</label>
        <?php endif; ?>
        <label class="full">Notas
            <textarea name="notes" rows="2"><?= e($p['notes'] ?? '') ?></textarea>
        </label>
        <div class="full"><button type="submit">Guardar</button></div>
    </form>
</section>
