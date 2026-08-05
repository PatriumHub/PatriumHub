<section class="page">
    <div class="page-head"><h1><?= e($title) ?></h1><a class="btn ghost" href="<?= e(url('/movimientos')) ?>">Volver</a></div>
    <?php require __DIR__ . '/../partials/alerts.php'; ?>
    <?php
    $i = $item;
    $isEdit = !empty($i['id']);
    $action = $isEdit ? '/movimientos/' . (int) $i['id'] : '/movimientos';
    ?>
    <form method="post" action="<?= e(url($action)) ?>" class="panel form-grid" id="tx-form">
        <?= csrf_field() ?>
        <label>Entidad *
            <select name="entity_id" required>
                <option value="">—</option>
                <?php foreach ($entities as $ent): ?>
                    <option value="<?= (int) $ent['id'] ?>"<?= selected($i['entity_id'] ?? '', $ent['id']) ?>><?= e($ent['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Tipo
            <select name="type" id="tx-type">
                <?php foreach (['income' => 'Ingreso', 'expense' => 'Egreso', 'transfer' => 'Transferencia', 'adjustment' => 'Ajuste'] as $t => $label): ?>
                    <option value="<?= $t ?>"<?= selected($i['type'] ?? 'expense', $t) ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Fecha
            <input type="datetime-local" name="occurred_at" value="<?= e($i['occurred_at'] ?? date('Y-m-d\TH:i')) ?>">
        </label>
        <label>Importe *
            <input name="amount" required value="<?= e((string) ($i['amount'] ?? '')) ?>">
        </label>
        <label>Moneda
            <select name="currency_code">
                <?php foreach ($currencies as $c): ?>
                    <option value="<?= e($c['code']) ?>"<?= selected($i['currency_code'] ?? 'ARS', $c['code']) ?>><?= e($c['code']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label id="label-account"><span id="tx-account-title">Cuenta *</span>
            <select name="account_id" id="tx-account" required>
                <option value="">—</option>
                <?php foreach ($accounts as $a): ?>
                    <option value="<?= (int) $a['id'] ?>"<?= selected($i['account_id'] ?? '', $a['id']) ?>><?= e($a['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label id="label-destination"><span>Cuenta destino *</span>
            <select name="counterparty_account_id" id="tx-destination">
                <option value="">—</option>
                <?php foreach ($accounts as $a): ?>
                    <option value="<?= (int) $a['id'] ?>"<?= selected($i['counterparty_account_id'] ?? '', $a['id']) ?>><?= e($a['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label id="label-adjustment"><span>Ajuste</span>
            <select name="adjustment_direction">
                <option value="plus"<?= selected($i['adjustment_direction'] ?? 'plus', 'plus') ?>>Sumar al saldo</option>
                <option value="minus"<?= selected($i['adjustment_direction'] ?? 'plus', 'minus') ?>>Restar al saldo</option>
            </select>
        </label>
        <label>Categoría
            <select name="category_id">
                <option value="">—</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= (int) $cat['id'] ?>"<?= selected($i['category_id'] ?? '', $cat['id']) ?>><?= e($cat['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="full">Descripción
            <input name="description" value="<?= e($i['description'] ?? '') ?>">
        </label>
        <p class="muted full" id="tx-hint"></p>
        <?php if ($isEdit): ?>
            <p class="muted full">Al cambiar importe, tipo o cuentas se recalculan los saldos (se revierte el movimiento anterior y se aplica el nuevo).</p>
        <?php endif; ?>
        <div class="full"><button type="submit"><?= $isEdit ? 'Guardar cambios' : 'Registrar' ?></button></div>
    </form>
</section>
<script>
(() => {
  const type = document.getElementById('tx-type');
  const labelDest = document.getElementById('label-destination');
  const labelAdj = document.getElementById('label-adjustment');
  const hint = document.getElementById('tx-hint');
  const accountTitle = document.getElementById('tx-account-title');
  const destSelect = document.getElementById('tx-destination');
  const sync = () => {
    const t = type.value;
    const isTransfer = t === 'transfer';
    const isAdj = t === 'adjustment';
    accountTitle.textContent = isTransfer ? 'Cuenta origen *' : 'Cuenta *';
    labelDest.style.display = isTransfer ? '' : 'none';
    labelAdj.style.display = isAdj ? '' : 'none';
    destSelect.required = isTransfer;
    if (!isTransfer) destSelect.value = '';
    if (isTransfer) {
      hint.textContent = 'El importe se resta de la cuenta origen y se suma en la cuenta destino.';
    } else if (t === 'income') {
      hint.textContent = 'Suma el importe al saldo de la cuenta.';
    } else if (t === 'expense') {
      hint.textContent = 'Resta el importe del saldo de la cuenta.';
    } else if (isAdj) {
      hint.textContent = 'Ajusta el saldo sin registrar un ingreso/egreso operativo.';
    } else {
      hint.textContent = '';
    }
  };
  type.addEventListener('change', sync);
  sync();
})();
</script>
