<?php

declare(strict_types=1);

$sync->ensureFresh();

$accounts = $store->read('accounts', []);
$zonesByAccount = $store->read('zones', []);
$meta = $store->read('meta', []);
$records = $store->read('records', []);

$zoneCount = 0;
$recordCount = 0;
foreach ($zonesByAccount as $zones) {
    $filtered = $access->filterZones($zones);
    $zoneCount += count($filtered);
    foreach ($filtered as $zone) {
        $recordCount += count($records[$zone['id']] ?? []);
    }
}

$visibleAccounts = $accounts;
if (!$access->isAdmin()) {
    // Non-admins still see account groups that contain accessible domains
    $visibleAccounts = [];
    foreach ($accounts as $account) {
        $zones = $access->filterZones($zonesByAccount[$account['id']] ?? []);
        if (!empty($zones)) {
            $visibleAccounts[] = $account;
        }
    }
}

render('dashboard', [
    'title' => 'Dashboard',
    'user' => $access->user(),
    'access' => $access,
    'accounts' => $visibleAccounts,
    'accounts_total' => count($accounts),
    'zone_count' => $zoneCount,
    'record_count' => $recordCount,
    'meta' => $meta,
    'active_nav' => 'dashboard',
]);
