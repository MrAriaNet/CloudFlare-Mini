<?php

declare(strict_types=1);

$config = require __DIR__ . '/config.php';

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/JsonStore.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/Access.php';
require_once __DIR__ . '/Logger.php';
require_once __DIR__ . '/CloudflareClient.php';
require_once __DIR__ . '/SyncService.php';

if (PHP_SAPI !== 'cli' && session_status() !== PHP_SESSION_ACTIVE) {
    session_name((string) $config['session_name']);
    session_start();
}

$dataPath = (string) $config['data_path'];
if (!is_dir($dataPath)) {
    mkdir($dataPath, 0755, true);
}

foreach (['accounts', 'zones', 'records', 'operators', 'logs', 'meta'] as $storeName) {
    $file = $dataPath . DIRECTORY_SEPARATOR . $storeName . '.json';
    if (!is_file($file)) {
        if ($storeName === 'zones' || $storeName === 'records' || $storeName === 'meta') {
            file_put_contents($file, "{}\n");
        } else {
            file_put_contents($file, "[]\n");
        }
    }
}

$store = new JsonStore($dataPath);
$auth = new Auth($store);
$access = new Access($auth);
$logger = new Logger($store);
$sync = new SyncService($store, $logger);

return compact('config', 'store', 'auth', 'access', 'logger', 'sync');
