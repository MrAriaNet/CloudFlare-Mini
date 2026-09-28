<?php

declare(strict_types=1);

final class PtrService
{
    private JsonStore $store;
    private SyncService $sync;
    private Logger $logger;

    public function __construct(JsonStore $store, SyncService $sync, Logger $logger)
    {
        $this->store = $store;
        $this->sync = $sync;
        $this->logger = $logger;
        $this->ensureDefaults();
    }

    public function ensureDefaults(): void
    {
        $settings = $this->store->read('ptr_settings', null);
        if (!is_array($settings) || !isset($settings['auto_check'])) {
            $this->store->write('ptr_settings', [
                'auto_check' => false,
                'auto_delete' => false,
                'use_ping' => false,
                'audit_interval' => 21600,
                'updated_at' => null,
            ]);
        }
        if (!is_file($this->store->path('ptr_issues'))) {
            $this->store->write('ptr_issues', []);
        }
    }

    public function getSettings(): array
    {
        $s = $this->store->read('ptr_settings', []);
        $interval = isset($s['audit_interval']) ? (int) $s['audit_interval'] : 21600;
        if ($interval < 3600) {
            $interval = 3600;
        }
        return [
            'auto_check' => !empty($s['auto_check']),
            'auto_delete' => !empty($s['auto_delete']),
            'use_ping' => !empty($s['use_ping']),
            'audit_interval' => $interval,
            'updated_at' => $s['updated_at'] ?? null,
            'last_run_at' => $s['last_run_at'] ?? null,
            'last_run_summary' => $s['last_run_summary'] ?? null,
            'audit_cursor' => (int) ($s['audit_cursor'] ?? 0),
        ];
    }

    public function saveSettings(array $input): void
    {
        $current = $this->getSettings();
        $interval = isset($input['audit_interval']) ? (int) $input['audit_interval'] : $current['audit_interval'];
        if ($interval < 3600) {
            $interval = 3600;
        }
        if ($interval > 86400 * 7) {
            $interval = 86400 * 7;
        }
        $this->store->write('ptr_settings', [
            'auto_check' => !empty($input['auto_check']),
            'auto_delete' => !empty($input['auto_delete']),
            'use_ping' => !empty($input['use_ping']),
            'audit_interval' => $interval,
            'updated_at' => now_iso(),
            'last_run_at' => $current['last_run_at'],
            'last_run_summary' => $current['last_run_summary'],
            'audit_cursor' => $current['audit_cursor'],
        ]);
    }

    public function shouldAutoRun(): bool
    {
        $settings = $this->getSettings();
        if (empty($settings['auto_check'])) {
            return false;
        }
        $last = $settings['last_run_at'] ?? null;
        if (!$last) {
            return true;
        }
        $ts = strtotime((string) $last);
        if ($ts === false) {
            return true;
        }
        return (time() - $ts) >= (int) $settings['audit_interval'];
    }

    public function isReverseZone(string $zoneName): bool
    {
        $name = strtolower(rtrim($zoneName, '.'));
        return (bool) preg_match('/\.in-addr\.arpa$/i', $name) || $name === 'in-addr.arpa';
    }

    /**
     * @return array{cidr:string,network:string,prefix:int,label:string}|null
     */
    public function zoneIpRange(string $zoneName): ?array
    {
        $name = strtolower(rtrim($zoneName, '.'));
        if (!$this->isReverseZone($name)) {
            return null;
        }

        $prefixPart = preg_replace('/\.in-addr\.arpa$/i', '', $name);
        if ($prefixPart === null || $prefixPart === '' || $prefixPart === 'in-addr') {
            return [
                'cidr' => '0.0.0.0/0',
                'network' => '0.0.0.0',
                'prefix' => 0,
                'label' => 'IPv4 reverse (full)',
            ];
        }

        $octets = array_values(array_filter(explode('.', $prefixPart), static function ($o) {
            return $o !== '';
        }));
        if ($octets === [] || count($octets) > 3) {
            return null;
        }

        foreach ($octets as $o) {
            if (!ctype_digit((string) $o) || (int) $o > 255) {
                return null;
            }
        }

        $reversed = array_reverse($octets);
        while (count($reversed) < 4) {
            $reversed[] = '0';
        }
        $network = implode('.', array_slice($reversed, 0, 4));
        $prefix = count($octets) * 8;

        return [
            'cidr' => $network . '/' . $prefix,
            'network' => $network,
            'prefix' => $prefix,
            'label' => $network . '/' . $prefix,
        ];
    }

