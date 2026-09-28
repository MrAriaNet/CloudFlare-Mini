<?php

declare(strict_types=1);

$access->requirePermission('manage_roles');
$rolesSvc = $access->roles();
$action = $_GET['action'] ?? 'list';

if (is_post()) {
    verify_csrf();
    $formAction = $_POST['form_action'] ?? '';

    if ($formAction === 'save') {
        $existingId = (string) ($_POST['existing_id'] ?? '');
        $existing = $existingId !== '' ? $rolesSvc->find($existingId) : null;
        $perms = [];
        foreach (RoleService::PERMISSIONS as $key => $_label) {
            $perms[$key] = !empty($_POST['permissions'][$key]);
        }
        try {
            $payload = [
                'id' => $existing && !empty($existing['system']) ? $existing['id'] : (string) ($_POST['id'] ?? ''),
                'name' => (string) ($_POST['name'] ?? ''),
                'description' => (string) ($_POST['description'] ?? ''),
                'rank' => (int) ($_POST['rank'] ?? 1),
                'max_assignable_rank' => (int) ($_POST['max_assignable_rank'] ?? 0),
                'permissions' => $perms,
            ];
            if ($existing && !empty($existing['system'])) {
                $payload['id'] = $existing['id'];
            }
            $role = $rolesSvc->normalize($payload, $existing);
            // Prevent demoting admin system role below 100
            if (!empty($role['system']) && ($role['id'] ?? '') === 'admin') {
                $role['rank'] = 100;
                foreach (array_keys(RoleService::PERMISSIONS) as $permKey) {
                    $role['permissions'][$permKey] = true;
                }
                $role['max_assignable_rank'] = max(100, (int) $role['max_assignable_rank']);
            }
            $rolesSvc->upsert($role);
            $logger->log('role.save', $access->user(), [
                'role_id' => $role['id'],
                'name' => $role['name'],
                'rank' => $role['rank'],
            ]);
            flash('success', 'Access level saved.');
            redirect('r=roles');
        } catch (Throwable $e) {
            flash('error', $e->getMessage());
            redirect('r=roles&action=' . ($existing ? 'edit&id=' . urlencode($existingId) : 'create'));
        }
    }

    if ($formAction === 'delete') {
        $id = (string) ($_POST['id'] ?? '');
        $role = $rolesSvc->find($id);
        if (!$role) {
            flash('error', 'Access level not found.');
            redirect('r=roles');
        }
        if (!empty($role['system'])) {
            flash('error', 'System access levels cannot be deleted.');
            redirect('r=roles');
        }
        // Block delete if operators still use it
        foreach ($store->read('operators', []) as $op) {
            if (($op['role'] ?? '') === $id) {
                flash('error', 'Cannot delete: operators are still assigned to this access level.');
                redirect('r=roles');
            }
        }
        $rolesSvc->delete($id);
        $logger->log('role.delete', $access->user(), ['role_id' => $id]);
        flash('success', 'Access level deleted.');
        redirect('r=roles');
    }
}

if ($action === 'create') {
    render('roles_form', [
        'title' => 'Add Access Level',
        'user' => $access->user(),
        'access' => $access,
        'role' => null,
        'permissionDefs' => RoleService::PERMISSIONS,
        'permissionGroups' => RoleService::PERMISSION_GROUPS,
        'active_nav' => 'roles',
    ]);
    return;
}

if ($action === 'edit') {
    $id = (string) ($_GET['id'] ?? '');
    $role = $rolesSvc->find($id);
    if (!$role) {
        flash('error', 'Access level not found.');
        redirect('r=roles');
    }
    render('roles_form', [
        'title' => 'Edit Access Level',
        'user' => $access->user(),
        'access' => $access,
        'role' => $role,
        'permissionDefs' => RoleService::PERMISSIONS,
        'permissionGroups' => RoleService::PERMISSION_GROUPS,
        'active_nav' => 'roles',
    ]);
    return;
}

render('roles', [
    'title' => 'Access Levels',
    'user' => $access->user(),
    'access' => $access,
    'rolesList' => $rolesSvc->all(),
    'permissionDefs' => RoleService::PERMISSIONS,
    'permissionGroups' => RoleService::PERMISSION_GROUPS,
    'active_nav' => 'roles',
]);
