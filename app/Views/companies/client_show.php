<?php
$c = $company;
$client = $client;
$canWrite = !empty($canWrite);
$contract = $contract ?? null;
$extraDocs = $extraDocs ?? [];
$companyPath = '/empresas/' . (int) $c['id'];
$clientPath = $companyPath . '/clientes/' . (int) $client['id'];
$back = $companyPath . '?tab=clientes';
$currency = 'ARS';
$years = is_array($client['years'] ?? null) ? $client['years'] : [];
$isActive = (int) ($client['is_active'] ?? 1) === 1;
?>
<section class="page">
    <div class="page-head">
        <div>
            <h1><?= e($client['name']) ?></h1>
            <p class="lede">
                Cliente de <?= e($c['name']) ?>
                · <span class="badge <?= $isActive ? 'badge-ok' : 'badge-danger' ?>"><?= $isActive ? 'Activo' : 'Inactivo' ?></span>
            </p>
        </div>
        <div class="actions">
            <?php if ($canWrite): ?>
                <a class="btn ghost" href="<?= e(url($clientPath . '/editar')) ?>">Editar ficha</a>
            <?php endif; ?>
            <a class="btn ghost" href="<?= e(url($companyPath . '?tab=estados-proyeccion')) ?>">Ver proyección</a>
            <a class="btn ghost" href="<?= e(url($back)) ?>">Volver</a>
        </div>
    </div>
    <?php require __DIR__ . '/../partials/alerts.php'; ?>

    <div class="grid-2">
        <div class="panel">
            <h2>Contacto</h2>
            <ul class="plain">
                <li>Nombre: <strong><?= e($client['contact_name'] ?: '—') ?></strong></li>
                <li>Email: <strong><?= e($client['email'] ?: '—') ?></strong></li>
                <li>Teléfono: <strong><?= e($client['phone'] ?: '—') ?></strong></li>
            </ul>
            <?php if (trim((string) ($client['contract_notes'] ?? '')) !== ''): ?>
                <h3>Notas</h3>
                <div class="lede" style="white-space:pre-wrap"><?= e($client['contract_notes']) ?></div>
            <?php endif; ?>
        </div>
        <div class="panel">
            <h2>Valores (proyección)</h2>
            <ul class="plain">
                <li>Total general: <strong><?= e(money($client['value_total'] ?? 0, $currency)) ?></strong></li>
            </ul>
            <?php if ($years !== []): ?>
                <table class="table mt">
                    <thead><tr><th>Año</th><th class="num">Total</th></tr></thead>
                    <tbody>
                    <?php foreach ($years as $yearName => $amount): ?>
                        <tr>
                            <td><?= e((string) $yearName) ?></td>
                            <td class="num"><?= e(money($amount, $currency)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <div class="panel mt">
        <h2>Contrato (PDF)</h2>
        <?php if ($contract): ?>
            <p>
                <a class="btn ghost" href="<?= e(url($clientPath . '/archivos/' . (int) $contract['id'])) ?>">
                    Descargar <?= e($contract['title'] ?? 'contrato.pdf') ?>
                </a>
            </p>
        <?php else: ?>
            <p class="muted">Sin contrato PDF<?= $canWrite ? ' — cargalo en Editar ficha' : '' ?>.</p>
        <?php endif; ?>
    </div>

    <div class="panel mt">
        <h2>Documentación extra</h2>
        <?php if ($extraDocs === []): ?>
            <p class="muted">Sin documentos adicionales<?= $canWrite ? ' — cargalos en Editar ficha' : '' ?>.</p>
        <?php else: ?>
            <table class="table">
                <thead><tr><th>Documento</th><th>Fecha</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($extraDocs as $doc): ?>
                    <tr>
                        <td><?= e($doc['title'] ?? 'Documento') ?></td>
                        <td><?= e(substr((string) ($doc['created_at'] ?? ''), 0, 16)) ?></td>
                        <td class="actions">
                            <a href="<?= e(url($clientPath . '/archivos/' . (int) $doc['id'])) ?>">Descargar</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</section>
