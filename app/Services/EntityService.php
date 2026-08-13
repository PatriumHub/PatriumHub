<?php

declare(strict_types=1);

/**
 * Borrado de personas/empresas (cascada vía FKs de entities).
 */
final class EntityService
{
    /**
     * Conteos de dependencias para mostrar en la confirmación.
     *
     * @return array<string,int>
     */
    public static function dependencyCounts(int $entityId): array
    {
        $pdo = Database::pdo();
        $one = static function (string $sql, array $params) use ($pdo): int {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return (int) $stmt->fetchColumn();
        };

        return [
            'cuentas' => $one('SELECT COUNT(*) FROM accounts WHERE entity_id = ?', [$entityId]),
            'movimientos' => $one('SELECT COUNT(*) FROM transactions WHERE entity_id = ?', [$entityId]),
            'activos' => $one('SELECT COUNT(*) FROM assets WHERE entity_id = ?', [$entityId]),
            'pasivos' => $one('SELECT COUNT(*) FROM liabilities WHERE entity_id = ?', [$entityId]),
            'propiedades' => $one('SELECT COUNT(*) FROM properties WHERE entity_id = ?', [$entityId]),
            'cobrables' => $one('SELECT COUNT(*) FROM receivables WHERE entity_id = ?', [$entityId]),
            'inventario' => $one('SELECT COUNT(*) FROM inventories WHERE entity_id = ?', [$entityId]),
            'presupuestos' => $one('SELECT COUNT(*) FROM budget_templates WHERE entity_id = ?', [$entityId]),
            'integraciones' => $one('SELECT COUNT(*) FROM integrations WHERE entity_id = ?', [$entityId]),
            'participaciones' => $one(
                'SELECT COUNT(*) FROM ownerships WHERE owner_entity_id = ? OR company_entity_id = ?',
                [$entityId, $entityId]
            ),
            'estados_proyeccion' => $one(
                'SELECT COUNT(*) FROM company_financial_plans WHERE entity_id = ?',
                [$entityId]
            ),
        ];
    }

    /**
     * @return array{ok:bool,error?:string,name?:string}
     */
    public static function delete(int $entityId, string $expectedType): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT id, type, name FROM entities WHERE id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $entityId]);
        $row = $stmt->fetch();
        if (!$row) {
            return ['ok' => false, 'error' => 'Entidad no encontrada.'];
        }
        if (($row['type'] ?? '') !== $expectedType) {
            return ['ok' => false, 'error' => 'Tipo de entidad incorrecto.'];
        }

        try {
            Database::pdo()->prepare('DELETE FROM entities WHERE id = :id')->execute(['id' => $entityId]);
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }

        return ['ok' => true, 'name' => (string) $row['name']];
    }

    /** @param array<string,int> $counts */
    public static function confirmMessage(string $name, array $counts): string
    {
        $parts = [];
        foreach ($counts as $label => $n) {
            if ($n > 0) {
                $parts[] = str_replace('_', ' ', $label) . ': ' . $n;
            }
        }
        $extra = $parts !== [] ? ' También se borran — ' . implode(', ', $parts) . '.' : '';
        return '¿Eliminar permanentemente «' . $name . '»?' . $extra . ' No se puede deshacer.';
    }
}
