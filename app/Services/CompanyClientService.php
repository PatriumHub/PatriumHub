<?php

declare(strict_types=1);

/**
 * Fichas de cliente de empresas de servicios.
 * Lista/montos desde Estados y proyección; contacto, estado y archivos acá.
 */
final class CompanyClientService
{
    private static bool $ready = false;

    public static function ensureSchema(): void
    {
        if (self::$ready) {
            return;
        }
        Database::pdo()->exec(
            'CREATE TABLE IF NOT EXISTS company_clients (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                entity_id INT UNSIGNED NOT NULL,
                name VARCHAR(180) NOT NULL,
                contact_name VARCHAR(180) NULL,
                email VARCHAR(180) NULL,
                phone VARCHAR(60) NULL,
                contract_notes TEXT NULL,
                value_annual DECIMAL(18,2) NOT NULL DEFAULT 0.00,
                value_total DECIMAL(18,2) NOT NULL DEFAULT 0.00,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_cc_entity (entity_id),
                KEY idx_cc_name (entity_id, name),
                CONSTRAINT fk_cc_entity FOREIGN KEY (entity_id) REFERENCES entities(id) ON DELETE CASCADE
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        self::$ready = true;
    }

    /**
     * @return array{
     *   rows:list<array<string,mixed>>,
     *   sort:string,
     *   dir:string,
     *   stats:array{active:int,inactive:int,total_all:int}
     * }
     */
    public static function listForUi(
        int $entityId,
        string $sort = 'name',
        string $dir = 'asc'
    ): array {
        self::ensureSchema();
        $plan = FinancialPlanService::ensureForCompany($entityId);
        $workbook = is_array($plan['workbook'] ?? null) ? $plan['workbook'] : [];
        self::syncFromProjection($entityId, $workbook);

        $sort = in_array($sort, ['name', 'contact', 'total', 'status'], true) ? $sort : 'name';
        $dir = strtolower($dir) === 'desc' ? 'desc' : 'asc';

        $rows = self::mergedList($entityId, $workbook);
        $totalAll = count($rows);

        $list = [];
        $active = 0;
        $inactive = 0;
        foreach ($rows as $row) {
            $row['is_active'] = (int) ($row['is_active'] ?? 1) === 1 ? 1 : 0;
            if ($row['is_active'] === 1) {
                $active++;
            } else {
                $inactive++;
            }
            $list[] = $row;
        }

        usort($list, static function (array $a, array $b) use ($sort, $dir): int {
            $cmp = match ($sort) {
                'contact' => strcasecmp((string) ($a['contact_name'] ?? ''), (string) ($b['contact_name'] ?? '')),
                'total' => ((float) ($a['value_total'] ?? 0)) <=> ((float) ($b['value_total'] ?? 0)),
                'status' => ((int) ($b['is_active'] ?? 0)) <=> ((int) ($a['is_active'] ?? 0)),
                default => strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? '')),
            };
            if ($cmp === 0) {
                $cmp = strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
            }
            return $dir === 'desc' ? -$cmp : $cmp;
        });

