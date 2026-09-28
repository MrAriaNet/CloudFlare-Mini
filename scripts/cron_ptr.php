<?php

declare(strict_types=1);

/**
 * CLI PTR audit cron (separate from sync).
 *   php scripts/cron_ptr.php
 *   php scripts/cron_ptr.php --force
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only. For HTTP: /cron_ptr.php?key=SECRET\n");
    exit(1);
}

$force = in_array('--force', $argv ?? [], true);
$app = require dirname(__DIR__) . '/app/bootstrap.php';
extract($app);

$runner = new PtrCronRunner($ptr, $logger);
$result = $runner->run($force, 'cli');

foreach ($result['lines'] as $line) {
    echo $line . PHP_EOL;
}

if (!empty($result['locked'])) {
    echo "Exit: PTR cron already running.\n";
    exit(0);
}

echo "PTR cron done.\n";
exit(0);
