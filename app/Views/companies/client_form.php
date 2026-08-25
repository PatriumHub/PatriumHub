<?php
$c = $company;
$client = $client ?? null;
$contract = $contract ?? null;
$extraDocs = $extraDocs ?? [];
$companyPath = '/empresas/' . (int) $c['id'];
$clientPath = $companyPath . '/clientes/' . (int) ($client['id'] ?? 0);
$back = $clientPath;
$years = is_array($client['years'] ?? null) ? $client['years'] : [];
?>
<section class="page">
    <div class="page-head">
        <div>
            <h1><?= e($title) ?></h1>
            <p class="lede">Contacto, contrato PDF y documentación · montos desde proyección</p>
        </div>
        <a class="btn ghost" href="<?= e(url($back)) ?>">Volver</a>
    </div>
    <?php require __DIR__ . '/../partials/alerts.php'; ?>

    <div class="panel form-grid" style="margin-bottom:1rem">
        <label>Cliente (desde proyección)
            <input type="text" readonly value="<?= e($client['name'] ?? '') ?>">
        </label>
        <label>Total general (todos los años)
            <input type="text" readonly value="<?= e(money($client['value_total'] ?? 0, 'ARS')) ?>">
        </label>
        <?php if ($years !== []): ?>
            <div class="full">
                <p class="muted" style="margin:0 0 0.35rem">Por año</p>
                <ul class="plain" style="margin:0">
                    <?php foreach ($years as $yearName => $amount): ?>
                        <li><?= e((string) $yearName) ?>: <strong><?= e(money($amount, 'ARS')) ?></strong></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    </div>

    <form method="post" action="<?= e(url($clientPath)) ?>" class="panel form-grid">
        <?= csrf_field() ?>
        <label>Contacto
            <input name="contact_name" value="<?= e($client['contact_name'] ?? '') ?>" placeholder="Nombre de la persona de contacto">
        </label>
        <label>Email
            <input name="email" type="email" value="<?= e($client['email'] ?? '') ?>" placeholder="contacto@cliente.com">
        </label>
        <label>Teléfono
            <input name="phone" value="<?= e($client['phone'] ?? '') ?>" placeholder="+54 9 11 …">
        </label>
        <?php $active = (int) ($client['is_active'] ?? 1) === 1; ?>
        <label class="full">Estado
            <div class="status-toggle" role="radiogroup" aria-label="Estado del cliente">
                <label class="status-option status-option-ok">
                    <input type="radio" name="is_active" value="1"<?= $active ? ' checked' : '' ?>>
                    <span class="badge badge-ok">Activo</span>
                </label>
                <label class="status-option status-option-danger">
                    <input type="radio" name="is_active" value="0"<?= !$active ? ' checked' : '' ?>>
                    <span class="badge badge-danger">Inactivo</span>
                </label>
            </div>
        </label>
        <label class="full">Notas
            <textarea name="contract_notes" rows="3" placeholder="Notas internas (opcionales)"><?= e($client['contract_notes'] ?? '') ?></textarea>
        </label>
        <div class="full"><button type="submit">Guardar ficha</button></div>
    </form>

    <div class="panel mt">
        <h2 style="margin-top:0">Contrato (PDF)</h2>
        <?php if ($contract): ?>
            <p>
                <a href="<?= e(url($clientPath . '/archivos/' . (int) $contract['id'])) ?>">
                    <?= e($contract['title'] ?? 'Contrato.pdf') ?>
                </a>
                <span class="muted"> · <?= e(substr((string) ($contract['created_at'] ?? ''), 0, 16)) ?></span>
            </p>
            <form method="post" action="<?= e(url($clientPath . '/documentos/' . (int) $contract['id'] . '/eliminar')) ?>" class="inline"
                  onsubmit="return confirm('¿Eliminar el contrato PDF?');">
                <?= csrf_field() ?>
                        <button type="submit" class="btn danger">Quitar contrato</button>
            </form>
        <?php else: ?>
            <p class="muted">Sin contrato cargado.</p>
        <?php endif; ?>
        <form method="post" action="<?= e(url($clientPath . '/contrato')) ?>" enctype="multipart/form-data" class="form-grid mt"
              style="padding:0;border:0;box-shadow:none;background:transparent">
            <?= csrf_field() ?>
            <label class="full">Subir / reemplazar PDF
                <input type="file" name="contract_pdf" accept="application/pdf,.pdf" required>
            </label>
            <div class="full"><button type="submit">Subir contrato</button></div>
        </form>
    </div>

    <div class="panel mt">
        <h2 style="margin-top:0">Documentación extra</h2>
        <?php if ($extraDocs === []): ?>
            <p class="muted">Sin documentos adicionales.</p>
        <?php else: ?>
            <table class="table">
                <thead><tr><th>Documento</th><th>Fecha</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($extraDocs as $doc): ?>
                    <tr>
                        <td>
                            <a href="<?= e(url($clientPath . '/archivos/' . (int) $doc['id'])) ?>">
                                <?= e($doc['title'] ?? 'Documento') ?>
                            </a>
                        </td>
                        <td><?= e(substr((string) ($doc['created_at'] ?? ''), 0, 16)) ?></td>
                        <td class="actions">
                            <form method="post" action="<?= e(url($clientPath . '/documentos/' . (int) $doc['id'] . '/eliminar')) ?>"
                                  onsubmit="return confirm('¿Eliminar este documento?');">
                                <?= csrf_field() ?>
                                <?= icon_action_button('trash', 'Eliminar', 'danger') ?>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        <form method="post" action="<?= e(url($clientPath . '/documentos')) ?>" enctype="multipart/form-data" class="form-grid mt"
              style="padding:0;border:0;box-shadow:none;background:transparent">
            <?= csrf_field() ?>
            <label>Título
                <input name="title" placeholder="Ej. Anexo, cotización, DNI…">
            </label>
            <label>Archivo
                <input type="file" name="extra_file" required
                       accept=".pdf,.png,.jpg,.jpeg,.webp,.doc,.docx,.xls,.xlsx,.txt,application/pdf">
            </label>
            <div class="full"><button type="submit">Subir documento</button></div>
        </form>
    </div>

    <div class="panel mt" style="border-color: rgba(240, 113, 120, 0.35)">
        <h2 style="margin:0;color:var(--danger)">Limpiar ficha</h2>
        <p class="muted">Borra contacto, email, teléfono y notas. No borra los archivos ni el cliente de la proyección.</p>
        <form method="post" action="<?= e(url($clientPath . '/eliminar')) ?>"
              onsubmit="return confirm('¿Limpiar datos de contacto de esta ficha?');">
            <?= csrf_field() ?>
            <button type="submit" class="btn danger">Limpiar ficha</button>
        </form>
    </div>
</section>
