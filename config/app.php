<?php

declare(strict_types=1);

require_once __DIR__ . '/env.php';

return [
    'name' => patrium_env('APP_NAME', 'PatriumHub'),
    'env' => strtolower((string) patrium_env('APP_ENV', 'local')),
    'debug' => filter_var(patrium_env('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOLEAN),
    'url' => rtrim((string) patrium_env('APP_URL', ''), '/'),
    // Legacy opcional: la clave de cifrado real vive en storage/app.key (Configuración).
    'key' => (string) patrium_env('APP_KEY', ''),
    'session_name' => (string) patrium_env('SESSION_NAME', 'patriumhub_session'),
    // Cookie + idle (seconds). Por defecto 24 h: la ventana se renueva en cada visita;
    // si no vuelve dentro de ese lapso, la sesión se cierra.
    'session_lifetime' => (int) patrium_env('SESSION_LIFETIME', '86400'),
    'session_idle' => (int) patrium_env('SESSION_IDLE', '86400'),
    'timezone' => 'America/Argentina/Buenos_Aires',
];
