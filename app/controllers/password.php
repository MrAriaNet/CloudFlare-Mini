<?php

declare(strict_types=1);

$user = $access->user();
if (!$user) {
    redirect('r=login');
}

if (is_post()) {
    verify_csrf();
    $current = (string) ($_POST['current_password'] ?? '');
    $new = (string) ($_POST['new_password'] ?? '');
    $confirm = (string) ($_POST['confirm_password'] ?? '');

    if ($current === '' || $new === '' || $confirm === '') {
        flash('error', 'All password fields are required.');
        redirect('r=password');
    }

    if (!password_verify($current, (string) ($user['password_hash'] ?? ''))) {
        flash('error', 'Current password is incorrect.');
        $logger->log('password.change_failed', $user, ['reason' => 'bad_current']);
        redirect('r=password');
    }

    if (strlen($new) < 6) {
        flash('error', 'New password must be at least 6 characters.');
        redirect('r=password');
    }

    if ($new !== $confirm) {
        flash('error', 'New password and confirmation do not match.');
        redirect('r=password');
    }

    if (password_verify($new, (string) ($user['password_hash'] ?? ''))) {
        flash('error', 'New password must be different from the current password.');
        redirect('r=password');
    }

    $userId = (string) $user['id'];
    $store->update('operators', function (array $list) use ($userId, $new): array {
        foreach ($list as &$op) {
            if (($op['id'] ?? '') === $userId) {
                $op['password_hash'] = password_hash($new, PASSWORD_DEFAULT);
            }
        }
        unset($op);
        return $list;
    }, []);

    $logger->log('password.change', $user, []);
    flash('success', 'Password updated successfully.');
    redirect('r=password');
}

render('password', [
    'title' => 'Change Password',
    'user' => $user,
    'access' => $access,
    'active_nav' => 'password',
]);
