<?php

declare(strict_types=1);

$sync->ensureFresh();

$accounts = $store->read('accounts', []);
$zonesByAccount = $store->read('zones', []);
$q = trim((string) ($_GET['q'] ?? ''));
$filterAccount = (string) ($_GET['account_id'] ?? '');

$groups = [];
foreach ($accounts as $account) {
    $aid = (string) ($account['id'] ?? '');
    if ($filterAccount !== '' && $aid !== $filterAccount) {
        continue;
    }
    $zones = $access->filterZones($zonesByAccount[$aid] ?? []);
    if ($q !== '') {
        $zones = array_values(array_filter($zones, static function (array $z) use ($q): bool {
            return stripos((string) ($z['name'] ?? ''), $q) !== false;
        }));
    }
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
    'filterAccount' => $filterAccount,
    'active_nav' => 'domains',
]);
