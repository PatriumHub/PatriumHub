<?php

declare(strict_types=1);

/**
 * Cifrado AES-256-GCM para credentials de integraciones (WC / MP).
 * La clave vive en storage/app.key y se genera desde Configuración en el sistema.
 * Los tokens de WooCommerce NUNCA van en .env.
 */
final class CryptoService
{
    private const PREFIX = 'v1:';

    public static function keyPath(): string
    {
        return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'app.key';
    }

    public static function encrypt(string $plaintext): string
    {
        $key = self::key();
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($cipher === false) {
            throw new RuntimeException('No se pudo cifrar el secreto.');
        }
        return self::PREFIX . base64_encode($iv . $tag . $cipher);
    }

    public static function decrypt(string $payload): string
    {
        if (!str_starts_with($payload, self::PREFIX)) {
            throw new RuntimeException('Formato de secreto cifrado inválido.');
        }
        $raw = base64_decode(substr($payload, strlen(self::PREFIX)), true);
        if ($raw === false || strlen($raw) < 28) {
            throw new RuntimeException('Secreto cifrado corrupto.');
        }
        $iv = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $cipher = substr($raw, 28);
        $plain = openssl_decrypt($cipher, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag);
        if ($plain === false) {
            throw new RuntimeException('No se pudo descifrar. Regeneraste la clave de cifrado en Configuración?');
        }
        return $plain;
    }

    public static function isConfigured(): bool
    {
        $path = self::keyPath();
        if (is_file($path) && filesize($path) > 16) {
            return true;
        }
        // Compat: si ya existía APP_KEY en .env de Fase 0/2, migrar a archivo
        $legacy = trim((string) app_config('key', ''));
        if ($legacy !== '' && $legacy !== 'cambiar-generar-openssl-rand-base64-32') {
            self::saveRawKey($legacy);
            return true;
        }
        return false;
    }

    public static function generateAndSave(): string
    {
        $raw = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        self::saveRawKey($raw);
        return $raw;
    }

    public static function saveRawKey(string $raw): void
    {
        $raw = trim($raw);
        if ($raw === '') {
            throw new RuntimeException('Clave vacía.');
        }
        $dir = dirname(self::keyPath());
        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
            throw new RuntimeException('No se pudo crear storage/.');
        }
        if (file_put_contents(self::keyPath(), $raw . PHP_EOL, LOCK_EX) === false) {
            throw new RuntimeException('No se pudo escribir storage/app.key.');
        }
        @chmod(self::keyPath(), 0640);
    }

    public static function fingerprint(): ?string
    {
        if (!self::isConfigured()) {
            return null;
        }
        return substr(hash('sha256', self::rawSecret()), 0, 12);
    }

    private static function key(): string
    {
        return hash('sha256', self::rawSecret(), true);
    }

    private static function rawSecret(): string
    {
        $path = self::keyPath();
        if (is_file($path)) {
            $raw = trim((string) file_get_contents($path));
            if ($raw !== '') {
                return $raw;
            }
        }
        $legacy = trim((string) app_config('key', ''));
        if ($legacy !== '' && $legacy !== 'cambiar-generar-openssl-rand-base64-32') {
            self::saveRawKey($legacy);
            return $legacy;
        }
        throw new RuntimeException(
            'Falta la clave de cifrado. Generala en Configuración del sistema (no se usa .env para tokens WC/MP).'
        );
    }
}
