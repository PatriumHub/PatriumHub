<section class="page">
    <div class="page-head">
        <div>
            <h1>Integraciones</h1>
            <p class="lede">Conectá WooCommerce para sincronizar ventas e inventario. Las credenciales se guardan cifradas.</p>
        </div>
        <?php if (!empty($rows)): ?>
            <div class="actions">
                <a class="btn" href="<?= e(url('/integraciones/woocommerce/nueva')) ?>">+ WooCommerce</a>
            </div>
        <?php endif; ?>
    </div>

    <?php require __DIR__ . '/../partials/alerts.php'; ?>

    <?php if (empty($cryptoOk)): ?>
        <div class="alert alert-error">
            Primero generá la clave de cifrado en
            <a href="<?= e(url('/configuracion')) ?>">Configuración</a>.
            Después volvé a conectar WooCommerce desde acá.
        </div>
    <?php endif; ?>

    <?php if (empty($rows)): ?>
        <div class="panel integrations-empty">
            <p class="integrations-empty-lead">Todavía no hay conexiones.</p>
            <p class="muted">Las cuentas Mercado Pago se cargan a mano en
                <a href="<?= e(url('/cuentas')) ?>">Cuentas</a>
                (tipo Mercado Pago).
            </p>
            <div class="integration-cards" style="grid-template-columns:1fr;max-width:360px">
                <a class="integration-card" href="<?= e(url('/integraciones/woocommerce/nueva')) ?>">
                    <span class="integration-card-title">WooCommerce</span>
                    <span class="integration-card-desc">Ventas, pedidos e inventario de la tienda</span>
                    <span class="integration-card-cta">Conectar</span>
                </a>
            </div>
        </div>
    <?php else: ?>
        <div class="panel">
            <table class="table">
                <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Proveedor</th>
                    <th>Entidad</th>
                    <th>Estado</th>
                    <th>Última sync</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <?php
                    $providerLabel = match ((string) $row['provider']) {
                        'woocommerce' => 'WooCommerce',
                        default => (string) $row['provider'],
                    };
                    $status = (string) $row['status'];
                    $badge = $status === 'error' ? 'badge-danger' : ($status === 'active' ? 'badge-ok' : 'badge-warn');
                    ?>
                    <tr>
                        <td>
                            <a href="<?= e(url('/integraciones/' . $row['id'])) ?>"><?= e($row['name']) ?></a>
                            <?php if (!empty($row['stale']) && $status === 'active'): ?>
                                <span class="badge badge-warn">desactualizada</span>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge"><?= e($providerLabel) ?></span></td>
                        <td><?= e($row['entity_name']) ?></td>
                        <td><span class="badge <?= e($badge) ?>"><?= e($status) ?></span></td>
                        <td><?= e($row['last_sync_at'] ?? 'Nunca') ?></td>
                        <td class="actions">
                            <div class="row-icon-actions">
                                <?= icon_action_link(url('/integraciones/' . $row['id']), 'eye', 'Ver') ?>
                                <?php if ($status !== 'revoked'): ?>
                                    <?= icon_action_link(url('/integraciones/' . $row['id'] . '/editar'), 'edit', 'Editar') ?>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
