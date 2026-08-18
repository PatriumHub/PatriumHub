<?php

declare(strict_types=1);

final class HomeController
{
    public static function index(): void
    {
        Auth::requireAdminArea();

        $currency = $_GET['currency'] ?? 'ARS';
        $mode = 'consolidated';
        $summary = PatrimonioService::summarize(null, $currency, $mode);
        $pdo = Database::pdo();

        view('home/index', [
            'title' => 'Inicio',
            'summary' => $summary,
            'currency' => $currency,
            'mode' => $mode,
            'currencies' => Catalog::currencies(),
            'counts' => [
                'people' => (int) $pdo->query("SELECT COUNT(*) FROM entities WHERE type = 'person'")->fetchColumn(),
                'companies' => (int) $pdo->query("SELECT COUNT(*) FROM entities WHERE type = 'company'")->fetchColumn(),
                'accounts' => (int) $pdo->query("SELECT COUNT(*) FROM accounts WHERE status = 'active'")->fetchColumn(),
                'integrations' => (int) $pdo->query("SELECT COUNT(*) FROM integrations WHERE status <> 'revoked'")->fetchColumn(),
            ],
            'success' => flash('success'),
            'error' => flash('error'),
        ]);
    }
}
