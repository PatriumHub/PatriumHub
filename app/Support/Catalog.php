<?php

declare(strict_types=1);

final class Catalog
{
    /** @return list<array{code:string,name:string,symbol:string}> */
    public static function currencies(): array
    {
        return Database::pdo()
            ->query('SELECT code, name, symbol FROM currencies WHERE is_active = 1 ORDER BY code')
            ->fetchAll();
    }

    /**
     * Entidades activas visibles para el usuario logueado.
     * Admin: todas. Viewer: solo las asignadas en Configuración.
     *
     * @return list<array{id:int,name:string,type:string}>
     */
    public static function entities(?string $type = null): array
    {
        $rows = self::entitiesAll($type);
        $allowed = UserService::allowedEntityIds();
        if ($allowed === null) {
            return $rows;
        }
        if ($allowed === []) {
            return [];
        }
        $set = array_fill_keys($allowed, true);
        return array_values(array_filter(
            $rows,
            static fn (array $r): bool => isset($set[(int) $r['id']])
        ));
    }

    /**
     * Todas las entidades activas (sin filtro de permisos). Uso admin / asignación.
     *
     * @return list<array{id:int,name:string,type:string}>
     */
    public static function entitiesAll(?string $type = null): array
    {
        if ($type !== null) {
            $stmt = Database::pdo()->prepare(
                'SELECT id, name, type FROM entities WHERE is_active = 1 AND type = :t ORDER BY name'
            );
            $stmt->execute(['t' => $type]);
            return $stmt->fetchAll();
        }
        return Database::pdo()
            ->query('SELECT id, name, type FROM entities WHERE is_active = 1 ORDER BY type, name')
            ->fetchAll();
    }

    public static function entity(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM entities WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** @return list<array{id:int,name:string}> */
    public static function accountsForEntity(?int $entityId = null): array
    {
        if ($entityId) {
            $stmt = Database::pdo()->prepare(
                "SELECT id, name FROM accounts WHERE status = 'active' AND entity_id = :e ORDER BY name"
            );
            $stmt->execute(['e' => $entityId]);
            return $stmt->fetchAll();
        }
        return Database::pdo()
            ->query("SELECT id, name FROM accounts WHERE status = 'active' ORDER BY name")
            ->fetchAll();
    }

    /** @return list<array{id:int,name:string,kind:string}> */
    public static function transactionCategories(): array
    {
        return Database::pdo()
            ->query('SELECT id, name, kind FROM transaction_categories ORDER BY name')
            ->fetchAll();
    }
}
