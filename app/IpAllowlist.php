<?php

declare(strict_types=1);

final class IpAllowlist
{
    private JsonStore $store;

    public function __construct(JsonStore $store)
    {
        $this->store = $store;
        $this->ensureDefaults();
    }

    public function ensureDefaults(): void
    {
        $data = $this->store->read('ip_allowlist', null);
        if (!is_array($data) || !isset($data['enabled'])) {
            $this->store->write('ip_allowlist', [
                'enabled' => false,
                'entries' => [],
                'updated_at' => null,
            ]);
        }
    }

    public function get(): array
    {
        $data = $this->store->read('ip_allowlist', []);
        if (!is_array($data)) {
            $data = [];
        }
        return [
            'enabled' => !empty($data['enabled']),
            'entries' => array_values(array_filter(array_map('strval', $data['entries'] ?? []))),
            'updated_at' => $data['updated_at'] ?? null,
        ];
    }

    public function save(bool $enabled, array $entries): void
    {
        $clean = [];
        foreach ($entries as $entry) {
            $entry = trim((string) $entry);
            if ($entry === '' || strpos($entry, '#') === 0) {
                continue;
            }
            if (!$this->isValidEntry($entry)) {
                throw new InvalidArgumentException('Invalid IP or subnet: ' . $entry);
            }
            $clean[] = $entry;
        }
        $clean = array_values(array_unique($clean));

        $this->store->write('ip_allowlist', [
            'enabled' => $enabled,
            'entries' => $clean,
            'updated_at' => now_iso(),
        ]);
    }

    public function isEnabled(): bool
    {
        return !empty($this->get()['enabled']);
    }

    /**
     * When disabled or empty list while enabled, allow everyone / deny all respectively.
     * Empty + enabled = deny all (except we warn on save).
     */
    public function isAllowed(?string $ip = null): bool
    {
        $config = $this->get();
        if (empty($config['enabled'])) {
            return true;
        }

        $ip = $ip !== null && $ip !== '' ? $ip : client_ip();
        if ($ip === '' || $ip === 'cli' || $ip === '0.0.0.0') {
            return false;
        }

        $entries = $config['entries'];
        if ($entries === []) {
            return false;
        }

        foreach ($entries as $entry) {
            if ($this->match($ip, $entry)) {
                return true;
            }
        }
        return false;
    }

    public function isValidEntry(string $entry): bool
    {
        if (strpos($entry, '/') !== false) {
            $parts = explode('/', $entry, 2);
            if (count($parts) !== 2) {
                return false;
            }
            $mask = $parts[1];
            if (!ctype_digit($mask)) {
                return false;
            }
            $maskInt = (int) $mask;
            if (filter_var($parts[0], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                return $maskInt >= 0 && $maskInt <= 32;
            }
            if (filter_var($parts[0], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                return $maskInt >= 0 && $maskInt <= 128;
            }
            return false;
        }

        return (bool) filter_var($entry, FILTER_VALIDATE_IP);
    }

    public function match(string $ip, string $entry): bool
    {
        if (strpos($entry, '/') === false) {
            return $this->ipsEqual($ip, $entry);
        }

        $parts = explode('/', $entry, 2);
        $subnet = $parts[0];
        $prefix = (int) $parts[1];

        $ipBin = @inet_pton($ip);
        $subnetBin = @inet_pton($subnet);
        if ($ipBin === false || $subnetBin === false || strlen($ipBin) !== strlen($subnetBin)) {
            return false;
        }

        $maxBits = strlen($ipBin) * 8;
        if ($prefix < 0 || $prefix > $maxBits) {
            return false;
        }

        $fullBytes = intdiv($prefix, 8);
        $remainBits = $prefix % 8;

        if ($fullBytes > 0 && substr($ipBin, 0, $fullBytes) !== substr($subnetBin, 0, $fullBytes)) {
            return false;
        }

        if ($remainBits === 0) {
            return true;
        }

        $mask = (~((1 << (8 - $remainBits)) - 1)) & 0xFF;
        return (ord($ipBin[$fullBytes]) & $mask) === (ord($subnetBin[$fullBytes]) & $mask);
    }

    private function ipsEqual(string $a, string $b): bool
    {
        $aBin = @inet_pton($a);
        $bBin = @inet_pton($b);
        if ($aBin === false || $bBin === false) {
            return strcasecmp($a, $b) === 0;
        }
        return $aBin === $bBin;
    }
}
