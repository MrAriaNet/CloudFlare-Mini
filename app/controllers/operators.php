<?php

declare(strict_types=1);

$access->requireAdmin();

$action = $_GET['action'] ?? 'list';

if (is_post()) {
    verify_csrf();
    $formAction = $_POST['form_action'] ?? '';

    if ($formAction === 'create' || $formAction === 'update') {
        $id = (string) ($_POST['id'] ?? '');
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $role = (string) ($_POST['role'] ?? 'viewer');
        $domainAccess = (string) ($_POST['domain_access'] ?? 'selected');
        $allowedZoneIds = $_POST['allowed_zone_ids'] ?? [];
        if (!is_array($allowedZoneIds)) {
            $allowedZoneIds = [];
        }
        $allowedZoneIds = array_values(array_filter(array_map('strval', $allowedZoneIds)));
        $active = !empty($_POST['active']);

        if (!in_array($role, ['admin', 'editor', 'viewer'], true)) {
            flash('error', 'Invalid role.');
            redirect('r=operators');
        }
        if (!in_array($domainAccess, ['all', 'selected'], true)) {
            $domainAccess = 'selected';
        }
        if ($role === 'admin') {
            $domainAccess = 'all';
            $allowedZoneIds = [];
        }
        if ($username === '') {
            flash('error', 'Username is required.');
            redirect('r=operators&action=' . ($formAction === 'create' ? 'create' : 'edit&id=' . urlencode($id)));
        }

        $operators = $store->read('operators', []);

        // Unique username
        foreach ($operators as $op) {
            if (strcasecmp((string) ($op['username'] ?? ''), $username) === 0) {
                if ($formAction === 'create' || ($op['id'] ?? '') !== $id) {
                    flash('error', 'Username already exists.');
                    redirect('r=operators');
                }
            }
        }

        if ($formAction === 'create') {
            if ($password === '') {
                flash('error', 'Password is required for new operators.');
                redirect('r=operators&action=create');
            }
            $operator = [
                'id' => uuid(),
                'username' => $username,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'role' => $role,
                'domain_access' => $domainAccess,
                'allowed_zone_ids' => $allowedZoneIds,
                'active' => $active,
                'created_at' => now_iso(),
            ];
            $store->update('operators', function (array $list) use ($operator): array {
                $list[] = $operator;
                return $list;
            }, []);
            $logger->log('operator.create', $access->user(), [
                'operator_id' => $operator['id'],
                'username' => $username,
                'role' => $role,
                'domain_access' => $domainAccess,
            ]);
            flash('success', 'Operator created.');
            redirect('r=operators');
        }

        $existing = null;
        foreach ($operators as $op) {
            if (($op['id'] ?? '') === $id) {
                $existing = $op;
                break;
            }
        }
        if (!$existing) {
            flash('error', 'Operator not found.');
            redirect('r=operators');
        }

        // Prevent locking yourself out of admin
        $current = $access->user();
        if (($current['id'] ?? '') === $id && $role !== 'admin') {
            flash('error', 'You cannot remove your own admin role.');
            redirect('r=operators&action=edit&id=' . urlencode($id));
        }

        $store->update('operators', function (array $list) use ($id, $username, $password, $role, $domainAccess, $allowedZoneIds, $active): array {
            foreach ($list as &$op) {
                if (($op['id'] ?? '') !== $id) {
                    continue;
                }
                $op['username'] = $username;
                $op['role'] = $role;
                $op['domain_access'] = $domainAccess;
                $op['allowed_zone_ids'] = $allowedZoneIds;
                $op['active'] = $active;
                if ($password !== '') {
                    $op['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
                }
            }
            unset($op);
            return $list;
        }, []);

        $logger->log('operator.update', $access->user(), [
            'operator_id' => $id,
            'username' => $username,
            'role' => $role,
            'domain_access' => $domainAccess,
        ]);
        flash('success', 'Operator updated.');
        redirect('r=operators');
    }

    if ($formAction === 'delete') {
        $id = (string) ($_POST['id'] ?? '');
        $current = $access->user();
        if (($current['id'] ?? '') === $id) {
            flash('error', 'You cannot delete your own account.');
            redirect('r=operators');
        }
        $deleted = null;
        $store->update('operators', function (array $list) use ($id, &$deleted): array {
            $out = [];
            foreach ($list as $op) {
                if (($op['id'] ?? '') === $id) {
                    $deleted = $op;
                    continue;
                }
                $out[] = $op;
            }
            return $out;
        }, []);
        if (!$deleted) {
            flash('error', 'Operator not found.');
            redirect('r=operators');
        }
        $logger->log('operator.delete', $access->user(), [
            'operator_id' => $id,
            'username' => $deleted['username'] ?? '',
        ]);
        flash('success', 'Operator deleted.');
        redirect('r=operators');
    }
}

$allZones = [];
foreach ($store->read('zones', []) as $zones) {
    foreach ($zones as $zone) {
        $allZones[] = $zone;
    }
}
usort($allZones, static fn(array $a, array $b): int => strcmp($a['name'] ?? '', $b['name'] ?? ''));

if ($action === 'create') {
    render('operators_form', [
        'title' => 'Add Operator',
        'user' => $access->user(),
        'access' => $access,
        'operator' => null,
        'allZones' => $allZones,
        'active_nav' => 'operators',
    ]);
    return;
}

if ($action === 'edit') {
    $id = (string) ($_GET['id'] ?? '');
    $operator = null;
    foreach ($store->read('operators', []) as $op) {
        if (($op['id'] ?? '') === $id) {
            $operator = $op;
            break;
        }
    }
    if (!$operator) {
        flash('error', 'Operator not found.');
        redirect('r=operators');
    }
    render('operators_form', [
        'title' => 'Edit Operator',
        'user' => $access->user(),
        'access' => $access,
        'operator' => $operator,
        'allZones' => $allZones,
        'active_nav' => 'operators',
    ]);
    return;
}

$operators = $store->read('operators', []);

render('operators', [
    'title' => 'Operators',
    'user' => $access->user(),
    'access' => $access,
    'operators' => $operators,
    'allZones' => $allZones,
    'active_nav' => 'operators',
]);
