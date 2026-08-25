<section class="page">
    <div class="page-head">
        <h1><?= e($title) ?></h1>
        <a class="btn ghost" href="<?= e(url('/presupuestos/plantillas')) ?>">Volver</a>
    </div>
    <?php require __DIR__ . '/../partials/alerts.php'; ?>
    <?php $i = $item; $isEdit = !empty($i['id']); ?>
    <form method="post" action="<?= e(url($isEdit ? '/presupuestos/plantillas/' . $i['id'] : '/presupuestos/plantillas')) ?>" class="panel form-grid" id="budget-template-form">
        <?= csrf_field() ?>
        <label>Entidad *
            <select name="entity_id" id="bt-entity" required>
                <option value="">—</option>
                <?php foreach ($entities as $ent): ?>
                    <option value="<?= (int) $ent['id'] ?>"<?= selected($i['entity_id'] ?? '', $ent['id']) ?>><?= e($ent['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Nombre *
            <input name="name" required value="<?= e($i['name'] ?? '') ?>" placeholder="Ej. Alquiler, Luz, Internet">
        </label>
        <label>Monto mensual
            <input name="amount" type="number" step="0.01" min="0" value="<?= e((string) ($i['amount'] ?? '0')) ?>">
            <span class="hint" style="margin:0">Puede ser 0 si todavía no sabés el importe</span>
        </label>
        <label>Moneda
            <select name="currency_code">
                <?php foreach ($currencies as $c): ?>
                    <option value="<?= e($c['code']) ?>"<?= selected($i['currency_code'] ?? 'ARS', $c['code']) ?>><?= e($c['code']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Día de vencimiento
            <input name="due_day" type="number" min="1" max="31" value="<?= e((string) ($i['due_day'] ?? '10')) ?>">
            <span class="hint" style="margin:0">Ej. 10 = vence el día 10 de cada mes</span>
        </label>
        <label>Categoría
            <select name="category_id">
                <option value="">— sin categoría —</option>
                <?php foreach (($categories ?? []) as $cat): ?>
                    <option value="<?= (int) $cat['id'] ?>"<?= selected($i['category_id'] ?? '', $cat['id']) ?>><?= e($cat['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <span class="hint" style="margin:0">Las mismas de Movimientos; al pagar se asigna al egreso</span>
        </label>
        <label>Cuenta sugerida para pagar
            <select name="suggested_account_id" id="bt-account">
                <option value="">— ninguna —</option>
                <?php foreach ($accounts as $acc): ?>
                    <option value="<?= (int) $acc['id'] ?>"
                            data-entity="<?= (int) $acc['entity_id'] ?>"
                        <?= selected($i['suggested_account_id'] ?? '', $acc['id']) ?>>
                        <?= e(($acc['entity_name'] ?? '') . ' · ' . $acc['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="check"><input type="checkbox" name="is_active" value="1"<?= checked($i['is_active'] ?? 1) ?>> Activa (genera ítems)</label>
        <label class="check"><input type="checkbox" name="include_in_net_worth" value="1"<?= checked($i['include_in_net_worth'] ?? 1) ?>> Pendientes cuentan como pasivo</label>
        <label class="full">Notas<textarea name="notes" rows="2"><?= e($i['notes'] ?? '') ?></textarea></label>
        <div class="full"><button type="submit">Guardar</button></div>
    </form>
</section>
<script>
(() => {
  const entity = document.getElementById('bt-entity');
  const account = document.getElementById('bt-account');
  if (!entity || !account) return;
  const filter = () => {
    const eid = entity.value;
    let keep = false;
    [...account.options].forEach((opt, i) => {
      if (i === 0) { opt.hidden = false; return; }
      const match = !eid || opt.dataset.entity === eid;
      opt.hidden = !match;
      if (opt.selected && match) keep = true;
    });
    if (!keep) account.value = '';
  };
  entity.addEventListener('change', filter);
  filter();
})();
</script>
