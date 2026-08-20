<section class="page">
    <div class="page-head"><h1><?= e($title) ?></h1><a class="btn ghost" href="<?= e(url('/activos')) ?>">Volver</a></div>
    <?php require __DIR__ . '/../partials/alerts.php'; ?>
    <?php
    $a = $asset;
    $isEdit = !empty($a['id']);
    $accounts = $accounts ?? [];
    $people = $people ?? [];
    $companies = $companies ?? [];
    $owners = $owners ?? [];
    $ownerMap = [];
    foreach ($owners as $o) {
        $ownerMap[(int) $o['entity_id']] = (float) $o['ownership_pct'];
    }
    $entityType = 'person';
    if (!empty($a['entity_id'])) {
        foreach (($entities ?? []) as $ent) {
            if ((int) $ent['id'] === (int) $a['entity_id']) {
                $entityType = (string) ($ent['type'] ?? 'person');
                break;
            }
        }
    }
    $ownerMode = $entityType === 'company' ? 'company' : 'people';
    if ($owners !== []) {
        $ownerMode = 'people';
    }
    $equalPct = $people !== [] ? round(100 / max(1, count($people)), 4) : 100;
    ?>
    <form method="post" action="<?= e(url($isEdit ? '/activos/' . $a['id'] : '/activos')) ?>" class="panel form-grid" id="asset-form">
        <?= csrf_field() ?>
        <label>Titularidad *
            <select name="owner_mode" id="owner-mode" required>
                <option value="people"<?= selected($ownerMode, 'people') ?>>Persona(s)</option>
                <option value="company"<?= selected($ownerMode, 'company') ?>>Empresa</option>
            </select>
        </label>

        <div class="span-2" id="people-owners-wrap"<?= $ownerMode === 'company' ? ' hidden' : '' ?>>
            <p class="muted" style="margin:0 0 0.5rem">Podés marcar varias personas y repartir el %. Si no suman 100, se normaliza al guardar.</p>
            <div class="stack" style="gap:0.55rem">
                <?php foreach ($people as $p): ?>
                    <?php
                    $pid = (int) $p['id'];
                    $checked = array_key_exists($pid, $ownerMap)
                        || (!$isEdit && (int) ($a['entity_id'] ?? 0) === $pid)
                        || (!$isEdit && $ownerMap === [] && count($people) === 1);
                    $pctVal = $ownerMap[$pid] ?? ($checked ? ($ownerMap === [] && count($people) <= 2 ? (count($people) === 1 ? 100 : 50) : $equalPct) : 0);
                    ?>
                    <label class="check" style="display:flex;align-items:center;gap:0.75rem;flex-wrap:wrap">
                        <input type="checkbox" name="owner_ids[]" value="<?= $pid ?>" class="owner-check"
                            data-pct="<?= $pid ?>"<?= $checked ? ' checked' : '' ?>>
                        <span style="min-width:8rem"><?= e($p['name']) ?></span>
                        <input type="number" name="owner_pct[<?= $pid ?>]" class="owner-pct" data-for="<?= $pid ?>"
                               step="0.01" min="0" max="100" value="<?= e((string) $pctVal) ?>"
                               style="width:6.5rem"<?= $checked ? '' : ' disabled' ?>>
                        <span class="muted">%</span>
                    </label>
                <?php endforeach; ?>
                <?php if ($people === []): ?>
                    <p class="muted">No hay personas. Creá una en Personas primero.</p>
                <?php endif; ?>
            </div>
        </div>

        <label id="company-owner-wrap"<?= $ownerMode === 'company' ? '' : ' hidden' ?>>Empresa *
            <select name="company_entity_id" id="company-entity">
                <option value="">—</option>
                <?php foreach ($companies as $c): ?>
                    <option value="<?= (int) $c['id'] ?>"<?= selected($a['entity_id'] ?? '', $c['id']) ?>><?= e($c['name']) ?></option>
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
            <span class="hint" style="margin:0">Opcional. Para personas múltiples, filtrá por el titular principal (mayor %).</span>
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
            <span class="hint" style="margin:0">Opcional: registra el egreso y baja el saldo por el valor actual.</span>
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

    <?php if ($isEdit): ?>
        <div class="panel mt" style="border-color: color-mix(in srgb, var(--danger) 35%, var(--line))">
            <h2 style="margin-top:0;color:var(--danger)">Eliminar activo</h2>
            <p class="muted">Se borra el activo y sus co-titulares. No se puede deshacer.</p>
            <form method="post" action="<?= e(url('/activos/' . (int) $a['id'] . '/eliminar')) ?>" onsubmit="return confirm('¿Eliminar este activo? No se puede deshacer.');">
                <?= csrf_field() ?>
                <button type="submit" class="btn danger">Eliminar activo</button>
            </form>
        </div>
    <?php endif; ?>
</section>
<script>
(() => {
  const mode = document.getElementById('owner-mode');
  const peopleWrap = document.getElementById('people-owners-wrap');
  const companyWrap = document.getElementById('company-owner-wrap');
  const company = document.getElementById('company-entity');
  const account = document.getElementById('asset-account');
  const debit = document.getElementById('asset-debit-account');
  if (!mode) return;

  const syncMode = () => {
    const isCompany = mode.value === 'company';
    if (peopleWrap) peopleWrap.hidden = isCompany;
    if (companyWrap) companyWrap.hidden = !isCompany;
    if (company) company.required = isCompany;
    filterAccounts();
  };

  const primaryEntityId = () => {
    if (mode.value === 'company') return company ? company.value : '';
    let best = null;
    let bestPct = -1;
    document.querySelectorAll('.owner-check:checked').forEach((cb) => {
      const pctEl = document.querySelector('.owner-pct[data-for="' + cb.value + '"]');
      const pct = pctEl ? Number(pctEl.value) || 0 : 0;
      if (pct > bestPct) { bestPct = pct; best = cb.value; }
    });
    return best || '';
  };

  const filterAccounts = () => {
    const eid = primaryEntityId();
    [account, debit].forEach((select) => {
      if (!select) return;
      let keep = false;
      [...select.options].forEach((opt, i) => {
        if (i === 0) { opt.hidden = false; return; }
        // Débito: cualquier cuenta; asociada: preferir misma entidad si hay.
        const match = select === debit || !eid || opt.dataset.entity === eid;
        opt.hidden = select === debit ? false : !match;
        if (opt.selected && (select === debit || match)) keep = true;
      });
      if (!keep) select.value = '';
    });
  };

  document.querySelectorAll('.owner-check').forEach((cb) => {
    cb.addEventListener('change', () => {
      const pct = document.querySelector('.owner-pct[data-for="' + cb.value + '"]');
      if (pct) {
        pct.disabled = !cb.checked;
        if (cb.checked && (!pct.value || Number(pct.value) <= 0)) {
          const n = document.querySelectorAll('.owner-check:checked').length;
          pct.value = n <= 1 ? '100' : String(Math.round(1000 / n) / 10);
        }
      }
      filterAccounts();
    });
  });
  document.querySelectorAll('.owner-pct').forEach((el) => el.addEventListener('input', filterAccounts));
  mode.addEventListener('change', syncMode);
  if (company) company.addEventListener('change', filterAccounts);
  syncMode();
})();
</script>