    public function ipToPtrName(string $ip): ?string
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return null;
        }
        $parts = explode('.', $ip);
        return implode('.', array_reverse($parts)) . '.in-addr.arpa';
    }

    public function ptrNameToIp(string $name): ?string
    {
        $name = strtolower(rtrim($name, '.'));
        if (!preg_match('/^(\d+)\.(\d+)\.(\d+)\.(\d+)\.in-addr\.arpa$/', $name, $m)) {
            return null;
        }
        $ip = $m[4] . '.' . $m[3] . '.' . $m[2] . '.' . $m[1];
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ? $ip : null;
    }

    /**
     * Resolve PTR record name (+ reverse zone) to the IPv4 it represents.
     * Handles absolute FQDNs and relative names inside the zone.
     */
    public function ptrRecordToIp(string $recordName, string $zoneName = ''): ?string
    {
        $recordName = strtolower(rtrim(trim($recordName), '.'));
        $zoneName = strtolower(rtrim(trim($zoneName), '.'));

        $direct = $this->ptrNameToIp($recordName);
        if ($direct !== null) {
            return $direct;
        }

        if ($recordName === '' || $zoneName === '' || !$this->isReverseZone($zoneName)) {
            return null;
        }

        if ($recordName === '@') {
            return $this->ptrNameToIp($zoneName);
        }

        // Already ends with zone — try as absolute
        $suffix = '.' . $zoneName;
        if (substr($recordName, -strlen($suffix)) === $suffix || $recordName === $zoneName) {
            return $this->ptrNameToIp($recordName === $zoneName ? $zoneName : $recordName);
        }

        // Relative label(s), e.g. "131" in zone "5.107.87.in-addr.arpa"
        if (!preg_match('/^(\d+)(?:\.(\d+)){0,3}$/', $recordName)) {
            return null;
        }

        return $this->ptrNameToIp($recordName . '.' . $zoneName);
    }

    /**
     * Find PTR records matching an IP across cached zones.
     *
     * @return array<int, array{zone:array,record:array,ip:string,hostname:string}>
     */
    public function findByIp(string $ip, ?callable $zoneAllowed = null): array
    {
        $ptrName = $this->ipToPtrName($ip);
        if ($ptrName === null) {
            return [];
        }

        $results = [];
        $zonesByAccount = $this->store->read('zones', []);
        $recordsByZone = $this->store->read('records', []);

        foreach ($zonesByAccount as $zones) {
            foreach ($zones as $zone) {
                if ($zoneAllowed && !$zoneAllowed($zone)) {
                    continue;
                }
                $zoneName = strtolower(rtrim((string) ($zone['name'] ?? ''), '.'));
                if (!$this->isReverseZone($zoneName)) {
                    continue;
                }
                // Zone must be a suffix of the PTR name
                if ($ptrName !== $zoneName && substr($ptrName, -strlen('.' . $zoneName)) !== '.' . $zoneName) {
                    continue;
                }

                $zoneId = (string) ($zone['id'] ?? '');
                foreach ($recordsByZone[$zoneId] ?? [] as $record) {
                    if (strtoupper((string) ($record['type'] ?? '')) !== 'PTR') {
                        continue;
                    }
                    $recName = strtolower(rtrim((string) ($record['name'] ?? ''), '.'));
                    $recIp = $this->ptrRecordToIp($recName, $zoneName);
                    if ($recName === $ptrName || $recIp === $ip) {
                        $key = (string) ($zone['id'] ?? '') . ':' . (string) ($record['id'] ?? '');
                        $results[$key] = [
                            'zone' => $zone,
                            'record' => $record,
                            'ip' => $ip,
                            'hostname' => rtrim((string) ($record['content'] ?? ''), '.'),
                        ];
                    }
                }
            }
        }

        return array_values($results);
    }

    /**
     * Reverse zones that can hold a PTR for this IP (most specific first).
     *
     * @return array<int, array>
     */
    public function findZonesCoveringIp(string $ip, ?callable $zoneAllowed = null): array
    {
        $ptrName = $this->ipToPtrName($ip);
        if ($ptrName === null) {
            return [];
        }

        $matches = [];
        foreach ($this->store->read('zones', []) as $zones) {
            foreach ($zones as $zone) {
                if ($zoneAllowed && !$zoneAllowed($zone)) {
                    continue;
                }
                $zoneName = strtolower(rtrim((string) ($zone['name'] ?? ''), '.'));
                if (!$this->isReverseZone($zoneName)) {
                    continue;
                }
                if ($ptrName === $zoneName || substr($ptrName, -strlen('.' . $zoneName)) === '.' . $zoneName) {
                    $range = $this->zoneIpRange($zoneName);
                    $zone['ip_range'] = $range['label'] ?? null;
                    $zone['_specificity'] = strlen($zoneName);
                    $matches[] = $zone;
                }
            }
        }

        usort($matches, static function (array $a, array $b): int {
            return ((int) ($b['_specificity'] ?? 0)) <=> ((int) ($a['_specificity'] ?? 0));
        });

        foreach ($matches as &$m) {
            unset($m['_specificity']);
        }
        unset($m);

        return $matches;
    }

    /**
     * Relative DNS name inside a reverse zone for Cloudflare API.
     */
    public function relativePtrName(string $ip, string $zoneName): ?string
    {
        $ptrName = $this->ipToPtrName($ip);
        $zoneName = strtolower(rtrim($zoneName, '.'));
        if ($ptrName === null || !$this->isReverseZone($zoneName)) {
            return null;
        }
        if ($ptrName === $zoneName) {
            return '@';
        }
        $suffix = '.' . $zoneName;
        if (substr($ptrName, -strlen($suffix)) !== $suffix) {
            return null;
        }
        return substr($ptrName, 0, -strlen($suffix));
    }

    /**
     * Create a PTR record for an IP in the given reverse zone.
     *
     * @return array created/normalized record
     */
    public function createPtrForIp(string $ip, string $zoneId, string $hostname, ?array $actor = null): array
    {
        $hostname = trim($hostname);
        $hostname = rtrim($hostname, '.');
        if ($hostname === '') {
            throw new InvalidArgumentException('Hostname is required.');
        }
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            throw new InvalidArgumentException('Invalid IPv4 address.');
        }

        $zone = $this->sync->findZone($zoneId);
        if (!$zone) {
            throw new RuntimeException('Zone not found.');
        }
        $zoneName = (string) ($zone['name'] ?? '');
        if (!$this->isReverseZone($zoneName)) {
            throw new RuntimeException('Selected zone is not a reverse (in-addr.arpa) zone.');
        }

        $relative = $this->relativePtrName($ip, $zoneName);
        if ($relative === null) {
            throw new RuntimeException('This IP is not inside the selected reverse zone.');
        }

        // Avoid duplicate
        $existing = $this->findByIp($ip);
        foreach ($existing as $hit) {
            if (($hit['zone']['id'] ?? '') === $zoneId) {
                throw new RuntimeException('A PTR record for this IP already exists in that zone.');
            }
        }

        $account = $this->sync->findAccount((string) ($zone['account_id'] ?? ''));
        if (!$account) {
            throw new RuntimeException('Cloudflare account for this zone was not found.');
        }

        $payload = [
            'type' => 'PTR',
            'name' => $relative,
            'content' => $hostname,
            'ttl' => 1,
        ];

        $client = new CloudflareClient((string) $account['api_token']);
        $result = $client->createDnsRecord($zoneId, $payload);
        $record = $result['result'] ?? [];
        $this->sync->upsertCachedRecord($zoneId, $record);

        $this->logger->ptr('ptr.create', $actor, [
            'account_id' => $account['id'] ?? '',
            'zone_id' => $zoneId,
            'zone_name' => $zoneName,
            'after' => $this->sync->normalizeRecord($record),
            'reason' => 'ptr_create_from_ip_search',
            'ip' => $ip,
            'hostname' => $hostname,
        ]);

        return $this->sync->normalizeRecord($record);
    }

    /**
     * Resolve hostname to IPs via DNS (and optional ping).
     *
     * Result statuses:
     * - ok: hostname resolves (or pings) to the expected IP
     * - mismatch: hostname resolves to one or more IPs, but NOT the expected one
     * - unresolved: lookup failed / empty — do NOT treat as auto-delete candidate
     *
     * @return array{dns_ips:string[],ping_ips:string[],resolved_ips:string[],ok:bool,status:string,detail:string}
     */
    public function checkHostnamePointsToIp(string $hostname, string $expectedIp, bool $usePing = true): array
    {
        $hostname = strtolower(rtrim(trim($hostname), '.'));
        $expectedIp = trim($expectedIp);

        $dnsIps = $this->resolveDnsIps($hostname);
        $pingIps = [];
        if ($usePing) {
            $pingIps = $this->pingResolveIps($hostname);
        }

        $all = array_values(array_unique(array_merge($dnsIps, $pingIps)));
        $pointsToExpected = in_array($expectedIp, $dnsIps, true) || in_array($expectedIp, $pingIps, true);

        if ($pointsToExpected) {
            $status = 'ok';
            $ok = true;
        } elseif ($all !== []) {
            // Positive proof it points elsewhere
            $status = 'mismatch';
            $ok = false;
        } else {
            // Soft failure: DNS/ping gave nothing — often transient or local resolver issue
            $status = 'unresolved';
            $ok = false;
        }

        $detailParts = [];
        $detailParts[] = 'Expected: ' . $expectedIp;
        $detailParts[] = 'DNS: ' . ($dnsIps ? implode(', ', $dnsIps) : 'none');
        if ($usePing) {
            $detailParts[] = 'Ping: ' . ($pingIps ? implode(', ', $pingIps) : 'none/unavailable');
        }
        $detailParts[] = 'Status: ' . $status;

        return [
            'dns_ips' => $dnsIps,
            'ping_ips' => $pingIps,
            'resolved_ips' => $all,
            'ok' => $ok,
            'status' => $status,
            'detail' => implode(' | ', $detailParts),
        ];
    }

    /**
     * @return string[]
     */
    public function resolveDnsIps(string $hostname): array
    {
        $hostname = strtolower(rtrim(trim($hostname), '.'));
        if ($hostname === '' || !preg_match('/^[a-z0-9._-]+$/i', $hostname)) {
            return [];
        }

        $ips = [];

        // Prefer dns_get_record (A) — more reliable than gethostbynamel alone
        if (function_exists('dns_get_record')) {
            $records = @dns_get_record($hostname, DNS_A);
            if (is_array($records)) {
                foreach ($records as $row) {
                    $ip = (string) ($row['ip'] ?? '');
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                        $ips[] = $ip;
                    }
                }
            }
        }

        if ($ips === []) {
            $list = @gethostbynamel($hostname);
            if (is_array($list)) {
                foreach ($list as $ip) {
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                        $ips[] = $ip;
                    }
                }
            }
        }

        // Last resort single lookup
        if ($ips === []) {
            $one = @gethostbyname($hostname);
            if (is_string($one) && $one !== $hostname && filter_var($one, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                $ips[] = $one;
            }
        }

        return array_values(array_unique($ips));
    }

    /**
     * Best-effort ping with hard timeout (avoid hanging cron processes).
     *
     * @return string[]
     */
    public function pingResolveIps(string $hostname): array
    {
        $hostname = preg_replace('/[^a-zA-Z0-9.\-_]/', '', $hostname) ?? '';
        if ($hostname === '' || !function_exists('exec')) {
            return [];
        }

        $output = [];
        $code = 0;
        if (stripos(PHP_OS, 'WIN') === 0) {
            @exec('ping -n 1 -w 800 ' . escapeshellarg($hostname) . ' 2>&1', $output, $code);
        } else {
            $cmd = 'timeout 2s ping -c 1 -W 1 ' . escapeshellarg($hostname) . ' 2>&1';
            @exec($cmd, $output, $code);
        }

        $text = implode("\n", $output);
        $ips = [];
        if (preg_match_all('/\b(\d{1,3}(?:\.\d{1,3}){3})\b/', $text, $m)) {
            foreach ($m[1] as $ip) {
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                    $ips[] = $ip;
                }
            }
        }
        return array_values(array_unique($ips));
    }

    public function getIssues(): array
    {
        $issues = $this->store->read('ptr_issues', []);
        return is_array($issues) ? $issues : [];
    }

    public function saveIssues(array $issues): void
    {
        $unique = [];
        foreach ($issues as $issue) {
            if (!is_array($issue)) {
                continue;
            }
            $key = (string) ($issue['zone_id'] ?? '') . ':' . (string) ($issue['record_id'] ?? '');
            if ($key === ':') {
                $key = (string) ($issue['id'] ?? uuid());
            }
            // Keep newest by detected_at when duplicate
            if (!isset($unique[$key])) {
                $unique[$key] = $issue;
                continue;
            }
            $oldTs = strtotime((string) ($unique[$key]['detected_at'] ?? '')) ?: 0;
            $newTs = strtotime((string) ($issue['detected_at'] ?? '')) ?: 0;
            if ($newTs >= $oldTs) {
                $unique[$key] = $issue;
            }
        }
        $this->store->write('ptr_issues', array_values($unique));
    }

    /**
     * Scan PTR records. Options:
     * - fast: bool — skip ping (recommended for cron)
     * - limit: int — max records per run (0 = all); uses rotating cursor
     * - use_ping: bool — override settings
     *
     * @return array{checked:int,mismatched:int,deleted:int,ok:int,open_issues:int,issues:array}
     */
    public function runAudit(?callable $zoneAllowed = null, ?array $actor = null, array $options = []): array
    {
        $settings = $this->getSettings();
        $fast = !empty($options['fast']);
        $usePing = array_key_exists('use_ping', $options)
            ? !empty($options['use_ping'])
            : (!$fast && !empty($settings['use_ping']));
        $autoDelete = !empty($settings['auto_delete']);
        $limit = isset($options['limit']) ? (int) $options['limit'] : 0;
        if ($limit < 0) {
            $limit = 0;
        }

        $checked = 0;
        $okCount = 0;
        $mismatched = 0;
        $deleted = 0;
        $newIssues = [];

        $candidates = [];
        $zonesByAccount = $this->store->read('zones', []);
        $recordsByZone = $this->store->read('records', []);

        foreach ($zonesByAccount as $zones) {
            foreach ($zones as $zone) {
                if ($zoneAllowed && !$zoneAllowed($zone)) {
                    continue;
                }
                $zoneName = (string) ($zone['name'] ?? '');
                if (!$this->isReverseZone($zoneName)) {
                    continue;
                }
                $zoneId = (string) ($zone['id'] ?? '');
                foreach ($recordsByZone[$zoneId] ?? [] as $record) {
                    if (strtoupper((string) ($record['type'] ?? '')) !== 'PTR') {
                        continue;
                    }
                    $candidates[] = ['zone' => $zone, 'record' => $record];
                }
            }
        }

        $totalCandidates = count($candidates);
        $cursor = (int) ($settings['audit_cursor'] ?? 0);
        if ($cursor < 0 || ($totalCandidates > 0 && $cursor >= $totalCandidates)) {
            $cursor = 0;
        }

        if ($limit > 0 && $totalCandidates > 0) {
            $slice = [];
            for ($i = 0; $i < $limit && $i < $totalCandidates; $i++) {
                $idx = ($cursor + $i) % $totalCandidates;
                $slice[] = $candidates[$idx];
            }
            $candidates = $slice;
            $nextCursor = ($cursor + $limit) % $totalCandidates;
        } else {
            $nextCursor = 0;
        }

        $touchedKeys = [];
        foreach ($candidates as $item) {
            $zone = $item['zone'];
            $record = $item['record'];
            $zoneId = (string) ($zone['id'] ?? '');
            $accountId = (string) ($zone['account_id'] ?? '');
            $zoneName = (string) ($zone['name'] ?? '');
            $account = $this->sync->findAccount($accountId);

            $checked++;
            $recName = (string) ($record['name'] ?? '');
            $ip = $this->ptrRecordToIp($recName, $zoneName);
            $hostname = strtolower(rtrim(trim((string) ($record['content'] ?? '')), '.'));
            $key = $zoneId . ':' . (string) ($record['id'] ?? '');
            $touchedKeys[$key] = true;

            if ($ip === null || $hostname === '') {
                $mismatched++;
                $newIssues[] = $this->issueRow(
                    $zone,
                    $record,
                    $ip ?: '',
                    $hostname,
                    'invalid',
                    'Invalid PTR name or empty hostname (cannot derive IP from record/zone)',
                    []
                );
                continue;
            }

            $check = $this->checkHostnamePointsToIp($hostname, $ip, $usePing);
            if (!empty($check['ok']) || ($check['status'] ?? '') === 'ok') {
                $okCount++;
                continue;
            }

            $status = (string) ($check['status'] ?? 'mismatch');
            // Soft DNS failure: list as issue, never auto-delete (avoids wiping good PTRs on resolver blips)
            if ($status === 'unresolved') {
                $mismatched++;
                $newIssues[] = $this->issueRow(
                    $zone,
                    $record,
                    $ip,
                    $hostname,
                    'unresolved',
                    $check['detail'] . ' — not auto-deleted (no positive mismatch)',
                    $check['resolved_ips']
                );
                continue;
            }

            $mismatched++;
            $issue = $this->issueRow(
                $zone,
                $record,
                $ip,
                $hostname,
                'mismatch',
                $check['detail'],
                $check['resolved_ips']
            );

            // Auto-delete ONLY when hostname resolves to a different IP (hard mismatch)
            if ($autoDelete && $account && $status === 'mismatch' && !empty($check['resolved_ips'])) {
                try {
                    $client = new CloudflareClient((string) $account['api_token']);
                    $client->deleteDnsRecord($zoneId, (string) $record['id']);
                    $this->sync->removeCachedRecord($zoneId, (string) $record['id']);
                    $this->logger->ptr('ptr.delete', $actor ?: ['username' => 'ptr_audit'], [
                        'account_id' => $accountId,
                        'zone_id' => $zoneId,
                        'zone_name' => $zoneName,
                        'before' => $record,
                        'reason' => 'ptr_auto_delete_mismatch',
                        'ip' => $ip,
                        'hostname' => $hostname,
                        'resolved_ips' => $check['resolved_ips'],
                        'detail' => $check['detail'],
                    ]);
                    $deleted++;
                    $issue['status'] = 'auto_deleted';
                } catch (Throwable $e) {
                    $issue['detail'] .= ' | Auto-delete failed: ' . $e->getMessage();
                    $newIssues[] = $issue;
                }
            } else {
                $newIssues[] = $issue;
            }
        }

        $openNew = array_values(array_filter($newIssues, static function (array $i): bool {
            return ($i['status'] ?? '') !== 'auto_deleted';
        }));

        if ($limit > 0) {
            $merged = [];
            foreach ($this->getIssues() as $old) {
                $key = (string) ($old['zone_id'] ?? '') . ':' . (string) ($old['record_id'] ?? '');
                if (!isset($touchedKeys[$key])) {
                    $merged[] = $old;
                }
            }
            foreach ($openNew as $issue) {
                $merged[] = $issue;
            }
            $this->saveIssues($merged);
            $openCount = count($merged);
        } else {
            $this->saveIssues($openNew);
            $openCount = count($openNew);
        }

        $summary = [
            'checked' => $checked,
            'ok' => $okCount,
            'mismatched' => $mismatched,
            'deleted' => $deleted,
            'open_issues' => $openCount,
            'batch_total_candidates' => $totalCandidates,
            'fast' => $fast,
        ];

        $settings = $this->getSettings();
        $this->store->write('ptr_settings', [
            'auto_check' => $settings['auto_check'],
            'auto_delete' => $settings['auto_delete'],
            'use_ping' => $settings['use_ping'],
            'audit_interval' => $settings['audit_interval'],
            'updated_at' => $settings['updated_at'],
            'last_run_at' => now_iso(),
            'last_run_summary' => $summary,
            'audit_cursor' => $nextCursor,
        ]);

        $this->logger->ptr('ptr.audit', $actor, $summary);

        return array_merge($summary, ['issues' => $this->getIssues()]);
    }

    private function issueRow(array $zone, array $record, string $ip, string $hostname, string $status, string $detail, array $resolvedIps): array
    {
        return [
            'id' => uuid(),
            'detected_at' => now_iso(),
            'status' => $status,
            'ip' => $ip,
            'hostname' => $hostname,
            'zone_id' => $zone['id'] ?? '',
            'zone_name' => $zone['name'] ?? '',
            'account_id' => $zone['account_id'] ?? '',
            'account_name' => $zone['account_name'] ?? '',
            'record_id' => $record['id'] ?? '',
            'record_name' => $record['name'] ?? '',
            'record_content' => $record['content'] ?? '',
            'resolved_ips' => $resolvedIps,
            'detail' => $detail,
        ];
    }

    /**
     * Delete one or more broken PTR records from Cloudflare + issues list.
     *
     * @param string[] $issueIds
     */
    public function deleteIssues(array $issueIds, ?array $actor = null): array
    {
        $issues = $this->getIssues();
        $deleted = 0;
        $failed = 0;
        $remaining = [];

        foreach ($issues as $issue) {
            $iid = (string) ($issue['id'] ?? '');
            if (!in_array($iid, $issueIds, true)) {
                $remaining[] = $issue;
                continue;
            }

            $zoneId = (string) ($issue['zone_id'] ?? '');
            $recordId = (string) ($issue['record_id'] ?? '');
            $account = $this->sync->findAccount((string) ($issue['account_id'] ?? ''));
            $zone = $this->sync->findZone($zoneId);
            $before = $this->sync->findRecord($zoneId, $recordId) ?: [
                'id' => $recordId,
                'type' => 'PTR',
                'name' => $issue['record_name'] ?? '',
                'content' => $issue['record_content'] ?? '',
            ];

            try {
                if (!$account || $zoneId === '' || $recordId === '') {
                    throw new RuntimeException('Missing account/zone/record for issue.');
                }
                $client = new CloudflareClient((string) $account['api_token']);
                $client->deleteDnsRecord($zoneId, $recordId);
                $this->sync->removeCachedRecord($zoneId, $recordId);
                $this->logger->ptr('ptr.delete', $actor, [
                    'account_id' => $account['id'] ?? '',
                    'zone_id' => $zoneId,
                    'zone_name' => $zone['name'] ?? ($issue['zone_name'] ?? ''),
                    'before' => $before,
                    'reason' => 'ptr_manual_delete_mismatch',
                    'ip' => $issue['ip'] ?? '',
                    'hostname' => $issue['hostname'] ?? '',
                ]);
                $deleted++;
            } catch (Throwable $e) {
                $failed++;
                $issue['detail'] = ($issue['detail'] ?? '') . ' | Delete failed: ' . $e->getMessage();
                $remaining[] = $issue;
            }
        }

        $this->saveIssues($remaining);
        return ['deleted' => $deleted, 'failed' => $failed, 'remaining' => count($remaining)];
    }

    public function dismissIssues(array $issueIds): int
    {
        $before = $this->getIssues();
        $after = array_values(array_filter($before, static function (array $i) use ($issueIds): bool {
            return !in_array((string) ($i['id'] ?? ''), $issueIds, true);
        }));
        $this->saveIssues($after);
        return count($before) - count($after);
    }
}
