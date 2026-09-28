<?php

declare(strict_types=1);

final class SyncService
{
    private JsonStore $store;
    private Logger $logger;
    private bool $cronLogging = false;

    public function __construct(JsonStore $store, Logger $logger)
    {
        $this->store = $store;
        $this->logger = $logger;
    }

    public function useCronLogging(bool $enabled): void
    {
        $this->cronLogging = $enabled;
    }

    public function ensureFresh(?string $accountId = null, bool $force = false): void
    {
        // Web requests must stay fast — background sync belongs to cron/CLI.
        if (!$force && !$this->allowBackgroundSync()) {
            return;
        }

        $accounts = $this->store->read('accounts', []);
        foreach ($accounts as $account) {
            if ($accountId !== null && ($account['id'] ?? '') !== $accountId) {
                continue;
            }
            if (empty($account['api_token'])) {
                continue;
            }
            if ($force || $this->needsSync((string) $account['id'])) {
                $this->syncAccount($account);
            }
        }
    }

    public function allowBackgroundSync(): bool
    {
        if (PHP_SAPI === 'cli') {
            return true;
        }

        return (bool) config('web_lazy_sync', false);
    }

    public function needsSync(string $accountId): bool
    {
        $meta = $this->store->read('meta', []);
        $last = (int) ($meta[$accountId]['last_sync'] ?? 0);
        $interval = (int) config('sync_interval', 3600);
        return (time() - $last) >= $interval;
    }

    public function syncAccount(array $account): bool
    {
        $accountId = (string) ($account['id'] ?? '');
        if ($accountId === '' || empty($account['api_token'])) {
            return false;
        }

        try {
            $client = new CloudflareClient((string) $account['api_token']);
            $client->verifyToken();
            $zonesRaw = $client->listZones();

            $zones = [];
            $recordsByZone = [];
            $existingZones = $this->store->read('zones', []);
            $previousZoneIds = array_column($existingZones[$accountId] ?? [], 'id');

            foreach ($zonesRaw as $zone) {
                $zoneId = (string) ($zone['id'] ?? '');
                if ($zoneId === '') {
                    continue;
                }
                $zones[] = [
                    'id' => $zoneId,
                    'name' => (string) ($zone['name'] ?? ''),
                    'status' => (string) ($zone['status'] ?? ''),
                    'paused' => (bool) ($zone['paused'] ?? false),
                    'account_id' => $accountId,
                    'account_name' => (string) ($account['name'] ?? ''),
                ];

                $dns = $client->listDnsRecords($zoneId);
                $recordsByZone[$zoneId] = array_map([$this, 'normalizeRecord'], $dns);
            }

            $currentZoneIds = array_column($zones, 'id');

            $this->store->update('zones', function (array $all) use ($accountId, $zones): array {
                $all[$accountId] = $zones;
                return $all;
            }, []);

            $this->store->update('records', function (array $all) use ($previousZoneIds, $currentZoneIds, $recordsByZone): array {
                foreach ($previousZoneIds as $zid) {
                    if (!in_array($zid, $currentZoneIds, true)) {
                        unset($all[$zid]);
                    }
                }
                foreach ($currentZoneIds as $zid) {
                    $all[$zid] = $recordsByZone[$zid] ?? [];
                }
                return $all;
            }, []);

            $this->store->update('meta', function (array $meta) use ($accountId): array {
                $meta[$accountId] = [
                    'last_sync' => time(),
                    'last_sync_iso' => now_iso(),
                    'last_error' => null,
                ];
                return $meta;
            }, []);

            $this->store->update('accounts', function (array $accounts) use ($accountId): array {
                foreach ($accounts as &$acc) {
                    if (($acc['id'] ?? '') === $accountId) {
                        $acc['status'] = 'ok';
                        $acc['last_sync'] = now_iso();
                    }
                }
                unset($acc);
                return $accounts;
            }, []);

            return true;
        } catch (Throwable $e) {
            $this->store->update('meta', function (array $meta) use ($accountId, $e): array {
                $meta[$accountId] = array_merge($meta[$accountId] ?? [], [
                    'last_error' => $e->getMessage(),
                    'last_error_at' => now_iso(),
                ]);
                return $meta;
            }, []);

            $this->store->update('accounts', function (array $accounts) use ($accountId, $e): array {
                foreach ($accounts as &$acc) {
                    if (($acc['id'] ?? '') === $accountId) {
                        $acc['status'] = 'error';
                        $acc['last_error'] = $e->getMessage();
                    }
                }
                unset($acc);
                return $accounts;
            }, []);

            $payload = [
                'account_id' => $accountId,
                'account_name' => $account['name'] ?? '',
                'error' => $e->getMessage(),
            ];
            if ($this->cronLogging) {
                $this->logger->cron('sync.failed', ['username' => 'cron'], $payload);
            } else {
                $this->logger->log('sync.failed', null, $payload);
            }

            return false;
        }
    }

