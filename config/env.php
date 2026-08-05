<?php

declare(strict_types=1);

/**
 * Carga .env del root de la app (sin abrir MySQL).
 */

if (!function_exists('patrium_load_env')) {
    function patrium_load_env(string $file = '.env'): bool
    {
        $envPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . $file;
        if (!is_file($envPath)) {
            return false;
        }

        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return false;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (!str_contains($line, '=')) {
                continue;
            }
            [$name, $value] = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);
            if (
                (str_starts_with($value, '"') && str_ends_with($value, '"'))
                || (str_starts_with($value, "'") && str_ends_with($value, "'"))
            ) {
                $value = substr($value, 1, -1);
            }
            $_ENV[$name] = $value;
            putenv($name . '=' . $value);
        }

        return true;
    }
}

if (!function_exists('patrium_env')) {
    function patrium_env(string $key, ?string $default = null): ?string
    {
        if (array_key_exists($key, $_ENV)) {
            return (string) $_ENV[$key];
        }
        $fromGetenv = getenv($key);
        if ($fromGetenv !== false) {
            return (string) $fromGetenv;
        }
        return $default;
    }
}

patrium_load_env();
