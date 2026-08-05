<?php

declare(strict_types=1);

final class SyncOrchestrator
{
    /** @return array{id:int,provider:string,name:string,ok:bool,message:string,read?:int,upserted?:int} */
    public static function runOne(int $integrationId): array
    {
        $integration = IntegrationService::find($integrationId);
        if (!$integration) {
            return [
                'id' => $integrationId,
                'provider' => 'unknown',
                'name' => '',
                'ok' => false,
                'message' => 'No encontrada',
            ];
        }

        $result = match ($integration['provider']) {
            'woocommerce' => WooCommerceSyncService::sync($integrationId),
            default => [
                'ok' => false,
                'message' => 'Proveedor no soportado.',
                'read' => 0,
                'upserted' => 0,
            ],
        };

        return [
            'id' => $integrationId,
            'provider' => $integration['provider'],
            'name' => $integration['name'],
            'ok' => (bool) $result['ok'],
            'message' => (string) $result['message'],
            'read' => (int) ($result['read'] ?? 0),
            'upserted' => (int) ($result['upserted'] ?? 0),
        ];
    }

    /** Cron: WC + MP con sync_auto. */
    public static function runCron(): array
    {
        $rows = Database::pdo()->query(
            "SELECT id, provider, name, config_json, status FROM integrations
             WHERE status IN ('active', 'error')
             ORDER BY id"
        )->fetchAll();

        $results = [];
        foreach ($rows as $row) {
            $config = IntegrationService::config($row);
            if (($config['sync_auto'] ?? true) === false) {
                continue;
            }
            if ($row['provider'] !== 'woocommerce') {
                continue;
            }
            $results[] = self::runOne((int) $row['id']);
        }
        return $results;
    }
}
