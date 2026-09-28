<?php

declare(strict_types=1);

final class RoleService
{
    /** Flat map for normalize/save */
    public const PERMISSIONS = [
        'manage_accounts' => 'Manage Cloudflare accounts',
        'sync_account' => 'Sync a single Cloudflare account',
        'force_sync' => 'Force sync all accounts',
        'manage_ip_allowlist' => 'Manage IP allowlist',
        'view_domains' => 'View domains list',
        'view_dns' => 'View DNS records',
        'create_dns' => 'Create DNS records',
        'edit_dns' => 'Edit DNS records',
        'delete_dns' => 'Delete DNS records',
        'refresh_dns' => 'Refresh DNS from Cloudflare',
        'view_ptr_audit' => 'View PTR mismatch list',
        'run_ptr_audit' => 'Run PTR consistency audit',
        'manage_ptr_settings' => 'Configure PTR auto-check / auto-delete',
        'delete_broken_ptr' => 'Delete mismatched PTR records',
        'manage_operators' => 'Manage operators',
        'manage_roles' => 'Define access levels (roles)',
        'view_audit_logs' => 'View audit logs',
        'view_dns_logs' => 'View DNS change logs',
        'view_cron_logs' => 'View cron logs',
        'view_ptr_logs' => 'View PTR logs',
        'view_all_logs' => 'View everyone’s logs (not only own)',
        'clear_audit_logs' => 'Clear audit logs',
        'clear_dns_logs' => 'Clear DNS change logs',
        'clear_cron_logs' => 'Clear cron logs',
        'clear_ptr_logs' => 'Clear PTR logs',
    ];

    /** Grouped for UI */
    public const PERMISSION_GROUPS = [
        'Accounts & security' => [
            'manage_accounts' => 'Manage Cloudflare accounts',
            'sync_account' => 'Sync a single Cloudflare account',
            'force_sync' => 'Force sync all accounts',
            'manage_ip_allowlist' => 'Manage IP allowlist',
        ],
        'Domains & DNS' => [
            'view_domains' => 'View domains list',
            'view_dns' => 'View DNS records',
            'create_dns' => 'Create DNS records',
            'edit_dns' => 'Edit DNS records',
            'delete_dns' => 'Delete DNS records',
            'refresh_dns' => 'Refresh DNS from Cloudflare',
            'view_ptr_audit' => 'View PTR mismatch list',
            'run_ptr_audit' => 'Run PTR consistency audit',
            'manage_ptr_settings' => 'Configure PTR auto-check / auto-delete',
            'delete_broken_ptr' => 'Delete mismatched PTR records',
        ],
        'Operators' => [
            'manage_operators' => 'Manage operators',
            'manage_roles' => 'Define access levels (roles)',
        ],
        'Logs' => [
            'view_audit_logs' => 'View audit logs',
            'view_dns_logs' => 'View DNS change logs',
            'view_cron_logs' => 'View cron logs',
            'view_ptr_logs' => 'View PTR logs',
            'view_all_logs' => 'View everyone’s logs (not only own)',
            'clear_audit_logs' => 'Clear audit logs',
            'clear_dns_logs' => 'Clear DNS change logs',
            'clear_cron_logs' => 'Clear cron logs',
            'clear_ptr_logs' => 'Clear PTR logs',
        ],
    ];

    private JsonStore $store;

    public function __construct(JsonStore $store)
    {
        $this->store = $store;
        $this->ensureDefaults();
        $this->migrateExistingRoles();
    }

    public function ensureDefaults(): void
    {
        $roles = $this->store->read('roles', []);
        if (!empty($roles)) {
            return;
        }
        $this->store->write('roles', $this->defaultRoles());
    }

