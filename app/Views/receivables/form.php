<section class="page">
    <?php
    $returnTo = safe_return_path((string) ($returnTo ?? ''), '');
    $backUrl = $returnTo !== '' ? $returnTo : '/cobrables';
    ?>
    <div class="page-head"><h1><?= e($title) ?></h1><a class="btn ghost" href="<?= e(url($backUrl)) ?>">Volver</a></div>
    <?php require __DIR__ . '/../partials/alerts.php'; ?>
    <?php
    $i = $item;
    $isEdit = !empty($i['id']);
    $statusLabels = $statusLabels ?? [
        'current' => 'Al día',
        'overdue' => 'Vencido',
        'paid' => 'Pago',
        'uncollectible' => 'Incobrable',
        'cancelled' => 'Cancelado',
    ];
    $accounts = $accounts ?? [];
    $isPaid = ($i['status'] ?? '') === 'paid';
    $payHref = '/cobrables/' . (int) ($i['id'] ?? 0) . '/pagar';
    if ($isEdit && $returnTo !== '') {
        $payHref .= '?return_to=' . rawurlencode($returnTo);
    }
    ?>
    <form method="post" action="<?= e(url($isEdit ? '/cobrables/' . $i['id'] : '/cobrables')) ?>" class="panel form-grid" id="recv-form">
        <?= csrf_field() ?>
        <?php if ($returnTo !== ''): ?>
            <input type="hidden" name="return_to" value="<?= e($returnTo) ?>">
        <?php endif; ?>
        <label>Quién cobra (entidad) *
            <select name="entity_id" id="recv-entity" required>
                <option value="">—</option>
                <?php foreach ($entities as $ent): ?>
                    <option value="<?= (int) $ent['id'] ?>"<?= selected($i['entity_id'] ?? '', $ent['id']) ?>><?= e($ent['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Deudor *
            <input name="debtor_name" required value="<?= e($i['debtor_name'] ?? '') ?>" placeholder="Nombre de quien debe">
        </label>
        <label>Si es entidad interna
            <select name="debtor_entity_id">
                <option value="">— externo —</option>
                <?php foreach ($entities as $ent): ?>
                    <option value="<?= (int) $ent['id'] ?>"<?= selected($i['debtor_entity_id'] ?? '', $ent['id']) ?>><?= e($ent['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Moneda
            <select name="currency_code">
                <?php foreach ($currencies as $c): ?>
                    <option value="<?= e($c['code']) ?>"<?= selected($i['currency_code'] ?? 'ARS', $c['code']) ?>><?= e($c['code']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Saldo a cobrar *
            <input name="outstanding_amount" required value="<?= e((string) ($i['outstanding_amount'] ?? $i['original_amount'] ?? '0')) ?>"<?= $isPaid ? ' readonly' : '' ?>>
        </label>
        <?php if ($isEdit): ?>
            <label>Monto original
                <input name="original_amount" value="<?= e((string) ($i['original_amount'] ?? '0')) ?>">
            </label>
            <label>Estado
                <select name="status" id="recv-status">
                    <?php foreach ($statusLabels as $s => $label): ?>
                        <option value="<?= $s ?>"<?= selected($i['status'] ?? 'current', $s) ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label id="recv-account-wrap" style="display:none">
                Si marcás <strong>Pago</strong>, ¿a qué cuenta sumamos?
                <select name="deposit_account_id" id="recv-account">
                    <option value="">— elegí cuenta —</option>
                    <?php foreach ($accounts as $acc): ?>
                        <option value="<?= (int) $acc['id'] ?>">
                            <?= e($acc['name']) ?> (<?= e($acc['currency_code']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
        <?php else: ?>
            <input type="hidden" name="original_amount" value="">
            <input type="hidden" name="status" value="current">
        <?php endif; ?>
        <label>Vencimiento
            <input type="date" name="due_date" value="<?= e($i['due_date'] ?? '') ?>">
        </label>
        <label class="check"><input type="checkbox" name="include_in_net_worth" value="1"<?= checked($i['include_in_net_worth'] ?? 1) ?>> Incluir en patrimonio</label>
        <label class="full">Notas<textarea name="notes" rows="2"><?= e($i['notes'] ?? '') ?></textarea></label>
        <div class="full">
            <button type="submit">Guardar</button>
            <?php if ($isEdit && !$isPaid): ?>
                <a class="btn ghost" href="<?= e(url($payHref)) ?>">Cobrar / marcar pago…</a>
            <?php endif; ?>
        </div>
    </form>
</section>
<?php if ($isEdit && !$isPaid): ?>
<script>
(function () {
  const status = document.getElementById('recv-status');
  const wrap = document.getElementById('recv-account-wrap');
  const account = document.getElementById('recv-account');
  const form = document.getElementById('recv-form');
  function sync() {
    const paid = status && status.value === 'paid';
    if (wrap) wrap.style.display = paid ? '' : 'none';
    if (account) account.required = !!paid;
  }
  status?.addEventListener('change', sync);
  form?.addEventListener('submit', function (ev) {
    if (status && status.value === 'paid' && account && !account.value) {
      ev.preventDefault();
      alert('Elegí a qué cuenta sumar el cobro.');
      account.focus();
    }
  });
  sync();
})();
</script>
<?php endif; ?>
