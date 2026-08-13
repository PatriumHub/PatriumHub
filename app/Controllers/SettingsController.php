<?php

declare(strict_types=1);

final class SettingsController
{
    public static function index(): void
    {
        Auth::requireLogin();
        UserService::ensureSchema();
        $tab = (string) ($_GET['tab'] ?? 'perfil');
        if (!in_array($tab, ['perfil', 'usuarios', 'sistema'], true)) {
            $tab = 'perfil';
        }
        if (!Auth::isAdmin() && $tab !== 'perfil') {
            // Solo lectura: perfil propio (nombre/clave). Sin usuarios ni sistema.
            $tab = 'perfil';
        }
        if ($tab === 'usuarios' && !Auth::isAdmin()) {
            flash('error', 'Solo un administrador puede gestionar usuarios.');
            redirect('/configuracion?tab=perfil');
        }

        $editUser = null;
        $editAccess = [];
        if ($tab === 'usuarios' && isset($_GET['edit'])) {
            $editUser = UserService::find((int) $_GET['edit']);
            if ($editUser) {
                $editAccess = UserService::entityIdsForUser((int) $editUser['id']);
            }
        }

        view('settings/index', [
            'title' => 'Configuración',
            'tab' => $tab,
            'cryptoOk' => CryptoService::isConfigured(),
            'fingerprint' => CryptoService::fingerprint(),
            'keyPath' => 'storage/app.key',
            'hideAmounts' => SettingsService::hideAmounts(),
            'me' => Auth::user(),
            'isAdmin' => Auth::isAdmin(),
            'users' => Auth::isAdmin() ? UserService::all() : [],
            'allEntities' => Auth::isAdmin() ? Catalog::entitiesAll() : [],
            'editUser' => $editUser,
            'editAccess' => $editAccess,
            'success' => flash('success'),
            'error' => flash('error'),
        ]);
    }

    public static function updateProfile(): void
    {
        Auth::requireLogin();
        require_csrf();
        $me = Auth::user();
        if (!$me) {
            redirect('/login');
        }
        $result = UserService::updateProfile(
            (int) $me['id'],
            post_string('name'),
            post_string('email')
        );
        if (!$result['ok']) {
            flash('error', $result['error'] ?? 'No se pudo actualizar.');
            redirect('/configuracion?tab=perfil');
        }
        AuditService::log('update', 'user', (int) $me['id'], 'Perfil actualizado');
        flash('success', 'Perfil actualizado.');
        redirect('/configuracion?tab=perfil');
    }

    public static function changePassword(): void
    {
        Auth::requireLogin();
        require_csrf();
        $me = Auth::user();
        if (!$me) {
            redirect('/login');
        }
        $result = UserService::changePassword(
            (int) $me['id'],
            post_string('current_password'),
            post_string('new_password'),
            post_string('confirm_password')
        );
        if (!$result['ok']) {
            flash('error', $result['error'] ?? 'No se pudo cambiar la contraseña.');
            redirect('/configuracion?tab=perfil');
        }
        AuditService::log('update', 'user', (int) $me['id'], 'Contraseña cambiada');
        flash('success', 'Contraseña actualizada.');
        redirect('/configuracion?tab=perfil');
    }

    public static function storeUser(): void
    {
        Auth::requireAdmin();
        require_csrf();
        $entityIds = self::postedEntityIds();
        $active = isset($_POST['is_active']) && (string) $_POST['is_active'] === '1';
        $result = UserService::create(
            post_string('name'),
            post_string('email'),
            post_string('password'),
            post_string('role', 'viewer'),
            $active,
            $entityIds
        );
        if (!$result['ok']) {
            flash('error', $result['error'] ?? 'No se pudo crear.');
            redirect('/configuracion?tab=usuarios');
        }
        AuditService::log('create', 'user', (int) ($result['id'] ?? 0), 'Usuario creado: ' . post_string('email'));
        flash('success', 'Usuario creado.');
        redirect('/configuracion?tab=usuarios');
    }

    public static function updateUser(string $id): void
    {
        Auth::requireAdmin();
        require_csrf();
        $userId = (int) $id;
        $active = isset($_POST['is_active']) && (string) $_POST['is_active'] === '1';
        $newPass = post_string('password');
        $result = UserService::update(
            $userId,
            post_string('name'),
            post_string('email'),
            post_string('role', 'viewer'),
            $active,
            self::postedEntityIds(),
            $newPass !== '' ? $newPass : null
        );
        if (!$result['ok']) {
            flash('error', $result['error'] ?? 'No se pudo actualizar.');
            redirect('/configuracion?tab=usuarios&edit=' . $userId);
        }
        AuditService::log('update', 'user', $userId, 'Usuario actualizado');
        flash('success', 'Usuario actualizado.');
        redirect('/configuracion?tab=usuarios');
    }

    public static function generateKey(): void
    {
        Auth::requireAdmin();
        require_csrf();
        try {
            if (CryptoService::isConfigured() && empty($_POST['confirm_rotate'])) {
                flash('error', 'Ya hay una clave. Marcá “confirmar rotación” solo si entendés que los secretos previos dejarán de descifrarse.');
                redirect('/configuracion?tab=sistema');
            }
            CryptoService::generateAndSave();
            AuditService::log('generate_key', 'settings', null, 'Clave de cifrado generada/rotada desde Configuración');
            flash('success', 'Clave de cifrado lista. Ahora podés cargar WooCommerce desde Integraciones.');
        } catch (Throwable $e) {
            flash('error', $e->getMessage());
        }
        redirect('/configuracion?tab=sistema');
    }

    public static function toggleHideAmounts(): void
    {
        Auth::requireAdmin();
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
        redirect('/configuracion?tab=sistema');
    }

    /** @return list<int> */
    private static function postedEntityIds(): array
    {
        $raw = $_POST['entity_ids'] ?? [];
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $v) {
            $id = (int) $v;
            if ($id > 0) {
                $out[] = $id;
            }
        }
        return $out;
    }
}
