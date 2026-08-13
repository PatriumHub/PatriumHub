<?php

declare(strict_types=1);

final class AuthController
{
    public static function showLogin(): void
    {
        if (Auth::check()) {
            redirect(Auth::homePath());
        }
        view('auth/login', [
            'title' => 'Ingresar',
            'error' => flash('error'),
        ]);
    }

    public static function login(): void
    {
        if (!verify_csrf($_POST['_csrf'] ?? null)) {
            flash('error', 'Sesión inválida. Probá de nuevo.');
            redirect('/login');
        }

        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if ($email === '' || $password === '') {
            flash('error', 'Completá email y contraseña.');
            redirect('/login');
        }

        if (Auth::loginThrottled()) {
            flash('error', 'Demasiados intentos. Esperá unos minutos e intentá de nuevo.');
            redirect('/login');
        }

        if (!Auth::attempt($email, $password)) {
            flash('error', Auth::loginThrottled()
                ? 'Demasiados intentos. Esperá unos minutos e intentá de nuevo.'
                : 'Credenciales incorrectas.');
            redirect('/login');
        }

        redirect(Auth::homePath());
    }

    public static function logout(): void
    {
        if (!verify_csrf($_POST['_csrf'] ?? null)) {
            redirect(Auth::homePath());
        }
        Auth::logout();
        redirect('/login');
    }
}
