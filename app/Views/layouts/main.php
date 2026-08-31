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
    <script>
    (function () {
      try {
        var t = localStorage.getItem('patrium-theme');
        if (t !== 'dark' && t !== 'light') t = 'light';
        document.documentElement.setAttribute('data-theme', t);
      } catch (e) {
        document.documentElement.setAttribute('data-theme', 'light');
      }
    })();
    </script>
    <link rel="stylesheet" href="<?= e(url('/assets/css/app.css')) ?>?v=<?= (int) @filemtime(dirname(__DIR__, 3) . '/assets/css/app.css') ?>">
</head>
<body class="<?= $hideAmounts ? 'hide-amounts' : '' ?><?= $isAdmin ? '' : ' is-readonly-user' ?>">
<?php if (!$user): ?>
<button type="button" class="theme-toggle theme-toggle--auth" aria-label="Cambiar a modo oscuro">
    <svg class="theme-toggle__moon" width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
        <path d="M21 14.3A8.5 8.5 0 0 1 9.7 3 7 7 0 1 0 21 14.3Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
    </svg>
    <svg class="theme-toggle__sun" width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
        <circle cx="12" cy="12" r="4" stroke="currentColor" stroke-width="1.8"/>
        <path d="M12 2v2.2M12 19.8V22M4.2 4.2l1.6 1.6M18.2 18.2l1.6 1.6M2 12h2.2M19.8 12H22M4.2 19.8l1.6-1.6M18.2 5.8l1.6-1.6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
    </svg>
</button>
<?php endif; ?>
<?php if ($user): ?>
<header class="topbar" id="topbar">
    <div class="brand">
        <a href="<?= e(url($homeHref)) ?>"><?= e($appName) ?></a>
    </div>
    <div class="topbar-tools">
        <?php if ($isAdmin): ?>
        <form method="post" action="<?= e(url('/configuracion/ocultar-cifras')) ?>" class="inline">
            <?= csrf_field() ?>
            <button type="submit" class="privacy-toggle" aria-pressed="<?= $hideAmounts ? 'true' : 'false' ?>"
                    title="<?= $hideAmounts ? 'Mostrar cifras' : 'Ocultar cifras' ?>"
                    aria-label="<?= $hideAmounts ? 'Mostrar cifras' : 'Ocultar cifras' ?>">
                <?php if ($hideAmounts): ?>
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M3 3l18 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                        <path d="M10.6 5.2A9.6 9.6 0 0 1 12 5c5 0 9 4.5 9 7 0 .9-.5 2-1.4 3.1M6.3 6.9C3.9 8.4 3 10.4 3 12c0 2.5 4 7 9 7 1.6 0 3-.4 4.2-1"
                              stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                        <path d="M9.9 9.9a3 3 0 0 0 4.2 4.2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                    </svg>
                <?php else: ?>
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M3 12c0-2.5 4-7 9-7s9 4.5 9 7-4 7-9 7-9-4.5-9-7Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                        <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.8"/>
                    </svg>
                <?php endif; ?>
            </button>
        </form>
        <?php endif; ?>
        <button type="button" class="theme-toggle theme-toggle--bar" aria-label="Cambiar a modo oscuro">
            <svg class="theme-toggle__moon" width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path d="M21 14.3A8.5 8.5 0 0 1 9.7 3 7 7 0 1 0 21 14.3Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
            </svg>
            <svg class="theme-toggle__sun" width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <circle cx="12" cy="12" r="4" stroke="currentColor" stroke-width="1.8"/>
                <path d="M12 2v2.2M12 19.8V22M4.2 4.2l1.6 1.6M18.2 18.2l1.6 1.6M2 12h2.2M19.8 12H22M4.2 19.8l1.6-1.6M18.2 5.8l1.6-1.6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
            </svg>
        </button>
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
                <a class="<?= e(nav_active('/presupuestos')) ?>" href="<?= e(url('/presupuestos')) ?>">Presupuestos</a>
                <a class="<?= e(nav_active('/gastos', true)) ?>" href="<?= e(url('/gastos')) ?>">Gastos</a>
                <a class="<?= e(nav_active('/movimientos')) ?>" href="<?= e(url('/movimientos')) ?>">Movimientos</a>

                <details class="nav-drop nav-drop--wealth<?= $wealthActive ? ' is-active' : '' ?>">
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
            <?php else: ?>
                <a class="<?= e(nav_active('/personas')) ?>" href="<?= e(url('/personas')) ?>">Personas</a>
                <a class="<?= e(nav_active('/empresas')) ?>" href="<?= e(url('/empresas')) ?>">Empresas</a>
            <?php endif; ?>
        </nav>
        <div class="userbox">
            <button type="button" class="theme-toggle theme-toggle--user" aria-label="Cambiar a modo oscuro">
                <svg class="theme-toggle__moon" width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M21 14.3A8.5 8.5 0 0 1 9.7 3 7 7 0 1 0 21 14.3Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                </svg>
                <svg class="theme-toggle__sun" width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <circle cx="12" cy="12" r="4" stroke="currentColor" stroke-width="1.8"/>
                    <path d="M12 2v2.2M12 19.8V22M4.2 4.2l1.6 1.6M18.2 18.2l1.6 1.6M2 12h2.2M19.8 12H22M4.2 19.8l1.6-1.6M18.2 5.8l1.6-1.6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                </svg>
            </button>
            <?php if ($isAdmin): ?>
                <a class="userbox-link<?= e(nav_active('/reportes')) ?>" href="<?= e(url('/reportes/patrimonial')) ?>">Reporte</a>
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
<button type="button" class="back-to-top" id="back-to-top" aria-label="Volver arriba">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true">
        <path d="M12 19V6M5.5 12.5 12 6l6.5 6.5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
    </svg>
</button>
<script src="<?= e(url('/assets/js/chart.umd.min.js')) ?>?v=<?= (int) @filemtime(dirname(__DIR__, 3) . '/assets/js/chart.umd.min.js') ?>"></script>
<script src="<?= e(url('/assets/js/app.js')) ?>?v=<?= (int) @filemtime(dirname(__DIR__, 3) . '/assets/js/app.js') ?>"></script>
</body>
</html>
