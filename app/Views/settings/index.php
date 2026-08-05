<section class="page">
    <div class="page-head">
        <div>
            <h1>Configuración</h1>
            <p class="lede">Ajustes del sistema. Las conexiones WC/MP se cargan en Integraciones, no acá ni en .env.</p>
        </div>
    </div>

    <?php require __DIR__ . '/../partials/alerts.php'; ?>

    <div class="grid-2">
        <div class="panel">
            <h2>Privacidad en pantalla</h2>
            <p class="muted">Ocultá montos en dashboards y listados (útil en pantallas compartidas).</p>
            <ul class="plain">
                <li>Estado: <strong><?= !empty($hideAmounts) ? 'Cifras ocultas' : 'Cifras visibles' ?></strong></li>
            </ul>
            <form method="post" action="<?= e(url('/configuracion/ocultar-cifras')) ?>" class="mt">
                <?= csrf_field() ?>
                <button type="submit" class="btn"><?= !empty($hideAmounts) ? 'Mostrar cifras' : 'Ocultar cifras' ?></button>
            </form>
        </div>

        <div class="panel">
            <h2>Clave de cifrado de integraciones</h2>
            <p class="muted">
                Se usa solo para guardar cifrados en la base las Consumer Keys de WooCommerce.
                Archivo local: <code><?= e($keyPath) ?></code>
                (protegido por <code>.htaccess</code>).
            </p>
            <ul class="plain">
                <li>Estado:
                    <?php if (!empty($cryptoOk)): ?>
                        <strong class="ok-text">Configurada</strong>
                        <?php if ($fingerprint): ?> · huella <code><?= e($fingerprint) ?></code><?php endif; ?>
                    <?php else: ?>
                        <strong class="err-text">Pendiente</strong>
                    <?php endif; ?>
                </li>
            </ul>

            <form method="post" action="<?= e(url('/configuracion/clave')) ?>" class="stack mt">
                <?= csrf_field() ?>
                <?php if (!empty($cryptoOk)): ?>
                    <label class="check">
                        <input type="checkbox" name="confirm_rotate" value="1">
                        Confirmo rotar la clave (invalidará secretos ya guardados hasta re-cargarlos)
                    </label>
                    <button type="submit" class="btn ghost">Rotar clave</button>
                <?php else: ?>
                    <button type="submit">Generar clave de cifrado</button>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <div class="panel mt">
        <h2>Dónde van las credenciales</h2>
        <ul class="plain">
            <li><strong>WooCommerce</strong> → menú <a href="<?= e(url('/integraciones')) ?>">Integraciones</a></li>
            <li><strong>Mercado Pago</strong> → cuenta manual en <a href="<?= e(url('/cuentas')) ?>">Cuentas</a> (tipo Mercado Pago) o <a href="<?= e(url('/dashboard/mercadopago')) ?>">Dashboard MP</a></li>
            <li><strong>.env</strong> → solo host/usuario/clave de MySQL y URL de la app. Sin tokens.</li>
        </ul>
    </div>
</section>
