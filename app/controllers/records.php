<?php

declare(strict_types=1);

$zoneId = (string) ($_GET['zone_id'] ?? $_POST['zone_id'] ?? '');
if ($zoneId === '') {
    flash('error', 'Domain is required.');
    redirect('r=domains');
}

$zone = $sync->findZone($zoneId);
if (!$zone) {
    flash('error', 'Domain not found. Try syncing accounts.');
    redirect('r=domains');
}

$access->requireZoneAccess($zoneId);
if (!$access->canViewDns()) {
    http_response_code(403);
    exit('Forbidden.');
}
$account = $sync->findAccount((string) ($zone['account_id'] ?? ''));
if (!$account) {
    flash('error', 'Parent Cloudflare account not found.');
    redirect('r=domains');
}

$action = $_GET['action'] ?? 'list';
$dnsTypes = config('dns_types');

if (is_post()) {
    verify_csrf();
    $formAction = $_POST['form_action'] ?? '';

    if ($formAction === 'create') {
        $access->requireDnsPermission('create_dns', $zoneId);
        $payload = build_dns_payload($_POST, $dnsTypes);
        try {
            $client = new CloudflareClient((string) $account['api_token']);
            $result = $client->createDnsRecord($zoneId, $payload);
            $record = $result['result'] ?? [];
            $sync->upsertCachedRecord($zoneId, $record);
            $logger->dns('dns.create', $access->user(), [
                'account_id' => $account['id'],
                'zone_id' => $zoneId,
                'zone_name' => $zone['name'] ?? '',
                'after' => $sync->normalizeRecord($record),
            ]);
            flash('success', 'DNS record created.');
        } catch (Throwable $e) {
            flash('error', $e->getMessage());
        }
        redirect('r=records&zone_id=' . urlencode($zoneId));
    }

    if ($formAction === 'update') {
        $access->requireDnsPermission('edit_dns', $zoneId);
        $recordId = (string) ($_POST['record_id'] ?? '');
        $before = $sync->findRecord($zoneId, $recordId);
        if (!$before) {
            flash('error', 'Record not found.');
            redirect('r=records&zone_id=' . urlencode($zoneId));
        }
        $payload = build_dns_payload($_POST, $dnsTypes);
        try {
            $client = new CloudflareClient((string) $account['api_token']);
            $result = $client->updateDnsRecord($zoneId, $recordId, $payload);
            $record = $result['result'] ?? [];
            $sync->upsertCachedRecord($zoneId, $record);
            $logger->dns('dns.update', $access->user(), [
                'account_id' => $account['id'],
                'zone_id' => $zoneId,
                'zone_name' => $zone['name'] ?? '',
                'before' => $before,
                'after' => $sync->normalizeRecord($record),
            ]);
            flash('success', 'DNS record updated.');
        } catch (Throwable $e) {
            flash('error', $e->getMessage());
        }
        redirect('r=records&zone_id=' . urlencode($zoneId));
    }

    if ($formAction === 'bulk_create') {
        $access->requireDnsPermission('create_dns', $zoneId);
        $raw = (string) ($_POST['bulk_text'] ?? '');
        try {
            $rows = parse_bulk_dns_lines($raw, $dnsTypes);
            if ($rows === []) {
                throw new InvalidArgumentException('No valid records found. Add at least one line.');
            }
            if (count($rows) > 200) {
                throw new InvalidArgumentException('Bulk create is limited to 200 records per submit.');
            }
            $client = new CloudflareClient((string) $account['api_token']);
            $ok = 0;
            $errors = [];
            foreach ($rows as $row) {
                try {
                    $result = $client->createDnsRecord($zoneId, $row['payload']);
                    $record = $result['result'] ?? [];
                    $sync->upsertCachedRecord($zoneId, $record);
                    $logger->dns('dns.create', $access->user(), [
                        'account_id' => $account['id'],
                        'zone_id' => $zoneId,
                        'zone_name' => $zone['name'] ?? '',
                        'bulk' => true,
                        'after' => $sync->normalizeRecord($record),
                    ]);
                    $ok++;
                } catch (Throwable $e) {
                    $errors[] = 'Line ' . $row['line'] . ': ' . $e->getMessage();
                }
            }
            if ($ok > 0 && $errors === []) {
                flash('success', 'Bulk create finished: ' . $ok . ' record(s) created.');
            } elseif ($ok > 0) {
                flash('success', 'Bulk create partial: ' . $ok . ' created. Failures: ' . implode(' | ', array_slice($errors, 0, 5)));
            } else {
                flash('error', 'Bulk create failed. ' . implode(' | ', array_slice($errors, 0, 5)));
            }
        } catch (Throwable $e) {
            flash('error', $e->getMessage());
            redirect('r=records&zone_id=' . urlencode($zoneId) . '&action=bulk_create');
        }
        redirect('r=records&zone_id=' . urlencode($zoneId));
    }

    if ($formAction === 'bulk_update') {
        $access->requireDnsPermission('edit_dns', $zoneId);
        $rowsIn = $_POST['rows'] ?? [];
        if (!is_array($rowsIn) || $rowsIn === []) {
            flash('error', 'No records submitted for bulk edit.');
            redirect('r=records&zone_id=' . urlencode($zoneId));
        }
        if (count($rowsIn) > 200) {
            flash('error', 'Bulk edit is limited to 200 records per submit.');
            redirect('r=records&zone_id=' . urlencode($zoneId));
        }
        $client = new CloudflareClient((string) $account['api_token']);
        $ok = 0;
        $errors = [];
        foreach ($rowsIn as $recordId => $fields) {
            $recordId = (string) $recordId;
            if (!is_array($fields)) {
                continue;
            }
            $before = $sync->findRecord($zoneId, $recordId);
            if (!$before) {
                $errors[] = $recordId . ': not found in cache';
                continue;
            }
            try {
                $payload = build_dns_payload($fields, $dnsTypes);
                $result = $client->updateDnsRecord($zoneId, $recordId, $payload);
                $record = $result['result'] ?? [];
                $sync->upsertCachedRecord($zoneId, $record);
                $logger->dns('dns.update', $access->user(), [
                    'account_id' => $account['id'],
                    'zone_id' => $zoneId,
                    'zone_name' => $zone['name'] ?? '',
                    'bulk' => true,
                    'before' => $before,
                    'after' => $sync->normalizeRecord($record),
                ]);
                $ok++;
            } catch (Throwable $e) {
                $errors[] = ($before['name'] ?? $recordId) . ': ' . $e->getMessage();
            }
        }
        if ($ok > 0 && $errors === []) {
            flash('success', 'Bulk edit finished: ' . $ok . ' record(s) updated.');
        } elseif ($ok > 0) {
            flash('success', 'Bulk edit partial: ' . $ok . ' updated. Failures: ' . implode(' | ', array_slice($errors, 0, 5)));
        } else {
            flash('error', 'Bulk edit failed. ' . implode(' | ', array_slice($errors, 0, 5)));
        }
        redirect('r=records&zone_id=' . urlencode($zoneId));
    }

    if ($formAction === 'delete') {
        $access->requireDnsPermission('delete_dns', $zoneId);
        $recordId = (string) ($_POST['record_id'] ?? '');
        $before = $sync->findRecord($zoneId, $recordId);
        if (!$before) {
            flash('error', 'Record not found.');
            redirect('r=records&zone_id=' . urlencode($zoneId));
        }
        try {
            $client = new CloudflareClient((string) $account['api_token']);
            $client->deleteDnsRecord($zoneId, $recordId);
            $sync->removeCachedRecord($zoneId, $recordId);
            $logger->dns('dns.delete', $access->user(), [
                'account_id' => $account['id'],
                'zone_id' => $zoneId,
                'zone_name' => $zone['name'] ?? '',
                'before' => $before,
            ]);
            flash('success', 'DNS record deleted.');
        } catch (Throwable $e) {
            flash('error', $e->getMessage());
        }
        redirect('r=records&zone_id=' . urlencode($zoneId));
    }

    if ($formAction === 'refresh') {
        $access->requireDnsPermission('refresh_dns', $zoneId);
        try {
            $sync->syncZoneRecords($account, $zoneId);
            $logger->log('dns.refresh', $access->user(), [
                'account_id' => $account['id'],
                'zone_id' => $zoneId,
                'zone_name' => $zone['name'] ?? '',
            ]);
            flash('success', 'Records refreshed from Cloudflare.');
        } catch (Throwable $e) {
            flash('error', $e->getMessage());
        }
        redirect('r=records&zone_id=' . urlencode($zoneId));
    }
}

