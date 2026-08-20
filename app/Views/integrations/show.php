<section class="page">
    <div class="page-head">
        <div>
            <h1><?= e($item['name']) ?></h1>
            <p class="lede">
                WooCommerce · <?= e($item['entity_name']) ?>
                · estado <strong><?= e($item['status']) ?></strong>
            </p>
        </div>
        <div class="actions">
            <?php if ($item['status'] !== 'revoked'): ?>
                <a class="btn ghost" href="<?= e(url('/integraciones/' . $item['id'] . '/editar')) ?>">Editar</a>
            <?php endif; ?>
            <a class="btn ghost" href="<?= e(url('/integraciones')) ?>">Volver</a>
        </div>
    </div>

    <?php require __DIR__ . '/../partials/alerts.php'; ?>

    <?php if (!empty($stale) && $item['status'] === 'active'): ?>
        <div class="alert alert-error">Datos desactualizados: última sync <?= e($item['last_sync_at'] ?? 'nunca') ?>.</div>
    <?php endif; ?>
    <?php if (!empty($item['last_error'])): ?>
        <div class="alert alert-error">Último error: <?= e((string) $item['last_error']) ?></div>
    <?php endif; ?>

    <div class="grid-2">
        <div class="panel">
            <h2>Conexión</h2>
            <ul class="plain">
                <li>URL: <strong><?= e($config['store_url'] ?? '—') ?></strong></li>
                <li>API: <strong><?= e($config['api_version'] ?? 'wc/v3') ?></strong></li>
                <li>Moneda: <strong><?= e($config['currency_code'] ?? 'ARS') ?></strong></li>
                <li>Sync auto: <strong><?= !empty($config['sync_auto']) ? 'Sí' : 'No' ?></strong></li>
                <li>Última sync: <strong><?= e($item['last_sync_at'] ?? 'Nunca') ?></strong></li>
                <li>Credenciales: <strong>guardadas cifradas en BD</strong></li>
            </ul>
            <?php if ($item['status'] !== 'revoked'): ?>
                <div class="actions mt">
                    <form method="post" action="<?= e(url('/integraciones/' . $item['id'] . '/probar')) ?>" class="inline">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn ghost">Probar conexión</button>
                    </form>
                    <form method="post" action="<?= e(url('/integraciones/' . $item['id'] . '/sync')) ?>" class="inline">
                        <?= csrf_field() ?>
                        <button type="submit">Sincronizar ahora</button>
                    </form>
                    <form method="post" action="<?= e(url('/integraciones/' . $item['id'] . '/revocar')) ?>" class="inline" onsubmit="return confirm('¿Eliminar esta conexión? Se borrarán datos sync. No se puede deshacer.');">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn danger">Eliminar</button>
                    </form>
                </div>
            <?php endif; ?>
        </div>

        <div class="panel">
            <h2>Datos sincronizados</h2>
            <?php if ($inventory): ?>
                <ul class="plain">
                    <li>Stock valor: <strong><?= e(money($inventory['stock_value'], $inventory['currency_code'])) ?></strong></li>
                    <li>Unidades: <strong><?= e(number_format((float) $inventory['units_total'], 2, ',', '.')) ?></strong></li>
                    <li>Productos: <strong><?= (int) $inventory['products_count'] ?></strong></li>
                </ul>
            <?php else: ?>
                <p class="muted">Sin inventario sincronizado todavía.</p>
            <?php endif; ?>
            <?php
            $salesRows = $salesRows ?? ($sales ? [$sales] : []);
            if ($salesRows !== []):
                $salesSum = 0.0;
                $ordersSum = 0;
                foreach ($salesRows as $sr) {
                    $salesSum += (float) $sr['sales_total'];
                    $ordersSum += (int) $sr['orders_count'];
                }
            ?>
                <h3 class="mt">Ventas (últimos meses sync)</h3>
                <ul class="plain">
                    <li>Total período: <strong><?= e(money($salesSum, $salesRows[0]['currency_code'])) ?></strong>
                        · <?= $ordersSum ?> pedidos</li>
                    <?php foreach ($salesRows as $sr): ?>
                        <li>
                            <?= e($sr['period_start']) ?> → <?= e($sr['period_end']) ?>:
                            <strong><?= e(money($sr['sales_total'], $sr['currency_code'])) ?></strong>
                            (<?= (int) $sr['orders_count'] ?> pedidos)
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="muted mt">Sin ventas sincronizadas. Corré “Sincronizar ahora”.</p>
            <?php endif; ?>
            <p class="mt"><a href="<?= e(url('/empresas/' . $item['entity_id'] . '?tab=integraciones&currency=ARS')) ?>">Ver en ficha de entidad</a></p>
        </div>
    </div>

    <div class="panel mt">
        <h2>Historial de sync</h2>
        <?php if (empty($runs)): ?>
            <p class="muted">Sin ejecuciones aún.</p>
        <?php else: ?>
            <table class="table">
                <thead>
                <tr>
                    <th>Inicio</th>
                    <th>Fin</th>
                    <th>Estado</th>
                    <th class="num">Leídos</th>
                    <th class="num">Upserts</th>
                    <th>Mensaje</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($runs as $run): ?>
                    <tr>
                        <td><?= e($run['started_at']) ?></td>
                        <td><?= e($run['finished_at'] ?? '—') ?></td>
                        <td><span class="badge <?= $run['status'] === 'failed' ? 'badge-danger' : 'badge-ok' ?>"><?= e($run['status']) ?></span></td>
                        <td class="num"><?= (int) $run['records_read'] ?></td>
                        <td class="num"><?= (int) $run['records_upserted'] ?></td>
                        <td><?= e($run['message'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</section>
