<?php
/** @var string $templateFile */
/** @var string $appName */
/** @var string $title */
/** @var array|null $user */
$hideAmounts = $user ? hide_amounts() : false;
$isAdmin = $user ? Auth::isAdmin() : false;
$homeHref = $user ? Auth::homePath() : '/';
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
    <link rel="stylesheet" href="<?= e(url('/assets/css/app.css')) ?>?v=<?= (int) @filemtime(dirname(__DIR__, 3) . '/assets/css/app.css') ?>">
</head>
<body class="<?= $hideAmounts ? 'hide-amounts' : '' ?><?= $isAdmin ? '' : ' is-readonly-user' ?>">
<?php if ($user): ?>
<header class="topbar" id="topbar">
    <div class="brand">
        <a href="<?= e(url($homeHref)) ?>"><?= e($appName) ?></a>
    </div>
    <button type="button" class="nav-toggle" id="nav-toggle" aria-expanded="false" aria-controls="topbar-panel" aria-label="Abrir menú">
        <span class="nav-toggle-bar" aria-hidden="true"></span>
        <span class="nav-toggle-bar" aria-hidden="true"></span>
        <span class="nav-toggle-bar" aria-hidden="true"></span>
    </button>
    <div class="topbar-panel" id="topbar-panel">
        <nav class="nav">
            <?php if ($isAdmin): ?>
                <a class="<?= e(nav_active('/')) ?>" href="<?= e(url('/')) ?>">Inicio</a>
                <a class="<?= e(nav_active('/dashboard', true)) ?>" href="<?= e(url('/dashboard')) ?>">Dashboard</a>
                <a class="<?= e(nav_active('/objetivos', true)) ?>" href="<?= e(url('/objetivos')) ?>">Objetivos</a>
                <a class="<?= e(nav_active('/proyecciones', true)) ?>" href="<?= e(url('/proyecciones')) ?>">Proyecciones</a>

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
            <?php else: ?>
                <a class="<?= e(nav_active('/personas')) ?>" href="<?= e(url('/personas')) ?>">Personas</a>
                <a class="<?= e(nav_active('/empresas')) ?>" href="<?= e(url('/empresas')) ?>">Empresas</a>
            <?php endif; ?>
        </nav>
        <div class="userbox">
            <?php if ($isAdmin): ?>
            <form method="post" action="<?= e(url('/configuracion/ocultar-cifras')) ?>" class="inline" title="Ocultar/mostrar cifras">
                <?= csrf_field() ?>
                <button type="submit" class="linkish privacy-toggle" aria-pressed="<?= $hideAmounts ? 'true' : 'false' ?>">
                    <?= $hideAmounts ? 'Mostrar cifras' : 'Ocultar cifras' ?>
                </button>
            </form>
            <?php endif; ?>
            <details class="nav-drop user-drop<?= $profileActive ? ' is-active' : '' ?>">
                <summary><?= e($user['name'] ?? 'Perfil') ?></summary>
                <div class="nav-menu">
                    <a class="<?= e(nav_active('/configuracion')) ?>" href="<?= e(url('/configuracion?tab=perfil')) ?>">Configuración</a>
                    <?php if ($isAdmin): ?>
                        <a class="<?= e(nav_active('/integraciones')) ?>" href="<?= e(url('/integraciones')) ?>">Integraciones</a>
                    <?php endif; ?>
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
<script src="<?= e(url('/assets/js/chart.umd.min.js')) ?>?v=<?= (int) @filemtime(dirname(__DIR__, 3) . '/assets/js/chart.umd.min.js') ?>"></script>
<script src="<?= e(url('/assets/js/app.js')) ?>?v=<?= (int) @filemtime(dirname(__DIR__, 3) . '/assets/js/app.js') ?>"></script>
</body>
</html>
