<?php

declare(strict_types=1);

/**
 * HTTP sync cron (accounts/zones only).
 *   /cron.php?key=YOUR_SECRET
 */

$app = require dirname(__DIR__) . '/app/bootstrap.php';
extract($app);

header('Content-Type: text/plain; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');
header('Connection: close');

$expected = (string) config('cron_key', '');
$provided = (string) ($_GET['key'] ?? '');

if ($expected === '' || $expected === 'change-this-cron-secret') {
    http_response_code(503);
    echo "Cron key is not configured.\n";
    exit;
}
if ($provided === '' || !hash_equals($expected, $provided)) {
    http_response_code(403);
    echo "Forbidden\n";
    exit;
}

$force = isset($_GET['force']) && (string) $_GET['force'] === '1';
$runner = new CronRunner($sync, $logger, $store);
$result = $runner->run($force, 'http');

echo "Cloudflare Mini sync cron\n";
echo implode("\n", $result['lines']) . "\n";

if (!empty($result['locked'])) {
    http_response_code(409);
    echo "Already running\n";
    exit;
}

echo 'OK=' . $result['ok'] . ' Failed=' . $result['failed'] . ' Skipped=' . $result['skipped'] . "\n";
http_response_code($result['failed'] > 0 ? 500 : 200);
