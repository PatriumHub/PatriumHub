<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    Auth::startSession((string) app_config('session_name', 'patriumhub_session'));
}
$_SESSION['user'] = ['id' => 1, 'name' => 'smoke', 'email' => 'smoke@local', 'role' => 'admin'];

SettingsService::setHideAmounts(true);
$hidden = money(1234.5, 'ARS');
SettingsService::setHideAmounts(false);
$shown = money(1234.5, 'ARS');

$schema = SettingsService::get('schema.version');
$demo = (int) Database::pdo()
    ->query("SELECT COUNT(*) FROM entities WHERE name LIKE 'Demo %'")
    ->fetchColumn();

$okHide = str_contains($hidden, '••••');
$okShow = str_contains($shown, '1.234,50');
$okSchema = $schema === '0.5.0';
$okDemo = $demo >= 2;

echo "hide={$hidden} " . ($okHide ? 'OK' : 'FAIL') . "\n";
echo "show={$shown} " . ($okShow ? 'OK' : 'FAIL') . "\n";
echo "schema={$schema} " . ($okSchema ? 'OK' : 'FAIL') . "\n";
echo "demo_entities={$demo} " . ($okDemo ? 'OK' : 'FAIL') . "\n";
echo ($okHide && $okShow && $okSchema && $okDemo) ? "SMOKE PASS\n" : "SMOKE FAIL\n";
exit(($okHide && $okShow && $okSchema && $okDemo) ? 0 : 1);
