<?php

declare(strict_types=1);

/**
 * Captura diaria de patrimonio (personal, consolidado y por entidad).
 *
 * Uso: php cron/snapshots.php
 * Cron sugerido: 1×/día → php /ruta/PatriumHub/cron/snapshots.php
 */

require dirname(__DIR__) . '/app/bootstrap.php';

echo '[' . date('c') . "] PatriumHub snapshots\n";

$currency = $argv[1] ?? 'ARS';
$ids = SnapshotService::captureDaily($currency);
echo 'Snapshots creados: ' . count($ids) . ' (currency=' . $currency . ")\n";
foreach ($ids as $id) {
    echo "- #$id\n";
}
echo "DONE\n";