        return [
            'rows' => $list,
            'sort' => $sort,
            'dir' => $dir,
            'stats' => [
                'active' => $active,
                'inactive' => $inactive,
                'total_all' => $totalAll,
            ],
        ];
    }

    /** @return list<array<string,mixed>> */
    public static function listFromProjection(int $entityId): array
    {
        return self::listForUi($entityId)['rows'];
    }

    /**
     * @param array<string,mixed> $workbook
     * @return list<string>
     */
    public static function yearOptionsFromWorkbook(array $workbook): array
    {
        $years = [];
        foreach (($workbook['years'] ?? []) as $y) {
            if (!is_array($y)) {
                continue;
            }
            $name = trim((string) ($y['name'] ?? ''));
            if ($name !== '') {
                $years[$name] = true;
            }
        }
        $list = array_keys($years);
        natcasesort($list);
        return array_values($list);
    }

    /**
     * @param array<string,mixed> $workbook
     */
    public static function syncFromProjection(int $entityId, array $workbook): void
    {
        self::ensureSchema();
        $projected = FinancialPlanService::clientsFromWorkbook($workbook);
        if ($projected === []) {
            return;
        }

        $existing = self::forCompanyRaw($entityId);
        /** @var array<string,array<string,mixed>> $byKey */
        $byKey = [];
        foreach ($existing as $row) {
            $byKey[mb_strtolower(trim((string) $row['name']))] = $row;
        }

        $pdo = Database::pdo();
        foreach ($projected as $p) {
            $name = $p['name'];
            $key = mb_strtolower($name);
            $total = (float) $p['value_total'];
            if (isset($byKey[$key])) {
                $pdo->prepare(
                    'UPDATE company_clients
                     SET name = :name, value_total = :value_total, value_annual = :value_annual
                     WHERE id = :id AND entity_id = :e'
                )->execute([
                    'name' => $name,
                    'value_total' => $total,
                    'value_annual' => $total,
                    'id' => (int) $byKey[$key]['id'],
                    'e' => $entityId,
                ]);
            } else {
                $pdo->prepare(
                    'INSERT INTO company_clients
                     (entity_id, name, value_annual, value_total, is_active)
                     VALUES (:e, :name, :value_annual, :value_total, 1)'
                )->execute([
                    'e' => $entityId,
                    'name' => $name,
                    'value_annual' => $total,
                    'value_total' => $total,
                ]);
            }
        }
    }

    /**
     * @param array<string,mixed> $workbook
     * @return list<array<string,mixed>>
     */
    public static function mergedList(int $entityId, array $workbook): array
    {
        $projected = FinancialPlanService::clientsFromWorkbook($workbook);
        $fichas = self::forCompanyRaw($entityId);
        /** @var array<string,array<string,mixed>> $fichaByKey */
        $fichaByKey = [];
        foreach ($fichas as $row) {
            $fichaByKey[mb_strtolower(trim((string) $row['name']))] = $row;
        }

        $out = [];
        foreach ($projected as $p) {
            $key = mb_strtolower($p['name']);
            $ficha = $fichaByKey[$key] ?? null;
            $out[] = [
                'id' => $ficha ? (int) $ficha['id'] : 0,
                'entity_id' => $entityId,
                'name' => $p['name'],
                'contact_name' => $ficha['contact_name'] ?? null,
                'email' => $ficha['email'] ?? null,
                'phone' => $ficha['phone'] ?? null,
                'contract_notes' => $ficha['contract_notes'] ?? null,
                'value_total' => $p['value_total'],
                'years' => $p['years'],
                'is_active' => $ficha ? ((int) ($ficha['is_active'] ?? 1) === 1 ? 1 : 0) : 1,
                'from_projection' => true,
            ];
        }
        return $out;
    }

    public static function withProjectionTotals(int $entityId, array $client): array
    {
        $plan = FinancialPlanService::find($entityId) ?? FinancialPlanService::ensureForCompany($entityId);
        $workbook = $plan['workbook'] ?? [];
        $projected = FinancialPlanService::clientsFromWorkbook(is_array($workbook) ? $workbook : []);
        $key = mb_strtolower(trim((string) ($client['name'] ?? '')));
        foreach ($projected as $p) {
            if (mb_strtolower($p['name']) === $key) {
                $client['value_total'] = $p['value_total'];
                $client['years'] = $p['years'];
                $client['from_projection'] = true;
                $client['is_active'] = (int) ($client['is_active'] ?? 1) === 1 ? 1 : 0;
                return $client;
            }
        }
        $client['years'] = [];
        $client['from_projection'] = false;
        $client['is_active'] = (int) ($client['is_active'] ?? 1) === 1 ? 1 : 0;
        return $client;
    }

    /** @return list<array<string,mixed>> */
    public static function forCompany(int $entityId): array
    {
        return self::listFromProjection($entityId);
    }

    /** @return list<array<string,mixed>> */
    private static function forCompanyRaw(int $entityId): array
    {
        self::ensureSchema();
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM company_clients
             WHERE entity_id = :e
             ORDER BY name ASC'
        );
        $stmt->execute(['e' => $entityId]);
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        self::ensureSchema();
        $stmt = Database::pdo()->prepare('SELECT * FROM company_clients WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function findForCompany(int $entityId, int $clientId): ?array
    {
        $row = self::find($clientId);
        if (!$row || (int) $row['entity_id'] !== $entityId) {
            return null;
        }
        return self::withProjectionTotals($entityId, $row);
    }

    /** @return array{ok:bool,error?:string} */
    public static function updateContact(int $entityId, int $clientId, array $data): array
    {
        self::ensureSchema();
        $row = self::find($clientId);
        if (!$row || (int) $row['entity_id'] !== $entityId) {
            return ['ok' => false, 'error' => 'Cliente no encontrado.'];
        }
        $active = !empty($data['is_active']) ? 1 : 0;
        Database::pdo()->prepare(
            'UPDATE company_clients
             SET contact_name = :contact_name, email = :email, phone = :phone,
                 contract_notes = :contract_notes, is_active = :is_active
             WHERE id = :id AND entity_id = :entity_id'
        )->execute([
            'contact_name' => self::nullIfEmpty($data['contact_name'] ?? null),
            'email' => self::nullIfEmpty($data['email'] ?? null),
            'phone' => self::nullIfEmpty($data['phone'] ?? null),
            'contract_notes' => self::nullIfEmpty($data['contract_notes'] ?? null),
            'is_active' => $active,
            'id' => $clientId,
            'entity_id' => $entityId,
        ]);
        return ['ok' => true];
    }

    public static function create(int $entityId, array $data): array
    {
        return ['ok' => false, 'error' => 'Los clientes se agregan en Estados y proyección.'];
    }

    public static function update(int $entityId, int $clientId, array $data): array
    {
        return self::updateContact($entityId, $clientId, $data);
    }

    public static function clearContact(int $entityId, int $clientId): array
    {
        self::ensureSchema();
        $row = self::find($clientId);
        if (!$row || (int) $row['entity_id'] !== $entityId) {
            return ['ok' => false, 'error' => 'Cliente no encontrado.'];
        }
        Database::pdo()->prepare(
            'UPDATE company_clients
             SET contact_name = NULL, email = NULL, phone = NULL, contract_notes = NULL
             WHERE id = :id AND entity_id = :entity_id'
        )->execute(['id' => $clientId, 'entity_id' => $entityId]);
        return ['ok' => true];
    }

    public static function delete(int $entityId, int $clientId): array
    {
        return self::clearContact($entityId, $clientId);
    }

    private static function nullIfEmpty(mixed $v): ?string
    {
        $s = trim((string) ($v ?? ''));
        return $s === '' ? null : $s;
    }
}
