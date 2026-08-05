<?php

declare(strict_types=1);

final class SettingsController
{
    public static function index(): void
    {
        Auth::requireLogin();
        view('settings/index', [
            'title' => 'Configuración',
            'cryptoOk' => CryptoService::isConfigured(),
            'fingerprint' => CryptoService::fingerprint(),
            'keyPath' => 'storage/app.key',
            'hideAmounts' => SettingsService::hideAmounts(),
            'success' => flash('success'),
            'error' => flash('error'),
        ]);
    }

    public static function generateKey(): void
    {
        Auth::requireLogin();
        require_csrf();
        try {
            if (CryptoService::isConfigured() && empty($_POST['confirm_rotate'])) {
                flash('error', 'Ya hay una clave. Marcá “confirmar rotación” solo si entendés que los secretos previos dejarán de descifrarse.');
                redirect('/configuracion');
            }
            CryptoService::generateAndSave();
            AuditService::log('generate_key', 'settings', null, 'Clave de cifrado generada/rotada desde Configuración');
            flash('success', 'Clave de cifrado lista. Ahora podés cargar WooCommerce desde Integraciones.');
        } catch (Throwable $e) {
            flash('error', $e->getMessage());
        }
        redirect('/configuracion');
    }

    public static function toggleHideAmounts(): void
    {
        Auth::requireLogin();
        require_csrf();
        $next = SettingsService::toggleHideAmounts();
        AuditService::log('update', 'settings', null, $next ? 'Ocultar cifras: on' : 'Ocultar cifras: off');
        $ref = (string) ($_SERVER['HTTP_REFERER'] ?? '');
        $appUrl = (string) app_config('url', '');
        if ($appUrl !== '' && $ref !== '' && str_starts_with($ref, $appUrl)) {
            header('Location: ' . $ref);
            exit;
        }
        flash('success', $next ? 'Cifras ocultas.' : 'Cifras visibles.');
        redirect('/configuracion');
    }
}
