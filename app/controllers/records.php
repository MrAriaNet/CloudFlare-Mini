<?php

declare(strict_types=1);

$zoneId = (string) ($_GET['zone_id'] ?? $_POST['zone_id'] ?? '');
if ($zoneId === '') {
    flash('error', 'Domain is required.');
    redirect('r=domains');
}

$sync->ensureFresh();
$zone = $sync->findZone($zoneId);
if (!$zone) {
    flash('error', 'Domain not found. Try syncing accounts.');
    redirect('r=domains');
}

$access->requireZoneAccess($zoneId);
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
        $access->requireDnsMutation($zoneId);
        $payload = build_dns_payload($_POST, $dnsTypes);
        try {
            $client = new CloudflareClient((string) $account['api_token']);
            $result = $client->createDnsRecord($zoneId, $payload);
            $record = $result['result'] ?? [];
            $sync->upsertCachedRecord($zoneId, $record);
            $logger->log('dns.create', $access->user(), [
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
        $access->requireDnsMutation($zoneId);
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
            $logger->log('dns.update', $access->user(), [
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

    if ($formAction === 'delete') {
        $access->requireDnsMutation($zoneId);
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
            $logger->log('dns.delete', $access->user(), [
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
$editRecord = null;
if ($action === 'edit') {
    $access->requireDnsMutation($zoneId);
    $recordId = (string) ($_GET['record_id'] ?? '');
    $editRecord = $sync->findRecord($zoneId, $recordId);
    if (!$editRecord) {
        flash('error', 'Record not found.');
        redirect('r=records&zone_id=' . urlencode($zoneId));
    }
}

$showForm = $action === 'create' || $action === 'edit';
if ($showForm) {
    $access->requireDnsMutation($zoneId);
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
    'editRecord' => $editRecord,
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