$records = $store->read('records', [])[$zoneId] ?? [];
$recordFilter = trim((string) ($_GET['q'] ?? ''));
if ($recordFilter !== '') {
    $records = array_values(array_filter($records, static function (array $r) use ($recordFilter): bool {
        $hay = strtolower(
            ($r['name'] ?? '') . ' ' .
            ($r['content'] ?? '') . ' ' .
            ($r['type'] ?? '')
        );
        return strpos($hay, strtolower($recordFilter)) !== false;
    }));
}
$editRecord = null;
$bulkEditRecords = [];
if ($action === 'edit') {
    $access->requireDnsPermission('edit_dns', $zoneId);
    $recordId = (string) ($_GET['record_id'] ?? '');
    $editRecord = $sync->findRecord($zoneId, $recordId);
    if (!$editRecord) {
        flash('error', 'Record not found.');
        redirect('r=records&zone_id=' . urlencode($zoneId));
    }
}

if ($action === 'bulk_create') {
    $access->requireDnsPermission('create_dns', $zoneId);
}

if ($action === 'bulk_edit') {
    $access->requireDnsPermission('edit_dns', $zoneId);
    $ids = $_GET['ids'] ?? [];
    if (!is_array($ids)) {
        $ids = $ids !== '' ? [(string) $ids] : [];
    }
    $ids = array_values(array_filter(array_map('strval', $ids)));
    if ($ids === []) {
        flash('error', 'Select at least one record to bulk edit.');
        redirect('r=records&zone_id=' . urlencode($zoneId));
    }
    if (count($ids) > 200) {
        flash('error', 'Bulk edit is limited to 200 records.');
        redirect('r=records&zone_id=' . urlencode($zoneId));
    }
    foreach ($ids as $rid) {
        $found = $sync->findRecord($zoneId, $rid);
        if ($found) {
            $bulkEditRecords[] = $found;
        }
    }
    if ($bulkEditRecords === []) {
        flash('error', 'None of the selected records were found.');
        redirect('r=records&zone_id=' . urlencode($zoneId));
    }
}

