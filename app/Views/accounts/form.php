<section class="page">
    <div class="page-head">
        <h1><?= e($title) ?></h1>
        <a class="btn ghost" href="<?= e(url('/cuentas')) ?>">Volver</a>
    </div>
    <?php require __DIR__ . '/../partials/alerts.php'; ?>
    <?php
    $a = $account;
    $isEdit = !empty($a['id']);
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
    <form method="post" action="<?= e(url($isEdit ? '/cuentas/' . $a['id'] : '/cuentas')) ?>" class="panel form-grid" id="account-form">
        <?= csrf_field() ?>
        <label>Titularidad *
            <select name="owner_mode" id="owner-mode" required>
                <option value="people"<?= selected($ownerMode, 'people') ?>>Persona(s)</option>
                <option value="company"<?= selected($ownerMode, 'company') ?>>Empresa</option>
            </select>
        </label>

        <div class="span-2" id="people-owners-wrap"<?= $ownerMode === 'company' ? ' hidden' : '' ?>>
            <p class="muted" style="margin:0 0 0.5rem">Podés marcar varias personas y repartir el % (cuenta compartida). Si no suman 100, se normaliza al guardar.</p>
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

        <label>Nombre *
            <input name="name" required value="<?= e($a['name'] ?? '') ?>" placeholder="<?= e(($a['type'] ?? '') === 'mercadopago' ? 'Ej. MP Homespot' : 'Ej. Galicia ARS') ?>">
        </label>
        <label>Tipo
            <select name="type">
                <?php
                $types = [
                    'bank' => 'Banco', 'checking' => 'Cuenta corriente', 'savings' => 'Caja de ahorro',
                    'wallet' => 'Billetera', 'mercadopago' => 'Mercado Pago', 'cash' => 'Efectivo',
                    'broker' => 'Broker', 'virtual' => 'Virtual', 'other' => 'Otro',
                ];
                foreach ($types as $t => $label): ?>
                    <option value="<?= $t ?>"<?= selected($a['type'] ?? 'bank', $t) ?>><?= e($label) ?></option>
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
        <label>Saldo
            <input name="balance" value="<?= e((string) ($a['balance'] ?? '0')) ?>">
        </label>
        <?php if ($isEdit): ?>
            <label>Retenido / pendiente
                <input name="balance_held" value="<?= e((string) ($a['balance_held'] ?? '0')) ?>">
            </label>
            <label>Estado
                <select name="status">
                    <option value="active"<?= selected($a['status'] ?? 'active', 'active') ?>>Activa</option>
                    <option value="closed"<?= selected($a['status'] ?? '', 'closed') ?>>Cerrada</option>
                </select>
            </label>
        <?php else: ?>
            <input type="hidden" name="balance_held" value="0">
            <input type="hidden" name="status" value="active">
        <?php endif; ?>
        <label class="check"><input type="checkbox" name="include_in_net_worth" value="1"<?= checked($a['include_in_net_worth'] ?? 1) ?>> Incluir en patrimonio</label>
        <label class="full">Notas<textarea name="notes" rows="2"><?= e($a['notes'] ?? '') ?></textarea></label>
        <div class="full form-actions">
            <button type="submit">Guardar</button>
        </div>
    </form>
    <?php
    $canDelete = $isEdit
        && abs((float) ($a['balance'] ?? 0)) <= 0.009
        && abs((float) ($a['balance_held'] ?? 0)) <= 0.009;
    ?>
    <?php if ($isEdit): ?>
        <div class="panel mt">
            <h2 style="margin-top:0">Eliminar cuenta</h2>
            <?php if ($canDelete): ?>
                <p class="muted">Saldo en 0. Se borra la cuenta; los movimientos históricos quedan sin cuenta asociada.</p>
                <form method="post" action="<?= e(url('/cuentas/' . (int) $a['id'] . '/eliminar')) ?>" onsubmit="return confirm('¿Eliminar esta cuenta? No se puede deshacer.');">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn danger">Eliminar cuenta</button>
                </form>
            <?php else: ?>
                <p class="muted">Para eliminarla, el saldo y el retenido tienen que estar en 0.</p>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</section>
<script>
(() => {
  const mode = document.getElementById('owner-mode');
  const peopleWrap = document.getElementById('people-owners-wrap');
  const companyWrap = document.getElementById('company-owner-wrap');
  const company = document.getElementById('company-entity');
  if (!mode) return;

  const syncMode = () => {
    const isCompany = mode.value === 'company';
    if (peopleWrap) peopleWrap.hidden = isCompany;
    if (companyWrap) companyWrap.hidden = !isCompany;
    if (company) company.required = isCompany;
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
    });
  });
  mode.addEventListener('change', syncMode);
  syncMode();
})();
</script>
