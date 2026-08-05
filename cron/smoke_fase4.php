<?php

declare(strict_types=1);

/**
 * Smoke Fase 4: anti-doble conteo y neteo interno.
 * Uso: php cron/smoke_fase4.php
 */

require dirname(__DIR__) . '/app/bootstrap.php';

$pdo = Database::pdo();
$pdo->beginTransaction();

try {
    $mark = 'smoke_f4_' . bin2hex(random_bytes(3));

    $pdo->prepare(
        "INSERT INTO entities (type, name, is_active) VALUES ('person', :n, 1)"
    )->execute(['n' => $mark . '_person']);
    $personId = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO people (entity_id) VALUES (:id)')->execute(['id' => $personId]);

    $pdo->prepare(
        "INSERT INTO entities (type, name, is_active) VALUES ('company', :n, 1)"
    )->execute(['n' => $mark . '_co']);
    $companyId = (int) $pdo->lastInsertId();
    $pdo->prepare(
        'INSERT INTO companies (entity_id, legal_name) VALUES (:id, :legal)'
    )->execute(['id' => $companyId, 'legal' => $mark]);

    // Persona: cuenta 1000
    $pdo->prepare(
        "INSERT INTO accounts (entity_id, name, type, currency_code, balance, include_in_net_worth, status)
         VALUES (:e, 'cash', 'cash', 'ARS', 1000, 1, 'active')"
    )->execute(['e' => $personId]);

    // Empresa: cuenta 50000 + pasivo 10000 ⇒ neto 40000
    $pdo->prepare(
        "INSERT INTO accounts (entity_id, name, type, currency_code, balance, include_in_net_worth, status)
         VALUES (:e, 'bank', 'bank', 'ARS', 50000, 1, 'active')"
    )->execute(['e' => $companyId]);
    $pdo->prepare(
        "INSERT INTO liabilities
         (entity_id, name, liability_type, currency_code, original_amount, outstanding_amount, status, include_in_net_worth)
         VALUES (:e, 'loan', 'loan', 'ARS', 10000, 10000, 'open', 1)"
    )->execute(['e' => $companyId]);

    // 100% ownership + valuación 80000
    $pdo->prepare(
        'INSERT INTO ownerships (owner_entity_id, company_entity_id, ownership_pct) VALUES (:o, :c, 100)'
    )->execute(['o' => $personId, 'c' => $companyId]);
    $pdo->prepare(
        "INSERT INTO business_valuations (company_entity_id, valued_at, currency_code, total_value, method)
         VALUES (:c, CURDATE(), 'ARS', 80000, 'smoke')"
    )->execute(['c' => $companyId]);

    // Deuda interna: empresa debe a persona 5000 (cobrable en persona, pasivo en empresa)
    $pdo->prepare(
        "INSERT INTO receivables
         (entity_id, debtor_entity_id, debtor_name, currency_code, original_amount, outstanding_amount, status, include_in_net_worth)
         VALUES (:e, :d, 'co', 'ARS', 5000, 5000, 'current', 1)"
    )->execute(['e' => $personId, 'd' => $companyId]);
    $pdo->prepare(
        "INSERT INTO liabilities
         (entity_id, creditor_entity_id, name, liability_type, currency_code, original_amount, outstanding_amount, status, include_in_net_worth)
         VALUES (:e, :c, 'debt to person', 'internal', 'ARS', 5000, 5000, 'open', 1)"
    )->execute(['e' => $companyId, 'c' => $personId]);

    $personal = PatrimonioService::summarize([$personId], 'ARS', 'personal');
    $consolidated = PatrimonioService::summarize([$personId, $companyId], 'ARS', 'consolidated');
    $naive = PatrimonioService::summarize([$personId, $companyId], 'ARS', 'entity');

    $expectedPersonal = 1000 + 5000 + 80000; // cash + receivable + 100% valuation
    // liabilities of person = 0 → net = 86000
    $okPersonal = abs($personal['net_worth'] - $expectedPersonal) < 0.01
        && abs($personal['participations'] - 80000) < 0.01;

    // Consolidado: no suma participaciones; netea 5000 internos
    // accounts 51000 + receivables (5000-5000=0) + liabilities (15000-5000=10000) ⇒ assets 51000, net 41000
    $okConsol = abs($consolidated['participations']) < 0.01
        && $consolidated['internal_netting'] >= 4999.99
        && abs($consolidated['net_worth'] - 41000) < 0.01;

    // Anti-doble: personal usa valuación (no suma internos de empresa).
    // Consolidado no suma participaciones y reporta neteo interno (el neto puede coincidir con suma bruta
    // porque el neteo resta el mismo monto de cobrables y pasivos).
    $okDistinct = abs($personal['net_worth'] - $consolidated['net_worth']) > 1
        && ($personal['participations'] ?? 0) > 0
        && abs($consolidated['participations'] ?? 0) < 0.01
        && ($consolidated['internal_netting'] ?? 0) > 0
        && abs($naive['internal_netting'] ?? 0) < 0.01;

    echo "Personal net={$personal['net_worth']} part={$personal['participations']} expected={$expectedPersonal} " . ($okPersonal ? 'OK' : 'FAIL') . "\n";
    echo "Consolidated net={$consolidated['net_worth']} netting={$consolidated['internal_netting']} expected=41000 " . ($okConsol ? 'OK' : 'FAIL') . "\n";
    echo "Modes distinct (personal≠consol, part/netting flags)=" . ($okDistinct ? 'OK' : 'FAIL') . "\n";

    // Captura puntual (sin captureDaily: evita mezclar con datos reales en la misma TX)
    $snapId = SnapshotService::capture('personal', $personal, $personId);
    echo "Snapshot id={$snapId}\n";

    $tables = $pdo->query("SHOW TABLES LIKE 'net_worth_snapshots'")->fetchAll();
    $views = $pdo->query("SHOW TABLES LIKE 'saved_views'")->fetchAll();
    echo 'Tables: snapshots=' . (count($tables) ? 'OK' : 'FAIL') . ' saved_views=' . (count($views) ? 'OK' : 'FAIL') . "\n";

    $pdo->rollBack();
    echo ($okPersonal && $okConsol && $okDistinct && $tables && $views && $snapId > 0) ? "SMOKE PASS\n" : "SMOKE FAIL\n";
    exit(($okPersonal && $okConsol && $okDistinct && $tables && $views && $snapId > 0) ? 0 : 1);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, 'ERROR: ' . $e->getMessage() . "\n");
    exit(1);
}
