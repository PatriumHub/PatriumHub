<section class="page">
    <div class="page-head"><h1><?= e($title) ?></h1><a class="btn ghost" href="<?= e(url('/activos')) ?>">Volver</a></div>
    <?php require __DIR__ . '/../partials/alerts.php'; ?>
    <?php
    $a = $asset;
    $isEdit = !empty($a['id']);
    $accounts = $accounts ?? [];
    ?>
    <form method="post" action="<?= e(url($isEdit ? '/activos/' . $a['id'] : '/activos')) ?>" class="panel form-grid" id="asset-form">
        <?= csrf_field() ?>
        <label>Entidad *
            <select name="entity_id" id="asset-entity" required>
                <option value="">—</option>
                <?php foreach ($entities as $ent): ?>
                    <option value="<?= (int) $ent['id'] ?>"<?= selected($a['entity_id'] ?? '', $ent['id']) ?>><?= e($ent['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Cuenta asociada
            <select name="account_id" id="asset-account">
                <option value="">— ninguna —</option>
                <?php foreach ($accounts as $acc): ?>
                    <option value="<?= (int) $acc['id'] ?>"
                            data-entity="<?= (int) $acc['entity_id'] ?>"
                        <?= selected($a['account_id'] ?? '', $acc['id']) ?>>
                        <?= e($acc['entity_name'] . ' · ' . $acc['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <span class="hint" style="margin:0">Opcional: vincular a una cuenta (p. ej. inversión). No mueve saldo.</span>
        </label>
        <?php if (!$isEdit): ?>
        <label>Debitar compra de
            <select name="debit_account_id" id="asset-debit-account">
                <option value="">— no debitar —</option>
                <?php foreach ($accounts as $acc): ?>
                    <option value="<?= (int) $acc['id'] ?>"
                            data-entity="<?= (int) $acc['entity_id'] ?>">
                        <?= e($acc['entity_name'] . ' · ' . $acc['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <span class="hint" style="margin:0">Opcional: si lo pagás desde una cuenta, se registra el egreso y baja el saldo por el valor actual.</span>
        </label>
        <?php endif; ?>
        <label>Nombre *
            <input name="name" required value="<?= e($a['name'] ?? '') ?>">
        </label>
        <label>Categoría
            <select name="category">
                <?php
                $cats = [
                    'vehicle' => 'Vehículo', 'equipment' => 'Equipo', 'machinery' => 'Maquinaria',
                    'investment' => 'Inversión', 'intangible' => 'Intangible', 'crypto' => 'Crypto', 'other' => 'Otro',
                ];
                foreach ($cats as $t => $label): ?>
                    <option value="<?= $t ?>"<?= selected($a['category'] ?? 'other', $t) ?>><?= e($label) ?></option>
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
        <label>Valor actual
            <input name="current_value" value="<?= e((string) ($a['current_value'] ?? '0')) ?>">
        </label>
        <?php
        $entityTypes = [];
        foreach ($entities as $ent) {
            $entityTypes[(int) $ent['id']] = (string) ($ent['type'] ?? 'company');
        }
        $selectedType = $entityTypes[(int) ($a['entity_id'] ?? 0)] ?? 'person';
        ?>
        <label id="asset-ownership-wrap"<?= $selectedType === 'company' ? ' hidden' : '' ?>>
            % que te pertenece (activo personal)
            <input name="ownership_pct" id="asset-ownership" type="number" step="0.01" min="0" max="100"
                   value="<?= e((string) ($selectedType === 'company' ? '100' : ($a['ownership_pct'] ?? '100'))) ?>">
            <span class="hint" style="margin:0">Solo para activos a tu nombre. Si es de una empresa, tu % se define en Participaciones.</span>
        </label>
        <input type="hidden" name="ownership_pct" id="asset-ownership-company" value="100"<?= $selectedType === 'company' ? '' : ' disabled' ?>>
        <?php if ($isEdit): ?>
            <label>Estado
                <select name="status">
                    <?php foreach (['active' => 'Activo', 'sold' => 'Vendido', 'archived' => 'Archivado'] as $s => $label): ?>
                        <option value="<?= $s ?>"<?= selected($a['status'] ?? 'active', $s) ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        <?php else: ?>
            <input type="hidden" name="status" value="active">
        <?php endif; ?>
        <input type="hidden" name="valuation_method" value="<?= e($a['valuation_method'] ?? 'manual') ?>">
        <input type="hidden" name="valued_at" value="<?= e($a['valued_at'] ?? date('Y-m-d')) ?>">
        <label class="check"><input type="checkbox" name="include_in_net_worth" value="1"<?= checked($a['include_in_net_worth'] ?? 1) ?>> Incluir en patrimonio</label>
        <label class="full">Notas<textarea name="notes" rows="2"><?= e($a['notes'] ?? '') ?></textarea></label>
        <div class="full"><button type="submit">Guardar</button></div>
    </form>
</section>
<script>
(() => {
  const entity = document.getElementById('asset-entity');
  const account = document.getElementById('asset-account');
  const debit = document.getElementById('asset-debit-account');
  const ownershipWrap = document.getElementById('asset-ownership-wrap');
  const ownership = document.getElementById('asset-ownership');
  const ownershipCompany = document.getElementById('asset-ownership-company');
  const entityTypes = <?= json_encode($entityTypes, JSON_UNESCAPED_UNICODE) ?>;
  if (!entity || !account) return;
  const filterSelect = (select, sameEntityOnly) => {
    if (!select) return;
    const eid = entity.value;
    let keep = false;
    [...select.options].forEach((opt, i) => {
      if (i === 0) { opt.hidden = false; return; }
      const match = !sameEntityOnly || !eid || opt.dataset.entity === eid;
      opt.hidden = !match;
      if (opt.selected && match) keep = true;
    });
    if (!keep) select.value = '';
  };
  const syncOwnership = () => {
    const type = entityTypes[entity.value] || 'person';
    const isCompany = type === 'company';
    if (ownershipWrap) ownershipWrap.hidden = isCompany;
    if (ownership) ownership.disabled = isCompany;
    if (ownershipCompany) {
      ownershipCompany.disabled = !isCompany;
      if (isCompany) ownershipCompany.value = '100';
    }
  };
  const filter = () => {
    filterSelect(account, true);
    // Débito: se puede pagar desde cualquier cuenta activa (p. ej. personal → activo empresa).
    filterSelect(debit, false);
    syncOwnership();
  };
  entity.addEventListener('change', filter);
  filter();
})();
</script>
