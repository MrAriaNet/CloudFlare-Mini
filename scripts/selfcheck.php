<?php

declare(strict_types=1);

/**
 * Lightweight smoke check without a web server.
 * Run: php scripts/selfcheck.php
 */

if (PHP_SAPI !== 'cli') {
    exit("CLI only\n");
}

$root = dirname(__DIR__);
$required = [
    'app/bootstrap.php',
    'app/config.php',
    'app/JsonStore.php',
    'app/Auth.php',
    'app/Access.php',
    'app/Logger.php',
    'app/CloudflareClient.php',
    'app/SyncService.php',
    'public/index.php',
    'app/controllers/login.php',
    'app/controllers/dashboard.php',
    'app/controllers/accounts.php',
    'app/controllers/domains.php',
    'app/controllers/records.php',
    'app/controllers/operators.php',
    'app/controllers/logs.php',
    'app/views/layout.php',
];

$missing = [];
foreach ($required as $rel) {
    if (!is_file($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel))) {
        $missing[] = $rel;
    }
}

if ($missing) {
    echo "Missing files:\n- " . implode("\n- ", $missing) . "\n";
    exit(1);
}

$app = require $root . '/app/bootstrap.php';
extract($app);

$auth->ensureDefaultAdmin();
$ops = $store->read('operators', []);
if (empty($ops)) {
    echo "Failed to seed default admin.\n";
    exit(1);
}

echo "OK — files present, bootstrap loaded, admin seeded (" . ($ops[0]['username'] ?? '?') . ").\n";
exit(0);
