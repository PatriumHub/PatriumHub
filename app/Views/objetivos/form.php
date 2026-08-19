<?php
/** @var string $currency */
/** @var list<array<string,mixed>> $currencies */
?>
<section class="page objetivos-page">
    <div class="page-head">
        <div>
            <h1>Nuevo objetivo</h1>
            <p class="lede">Definí meta, fechas y cuánto llevás juntado.</p>
        </div>
        <a class="btn ghost" href="<?= e(url('/objetivos?currency=' . urlencode($currency))) ?>">Volver</a>
    </div>

    <?php require __DIR__ . '/../partials/alerts.php'; ?>

    <form method="post" action="<?= e(url('/objetivos')) ?>" class="panel form-grid obj-create-form">
        <?= csrf_field() ?>
        <label>Moneda
            <select name="currency_code">
                <?php foreach ($currencies as $c): ?>
                    <option value="<?= e($c['code']) ?>"<?= selected($currency, $c['code']) ?>><?= e($c['code']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="span-2">Nombre *
            <input name="name" required maxlength="180" placeholder="Ej. Viaje, auto, fondo casa">
        </label>
        <label>Valor total *
            <input type="number" name="target_amount" step="0.01" min="0.01" required>
        </label>
        <label>Ya juntado
            <input type="number" name="current_amount" step="0.01" min="0" value="0">
        </label>
        <label>Desde *
            <input type="date" name="starts_on" required value="<?= e(date('Y-m-01')) ?>">
        </label>
        <label>Hasta *
            <input type="date" name="ends_on" required value="<?= e(date('Y-12-31')) ?>">
        </label>
        <label class="span-2">Notas
            <input name="notes" maxlength="500" placeholder="Opcional">
        </label>
        <div class="span-2 form-actions">
            <button type="submit" class="btn">Crear objetivo</button>
            <a class="btn ghost" href="<?= e(url('/objetivos?currency=' . urlencode($currency))) ?>">Cancelar</a>
        </div>
    </form>
</section>
