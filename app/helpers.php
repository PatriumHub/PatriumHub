<?php

declare(strict_types=1);

function app_config(?string $key = null, mixed $default = null): mixed
{
    static $config = null;
    if ($config === null) {
        $config = require dirname(__DIR__) . '/config/app.php';
    }
    if ($key === null) {
        return $config;
    }
    return $config[$key] ?? $default;
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function base_path(): string
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    // Preferir el path de APP_URL (ej. http://192.168.100.50/patrium → /patrium)
    $appUrl = (string) app_config('url', '');
    if ($appUrl !== '') {
        $urlPath = parse_url($appUrl, PHP_URL_PATH);
        if (is_string($urlPath) && $urlPath !== '' && $urlPath !== '/') {
            $cached = rtrim($urlPath, '/');
            return $cached;
        }
        $cached = '';
        return $cached;
    }

    $script = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    if ($script === '/' || $script === '\\' || $script === '.') {
        $cached = '';
        return $cached;
    }
    $cached = rtrim($script, '/');
    return $cached;
}

/**
 * Links de la app: /patrium/index.php?r=/ruta (sin rewrite, como Puestito en Apache pelado).
 * Assets: /patrium/assets/...
 */
function url(string $path = '/'): string
{
    $extraQuery = [];
    $hash = '';
    if (str_contains($path, '#')) {
        [$path, $hash] = explode('#', $path, 2);
        $hash = '#' . $hash;
    }
    if (str_contains($path, '?')) {
        [$path, $qs] = explode('?', $path, 2);
        parse_str($qs, $extraQuery);
    }

    $path = '/' . ltrim($path, '/');
    if ($path === '//') {
        $path = '/';
    }

    $base = base_path();

    if (str_starts_with($path, '/assets/') || preg_match('#^/[^/]+\.php$#', $path) === 1) {
        $suffix = $extraQuery ? ('?' . http_build_query($extraQuery)) : '';
        return $base . $path . $suffix . $hash;
    }

    $script = $base . '/index.php';
    $params = $extraQuery;
    if ($path !== '/') {
        $params = array_merge(['r' => $path], $params);
    }
    $suffix = $params ? ('?' . http_build_query($params)) : '';
    return $script . $suffix . $hash;
}

function redirect(string $path): never
{
    header('Location: ' . url($path));
    exit;
}

/**
 * Ruta interna segura para return_to / redirect_to (evita open redirect).
 * Solo acepta paths relativos que empiezan con "/" (no "//").
 */
function safe_return_path(string $path, string $fallback = '/'): string
{
    $path = trim($path);
    if ($path === '' || !str_starts_with($path, '/') || str_starts_with($path, '//')) {
        return $fallback;
    }
    if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $path)) {
        return $fallback;
    }
    return $path;
}

/** Action de formularios GET/POST al front controller. */
function form_action(): string
{
    return base_path() . '/index.php';
}

/** Hidden `r` para que los GET no pierdan la ruta al enviar filtros. */
function route_field(string $path): string
{
    $path = '/' . ltrim($path, '/');
    if ($path === '/' || $path === '//') {
        return '';
    }
    return '<input type="hidden" name="r" value="' . e($path) . '">';
}

function view(string $template, array $data = []): void
{
    extract($data, EXTR_SKIP);
    $appName = (string) app_config('name', 'PatriumHub');
    $user = Auth::user();
    $templateFile = dirname(__DIR__) . '/app/Views/' . $template . '.php';
    if (!is_file($templateFile)) {
        throw new RuntimeException('View not found: ' . $template);
    }
    require dirname(__DIR__) . '/app/Views/layouts/main.php';
}

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function verify_csrf(?string $token): bool
{
    return is_string($token)
        && isset($_SESSION['_csrf'])
        && hash_equals($_SESSION['_csrf'], $token);
}

function flash(string $key, ?string $message = null): ?string
{
    if ($message !== null) {
        $_SESSION['_flash'][$key] = $message;
        return null;
    }
    $value = $_SESSION['_flash'][$key] ?? null;
    unset($_SESSION['_flash'][$key]);
    return $value;
}

function require_csrf(): void
{
    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        flash('error', 'Sesión inválida. Probá de nuevo.');
        $ref = (string) ($_SERVER['HTTP_REFERER'] ?? '');
        $appUrl = (string) app_config('url', '');
        if ($appUrl !== '' && $ref !== '' && str_starts_with($ref, $appUrl)) {
            header('Location: ' . $ref);
            exit;
        }
        redirect('/');
    }
}

function money(float|int|string|null $amount, string $currency = 'ARS'): string
{
    if (hide_amounts()) {
        return $currency . ' ••••';
    }
    $n = (float) $amount;
    return $currency . ' ' . number_format($n, 2, ',', '.');
}

/** 1 = ingreso, -1 = egreso, 0 = neutro (transfer/ajuste). */
function movement_direction(string $type): int
{
    return match ($type) {
        'income', 'collection', 'loan', 'ingreso' => 1,
        'expense', 'payment', 'egreso' => -1,
        default => 0,
    };
}

