<?php

declare(strict_types=1);

/**
 * Job de sincronización de integraciones activas (WooCommerce).
 *
 * Uso: php cron/sync.php
 * Cron sugerido: cada 30 min → php /ruta/PatriumHub/cron/sync.php
 */

require dirname(__DIR__) . '/app/bootstrap.php';

echo '[' . date('c') . "] PatriumHub sync\n";

if (!CryptoService::isConfigured()) {
    fwrite(STDERR, "Falta storage/app.key — generá la clave en Configuración del sistema.\n");
    exit(1);
}

$results = SyncOrchestrator::runCron();
echo 'Integraciones procesadas: ' . count($results) . "\n";

$failures = 0;
foreach ($results as $result) {
    $mark = $result['ok'] ? 'OK' : 'FAIL';
    if (!$result['ok']) {
        $failures++;
    }
    echo sprintf(
        "- [%s] #%d %s (%s) read=%d upsert=%d — %s\n",
        $mark,
        $result['id'],
        $result['name'],
        $result['provider'],
        $result['read'] ?? 0,
        $result['upserted'] ?? 0,
        $result['message']
    );
}

echo $failures > 0 ? "DONE with failures\n" : "DONE\n";
exit($failures > 0 ? 2 : 0);
