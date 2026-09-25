<?php

declare(strict_types=1);

final class Access
{
    public const ROLE_ADMIN = 'admin';
    public const ROLE_EDITOR = 'editor';
    public const ROLE_VIEWER = 'viewer';

    private Auth $auth;

    public function __construct(Auth $auth)
    {
        $this->auth = $auth;
    }

    public function user(): ?array
    {
        return $this->auth->user();
    }

    public function isAdmin(): bool
    {
        $user = $this->user();
        return $user !== null && ($user['role'] ?? '') === self::ROLE_ADMIN;
    }

    public function canManageAccounts(): bool
    {
        return $this->isAdmin();
    }

    public function canManageOperators(): bool
    {
        return $this->isAdmin();
    }

    public function canViewLogs(): bool
    {
        return $this->user() !== null;
    }

    public function canViewAllLogs(): bool
    {
        return $this->isAdmin();
    }

    public function canMutateDns(): bool
    {
        $user = $this->user();
        if ($user === null) {
            return false;
        }
        $role = $user['role'] ?? '';
        return $role === self::ROLE_ADMIN || $role === self::ROLE_EDITOR;
    }

    public function canAccessZone(string $zoneId): bool
    {
        $user = $this->user();
        if ($user === null) {
            return false;
        }
        if (($user['role'] ?? '') === self::ROLE_ADMIN) {
            return true;
        }
        if (($user['domain_access'] ?? 'selected') === 'all') {
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
        if (($user['role'] ?? '') === self::ROLE_ADMIN || ($user['domain_access'] ?? '') === 'all') {
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

    public function requireZoneAccess(string $zoneId): void
    {
        if (!$this->canAccessZone($zoneId)) {
            http_response_code(403);
            exit('Forbidden: no access to this domain.');
        }
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