/** Monto con signo y color (+ verde / − rojo). HTML seguro. */
function money_flow(float|int|string|null $amount, string $currency, string $type): string
{
    $dir = movement_direction($type);
    $abs = abs((float) $amount);
    $label = money($abs, $currency);
    if (hide_amounts()) {
        return '<span class="muted">' . e($label) . '</span>';
    }
    if ($dir > 0) {
        return '<span class="amount-in">+' . e($label) . '</span>';
    }
    if ($dir < 0) {
        return '<span class="amount-out">−' . e($label) . '</span>';
    }
    return '<span>' . e(money($amount, $currency)) . '</span>';
}

function movement_type_label(string $type): string
{
    return match ($type) {
        'income', 'ingreso' => 'ingreso',
        'expense', 'egreso' => 'egreso',
        'transfer' => 'transferencia',
        'adjustment' => 'ajuste',
        'collection' => 'cobro',
        'payment' => 'pago',
        'loan' => 'préstamo',
        default => $type,
    };
}

function hide_amounts(): bool
{
    if (!class_exists('SettingsService') || !class_exists('Auth') || !Auth::check()) {
        return false;
    }
    return SettingsService::hideAmounts();
}

/**
 * @param array<string, string|null> $filters key => display label (null/'' = inactive)
 * @param array<string, string|int|null> $baseQuery current query params
 */
function filter_chips(array $filters, array $baseQuery, string $path): array
{
    $chips = [];
    foreach ($filters as $key => $label) {
        if ($label === null || $label === '') {
            continue;
        }
        $q = $baseQuery;
        unset($q[$key]);
        $chips[] = [
            'label' => $label,
            'clear_url' => $path . ($q ? ('?' . http_build_query($q)) : ''),
        ];
    }
    return $chips;
}

function selected(mixed $current, mixed $value): string
{
    return (string) $current === (string) $value ? ' selected' : '';
}

function checked(mixed $current, bool|int $flag = true): string
{
    return ((bool) $current === (bool) $flag) ? ' checked' : '';
}

function request_int(string $key, ?int $default = null): ?int
{
    if (!isset($_REQUEST[$key]) || $_REQUEST[$key] === '') {
        return $default;
    }
    return (int) $_REQUEST[$key];
}

function post_string(string $key, string $default = ''): string
{
    return trim((string) ($_POST[$key] ?? $default));
}

function post_float(string $key, float $default = 0.0): float
{
    if (!isset($_POST[$key]) || $_POST[$key] === '') {
        return $default;
    }
    $raw = str_replace(' ', '', (string) $_POST[$key]);
    if (str_contains($raw, ',') && str_contains($raw, '.')) {
        $raw = str_replace('.', '', $raw);
        $raw = str_replace(',', '.', $raw);
    } elseif (str_contains($raw, ',')) {
        $raw = str_replace(',', '.', $raw);
    }
    return is_numeric($raw) ? (float) $raw : $default;
}

function bool_post(string $key): int
{
    return isset($_POST[$key]) ? 1 : 0;
}

/**
 * Moneda para totales/gráficos de listados.
 * Si el usuario eligió ?currency=, se respeta; si no, la de mayor monto.
 *
 * @param list<array<string,mixed>> $rows
 */
function preferred_list_currency(array $rows, string $amountKey, string $fallback = 'ARS'): string
{
    if (isset($_GET['currency']) && is_string($_GET['currency']) && trim($_GET['currency']) !== '') {
        return strtoupper(trim($_GET['currency']));
    }

    $sums = [];
    $counts = [];
    foreach ($rows as $row) {
        $code = strtoupper(trim((string) ($row['currency_code'] ?? '')));
        if ($code === '') {
            continue;
        }
        $counts[$code] = ($counts[$code] ?? 0) + 1;
        $sums[$code] = ($sums[$code] ?? 0.0) + abs((float) ($row[$amountKey] ?? 0));
    }
    if ($sums !== []) {
        arsort($sums);
        $top = (string) array_key_first($sums);
        if (($sums[$top] ?? 0) > 0.009) {
            return $top;
        }
    }
    if ($counts !== []) {
        arsort($counts);
        return (string) array_key_first($counts);
    }
    return strtoupper($fallback);
}

function current_route_path(): string
{
    if (isset($_GET['r']) && is_string($_GET['r']) && $_GET['r'] !== '') {
        $path = '/' . trim($_GET['r'], '/');
        return $path === '/' ? '/' : rtrim($path, '/');
    }

    foreach (['PATH_INFO', 'ORIG_PATH_INFO'] as $key) {
        $info = $_SERVER[$key] ?? '';
        if (is_string($info) && $info !== '') {
            $path = '/' . trim($info, '/');
            return $path === '/' ? '/' : rtrim($path, '/');
        }
    }

    return '/';
}

function nav_active(string $prefix, bool $exact = false): string
{
    $path = current_route_path();
    if ($exact || $prefix === '/') {
        return $path === $prefix ? ' is-active' : '';
    }
    return ($path === $prefix || str_starts_with($path, $prefix . '/')) ? ' is-active' : '';
}
