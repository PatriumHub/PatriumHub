<?php

declare(strict_types=1);

/**
 * Dashboard de cuentas tipo Mercado Pago (manuales, sin sync API).
 */
final class MercadoPagoDashboardService
{
    /**
     * @return array{
     *   currency:string,
     *   available:float,
     *   pending:float,
     *   income:float,
     *   expense:float,
     *   fees:float,
     *   accounts:list<array>,
     *   movements:list<array>,
     *   alerts:list<string>,
     *   connections:int
     * }
     */
    public static function summarize(?int $entityId, ?int $accountId, string $currency, ?string $from, ?string $to): array
    {
        $accounts = self::accounts($entityId, $accountId, $currency);
        $available = 0.0;
        $pending = 0.0;

        foreach ($accounts as &$acc) {
            $available += (float) $acc['balance'];
            $pending += (float) $acc['balance_held'];
            $flow = self::flowForAccounts([(int) $acc['id']], $currency, $from, $to);
            $acc['income'] = $flow['income'];
            $acc['expense'] = $flow['expense'];
            $acc['fees'] = $flow['fees'];
        }
        unset($acc);

        $accountIds = array_map(static fn ($a) => (int) $a['id'], $accounts);
        $totals = self::flowForAccounts($accountIds, $currency, $from, $to);

        return [
            'currency' => $currency,
            'available' => $available,
            'pending' => $pending,
            'income' => $totals['income'],
            'expense' => $totals['expense'],
            'fees' => $totals['fees'],
            'accounts' => $accounts,
            'movements' => self::recentMovements($accountIds, $from, $to, 25),
            'alerts' => [],
            'connections' => count($accounts),
        ];
    }

    /** @return list<array<string,mixed>> */
    private static function accounts(?int $entityId, ?int $accountId, string $currency): array
    {
        $sql = "SELECT a.*, e.name AS entity_name
                FROM accounts a
                INNER JOIN entities e ON e.id = a.entity_id
                WHERE a.type = 'mercadopago'
                  AND a.status = 'active'
                  AND a.currency_code = :cur";
        $params = ['cur' => $currency];
        if ($entityId) {
            $sql .= ' AND a.entity_id = :e';
            $params['e'] = $entityId;
        }
        if ($accountId) {
            $sql .= ' AND a.id = :aid';
            $params['aid'] = $accountId;
        }
        $sql .= ' ORDER BY e.name, a.name, a.id';
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll() ?: [];
    }

    /**
     * @param list<int> $accountIds
     * @return array{income:float,expense:float,fees:float}
     */
    private static function flowForAccounts(array $accountIds, string $currency, ?string $from, ?string $to): array
    {
        if ($accountIds === []) {
            return ['income' => 0.0, 'expense' => 0.0, 'fees' => 0.0];
        }

        $in = [];
        $params = ['cur' => $currency];
        foreach ($accountIds as $i => $id) {
            $key = 'a' . $i;
            $in[] = ':' . $key;
            $params[$key] = $id;
        }
        $placeholders = implode(',', $in);

        $sql = "SELECT
                    COALESCE(SUM(CASE
                        WHEN type = 'income'
                         AND IFNULL(description,'') NOT LIKE 'MP comisión%'
                         AND IFNULL(JSON_UNQUOTE(JSON_EXTRACT(metadata_json, '$.is_wallet_funding')), '0') <> '1'
                         AND IFNULL(JSON_UNQUOTE(JSON_EXTRACT(metadata_json, '$.operation_type')), '') NOT IN ('account_fund','money_transfer')
                        THEN amount ELSE 0 END),0) AS income,
                    COALESCE(SUM(CASE
                        WHEN type = 'expense' AND IFNULL(description,'') NOT LIKE 'MP comisión%' THEN amount ELSE 0 END),0) AS expense,
                    COALESCE(SUM(CASE
                        WHEN type = 'expense' AND description LIKE 'MP comisión%' THEN amount ELSE 0 END),0) AS fees
                FROM transactions
                WHERE account_id IN ($placeholders)
                  AND currency_code = :cur";

        if ($from) {
            $sql .= ' AND occurred_at >= :from';
            $params['from'] = $from . ' 00:00:00';
        }
        if ($to) {
            $sql .= ' AND occurred_at <= :to';
            $params['to'] = $to . ' 23:59:59';
        }

        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch() ?: [];
        return [
            'income' => (float) ($row['income'] ?? 0),
            'expense' => (float) ($row['expense'] ?? 0),
            'fees' => (float) ($row['fees'] ?? 0),
        ];
    }

    /**
     * @param list<int> $accountIds
     * @return list<array<string,mixed>>
     */
    private static function recentMovements(array $accountIds, ?string $from, ?string $to, int $limit): array
    {
        if ($accountIds === []) {
            return [];
        }
        $inFrom = [];
        $inTo = [];
        $params = [];
        foreach ($accountIds as $i => $id) {
            $kFrom = 'af' . $i;
            $kTo = 'at' . $i;
            $inFrom[] = ':' . $kFrom;
            $inTo[] = ':' . $kTo;
            $params[$kFrom] = $id;
            $params[$kTo] = $id;
        }
        $phFrom = implode(',', $inFrom);
        $phTo = implode(',', $inTo);
        $sql = "SELECT t.*, e.name AS entity_name,
                       COALESCE(a.name, dest.name) AS account_name
                FROM transactions t
                INNER JOIN entities e ON e.id = t.entity_id
                LEFT JOIN accounts a ON a.id = t.account_id
                LEFT JOIN accounts dest ON dest.id = t.counterparty_account_id
                WHERE (
                    t.account_id IN ($phFrom)
                    OR (t.type = 'transfer' AND t.counterparty_account_id IN ($phTo))
                )";
        if ($from) {
            $sql .= ' AND t.occurred_at >= :from';
            $params['from'] = $from . ' 00:00:00';
        }
        if ($to) {
            $sql .= ' AND t.occurred_at <= :to';
            $params['to'] = $to . ' 23:59:59';
        }
        $sql .= ' ORDER BY t.occurred_at DESC, t.id DESC LIMIT ' . (int) $limit;
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
