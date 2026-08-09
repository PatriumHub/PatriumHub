<?php
/** @var string $templateFile */
/** @var string $appName */
/** @var string $title */
/** @var array|null $user */
$hideAmounts = $user ? hide_amounts() : false;
$wealthActive = nav_active('/personas') || nav_active('/empresas') || nav_active('/participaciones')
    || nav_active('/cuentas') || nav_active('/activos') || nav_active('/propiedades')
    || nav_active('/cobrables') || nav_active('/pasivos') || nav_active('/inventario');
$profileActive = nav_active('/configuracion') || nav_active('/integraciones');
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
<header class="topbar" id="topbar">
    <div class="brand">
        <a href="<?= e(url('/')) ?>"><?= e($appName) ?></a>
    </div>
    <button type="button" class="nav-toggle" id="nav-toggle" aria-expanded="false" aria-controls="topbar-panel" aria-label="Abrir menú">
        <span class="nav-toggle-bar" aria-hidden="true"></span>
        <span class="nav-toggle-bar" aria-hidden="true"></span>
        <span class="nav-toggle-bar" aria-hidden="true"></span>
    </button>
    <div class="topbar-panel" id="topbar-panel">
        <nav class="nav">
            <a class="<?= e(nav_active('/')) ?>" href="<?= e(url('/')) ?>">Inicio</a>
            <a class="<?= e(nav_active('/dashboard', true)) ?>" href="<?= e(url('/dashboard')) ?>">Dashboard</a>

            <details class="nav-drop<?= $wealthActive ? ' is-active' : '' ?>">
                <summary>Patrimonio</summary>
                <div class="nav-menu">
                    <a class="<?= e(nav_active('/personas')) ?>" href="<?= e(url('/personas')) ?>">Personas</a>
                    <a class="<?= e(nav_active('/empresas')) ?>" href="<?= e(url('/empresas')) ?>">Empresas</a>
                    <a class="<?= e(nav_active('/participaciones')) ?>" href="<?= e(url('/participaciones')) ?>">Participaciones</a>
                    <a class="<?= e(nav_active('/cuentas')) ?>" href="<?= e(url('/cuentas')) ?>">Cuentas</a>
                    <a class="<?= e(nav_active('/activos')) ?>" href="<?= e(url('/activos')) ?>">Activos varios</a>
                    <a class="<?= e(nav_active('/propiedades')) ?>" href="<?= e(url('/propiedades')) ?>">Propiedades</a>
                    <a class="<?= e(nav_active('/cobrables')) ?>" href="<?= e(url('/cobrables')) ?>">Cobrables</a>
                    <a class="<?= e(nav_active('/pasivos')) ?>" href="<?= e(url('/pasivos')) ?>">Pasivos</a>
                    <a class="<?= e(nav_active('/inventario')) ?>" href="<?= e(url('/inventario')) ?>">Inventario</a>
                </div>
            </details>

            <a class="<?= e(nav_active('/presupuestos')) ?>" href="<?= e(url('/presupuestos')) ?>">Presupuestos</a>
            <a class="<?= e(nav_active('/movimientos')) ?>" href="<?= e(url('/movimientos')) ?>">Movimientos</a>
        </nav>
        <div class="userbox">
            <form method="post" action="<?= e(url('/configuracion/ocultar-cifras')) ?>" class="inline" title="Ocultar/mostrar cifras">
                <?= csrf_field() ?>
                <button type="submit" class="linkish privacy-toggle" aria-pressed="<?= $hideAmounts ? 'true' : 'false' ?>">
                    <?= $hideAmounts ? 'Mostrar cifras' : 'Ocultar cifras' ?>
                </button>
            </form>
            <details class="nav-drop user-drop<?= $profileActive ? ' is-active' : '' ?>">
                <summary><?= e($user['name'] ?? 'Perfil') ?></summary>
                <div class="nav-menu">
                    <a class="<?= e(nav_active('/configuracion')) ?>" href="<?= e(url('/configuracion')) ?>">Configuración</a>
                    <form method="post" action="<?= e(url('/logout')) ?>" class="nav-menu-form">
                        <?= csrf_field() ?>
                        <button type="submit" class="nav-menu-btn">Salir</button>
                    </form>
                </div>
            </details>
        </div>
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
