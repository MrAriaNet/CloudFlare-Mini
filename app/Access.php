<?php

declare(strict_types=1);

final class Access
{
    private Auth $auth;
    private RoleService $roles;

    public function __construct(Auth $auth, RoleService $roles)
    {
        $this->auth = $auth;
        $this->roles = $roles;
    }

    public function roles(): RoleService
    {
        return $this->roles;
    }

    public function user(): ?array
    {
        return $this->auth->user();
    }

    public function role(?array $user = null): array
    {
        $user = $user ?? $this->user();
        if ($user === null) {
            return $this->roles->findOrFallback('viewer');
        }
        return $this->roles->findOrFallback((string) ($user['role'] ?? 'viewer'));
    }

    public function can(string $permission, ?array $user = null): bool
    {
        return $this->roles->permission($this->role($user), $permission);
    }

    public function isAdmin(): bool
    {
        $role = $this->role();
        return ($role['id'] ?? '') === 'admin' || $this->roles->rank($role) >= 100;
    }

    public function canManageAccounts(): bool
    {
        return $this->can('manage_accounts');
    }

    public function canSyncAccount(): bool
    {
        return $this->can('sync_account') || $this->can('manage_accounts');
    }

    public function canManageIpAllowlist(): bool
    {
        return $this->can('manage_ip_allowlist');
    }

    public function canManageOperators(): bool
    {
        return $this->can('manage_operators');
    }

    public function canManageRoles(): bool
    {
        return $this->can('manage_roles');
    }

    public function canViewDomains(): bool
    {
        return $this->can('view_domains');
    }

    public function canViewDns(): bool
    {
        return $this->can('view_dns');
    }

    public function canCreateDns(): bool
    {
        return $this->can('create_dns');
    }

    public function canEditDns(): bool
    {
        return $this->can('edit_dns');
    }

    public function canDeleteDns(): bool
    {
        return $this->can('delete_dns');
    }

    public function canRefreshDns(): bool
    {
        return $this->can('refresh_dns');
    }

    public function canViewPtrAudit(): bool
    {
        return $this->can('view_ptr_audit');
    }

    public function canRunPtrAudit(): bool
    {
        return $this->can('run_ptr_audit');
    }

    public function canManagePtrSettings(): bool
    {
        return $this->can('manage_ptr_settings');
    }

    public function canDeleteBrokenPtr(): bool
    {
        return $this->can('delete_broken_ptr');
    }

    /** Any DNS write capability */
    public function canMutateDns(): bool
    {
        return $this->canCreateDns() || $this->canEditDns() || $this->canDeleteDns();
    }

    public function canViewAuditLogs(): bool
    {
        return $this->can('view_audit_logs');
    }

    public function canViewDnsLogs(): bool
    {
        return $this->can('view_dns_logs');
    }

    public function canViewCronLogs(): bool
    {
        return $this->can('view_cron_logs');
    }

    public function canViewPtrLogs(): bool
    {
        return $this->can('view_ptr_logs') || $this->can('view_ptr_audit');
    }

    public function canViewLogs(): bool
    {
        return $this->canViewAuditLogs()
            || $this->canViewDnsLogs()
            || $this->canViewCronLogs()
            || $this->canViewPtrLogs();
    }

    public function canViewAllLogs(): bool
    {
        return $this->can('view_all_logs');
    }

    public function canClearAuditLogs(): bool
    {
        return $this->can('clear_audit_logs');
    }

    public function canClearDnsLogs(): bool
    {
        return $this->can('clear_dns_logs');
    }

    public function canClearCronLogs(): bool
    {
        return $this->can('clear_cron_logs');
    }

    public function canClearPtrLogs(): bool
    {
        return $this->can('clear_ptr_logs') || $this->can('manage_ptr_settings');
    }

    public function canForceSync(): bool
    {
        return $this->can('force_sync');
    }

    public function canAssignRole(string $roleId): bool
    {
        return $this->roles->canAssignRole($this->role(), $roleId);
    }

    public function assignableRoles(): array
    {
        return $this->roles->assignableRoles($this->role());
    }

    public function canManageOperator(array $targetOperator): bool
    {
        if ($this->user() === null) {
            return false;
        }
        return $this->roles->canManageOperator($this->role(), $targetOperator);
    }

    public function canAccessZone(string $zoneId): bool
    {
        $user = $this->user();
        if ($user === null) {
            return false;
        }
        if ($this->isAdmin() || ($user['domain_access'] ?? '') === 'all') {
            return true;
        }
        $allowed = $user['allowed_zone_ids'] ?? [];
        return in_array($zoneId, $allowed, true);
    }

    public function filterZones(array $zones): array
    {
        $user = $this->user();
        if ($user === null) {
            return [];
        }
        if ($this->isAdmin() || ($user['domain_access'] ?? '') === 'all') {
            return $zones;
        }
        $allowed = $user['allowed_zone_ids'] ?? [];
        return array_values(array_filter($zones, static function (array $zone) use ($allowed): bool {
            return in_array($zone['id'] ?? '', $allowed, true);
        }));
    }

    public function requireAdmin(): void
    {
        if (!$this->isAdmin()) {
            http_response_code(403);
            exit('Forbidden.');
        }
    }

    public function requirePermission(string $permission): void
    {
        if (!$this->can($permission)) {
            http_response_code(403);
            exit('Forbidden.');
        }
    }

    public function requireManageOperators(): void
    {
        $this->requirePermission('manage_operators');
    }

    public function requireZoneAccess(string $zoneId): void
    {
        if (!$this->canAccessZone($zoneId)) {
            http_response_code(403);
            exit('Forbidden: no access to this domain.');
        }
    }

    public function requireDnsPermission(string $permission, string $zoneId): void
    {
        $this->requireZoneAccess($zoneId);
        $this->requirePermission($permission);
    }

    public function requireDnsMutation(string $zoneId): void
    {
        $this->requireZoneAccess($zoneId);
        if (!$this->canMutateDns()) {
            http_response_code(403);
            exit('Forbidden: read-only access.');
        }
    }
}
