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
        <?php $bm = ($c['business_model'] ?? 'services') === 'products' ? 'products' : 'services'; ?>
        <?php if ($isEdit): ?>
            <label class="full">Tipo de negocio
                <input type="text" readonly value="<?= $bm === 'products' ? 'Productos — proyección por mes' : 'Servicios — proyección por cliente' ?>">
                <span class="hint" style="display:block;margin-top:0.35rem">
                    Se elige al crear la empresa y no se puede cambiar después.
                </span>
            </label>
        <?php else: ?>
            <label class="full">Tipo de negocio *
                <select name="business_model" required>
                    <option value="services"<?= $bm === 'services' ? ' selected' : '' ?>>Servicios — proyección por cliente</option>
                    <option value="products"<?= $bm === 'products' ? ' selected' : '' ?>>Productos — proyección por mes (total de ingresos)</option>
                </select>
                <span class="hint" style="display:block;margin-top:0.35rem">
                    Define la plantilla de <strong>Estados y proyección</strong>. No se puede cambiar después de creada.
                </span>
            </label>
        <?php endif; ?>
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

    <?php if ($isEdit): ?>
        <?php
        $counts = $deleteCounts ?? [];
        $confirmMsg = EntityService::confirmMessage((string) ($c['name'] ?? ''), $counts);
        $nonZero = array_filter($counts, static fn ($n) => (int) $n > 0);
        ?>
        <div class="panel mt" style="border-color: rgba(240, 113, 120, 0.35)">
            <h2 style="margin:0;color:var(--danger)">Eliminar empresa</h2>
            <p class="muted">Borra la empresa y todo lo asociado (cuentas, movimientos, proyección, etc.). No se puede deshacer.</p>
            <?php if ($nonZero !== []): ?>
                <ul class="plain">
                    <?php foreach ($nonZero as $label => $n): ?>
                        <li><?= e(str_replace('_', ' ', (string) $label)) ?>: <strong><?= (int) $n ?></strong></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <form method="post" action="<?= e(url('/empresas/' . (int) $c['id'] . '/eliminar')) ?>" class="form-grid mt"
                  style="padding:0;border:0;box-shadow:none;background:transparent"
                  onsubmit="return confirm(<?= json_encode($confirmMsg, JSON_UNESCAPED_UNICODE) ?>);">
                <?= csrf_field() ?>
                <label class="full">Escribí el nombre exacto para confirmar: <strong><?= e($c['name'] ?? '') ?></strong>
                    <input name="confirm_name" required autocomplete="off" placeholder="<?= e($c['name'] ?? '') ?>">
                </label>
                <div class="full">
                    <button type="submit" class="btn danger">Eliminar empresa</button>
                </div>
            </form>
        </div>
    <?php endif; ?>
</section>
