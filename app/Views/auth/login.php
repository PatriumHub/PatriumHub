<section class="auth-card">
    <h1><?= e($appName) ?></h1>
    <p class="lede">Centro de comando patrimonial</p>

    <?php if (!empty($error)): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" action="<?= e(url('/login')) ?>" class="stack">
        <?= csrf_field() ?>
        <label>
            Email
            <input type="email" name="email" required autocomplete="username">
        </label>
        <label>
            Contraseña
            <input type="password" name="password" required autocomplete="current-password">
        </label>
        <button type="submit">Ingresar</button>
    </form>
</section>
