<?php

declare(strict_types=1);

/**
 * Usuarios, perfil y acceso por entidad (persona/empresa).
 */
final class UserService
{
    private static bool $ready = false;

    public static function ensureSchema(): void
    {
        if (self::$ready) {
            return;
        }
        $pdo = Database::pdo();
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS user_entity_access (
                user_id INT UNSIGNED NOT NULL,
                entity_id INT UNSIGNED NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (user_id, entity_id),
                KEY idx_uea_entity (entity_id),
                CONSTRAINT fk_uea_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                CONSTRAINT fk_uea_entity FOREIGN KEY (entity_id) REFERENCES entities(id) ON DELETE CASCADE
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        self::$ready = true;
    }

    public static function isAdmin(?array $user = null): bool
    {
        $user = $user ?? Auth::user();
        return is_array($user) && ($user['role'] ?? '') === 'admin';
    }

    /** null = acceso total (admin). */
    public static function allowedEntityIds(?int $userId = null): ?array
    {
        self::ensureSchema();
        $user = Auth::user();
        if ($userId === null) {
            if (!$user) {
                return [];
            }
            if (self::isAdmin($user)) {
                return null;
            }
            $userId = (int) $user['id'];
        } else {
            $row = self::find($userId);
            if ($row && ($row['role'] ?? '') === 'admin') {
                return null;
            }
        }

        $stmt = Database::pdo()->prepare(
            'SELECT entity_id FROM user_entity_access WHERE user_id = :u'
        );
        $stmt->execute(['u' => $userId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    public static function canAccessEntity(int $entityId): bool
    {
        $allowed = self::allowedEntityIds();
        if ($allowed === null) {
            return true;
        }
        return in_array($entityId, $allowed, true);
    }

    public static function requireEntityAccess(int $entityId): void
    {
        if (!self::canAccessEntity($entityId)) {
            flash('error', 'No tenés permiso para ver esa entidad.');
            redirect('/');
        }
    }

    /** @return list<array<string,mixed>> */
    public static function all(): array
    {
        self::ensureSchema();
        return Database::pdo()->query(
            'SELECT id, name, email, role, is_active, last_login_at, created_at, updated_at
             FROM users
             ORDER BY name, id'
        )->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public static function find(int $id): ?array
    {
        self::ensureSchema();
        $stmt = Database::pdo()->prepare(
            'SELECT id, name, email, role, is_active, last_login_at, created_at, updated_at
             FROM users WHERE id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** @return list<int> */
    public static function entityIdsForUser(int $userId): array
    {
        self::ensureSchema();
        $stmt = Database::pdo()->prepare(
            'SELECT entity_id FROM user_entity_access WHERE user_id = :u ORDER BY entity_id'
        );
        $stmt->execute(['u' => $userId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /** Agrega una entidad al acceso del usuario (si no es admin). */
    public static function grantEntity(int $userId, int $entityId): void
    {
        self::ensureSchema();
        $row = self::find($userId);
        if (!$row || ($row['role'] ?? '') === 'admin') {
            return;
        }
        Database::pdo()->prepare(
            'INSERT IGNORE INTO user_entity_access (user_id, entity_id) VALUES (:u, :e)'
        )->execute(['u' => $userId, 'e' => $entityId]);
    }

    /**
     * @param list<int> $entityIds
     */
    public static function setEntityAccess(int $userId, array $entityIds): void
    {
        self::ensureSchema();
        $pdo = Database::pdo();
        $pdo->prepare('DELETE FROM user_entity_access WHERE user_id = :u')->execute(['u' => $userId]);
        $ins = $pdo->prepare(
            'INSERT INTO user_entity_access (user_id, entity_id) VALUES (:u, :e)'
        );
        $seen = [];
        foreach ($entityIds as $eid) {
            $eid = (int) $eid;
            if ($eid <= 0 || isset($seen[$eid])) {
                continue;
            }
            $seen[$eid] = true;
            $ins->execute(['u' => $userId, 'e' => $eid]);
        }
    }

    /**
     * @return array{ok:bool,error?:string,id?:int}
     */
    public static function create(
        string $name,
        string $email,
        string $password,
        string $role,
        bool $active,
        array $entityIds
    ): array {
        self::ensureSchema();
        $name = trim($name);
        $email = trim(mb_strtolower($email));
        $role = $role === 'admin' ? 'admin' : 'viewer';
        if ($name === '' || $email === '') {
            return ['ok' => false, 'error' => 'Nombre y email son obligatorios.'];
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'Email inválido.'];
        }
        if (strlen($password) < 6) {
            return ['ok' => false, 'error' => 'La contraseña debe tener al menos 6 caracteres.'];
        }
        if (self::emailTaken($email)) {
            return ['ok' => false, 'error' => 'Ya existe un usuario con ese email.'];
        }

        $pdo = Database::pdo();
        $pdo->prepare(
            'INSERT INTO users (name, email, password_hash, role, is_active)
             VALUES (:n, :e, :p, :r, :a)'
        )->execute([
            'n' => $name,
            'e' => $email,
            'p' => password_hash($password, PASSWORD_DEFAULT),
            'r' => $role,
            'a' => $active ? 1 : 0,
        ]);
        $id = (int) $pdo->lastInsertId();
        if ($role !== 'admin') {
            self::setEntityAccess($id, $entityIds);
        }
        return ['ok' => true, 'id' => $id];
    }

    /**
     * @return array{ok:bool,error?:string}
     */
    public static function update(
        int $id,
        string $name,
        string $email,
        string $role,
        bool $active,
        array $entityIds,
        ?string $newPassword = null
    ): array {
        self::ensureSchema();
        $existing = self::find($id);
        if (!$existing) {
            return ['ok' => false, 'error' => 'Usuario no encontrado.'];
        }
        $name = trim($name);
        $email = trim(mb_strtolower($email));
        $role = $role === 'admin' ? 'admin' : 'viewer';
        if ($name === '' || $email === '') {
            return ['ok' => false, 'error' => 'Nombre y email son obligatorios.'];
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'Email inválido.'];
        }
        if (self::emailTaken($email, $id)) {
            return ['ok' => false, 'error' => 'Ya existe un usuario con ese email.'];
        }

        $me = Auth::user();
        if ($me && (int) $me['id'] === $id && !$active) {
            return ['ok' => false, 'error' => 'No podés desactivarte a vos mismo.'];
        }
        if ($me && (int) $me['id'] === $id && $role !== 'admin' && ($existing['role'] ?? '') === 'admin') {
            // evitar auto-degradarse si es el único admin
            if (self::countAdmins() <= 1) {
                return ['ok' => false, 'error' => 'No podés quitarte el rol admin: sos el único administrador.'];
            }
        }

        $pdo = Database::pdo();
        $pdo->prepare(
            'UPDATE users SET name = :n, email = :e, role = :r, is_active = :a,
             updated_at = CURRENT_TIMESTAMP WHERE id = :id'
        )->execute([
            'n' => $name,
            'e' => $email,
            'r' => $role,
            'a' => $active ? 1 : 0,
            'id' => $id,
        ]);

        if ($newPassword !== null && $newPassword !== '') {
            if (strlen($newPassword) < 6) {
                return ['ok' => false, 'error' => 'La contraseña debe tener al menos 6 caracteres.'];
            }
            $pdo->prepare(
                'UPDATE users SET password_hash = :p, updated_at = CURRENT_TIMESTAMP WHERE id = :id'
            )->execute([
                'p' => password_hash($newPassword, PASSWORD_DEFAULT),
                'id' => $id,
            ]);
        }

        if ($role === 'admin') {
            $pdo->prepare('DELETE FROM user_entity_access WHERE user_id = :u')->execute(['u' => $id]);
        } else {
            self::setEntityAccess($id, $entityIds);
        }

        if ($me && (int) $me['id'] === $id) {
            Auth::refreshSessionUser();
        }

        return ['ok' => true];
    }

    /**
     * @return array{ok:bool,error?:string}
     */
    public static function updateProfile(int $userId, string $name, string $email): array
    {
        $name = trim($name);
        $email = trim(mb_strtolower($email));
        if ($name === '' || $email === '') {
            return ['ok' => false, 'error' => 'Nombre y email son obligatorios.'];
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'Email inválido.'];
        }
        if (self::emailTaken($email, $userId)) {
            return ['ok' => false, 'error' => 'Ya existe un usuario con ese email.'];
        }
        Database::pdo()->prepare(
            'UPDATE users SET name = :n, email = :e, updated_at = CURRENT_TIMESTAMP WHERE id = :id'
        )->execute(['n' => $name, 'e' => $email, 'id' => $userId]);
        Auth::refreshSessionUser();
        return ['ok' => true];
    }

    /**
     * @return array{ok:bool,error?:string}
     */
    public static function changePassword(int $userId, string $current, string $new, string $confirm): array
    {
        if ($new === '' || strlen($new) < 6) {
            return ['ok' => false, 'error' => 'La contraseña nueva debe tener al menos 6 caracteres.'];
        }
        if ($new !== $confirm) {
            return ['ok' => false, 'error' => 'La confirmación no coincide.'];
        }
        $stmt = Database::pdo()->prepare(
            'SELECT password_hash FROM users WHERE id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $userId]);
        $hash = $stmt->fetchColumn();
        if (!$hash || !password_verify($current, (string) $hash)) {
            return ['ok' => false, 'error' => 'La contraseña actual no es correcta.'];
        }
        Database::pdo()->prepare(
            'UPDATE users SET password_hash = :p, updated_at = CURRENT_TIMESTAMP WHERE id = :id'
        )->execute([
            'p' => password_hash($new, PASSWORD_DEFAULT),
            'id' => $userId,
        ]);
        return ['ok' => true];
    }

    private static function emailTaken(string $email, ?int $exceptId = null): bool
    {
        if ($exceptId) {
            $stmt = Database::pdo()->prepare(
                'SELECT id FROM users WHERE email = :e AND id <> :id LIMIT 1'
            );
            $stmt->execute(['e' => $email, 'id' => $exceptId]);
        } else {
            $stmt = Database::pdo()->prepare(
                'SELECT id FROM users WHERE email = :e LIMIT 1'
            );
            $stmt->execute(['e' => $email]);
        }
        return (bool) $stmt->fetchColumn();
    }

    private static function countAdmins(): int
    {
        return (int) Database::pdo()->query(
            "SELECT COUNT(*) FROM users WHERE role = 'admin' AND is_active = 1"
        )->fetchColumn();
    }
}
