<?php
$tab = $tab ?? 'perfil';
$me = $me ?? Auth::user();
$isAdmin = !empty($isAdmin);
$users = $users ?? [];
$allEntities = $allEntities ?? [];
$editUser = $editUser ?? null;
$editAccess = $editAccess ?? [];
$people = array_values(array_filter($allEntities, static fn ($e) => ($e['type'] ?? '') === 'person'));
$companies = array_values(array_filter($allEntities, static fn ($e) => ($e['type'] ?? '') === 'company'));
?>
<section class="page">
    <div class="page-head">
        <div>
            <h1>Configuración</h1>
            <p class="lede">Tu perfil, usuarios del sistema y ajustes generales.</p>
        </div>
    </div>

    <?php require __DIR__ . '/../partials/alerts.php'; ?>

    <nav class="tabs">
        <a class="<?= $tab === 'perfil' ? 'is-active' : '' ?>" href="<?= e(url('/configuracion?tab=perfil')) ?>">Mi perfil</a>
        <?php if ($isAdmin): ?>
            <a class="<?= $tab === 'usuarios' ? 'is-active' : '' ?>" href="<?= e(url('/configuracion?tab=usuarios')) ?>">Usuarios</a>
        <?php endif; ?>
        <a class="<?= $tab === 'sistema' ? 'is-active' : '' ?>" href="<?= e(url('/configuracion?tab=sistema')) ?>">Sistema</a>
    </nav>

    <?php if ($tab === 'perfil'): ?>
        <div class="grid-2 mt">
            <div class="panel">
                <h2>Datos de cuenta</h2>
                <p class="muted">Nombre visible y email de login.</p>
                <form method="post" action="<?= e(url('/configuracion/perfil')) ?>" class="form-grid mt" style="padding:0;border:0;box-shadow:none;background:transparent">
                    <?= csrf_field() ?>
                    <label>Nombre *
                        <input name="name" required value="<?= e($me['name'] ?? '') ?>">
                    </label>
                    <label>Email *
                        <input type="email" name="email" required value="<?= e($me['email'] ?? '') ?>">
                    </label>
                    <div class="full"><button type="submit" class="btn">Guardar perfil</button></div>
                </form>
            </div>
            <div class="panel">
                <h2>Cambiar contraseña</h2>
                <p class="muted">Mínimo 6 caracteres.</p>
                <form method="post" action="<?= e(url('/configuracion/password')) ?>" class="form-grid mt" style="padding:0;border:0;box-shadow:none;background:transparent">
                    <?= csrf_field() ?>
                    <label class="full">Contraseña actual *
                        <input type="password" name="current_password" required autocomplete="current-password">
                    </label>
                    <label>Nueva *
                        <input type="password" name="new_password" required minlength="6" autocomplete="new-password">
                    </label>
                    <label>Confirmar *
                        <input type="password" name="confirm_password" required minlength="6" autocomplete="new-password">
                    </label>
                    <div class="full"><button type="submit" class="btn">Actualizar contraseña</button></div>
                </form>
            </div>
        </div>
        <p class="muted mt">Rol actual: <strong><?= e(($me['role'] ?? '') === 'admin' ? 'Administrador' : 'Usuario con acceso limitado') ?></strong></p>

    <?php elseif ($tab === 'usuarios' && $isAdmin): ?>
        <div class="panel mt">
            <div class="page-head" style="margin:0">
                <div>
                    <h2 style="margin:0">Usuarios</h2>
                    <p class="muted" style="margin:0.35rem 0 0">Los administradores ven todo. Los demás solo las personas/empresas tildadas.</p>
                </div>
            </div>
            <?php if (empty($users)): ?>
                <p class="muted mt">Sin usuarios.</p>
            <?php else: ?>
                <table class="table mt">
                    <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Email</th>
                        <th>Rol</th>
                        <th>Estado</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td><?= e($u['name']) ?></td>
                            <td><?= e($u['email']) ?></td>
                            <td><?= ($u['role'] ?? '') === 'admin' ? 'Admin' : 'Limitado' ?></td>
                            <td><?= !empty($u['is_active']) ? 'Activo' : 'Inactivo' ?></td>
                            <td class="actions">
                                <?= icon_action_link(url('/configuracion?tab=usuarios&edit=' . (int) $u['id']), 'edit', 'Editar') ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <?php
        $formUser = $editUser;
        $isEditUser = !empty($formUser['id']);
        $accessSet = array_fill_keys(array_map('intval', $editAccess), true);
        $formAction = $isEditUser
            ? url('/configuracion/usuarios/' . (int) $formUser['id'])
            : url('/configuracion/usuarios');
        $formRole = ($formUser['role'] ?? 'viewer') === 'admin' ? 'admin' : 'viewer';
        ?>
        <div class="panel mt">
            <h2><?= $isEditUser ? 'Editar usuario' : 'Nuevo usuario' ?></h2>
            <?php if ($isEditUser): ?>
                <p class="muted"><a href="<?= e(url('/configuracion?tab=usuarios')) ?>">← Cancelar edición / crear otro</a></p>
            <?php endif; ?>
            <form method="post" action="<?= e($formAction) ?>" class="form-grid mt" style="padding:0;border:0;box-shadow:none;background:transparent">
                <?= csrf_field() ?>
                <label>Nombre *
                    <input name="name" required value="<?= e($formUser['name'] ?? '') ?>">
                </label>
                <label>Email *
                    <input type="email" name="email" required value="<?= e($formUser['email'] ?? '') ?>">
                </label>
                <label>Rol *
                    <select name="role" id="user-role">
                        <option value="viewer"<?= $formRole === 'viewer' ? ' selected' : '' ?>>Limitado (elige entidades)</option>
                        <option value="admin"<?= $formRole === 'admin' ? ' selected' : '' ?>>Administrador (todo)</option>
                    </select>
                </label>
                <label><?= $isEditUser ? 'Nueva contraseña (opcional)' : 'Contraseña *' ?>
                    <input type="password" name="password" <?= $isEditUser ? '' : 'required minlength="6"' ?> autocomplete="new-password">
                </label>
                <label class="check full">
                    <input type="checkbox" name="is_active" value="1"<?= checked($formUser['is_active'] ?? 1) ?>>
                    Usuario activo
                </label>

                <div class="full" id="entity-access-block">
                    <h3 style="margin:0 0 0.5rem">Puede ver</h3>
                    <p class="muted" style="margin:0 0 0.75rem">Solo aplica si el rol es limitado.</p>
                    <div class="grid-2">
                        <div>
                            <strong>Personas</strong>
                            <?php if (empty($people)): ?>
                                <p class="muted">Sin personas.</p>
                            <?php else: ?>
                                <ul class="plain">
                                    <?php foreach ($people as $e): ?>
                                        <li class="check">
                                            <label>
                                                <input type="checkbox" name="entity_ids[]" value="<?= (int) $e['id'] ?>"
                                                    <?= isset($accessSet[(int) $e['id']]) ? ' checked' : '' ?>>
                                                <?= e($e['name']) ?>
                                            </label>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                        <div>
                            <strong>Empresas</strong>
                            <?php if (empty($companies)): ?>
                                <p class="muted">Sin empresas.</p>
                            <?php else: ?>
                                <ul class="plain">
                                    <?php foreach ($companies as $e): ?>
                                        <li class="check">
                                            <label>
                                                <input type="checkbox" name="entity_ids[]" value="<?= (int) $e['id'] ?>"
                                                    <?= isset($accessSet[(int) $e['id']]) ? ' checked' : '' ?>>
                                                <?= e($e['name']) ?>
                                            </label>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="full">
                    <button type="submit" class="btn"><?= $isEditUser ? 'Guardar usuario' : 'Crear usuario' ?></button>
                </div>
            </form>
        </div>

    <?php else: /* sistema */ ?>
        <div class="panel mt">
            <div class="page-head" style="margin:0">
                <div>
                    <h2 style="margin:0">Integraciones</h2>
                    <p class="muted" style="margin:0.35rem 0 0">WooCommerce y otras conexiones. No van en el .env.</p>
                </div>
                <a class="btn" href="<?= e(url('/integraciones')) ?>">Abrir integraciones</a>
            </div>
        </div>

        <div class="grid-2 mt">
            <div class="panel">
                <h2>Privacidad en pantalla</h2>
                <p class="muted">Ocultá montos en dashboards y listados.</p>
                <ul class="plain">
                    <li>Estado: <strong><?= !empty($hideAmounts) ? 'Cifras ocultas' : 'Cifras visibles' ?></strong></li>
                </ul>
                <form method="post" action="<?= e(url('/configuracion/ocultar-cifras')) ?>" class="mt">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn"><?= !empty($hideAmounts) ? 'Mostrar cifras' : 'Ocultar cifras' ?></button>
                </form>
            </div>

            <div class="panel">
                <h2>Clave de cifrado</h2>
                <p class="muted">
                    Cifra secretos de WooCommerce. Archivo: <code><?= e($keyPath) ?></code>
                </p>
                <ul class="plain">
                    <li>Estado:
                        <?php if (!empty($cryptoOk)): ?>
                            <strong class="ok-text">Configurada</strong>
                            <?php if ($fingerprint): ?> · huella <code><?= e($fingerprint) ?></code><?php endif; ?>
                        <?php else: ?>
                            <strong class="err-text">Pendiente</strong>
                        <?php endif; ?>
                    </li>
                </ul>
                <?php if ($isAdmin): ?>
                    <form method="post" action="<?= e(url('/configuracion/clave')) ?>" class="stack mt">
                        <?= csrf_field() ?>
                        <?php if (!empty($cryptoOk)): ?>
                            <label class="check">
                                <input type="checkbox" name="confirm_rotate" value="1">
                                Confirmo rotar la clave
                            </label>
                            <button type="submit" class="btn ghost">Rotar clave</button>
                        <?php else: ?>
                            <button type="submit">Generar clave de cifrado</button>
                        <?php endif; ?>
                    </form>
                <?php else: ?>
                    <p class="muted mt">Solo un administrador puede generar o rotar la clave.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="panel mt">
            <h2>Dónde van las credenciales</h2>
            <ul class="plain">
                <li><strong>WooCommerce</strong> → <a href="<?= e(url('/integraciones')) ?>">Integraciones</a></li>
                <li><strong>Mercado Pago</strong> → <a href="<?= e(url('/cuentas')) ?>">Cuentas</a> (tipo Mercado Pago)</li>
                <li><strong>.env</strong> → solo MySQL y URL de la app</li>
            </ul>
        </div>
    <?php endif; ?>
</section>