    /**
     * Fill missing permission keys on existing roles (upgrade-safe).
     */
    public function migrateExistingRoles(): void
    {
        $roles = $this->store->read('roles', []);
        if (!is_array($roles) || $roles === []) {
            return;
        }

        $changed = false;
        foreach ($roles as $i => $role) {
            if (!is_array($role)) {
                continue;
            }
            $expanded = $this->expandPermissions($role['permissions'] ?? [], (string) ($role['id'] ?? ''));
            if ($expanded !== ($role['permissions'] ?? [])) {
                $roles[$i]['permissions'] = $expanded;
                $changed = true;
            }
        }

        if ($changed) {
            $this->store->write('roles', array_values($roles));
        }
    }

    /**
     * Expand legacy permission flags into the fine-grained set.
     */
    public function expandPermissions(array $perms, string $roleId = ''): array
    {
        $out = [];
        foreach (self::PERMISSIONS as $key => $_label) {
            $out[$key] = !empty($perms[$key]);
        }

        // Legacy: mutate_dns → create/edit/delete/refresh
        if (!empty($perms['mutate_dns'])) {
            if (!array_key_exists('create_dns', $perms)) {
                $out['create_dns'] = true;
            }
            if (!array_key_exists('edit_dns', $perms)) {
                $out['edit_dns'] = true;
            }
            if (!array_key_exists('delete_dns', $perms)) {
                $out['delete_dns'] = true;
            }
            if (!array_key_exists('refresh_dns', $perms)) {
                $out['refresh_dns'] = true;
            }
            if (!array_key_exists('view_dns', $perms)) {
                $out['view_dns'] = true;
            }
            if (!array_key_exists('view_domains', $perms)) {
                $out['view_domains'] = true;
            }
        }

        // Legacy: manage_accounts implied sync + ip allowlist
        if (!empty($perms['manage_accounts'])) {
            if (!array_key_exists('sync_account', $perms)) {
                $out['sync_account'] = true;
            }
            if (!array_key_exists('manage_ip_allowlist', $perms)) {
                $out['manage_ip_allowlist'] = true;
            }
        }

        // Legacy: anyone who had view_all_logs or was editor historically saw logs
        if (!empty($perms['view_all_logs']) || !empty($perms['view_cron_logs'])) {
            if (!array_key_exists('view_audit_logs', $perms)) {
                $out['view_audit_logs'] = true;
            }
            if (!array_key_exists('view_dns_logs', $perms)) {
                $out['view_dns_logs'] = true;
            }
        }

        // Anyone with PTR audit access gets PTR logs view unless explicitly denied later
        if (!empty($perms['view_ptr_audit']) || !empty($perms['run_ptr_audit']) || !empty($perms['delete_broken_ptr'])) {
            if (!array_key_exists('view_ptr_logs', $perms)) {
                $out['view_ptr_logs'] = true;
            }
        }
        if (!empty($perms['manage_ptr_settings'])) {
            if (!array_key_exists('clear_ptr_logs', $perms)) {
                $out['clear_ptr_logs'] = true;
            }
            if (!array_key_exists('view_ptr_logs', $perms)) {
                $out['view_ptr_logs'] = true;
            }
        }

        // Default baseline for known system roles when keys were never set
        if ($roleId === 'viewer' || $roleId === 'editor' || $roleId === 'manager') {
            if (!array_key_exists('view_domains', $perms)) {
                $out['view_domains'] = true;
            }
            if (!array_key_exists('view_dns', $perms)) {
                $out['view_dns'] = true;
            }
            if (!array_key_exists('view_audit_logs', $perms)) {
                $out['view_audit_logs'] = true;
            }
            if (!array_key_exists('view_dns_logs', $perms)) {
                $out['view_dns_logs'] = true;
            }
        }

        if ($roleId === 'editor' || $roleId === 'manager') {
            if (!array_key_exists('create_dns', $perms) && empty($perms['mutate_dns'])) {
                // only apply if mutate was the only signal — already handled above
            }
        }

        if ($roleId === 'admin' || (!empty($perms['manage_roles']) && !empty($perms['manage_accounts']))) {
            foreach (self::PERMISSIONS as $key => $_label) {
                if (!array_key_exists($key, $perms)) {
                    $out[$key] = true;
                }
            }
        }

        return $out;
    }

