<?php

declare(strict_types=1);

$access->requireManageOperators();

$action = $_GET['action'] ?? 'list';
$rolesSvc = $access->roles();
$currentUser = $access->user();
$assignableRoles = $access->assignableRoles();

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

        if (!$access->canAssignRole($role)) {
            flash('error', 'You cannot assign that access level.');
            redirect('r=operators');
        }

        $roleDef = $rolesSvc->find($role);
        if (!$roleDef) {
            flash('error', 'Invalid role.');
            redirect('r=operators');
        }

        if (!in_array($domainAccess, ['all', 'selected'], true)) {
            $domainAccess = 'selected';
        }
        if (($roleDef['id'] ?? '') === 'admin' || $rolesSvc->rank($roleDef) >= 100) {
            $domainAccess = 'all';
            $allowedZoneIds = [];
        }
        if ($username === '') {
            flash('error', 'Username is required.');
            redirect('r=operators&action=' . ($formAction === 'create' ? 'create' : ('edit&id=' . urlencode($id))));
        }

        $operators = $store->read('operators', []);

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
            if ($assignableRoles === []) {
                flash('error', 'Your access level cannot create operators.');
                redirect('r=operators');
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
                'created_by' => $currentUser['id'] ?? null,
            ];
            $store->update('operators', function (array $list) use ($operator): array {
                $list[] = $operator;
                return $list;
            }, []);
            $logger->log('operator.create', $currentUser, [
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

        if (($currentUser['id'] ?? '') !== $id && !$access->canManageOperator($existing)) {
            flash('error', 'You cannot edit this operator.');
            redirect('r=operators');
        }

        // Editing self: cannot raise own role beyond what you can assign; demoting self away from top role if last admin
        if (($currentUser['id'] ?? '') === $id) {
            if ($role !== ($existing['role'] ?? '') && !$access->canAssignRole($role)) {
                flash('error', 'You cannot change your own role to that level.');
                redirect('r=operators&action=edit&id=' . urlencode($id));
            }
            $newRole = $rolesSvc->findOrFallback($role);
            if ($rolesSvc->rank($access->role()) >= 100 && $rolesSvc->rank($newRole) < 100) {
                $adminsLeft = 0;
                foreach ($operators as $op) {
                    if (($op['id'] ?? '') === $id) {
                        continue;
                    }
                    $r = $rolesSvc->findOrFallback((string) ($op['role'] ?? ''));
                    if ($rolesSvc->rank($r) >= 100 && !empty($op['active'])) {
                        $adminsLeft++;
                    }
                }
                if ($adminsLeft < 1) {
                    flash('error', 'You cannot remove the last administrator role.');
                    redirect('r=operators&action=edit&id=' . urlencode($id));
                }
            }
        } else {
            // Changing target to a new role must be assignable; also cannot edit if current target is out of reach
            if (!$access->canAssignRole($role)) {
                flash('error', 'You cannot assign that access level.');
                redirect('r=operators&action=edit&id=' . urlencode($id));
            }
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

        $logger->log('operator.update', $currentUser, [
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
        if (($currentUser['id'] ?? '') === $id) {
            flash('error', 'You cannot delete your own account.');
            redirect('r=operators');
        }
        $deleted = null;
        foreach ($store->read('operators', []) as $op) {
            if (($op['id'] ?? '') === $id) {
                $deleted = $op;
                break;
            }
        }
        if (!$deleted) {
            flash('error', 'Operator not found.');
            redirect('r=operators');
        }
        if (!$access->canManageOperator($deleted)) {
            flash('error', 'You cannot delete this operator.');
            redirect('r=operators');
        }
        $store->update('operators', function (array $list) use ($id): array {
            return array_values(array_filter($list, static function (array $op) use ($id): bool {
                return ($op['id'] ?? '') !== $id;
            }));
        }, []);
        $logger->log('operator.delete', $currentUser, [
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
usort($allZones, static function (array $a, array $b): int {
    return strcmp($a['name'] ?? '', $b['name'] ?? '');
});

if ($action === 'create') {
    if ($assignableRoles === []) {
        flash('error', 'Your access level cannot create operators. Ask an administrator to raise max assignable rank.');
        redirect('r=operators');
    }
    render('operators_form', [
        'title' => 'Add Operator',
        'user' => $currentUser,
        'access' => $access,
        'operator' => null,
        'allZones' => $allZones,
        'assignableRoles' => $assignableRoles,
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
    $isSelf = ($currentUser['id'] ?? '') === ($operator['id'] ?? '');
    if (!$isSelf && !$access->canManageOperator($operator)) {
        flash('error', 'You cannot edit this operator.');
        redirect('r=operators');
    }

    // For edit form, include current role in options even if somehow edge-case
    $roleOptions = $assignableRoles;
    $currentRoleId = (string) ($operator['role'] ?? '');
    $hasCurrent = false;
    foreach ($roleOptions as $r) {
        if (($r['id'] ?? '') === $currentRoleId) {
            $hasCurrent = true;
            break;
        }
    }
    if (!$hasCurrent && $isSelf) {
        $selfRole = $rolesSvc->find($currentRoleId);
        if ($selfRole) {
            $roleOptions[] = $selfRole;
        }
    } elseif (!$hasCurrent && $access->canAssignRole($currentRoleId)) {
        $cr = $rolesSvc->find($currentRoleId);
        if ($cr) {
            $roleOptions[] = $cr;
        }
    }

    render('operators_form', [
        'title' => 'Edit Operator',
        'user' => $currentUser,
        'access' => $access,
        'operator' => $operator,
        'allZones' => $allZones,
        'assignableRoles' => $roleOptions,
        'active_nav' => 'operators',
    ]);
    return;
}

$operators = $store->read('operators', []);

render('operators', [
    'title' => 'Operators',
    'user' => $currentUser,
    'access' => $access,
    'operators' => $operators,
    'allZones' => $allZones,
    'rolesSvc' => $rolesSvc,
    'active_nav' => 'operators',
]);
