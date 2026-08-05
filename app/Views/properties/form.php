<section class="page">
    <div class="page-head"><h1><?= e($title) ?></h1><a class="btn ghost" href="<?= e(url('/propiedades')) ?>">Volver</a></div>
    <?php require __DIR__ . '/../partials/alerts.php'; ?>
    <?php $p = $property; $isEdit = !empty($p['id']); ?>
    <form method="post" action="<?= e(url($isEdit ? '/propiedades/' . $p['id'] : '/propiedades')) ?>" class="panel form-grid">
        <?= csrf_field() ?>
        <label>Entidad *
            <select name="entity_id" required>
                <option value="">—</option>
                <?php foreach ($entities as $ent): ?>
                    <option value="<?= (int) $ent['id'] ?>"<?= selected($p['entity_id'] ?? '', $ent['id']) ?>><?= e($ent['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Nombre *
            <input name="name" required value="<?= e($p['name'] ?? '') ?>" placeholder="Ej. Depto Palermo">
        </label>
        <label>Tipo
            <select name="property_type">
                <?php
                $types = [
                    'house' => 'Casa', 'apartment' => 'Departamento', 'local' => 'Local',
                    'warehouse' => 'Depósito', 'land' => 'Terreno', 'office' => 'Oficina', 'other' => 'Otro',
                ];
                foreach ($types as $t => $label): ?>
                    <option value="<?= $t ?>"<?= selected($p['property_type'] ?? 'other', $t) ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Ubicación
            <input name="location_text" value="<?= e($p['location_text'] ?? '') ?>">
        </label>
        <label>Moneda
            <select name="currency_code">
                <?php foreach ($currencies as $c): ?>
                    <option value="<?= e($c['code']) ?>"<?= selected($p['currency_code'] ?? 'USD', $c['code']) ?>><?= e($c['code']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Valor actual
            <input name="current_value" value="<?= e((string) ($p['current_value'] ?? '0')) ?>">
        </label>
        <?php
        $entityTypes = [];
        foreach ($entities as $ent) {
            $entityTypes[(int) $ent['id']] = (string) ($ent['type'] ?? 'company');
        }
        $selectedType = $entityTypes[(int) ($p['entity_id'] ?? 0)] ?? 'person';
        ?>
        <label id="property-ownership-wrap"<?= $selectedType === 'company' ? ' hidden' : '' ?>>
            % que te pertenece (propiedad personal)
            <input name="ownership_pct" id="property-ownership" type="number" step="0.01" min="0" max="100"
                   value="<?= e((string) ($selectedType === 'company' ? '100' : ($p['ownership_pct'] ?? '100'))) ?>">
            <span class="hint" style="margin:0">Solo para propiedades a tu nombre. Si es de una empresa, tu % se define en Participaciones.</span>
        </label>
        <input type="hidden" name="ownership_pct" id="property-ownership-company" value="100"<?= $selectedType === 'company' ? '' : ' disabled' ?>>
        <label>Hipoteca / deuda asociada
            <select name="linked_liability_id">
                <option value="">— ninguna —</option>
                <?php foreach ($liabilities as $l): ?>
                    <option value="<?= (int) $l['id'] ?>"<?= selected($p['linked_liability_id'] ?? '', $l['id']) ?>><?= e($l['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <?php if ($isEdit): ?>
            <label>Estado
                <select name="status">
                    <?php foreach (['active' => 'Activa', 'sold' => 'Vendida', 'archived' => 'Archivada'] as $s => $label): ?>
                        <option value="<?= $s ?>"<?= selected($p['status'] ?? 'active', $s) ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        <?php else: ?>
            <input type="hidden" name="status" value="active">
        <?php endif; ?>
        <input type="hidden" name="valuation_method" value="<?= e($p['valuation_method'] ?? 'manual') ?>">
        <input type="hidden" name="valued_at" value="<?= e($p['valued_at'] ?? date('Y-m-d')) ?>">
        <label class="check"><input type="checkbox" name="include_in_net_worth" value="1"<?= checked($p['include_in_net_worth'] ?? 1) ?>> Incluir en patrimonio</label>
        <label class="full">Notas<textarea name="notes" rows="2"><?= e($p['notes'] ?? '') ?></textarea></label>
        <div class="full"><button type="submit">Guardar</button></div>
    </form>
</section>
<script>
(() => {
  const entity = document.querySelector('select[name="entity_id"]');
  const ownershipWrap = document.getElementById('property-ownership-wrap');
  const ownership = document.getElementById('property-ownership');
  const ownershipCompany = document.getElementById('property-ownership-company');
  const entityTypes = <?= json_encode($entityTypes, JSON_UNESCAPED_UNICODE) ?>;
  if (!entity) return;
  const sync = () => {
    const type = entityTypes[entity.value] || 'person';
    const isCompany = type === 'company';
    if (ownershipWrap) ownershipWrap.hidden = isCompany;
    if (ownership) ownership.disabled = isCompany;
    if (ownershipCompany) {
      ownershipCompany.disabled = !isCompany;
      if (isCompany) ownershipCompany.value = '100';
    }
  };
  entity.addEventListener('change', sync);
  sync();
})();
</script>
