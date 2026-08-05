<?php
/** @var string $templateFile */
/** @var string $appName */
/** @var string $title */
/** @var array|null $user */
$hideAmounts = $user ? hide_amounts() : false;
$wealthActive = nav_active('/personas') || nav_active('/empresas') || nav_active('/participaciones')
    || nav_active('/cuentas') || nav_active('/activos') || nav_active('/propiedades')
    || nav_active('/cobrables') || nav_active('/pasivos') || nav_active('/inventario')
    || nav_active('/movimientos');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(($title ?? '') !== '' ? $title . ' · ' . $appName : $appName) ?></title>
    <link rel="stylesheet" href="<?= e(url('/assets/css/app.css')) ?>">
</head>
<body class="<?= $hideAmounts ? 'hide-amounts' : '' ?>">
<?php if ($user): ?>
<header class="topbar">
    <div class="brand">
        <a href="<?= e(url('/')) ?>"><?= e($appName) ?></a>
    </div>
    <nav class="nav">
        <a class="<?= e(nav_active('/')) ?>" href="<?= e(url('/')) ?>">Inicio</a>
        <a class="<?= e(nav_active('/dashboard', true)) ?>" href="<?= e(url('/dashboard')) ?>">Dashboard</a>
        <a class="<?= e(nav_active('/dashboard/mercadopago')) ?>" href="<?= e(url('/dashboard/mercadopago')) ?>">Mercado Pago</a>

        <details class="nav-drop<?= $wealthActive ? ' is-active' : '' ?>">
            <summary>Patrimonio</summary>
            <div class="nav-menu">
                <a class="<?= e(nav_active('/personas')) ?>" href="<?= e(url('/personas')) ?>">Personas</a>
                <a class="<?= e(nav_active('/empresas')) ?>" href="<?= e(url('/empresas')) ?>">Empresas</a>
                <a class="<?= e(nav_active('/participaciones')) ?>" href="<?= e(url('/participaciones')) ?>">Participaciones</a>
                <a class="<?= e(nav_active('/cuentas')) ?>" href="<?= e(url('/cuentas')) ?>">Cuentas</a>
                <a class="<?= e(nav_active('/activos')) ?>" href="<?= e(url('/activos')) ?>">Activos</a>
                <a class="<?= e(nav_active('/propiedades')) ?>" href="<?= e(url('/propiedades')) ?>">Propiedades</a>
                <a class="<?= e(nav_active('/cobrables')) ?>" href="<?= e(url('/cobrables')) ?>">Cobrables</a>
                <a class="<?= e(nav_active('/pasivos')) ?>" href="<?= e(url('/pasivos')) ?>">Pasivos</a>
                <a class="<?= e(nav_active('/inventario')) ?>" href="<?= e(url('/inventario')) ?>">Inventario</a>
                <a class="<?= e(nav_active('/movimientos')) ?>" href="<?= e(url('/movimientos')) ?>">Movimientos</a>
            </div>
        </details>

        <a class="<?= e(nav_active('/integraciones')) ?>" href="<?= e(url('/integraciones')) ?>">Integraciones</a>
        <a class="<?= e(nav_active('/configuracion')) ?>" href="<?= e(url('/configuracion')) ?>">Configuración</a>
    </nav>
    <div class="userbox">
        <form method="post" action="<?= e(url('/configuracion/ocultar-cifras')) ?>" class="inline" title="Ocultar/mostrar cifras">
            <?= csrf_field() ?>
            <button type="submit" class="linkish privacy-toggle" aria-pressed="<?= $hideAmounts ? 'true' : 'false' ?>">
                <?= $hideAmounts ? 'Mostrar cifras' : 'Ocultar cifras' ?>
            </button>
        </form>
        <span><?= e($user['name'] ?? '') ?></span>
        <form method="post" action="<?= e(url('/logout')) ?>" class="inline">
            <?= csrf_field() ?>
            <button type="submit" class="linkish">Salir</button>
        </form>
    </div>
</header>
<?php endif; ?>

<main class="<?= $user ? 'shell' : 'auth-shell' ?>">
    <?php require $templateFile; ?>
</main>

<footer class="footer">
    <span><?= e($appName) ?></span>
</footer>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" defer></script>
<script src="<?= e(url('/assets/js/app.js')) ?>" defer></script>
</body>
</html>