$showForm = $action === 'create' || $action === 'edit';
$showBulkCreate = $action === 'bulk_create';
$showBulkEdit = $action === 'bulk_edit';
if ($action === 'create') {
    $access->requireDnsPermission('create_dns', $zoneId);
}

render('records', [
    'title' => 'DNS Records — ' . ($zone['name'] ?? ''),
    'user' => $access->user(),
    'access' => $access,
    'zone' => $zone,
    'account' => $account,
    'records' => $records,
    'dnsTypes' => $dnsTypes,
    'showForm' => $showForm,
    'showBulkCreate' => $showBulkCreate,
    'showBulkEdit' => $showBulkEdit,
    'editRecord' => $editRecord,
    'bulkEditRecords' => $bulkEditRecords,
    'recordFilter' => $recordFilter,
    'active_nav' => 'domains',
]);

function build_dns_payload(array $input, array $allowedTypes): array
{
    $type = strtoupper(trim((string) ($input['type'] ?? 'A')));
    if (!in_array($type, $allowedTypes, true)) {
        throw new InvalidArgumentException('Unsupported DNS record type.');
    }
    $name = trim((string) ($input['name'] ?? ''));
    $content = trim((string) ($input['content'] ?? ''));
    if ($name === '' || $content === '') {
        throw new InvalidArgumentException('Name and content are required.');
    }
    $ttl = (int) ($input['ttl'] ?? 1);
    if ($ttl < 1) {
        $ttl = 1;
    }
    $payload = [
        'type' => $type,
        'name' => $name,
        'content' => $content,
        'ttl' => $ttl,
    ];
    if (in_array($type, ['A', 'AAAA', 'CNAME'], true)) {
        $payload['proxied'] = !empty($input['proxied']);
    }
    if (in_array($type, ['MX', 'SRV'], true) && isset($input['priority']) && $input['priority'] !== '') {
        $payload['priority'] = (int) $input['priority'];
    }
    return $payload;
}

/**
 * Parse bulk DNS lines. Formats: CSV, pipe |, or tab.
 * Columns: type, name, content [, ttl] [, proxied] [, priority]
 *
 * @return array<int, array{line:int, payload:array}>
 */
function parse_bulk_dns_lines(string $text, array $allowedTypes): array
{
    $lines = preg_split('/\R/', $text) ?: [];
    $rows = [];
    $lineNo = 0;
    foreach ($lines as $line) {
        $lineNo++;
        $line = trim($line);
        if ($line === '' || (isset($line[0]) && $line[0] === '#')) {
            continue;
        }

        if (strpos($line, '|') !== false) {
            $parts = array_map('trim', explode('|', $line));
        } elseif (strpos($line, "\t") !== false) {
            $parts = array_map('trim', explode("\t", $line));
        } else {
            $parts = str_getcsv($line);
            $parts = array_map(static function ($p) {
                return trim((string) $p);
            }, $parts);
        }

        if ($parts === [] || ($parts[0] ?? '') === '') {
            continue;
        }

        // Skip header
        if (strcasecmp((string) $parts[0], 'type') === 0) {
            continue;
        }

        if (count($parts) < 3) {
            throw new InvalidArgumentException(
                'Line ' . $lineNo . ': need at least type, name, content (got: ' . $line . ').'
            );
        }

        $proxiedRaw = strtolower((string) ($parts[4] ?? '0'));
        $proxied = in_array($proxiedRaw, ['1', 'true', 'yes', 'proxied', 'on'], true);

        $input = [
            'type' => $parts[0],
            'name' => $parts[1],
            'content' => $parts[2],
            'ttl' => $parts[3] ?? 1,
            'proxied' => $proxied ? '1' : '',
            'priority' => $parts[5] ?? '',
        ];

        try {
            $payload = build_dns_payload($input, $allowedTypes);
        } catch (Throwable $e) {
            throw new InvalidArgumentException('Line ' . $lineNo . ': ' . $e->getMessage());
        }

        $rows[] = [
            'line' => $lineNo,
            'payload' => $payload,
        ];
    }

    return $rows;
}
