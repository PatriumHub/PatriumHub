<?php

declare(strict_types=1);

final class SettingsService
{
    public static function get(string $key, ?string $default = null): ?string
    {
        $stmt = Database::pdo()->prepare(
            'SELECT setting_value FROM settings WHERE setting_key = :k LIMIT 1'
        );
        $stmt->execute(['k' => $key]);
        $value = $stmt->fetchColumn();
        if ($value === false || $value === null) {
            return $default;
        }
        return (string) $value;
    }

    public static function set(string $key, string $value): void
    {
        Database::pdo()->prepare(
            'INSERT INTO settings (setting_key, setting_value) VALUES (:k, :v)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
        )->execute(['k' => $key, 'v' => $value]);
    }

    public static function hideAmounts(): bool
    {
        if (isset($_SESSION['ui.hide_amounts'])) {
            return (bool) $_SESSION['ui.hide_amounts'];
        }
        $stored = self::get('ui.hide_amounts', '0');
        $flag = $stored === '1' || strtolower((string) $stored) === 'true';
        $_SESSION['ui.hide_amounts'] = $flag;
        return $flag;
    }

    public static function setHideAmounts(bool $hide): void
    {
        self::set('ui.hide_amounts', $hide ? '1' : '0');
        $_SESSION['ui.hide_amounts'] = $hide;
    }

    public static function toggleHideAmounts(): bool
    {
        $next = !self::hideAmounts();
        self::setHideAmounts($next);
        return $next;
    }
}