    public function syncZoneRecords(array $account, string $zoneId): void
    {
        $client = new CloudflareClient((string) $account['api_token']);
        $dns = $client->listDnsRecords($zoneId);
        $normalized = array_map([$this, 'normalizeRecord'], $dns);
        $this->store->update('records', function (array $all) use ($zoneId, $normalized): array {
            $all[$zoneId] = $normalized;
            return $all;
        }, []);
    }

    public function upsertCachedRecord(string $zoneId, array $record): void
    {
        $normalized = $this->normalizeRecord($record);
        $this->store->update('records', function (array $all) use ($zoneId, $normalized): array {
            $list = $all[$zoneId] ?? [];
            $found = false;
            foreach ($list as $i => $row) {
                if (($row['id'] ?? '') === ($normalized['id'] ?? '')) {
                    $list[$i] = $normalized;
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $list[] = $normalized;
            }
            $all[$zoneId] = $list;
            return $all;
        }, []);
    }

    public function removeCachedRecord(string $zoneId, string $recordId): void
    {
        $this->store->update('records', function (array $all) use ($zoneId, $recordId): array {
            $list = $all[$zoneId] ?? [];
            $all[$zoneId] = array_values(array_filter($list, static function (array $row) use ($recordId): bool {
                return ($row['id'] ?? '') !== $recordId;
            }));
            return $all;
        }, []);
    }

    public function findAccount(string $accountId): ?array
    {
        foreach ($this->store->read('accounts', []) as $account) {
            if (($account['id'] ?? '') === $accountId) {
                return $account;
            }
        }
        return null;
    }

    public function findZone(string $zoneId): ?array
    {
        $all = $this->store->read('zones', []);
        foreach ($all as $zones) {
            foreach ($zones as $zone) {
                if (($zone['id'] ?? '') === $zoneId) {
                    return $zone;
                }
            }
        }
        return null;
    }

    public function findRecord(string $zoneId, string $recordId): ?array
    {
        $records = $this->store->read('records', []);
        foreach ($records[$zoneId] ?? [] as $record) {
            if (($record['id'] ?? '') === $recordId) {
                return $record;
            }
        }
        return null;
    }

    public function normalizeRecord(array $record): array
    {
        return [
            'id' => (string) ($record['id'] ?? ''),
            'type' => (string) ($record['type'] ?? ''),
            'name' => (string) ($record['name'] ?? ''),
            'content' => (string) ($record['content'] ?? ''),
            'ttl' => (int) ($record['ttl'] ?? 1),
            'proxied' => (bool) ($record['proxied'] ?? false),
            'priority' => $record['priority'] ?? null,
            'proxiable' => (bool) ($record['proxiable'] ?? false),
            'comment' => $record['comment'] ?? null,
            'created_on' => $record['created_on'] ?? null,
            'modified_on' => $record['modified_on'] ?? null,
        ];
    }
}
