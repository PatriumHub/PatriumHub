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

    <?php if ($isEdit): ?>
        <?php
        $counts = $deleteCounts ?? [];
        $confirmMsg = EntityService::confirmMessage((string) ($p['name'] ?? ''), $counts);
        $nonZero = array_filter($counts, static fn ($n) => (int) $n > 0);
        ?>
        <div class="panel mt" style="border-color: rgba(240, 113, 120, 0.35)">
            <h2 style="margin:0;color:var(--danger)">Eliminar persona</h2>
            <p class="muted">Borra la persona y todo lo asociado (cuentas, movimientos, participaciones, etc.). No se puede deshacer.</p>
            <?php if ($nonZero !== []): ?>
                <ul class="plain">
                    <?php foreach ($nonZero as $label => $n): ?>
                        <li><?= e(str_replace('_', ' ', (string) $label)) ?>: <strong><?= (int) $n ?></strong></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <form method="post" action="<?= e(url('/personas/' . (int) $p['id'] . '/eliminar')) ?>" class="form-grid mt"
                  style="padding:0;border:0;box-shadow:none;background:transparent"
                  onsubmit="return confirm(<?= json_encode($confirmMsg, JSON_UNESCAPED_UNICODE) ?>);">
                <?= csrf_field() ?>
                <label class="full">Escribí el nombre exacto para confirmar: <strong><?= e($p['name'] ?? '') ?></strong>
                    <input name="confirm_name" required autocomplete="off" placeholder="<?= e($p['name'] ?? '') ?>">
                </label>
                <div class="full">
                    <button type="submit" class="btn danger">Eliminar persona</button>
                </div>
            </form>
        </div>
    <?php endif; ?>
</section>
