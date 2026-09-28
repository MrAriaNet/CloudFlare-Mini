<?php

declare(strict_types=1);

$access->requirePermission('view_domains');

/** @var PtrService $ptr */
/** @var SyncService $sync */
/** @var Logger $logger */

$zoneAllowed = function (array $zone) use ($access): bool {
    return $access->canAccessZone((string) ($zone['id'] ?? ''));
};

if (is_post()) {
    verify_csrf();
    $formAction = (string) ($_POST['form_action'] ?? '');

    if ($formAction === 'create_ptr') {
        $ip = trim((string) ($_POST['ip'] ?? ''));
        $zoneId = (string) ($_POST['zone_id'] ?? '');
        $hostname = trim((string) ($_POST['hostname'] ?? ''));
        $access->requireDnsPermission('create_dns', $zoneId);

        try {
            $ptr->createPtrForIp($ip, $zoneId, $hostname, $access->user());
            flash('success', 'PTR record created for ' . $ip . ' → ' . rtrim($hostname, '.') . '.');
        } catch (Throwable $e) {
            flash('error', $e->getMessage());
        }
        redirect('r=domains&ip=' . urlencode($ip));
    }
}

$accounts = $store->read('accounts', []);
$zonesByAccount = $store->read('zones', []);
$recordsByZone = $store->read('records', []);
$q = trim((string) ($_GET['q'] ?? ''));
$ipSearch = trim((string) ($_GET['ip'] ?? ''));
$filterAccount = (string) ($_GET['account_id'] ?? '');

$ipResults = [];
$ipError = null;
$createZones = [];
$suggestedHostname = '';

if ($ipSearch !== '') {
    if (!filter_var($ipSearch, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $ipError = 'Enter a valid IPv4 address.';
    } else {
        $ipResults = $ptr->findByIp($ipSearch, $zoneAllowed);
        if ($ipResults === []) {
            $createZones = $ptr->findZonesCoveringIp($ipSearch, $zoneAllowed);
            // Suggest a hostname placeholder based on IP
            $suggestedHostname = 'host-' . str_replace('.', '-', $ipSearch) . '.example.com';
        }
    }
}

$groups = [];
$reverseCount = 0;
$forwardCount = 0;
foreach ($accounts as $account) {
    $aid = (string) ($account['id'] ?? '');
    if ($filterAccount !== '' && $aid !== $filterAccount) {
        continue;
    }
    $zones = $access->filterZones($zonesByAccount[$aid] ?? []);
    if ($q !== '') {
        $zones = array_values(array_filter($zones, static function (array $z) use ($q, $ptr): bool {
            $name = (string) ($z['name'] ?? '');
            if (stripos($name, $q) !== false) {
                return true;
            }
            $range = $ptr->zoneIpRange($name);
            return $range && stripos($range['label'], $q) !== false;
        }));
    }
    foreach ($zones as &$z) {
        $range = $ptr->zoneIpRange((string) ($z['name'] ?? ''));
        $z['is_reverse'] = $range !== null;
        $z['ip_range'] = $range['label'] ?? null;
        $zid = (string) ($z['id'] ?? '');
        $cached = $recordsByZone[$zid] ?? [];
        $z['record_count'] = is_array($cached) ? count($cached) : 0;
        if ($z['is_reverse']) {
            $reverseCount++;
        } else {
            $forwardCount++;
        }
    }
    unset($z);

    if (!$access->isAdmin() && empty($zones)) {
        continue;
    }
    $groups[] = [
        'account' => $account,
        'zones' => $zones,
    ];
}

render('domains', [
    'title' => 'Domains',
    'user' => $access->user(),
    'access' => $access,
    'groups' => $groups,
    'accounts' => $accounts,
    'q' => $q,
    'ipSearch' => $ipSearch,
    'ipResults' => $ipResults,
    'ipError' => $ipError,
    'createZones' => $createZones,
    'suggestedHostname' => $suggestedHostname,
    'filterAccount' => $filterAccount,
    'reverseCount' => $reverseCount,
    'forwardCount' => $forwardCount,
    'active_nav' => 'domains',
]);
