<?php

declare(strict_types=1);

/**
 * CLI sync cron only.
 *   php scripts/cron.php
 *   php scripts/cron.php --force
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only. For HTTP: /cron.php?key=SECRET\n");
    exit(1);
}

$force = in_array('--force', $argv ?? [], true);
$app = require dirname(__DIR__) . '/app/bootstrap.php';
extract($app);

$runner = new CronRunner($sync, $logger, $store);
$result = $runner->run($force, 'cli');

foreach ($result['lines'] as $line) {
    echo $line . PHP_EOL;
}

if (!empty($result['locked'])) {
    echo "Exit: sync cron already running.\n";
    exit(0);
}

echo 'Done. OK=' . $result['ok'] . ' Failed=' . $result['failed'] . ' Skipped=' . $result['skipped'] . PHP_EOL;
exit($result['failed'] > 0 ? 1 : 0);
