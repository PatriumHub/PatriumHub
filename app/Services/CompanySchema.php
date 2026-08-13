<?php

declare(strict_types=1);

/** Columnas extra de companies (business_model). */
final class CompanySchema
{
    private static bool $ready = false;

    public static function ensure(): void
    {
        if (self::$ready) {
            return;
        }
        $pdo = Database::pdo();
        $cols = $pdo->query('SHOW COLUMNS FROM companies LIKE \'business_model\'')->fetch();
        if (!$cols) {
            $pdo->exec(
                "ALTER TABLE companies
                 ADD COLUMN business_model ENUM('services','products') NOT NULL DEFAULT 'services'
                 AFTER industry"
            );
            // Productos típicos ya cargados
            $pdo->exec(
                "UPDATE companies c
                 INNER JOIN entities e ON e.id = c.entity_id
                 SET c.business_model = 'products'
                 WHERE LOWER(e.name) LIKE '%homespot%'
                    OR LOWER(e.name) LIKE '%puestito%'"
            );
        }
        self::$ready = true;
    }

    public static function normalizeModel(?string $raw): string
    {
        $v = mb_strtolower(trim((string) $raw));
        return in_array($v, ['products', 'productos', 'producto'], true) ? 'products' : 'services';
    }
}