    public function allPermissionsTrue(): array
    {
        $out = [];
        foreach (self::PERMISSIONS as $key => $_label) {
            $out[$key] = true;
        }
        return $out;
    }

    public function defaultRoles(): array
    {
        $adminPerms = $this->allPermissionsTrue();

        $managerPerms = $this->expandPermissions([
            'manage_operators' => true,
            'mutate_dns' => true,
            'view_domains' => true,
            'view_dns' => true,
            'view_audit_logs' => true,
            'view_dns_logs' => true,
            'clear_dns_logs' => false,
        ], 'manager');

        $editorPerms = $this->expandPermissions([
            'mutate_dns' => true,
            'view_domains' => true,
            'view_dns' => true,
            'view_audit_logs' => true,
            'view_dns_logs' => true,
        ], 'editor');

        $viewerPerms = $this->expandPermissions([
            'view_domains' => true,
            'view_dns' => true,
            'view_audit_logs' => true,
            'view_dns_logs' => true,
        ], 'viewer');

        return [
            [
                'id' => 'admin',
                'name' => 'Administrator',
                'description' => 'Full system access',
                'rank' => 100,
                'system' => true,
                'permissions' => $adminPerms,
                'max_assignable_rank' => 100,
            ],
            [
                'id' => 'manager',
                'name' => 'Manager',
                'description' => 'DNS access and can create Editor / Viewer operators only',
                'rank' => 60,
                'system' => true,
                'permissions' => $managerPerms,
                'max_assignable_rank' => 40,
            ],
            [
                'id' => 'editor',
                'name' => 'Editor',
                'description' => 'Can change DNS on allowed domains',
                'rank' => 40,
                'system' => true,
                'permissions' => $editorPerms,
                'max_assignable_rank' => 0,
            ],
            [
                'id' => 'viewer',
                'name' => 'Viewer',
                'description' => 'Read-only access to allowed domains',
                'rank' => 20,
                'system' => true,
                'permissions' => $viewerPerms,
                'max_assignable_rank' => 0,
            ],
        ];
    }

    public function all(): array
    {
        $roles = $this->store->read('roles', []);
        if (!is_array($roles) || $roles === []) {
            $roles = $this->defaultRoles();
            $this->store->write('roles', $roles);
        }
        usort($roles, static function (array $a, array $b): int {
            return ((int) ($b['rank'] ?? 0)) <=> ((int) ($a['rank'] ?? 0));
        });
        return $roles;
    }

    public function find(string $id): ?array
    {
        foreach ($this->all() as $role) {
            if (($role['id'] ?? '') === $id) {
                return $role;
            }
        }
        return null;
    }

    public function findOrFallback(string $id): array
    {
        $role = $this->find($id);
        if ($role !== null) {
            return $role;
        }
        if ($id === 'admin') {
            return $this->defaultRoles()[0];
        }
        $viewer = $this->find('viewer');
        return $viewer ?: $this->defaultRoles()[3];
    }

    public function saveAll(array $roles): void
    {
        $this->store->write('roles', array_values($roles));
    }

    public function upsert(array $role): void
    {
        $roles = $this->all();
        $found = false;
        foreach ($roles as $i => $existing) {
            if (($existing['id'] ?? '') === ($role['id'] ?? '')) {
                $role['system'] = !empty($existing['system']);
                $roles[$i] = $role;
                $found = true;
                break;
            }
        }
        if (!$found) {
            $role['system'] = false;
            $roles[] = $role;
        }
        $this->saveAll($roles);
    }

    public function delete(string $id): bool
    {
        $role = $this->find($id);
        if (!$role || !empty($role['system'])) {
            return false;
        }
        $roles = array_values(array_filter($this->all(), static function (array $r) use ($id): bool {
            return ($r['id'] ?? '') !== $id;
        }));
        $this->saveAll($roles);
        return true;
    }

