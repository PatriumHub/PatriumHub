<?php

declare(strict_types=1);

final class FinancialGoalsController
{
    public static function index(): void
    {
        Auth::requireAdminArea();
        FinancialGoalsService::ensureSchema();

        $currency = strtoupper(trim((string) ($_GET['currency'] ?? 'ARS')));
        if ($currency === '') {
            $currency = 'ARS';
        }

        $data = FinancialGoalsService::dashboard($currency);

        view('objetivos/index', [
            'title' => 'Objetivos fundamentales',
            'currency' => $currency,
            'currencies' => Catalog::currencies(),
            'ramsey' => $data['ramsey'],
            'custom' => $data['custom'],
            'context' => $data['context'],
            'milestonesDone' => (int) ($data['milestones_done'] ?? 0),
            'milestonesTotal' => (int) ($data['milestones_total'] ?? 0),
            'customDone' => (int) ($data['custom_done'] ?? 0),
            'customTotal' => (int) ($data['custom_total'] ?? 0),
            'canWrite' => Auth::canWrite(),
            'success' => flash('success'),
            'error' => flash('error'),
        ]);
    }

    public static function create(): void
    {
        Auth::requireAdminArea();
        Auth::requireWrite();

        $currency = strtoupper(trim((string) ($_GET['currency'] ?? 'ARS')));
        if ($currency === '') {
            $currency = 'ARS';
        }

        view('objetivos/form', [
            'title' => 'Nuevo objetivo',
            'currency' => $currency,
            'currencies' => Catalog::currencies(),
            'error' => flash('error'),
        ]);
    }

    public static function updateEmergency(): void
    {
        Auth::requireAdminArea();
        Auth::requireWrite();
        require_csrf();

        $currency = strtoupper(trim(post_string('currency_code', 'ARS')));
        $result = FinancialGoalsService::updateEmergencyFund(post_float('current_amount'));
        if (!$result['ok']) {
            flash('error', (string) ($result['error'] ?? 'No se pudo actualizar el fondo.'));
            redirect('/objetivos?currency=' . urlencode($currency));
        }

        flash('success', 'Fondo de emergencia actualizado.');
        redirect('/objetivos?currency=' . urlencode($currency));
    }

    public static function updateMs03(): void
    {
        Auth::requireAdminArea();
        Auth::requireWrite();
        require_csrf();

        $currency = strtoupper(trim(post_string('currency_code', 'ARS')));
        $year = post_string('plan_year', (string) date('Y'));
        $result = FinancialGoalsService::updateMs03Saved(post_float('current_amount'), $year, $currency);
        if (!$result['ok']) {
            flash('error', (string) ($result['error'] ?? 'No se pudo actualizar el ahorro.'));
            redirect('/objetivos?currency=' . urlencode($currency));
        }

        flash('success', 'Ahorro del Milestone 03 actualizado.');
        redirect('/objetivos?currency=' . urlencode($currency));
    }

    public static function store(): void
    {
        Auth::requireAdminArea();
        Auth::requireWrite();
        require_csrf();

        $currency = strtoupper(trim(post_string('currency_code', 'ARS')));
        $result = FinancialGoalsService::create([
            'name' => post_string('name'),
            'target_amount' => post_float('target_amount'),
            'current_amount' => post_float('current_amount'),
            'currency_code' => $currency,
            'starts_on' => post_string('starts_on'),
            'ends_on' => post_string('ends_on'),
            'notes' => post_string('notes'),
        ]);

        if (!$result['ok']) {
            flash('error', (string) ($result['error'] ?? 'No se pudo crear el objetivo.'));
            redirect('/objetivos/nuevo?currency=' . urlencode($currency));
        }

        flash('success', 'Objetivo creado.');
        redirect('/objetivos?currency=' . urlencode($currency));
    }

    public static function updateProgress(string $id): void
    {
        Auth::requireAdminArea();
        Auth::requireWrite();
        require_csrf();

        $currency = strtoupper(trim(post_string('currency_code', 'ARS')));
        $result = FinancialGoalsService::updateProgress((int) $id, post_float('current_amount'));
        if (!$result['ok']) {
            flash('error', (string) ($result['error'] ?? 'No se pudo actualizar.'));
            redirect('/objetivos?currency=' . urlencode($currency));
        }

        flash('success', 'Progreso actualizado.');
        redirect('/objetivos?currency=' . urlencode($currency));
    }

    public static function destroy(string $id): void
    {
        Auth::requireAdminArea();
        Auth::requireWrite();
        require_csrf();

        $currency = strtoupper(trim(post_string('currency_code', 'ARS')));
        $result = FinancialGoalsService::destroy((int) $id);
        if (!$result['ok']) {
            flash('error', (string) ($result['error'] ?? 'No se pudo eliminar.'));
            redirect('/objetivos?currency=' . urlencode($currency));
        }

        flash('success', 'Objetivo eliminado.');
        redirect('/objetivos?currency=' . urlencode($currency));
    }
}
