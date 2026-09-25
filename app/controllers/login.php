<?php

declare(strict_types=1);

if ($auth->check()) {
    redirect('r=dashboard');
}

$error = null;

if (is_post()) {
    verify_csrf();
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    try {
        if ($auth->attempt($username, $password)) {
            $logger->log('auth.login', $auth->user(), []);
            redirect('r=dashboard');
        }
        $error = 'Invalid username or password.';
        $logger->log('auth.login_failed', null, ['username' => $username]);
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

view('login', [
    'title' => 'Login',
    'error' => $error,
    'app_name' => config('app_name'),
]);
