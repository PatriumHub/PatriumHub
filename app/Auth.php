<?php

declare(strict_types=1);

final class Auth
{
    public static function startSession(string $name): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $secure = self::cookieSecure();
        $lifetime = (int) app_config('session_lifetime', 86400);
        if ($lifetime < 300) {
            $lifetime = 300;
        }

        // Que el GC del servidor no borre el archivo de sesiÃ³n antes que expire la cookie.
        ini_set('session.gc_maxlifetime', (string) $lifetime);
        $lifetime = max(86400, $lifetime);
        $savePath = self::ensureSessionSavePath();
        if ($savePath !== null) {
            session_save_path($savePath);
        }
        ini_set('session.gc_maxlifetime', (string) max($lifetime, isset($idle) ? (int) $idle : $lifetime));
        ini_set('session.cookie_lifetime', (string) $lifetime);

        session_name($name);
        session_set_cookie_params([
            'lifetime' => $lifetime,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start([
            'cookie_lifetime' => $lifetime,
            'cookie_httponly' => true,
            'cookie_samesite' => 'Lax',
            'cookie_secure' => $secure,
            'use_strict_mode' => true,
            'use_only_cookies' => true,
        ]);

        self::enforceIdleTimeout((int) app_config('session_idle', 86400), $lifetime);
    }

    public static function attempt(string $email, string $password): bool
    {
        if (!self::allowLoginAttempt()) {
            return false;
        }

        $stmt = Database::pdo()->prepare(
            'SELECT id, name, email, password_hash, role, is_active
             FROM users
             WHERE email = :email
             LIMIT 1'
        );
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        if (!$user || !(int) $user['is_active']) {
            self::recordLoginFailure();
            return false;
        }

        if (!password_verify($password, $user['password_hash'])) {
            self::recordLoginFailure();
            return false;
        }

        self::clearLoginFailures();
        session_regenerate_id(true);
        $_SESSION['user'] = [
            'id' => (int) $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role'],
        ];
        $_SESSION['_last_activity'] = time();

        $upd = Database::pdo()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id');
        $upd->execute(['id' => $user['id']]);

        return true;
    }

    public static function check(): bool
    {
        return isset($_SESSION['user']['id']);
    }

    public static function user(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'] ?? '/',
                $params['domain'] ?? '',
                (bool) ($params['secure'] ?? false),
                (bool) ($params['httponly'] ?? true)
            );
        }
        session_destroy();
    }

    public static function requireLogin(): void
    {
        if (!self::check()) {
            redirect('/login');
        }
    }

    public static function isAdmin(): bool
    {
        $user = self::user();
        return is_array($user) && ($user['role'] ?? '') === 'admin';
    }

    /** Admin puede escribir; el resto es solo lectura. */
    public static function canWrite(): bool
    {
        return self::isAdmin();
    }

    /** Destino post-login / redirecciones para usuarios limitados. */
    public static function homePath(): string
    {
        if (self::isAdmin()) {
            return '/';
        }
        $companies = Catalog::entities('company');
        if ($companies !== []) {
            return '/empresas';
        }
        $people = Catalog::entities('person');
        if ($people !== []) {
            return '/personas';
        }
        return '/configuracion?tab=perfil';
    }

    public static function requireAdmin(): void
    {
        self::requireLogin();
        if (!self::isAdmin()) {
            flash('error', 'Solo un administrador puede hacer eso.');
            redirect(self::homePath());
        }
    }

    /** Altas, ediciones, borrados y acciones que cambian datos. */
    public static function requireWrite(): void
    {
        self::requireLogin();
        if (!self::canWrite()) {
            flash('error', 'Tu usuario es de solo lectura: no podÃ©s crear, editar ni borrar.');
            redirect(self::homePath());
        }
    }

    /** Secciones de administraciÃ³n / operaciÃ³n (dashboard, ABMs globales, etc.). */
    public static function requireAdminArea(): void
    {
        self::requireLogin();
        if (!self::isAdmin()) {
            flash('error', 'No tenÃ©s acceso a esa secciÃ³n.');
            redirect(self::homePath());
        }
    }

    /** Recarga nombre/email/rol desde BD a la sesiÃ³n. */
    public static function refreshSessionUser(): void
    {
        $user = self::user();
        if (!$user) {
            return;
        }
        $stmt = Database::pdo()->prepare(
            'SELECT id, name, email, role, is_active
             FROM users WHERE id = :id LIMIT 1'
        );
        $stmt->execute(['id' => (int) $user['id']]);
        $row = $stmt->fetch();
        if (!$row || !(int) $row['is_active']) {
            self::logout();
            return;
        }
        $_SESSION['user'] = [
            'id' => (int) $row['id'],
            'name' => $row['name'],
            'email' => $row['email'],
            'role' => $row['role'],
        ];
    }

    public static function loginThrottled(): bool
    {
        $key = self::throttleKey();
        $bucket = $_SESSION['_login_throttle'][$key] ?? null;
        if (!is_array($bucket)) {
            return false;
        }
        $until = (int) ($bucket['locked_until'] ?? 0);
        return $until > time();
    }

    private static function cookieSecure(): bool
    {
        $flag = patrium_env('SESSION_SECURE', null);
        if ($flag !== null && $flag !== '') {
            return filter_var($flag, FILTER_VALIDATE_BOOLEAN);
        }
        $env = (string) app_config('env', 'local');
        if (in_array($env, ['production', 'prod'], true)) {
            return true;
        }
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443)
            || (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');
        return $https;
    }

    /**
     * Cierra la sesiÃ³n si pasÃ³ mÃ¡s de `$idleSeconds` sin actividad.
     * Si sigue activa, renueva `_last_activity` y la cookie para que el plazo
     * de 24 h corra desde la Ãºltima visita (no desde el login original).
     */
    private static function enforceIdleTimeout(int $idleSeconds, int $cookieLifetime): void
    {
        if ($idleSeconds <= 0 || !isset($_SESSION['user'])) {
            return;
        }
        $last = (int) ($_SESSION['_last_activity'] ?? 0);
        if ($last > 0 && (time() - $last) > $idleSeconds) {
            self::logout();
            return;
        }
        $_SESSION['_last_activity'] = time();
        self::refreshSessionCookie($cookieLifetime);
    }

    /** ReenvÃ­a la cookie de sesiÃ³n con expires renovado (ventana deslizante). */
    private static function refreshSessionCookie(int $lifetime): void
    {
        if ($lifetime <= 0 || session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }
        $params = session_get_cookie_params();
        $options = [
            'expires' => time() + $lifetime,
            'path' => (string) ($params['path'] ?? '/'),
            'secure' => (bool) ($params['secure'] ?? false),
            'httponly' => (bool) ($params['httponly'] ?? true),
            'samesite' => (string) ($params['samesite'] ?? 'Lax'),
        ];
        $domain = (string) ($params['domain'] ?? '');
        if ($domain !== '') {
            $options['domain'] = $domain;
        }
        setcookie(session_name(), session_id(), $options);
    }

    private static function allowLoginAttempt(): bool
    {
        return !self::loginThrottled();
    }

    private static function recordLoginFailure(): void
    {
        $key = self::throttleKey();
        $bucket = $_SESSION['_login_throttle'][$key] ?? ['count' => 0, 'locked_until' => 0];
        $bucket['count'] = (int) $bucket['count'] + 1;
        if ($bucket['count'] >= 5) {
            $bucket['locked_until'] = time() + 300;
            $bucket['count'] = 0;
        }
        $_SESSION['_login_throttle'][$key] = $bucket;
    }

    private static function clearLoginFailures(): void
    {
        $key = self::throttleKey();
        unset($_SESSION['_login_throttle'][$key]);
    }

    private static function throttleKey(): string
    {
        return substr(hash('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? 'cli')), 0, 32);
    }

    /** Carpeta propia: el tmp compartido borra sesiones a ~24 min (gc=1440). */
    private static function ensureSessionSavePath(): ?string
    {
        $candidates = [
            dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'sessions',
            rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'patrium_sessions',
        ];
        foreach ($candidates as $dir) {
            if (!is_dir($dir)) {
                @mkdir($dir, 0777, true);
            }
            if (!is_dir($dir)) {
                continue;
            }
            @chmod($dir, 0777);
            $probe = $dir . DIRECTORY_SEPARATOR . '.write';
            if (@file_put_contents($probe, '1') === false) {
                continue;
            }
            @unlink($probe);
            return $dir;
        }
        return null;
    }
}
