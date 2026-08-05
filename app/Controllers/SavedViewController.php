<?php

declare(strict_types=1);

final class SavedViewController
{
    public static function store(): void
    {
        Auth::requireLogin();
        require_csrf();
        $user = Auth::user();
        $name = post_string('name');
        if ($name === '') {
            flash('error', 'Nombre de vista obligatorio.');
            redirect('/dashboard');
        }

        $filters = [
            'mode' => post_string('mode', 'consolidated') ?: 'consolidated',
            'entity_id' => request_int('entity_id'),
            'currency' => post_string('currency', 'ARS') ?: 'ARS',
            'from' => post_string('from') ?: null,
            'to' => post_string('to') ?: null,
        ];

        SavedViewService::save((int) $user['id'], $name, 'dashboard', $filters);
        AuditService::log('create', 'saved_view', null, 'Vista: ' . $name);
        flash('success', 'Vista guardada.');
        redirect(self::dashboardUrl($filters));
    }

    public static function destroy(string $id): void
    {
        Auth::requireLogin();
        require_csrf();
        $user = Auth::user();
        SavedViewService::delete((int) $id, (int) $user['id']);
        flash('success', 'Vista eliminada.');
        redirect('/dashboard');
    }

    /** @param array<string,mixed> $filters */
    private static function dashboardUrl(array $filters): string
    {
        $q = array_filter([
            'mode' => $filters['mode'] ?? null,
            'entity_id' => $filters['entity_id'] ?? null,
            'currency' => $filters['currency'] ?? null,
            'from' => $filters['from'] ?? null,
            'to' => $filters['to'] ?? null,
        ], static fn ($v) => $v !== null && $v !== '');
        return '/dashboard' . ($q ? ('?' . http_build_query($q)) : '');
    }
}
