<?php

declare(strict_types=1);

/**
 * Hourly sync entrypoint for cron / Windows Task Scheduler.
 * Example: php scripts/sync.php
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from the command line.\n");
    exit(1);
}

$app = require dirname(__DIR__) . '/app/bootstrap.php';
/** @var SyncService $sync */
/** @var Logger $logger */
/** @var JsonStore $store */
extract($app);

$accounts = $store->read('accounts', []);
$ok = 0;
$fail = 0;

foreach ($accounts as $account) {
    $name = $account['name'] ?? $account['id'] ?? 'unknown';
    echo 'Syncing ' . $name . '... ';
    if ($sync->syncAccount($account)) {
        echo "OK\n";
        $ok++;
    } else {
        echo "FAILED\n";
        $fail++;
    }
}

$logger->log('sync.cli', ['username' => 'cli'], [
    'ok' => $ok,
    'failed' => $fail,
]);

echo "Done. Success: {$ok}, Failed: {$fail}\n";
exit($fail > 0 ? 1 : 0);