    public function normalize(array $input, ?array $existing = null): array
    {
        $id = strtolower(trim((string) ($input['id'] ?? '')));
        $id = preg_replace('/[^a-z0-9_\-]/', '', $id) ?? '';
        if ($id === '') {
            throw new InvalidArgumentException('Role ID is required.');
        }

        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            throw new InvalidArgumentException('Role name is required.');
        }

        $rank = (int) ($input['rank'] ?? 0);
        if ($rank < 1) {
            $rank = 1;
        }
        if ($rank > 100) {
            $rank = 100;
        }

        $permissions = [];
        foreach (self::PERMISSIONS as $key => $_label) {
            $permissions[$key] = !empty($input['permissions'][$key]);
        }

        $maxAssignable = (int) ($input['max_assignable_rank'] ?? 0);
        if ($maxAssignable < 0) {
            $maxAssignable = 0;
        }
        if ($maxAssignable > 100) {
            $maxAssignable = 100;
        }
        if (empty($permissions['manage_operators'])) {
            $maxAssignable = 0;
        }

        return [
            'id' => $id,
            'name' => $name,
            'description' => trim((string) ($input['description'] ?? '')),
            'rank' => $rank,
            'system' => $existing ? !empty($existing['system']) : false,
            'permissions' => $permissions,
            'max_assignable_rank' => $maxAssignable,
        ];
    }

    public function permission(array $role, string $key): bool
    {
        $perms = $role['permissions'] ?? [];
        // Compat: mutate_dns still counted for DNS write checks if granular missing
        if (in_array($key, ['create_dns', 'edit_dns', 'delete_dns'], true)
            && empty($perms[$key])
            && !empty($perms['mutate_dns'])
        ) {
            return true;
        }
        return !empty($perms[$key]);
    }

    public function rank(array $role): int
    {
        return (int) ($role['rank'] ?? 0);
    }

    public function maxAssignableRank(array $role): int
    {
        if (!$this->permission($role, 'manage_operators')) {
            return 0;
        }
        return (int) ($role['max_assignable_rank'] ?? 0);
    }

    public function assignableRoles(array $actorRole): array
    {
        if (!$this->permission($actorRole, 'manage_operators')) {
            return [];
        }
        $actorRank = $this->rank($actorRole);
        $maxRank = min($actorRank, $this->maxAssignableRank($actorRole));
        $allowSameRank = $actorRank >= 100 && $maxRank >= 100;

        $out = [];
        foreach ($this->all() as $role) {
            $r = $this->rank($role);
            if ($allowSameRank) {
                if ($r <= $maxRank) {
                    $out[] = $role;
                }
            } elseif ($r < $actorRank && $r <= $maxRank) {
                $out[] = $role;
            }
        }
        return $out;
    }

    public function canAssignRole(array $actorRole, string $targetRoleId): bool
    {
        foreach ($this->assignableRoles($actorRole) as $role) {
            if (($role['id'] ?? '') === $targetRoleId) {
                return true;
            }
        }
        return false;
    }

    public function canManageOperator(array $actorRole, array $targetOperator): bool
    {
        if (!$this->permission($actorRole, 'manage_operators')) {
            return false;
        }
        $targetRole = $this->findOrFallback((string) ($targetOperator['role'] ?? 'viewer'));
        $targetRank = $this->rank($targetRole);
        $actorRank = $this->rank($actorRole);
        $maxRank = $this->maxAssignableRank($actorRole);

        if ($actorRank >= 100 && $maxRank >= 100) {
            return $targetRank <= $maxRank;
        }
        return $targetRank < $actorRank && $targetRank <= $maxRank;
    }

    public function label(string $roleId): string
    {
        $role = $this->find($roleId);
        return $role['name'] ?? $roleId;
    }
}
