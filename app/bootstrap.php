<?php

declare(strict_types=1);

$config = require __DIR__ . '/config.php';

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/JsonStore.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/Access.php';
require_once __DIR__ . '/Logger.php';
require_once __DIR__ . '/RoleService.php';
require_once __DIR__ . '/IpAllowlist.php';
require_once __DIR__ . '/CloudflareClient.php';
require_once __DIR__ . '/SyncService.php';
require_once __DIR__ . '/PtrService.php';
require_once __DIR__ . '/CronLock.php';
require_once __DIR__ . '/CronRunner.php';
require_once __DIR__ . '/PtrCronRunner.php';

if (PHP_SAPI !== 'cli' && session_status() !== PHP_SESSION_ACTIVE) {
    session_name((string) $config['session_name']);
    session_start();
}

$dataPath = (string) $config['data_path'];
if (!is_dir($dataPath)) {
    mkdir($dataPath, 0755, true);
}

foreach (['accounts', 'zones', 'records', 'operators', 'roles', 'logs', 'cron_logs', 'dns_logs', 'ptr_logs', 'ip_allowlist', 'ptr_settings', 'ptr_issues', 'meta'] as $storeName) {
    $file = $dataPath . DIRECTORY_SEPARATOR . $storeName . '.json';
    if (!is_file($file)) {
        if ($storeName === 'zones' || $storeName === 'records' || $storeName === 'meta') {
            file_put_contents($file, "{}\n");
        } elseif ($storeName === 'ip_allowlist') {
            file_put_contents($file, json_encode([
                'enabled' => false,
                'entries' => [],
                'updated_at' => null,
            ], JSON_PRETTY_PRINT) . "\n");
        } elseif ($storeName === 'ptr_settings') {
            file_put_contents($file, json_encode([
                'auto_check' => false,
                'auto_delete' => false,
                'use_ping' => false,
                'audit_interval' => 21600,
                'updated_at' => null,
            ], JSON_PRETTY_PRINT) . "\n");
        } else {
            file_put_contents($file, "[]\n");
        }
    }
}

$store = new JsonStore($dataPath);
$roles = new RoleService($store);
$auth = new Auth($store);
$access = new Access($auth, $roles);
$logger = new Logger($store);
$sync = new SyncService($store, $logger);
$ipAllowlist = new IpAllowlist($store);
$ptr = new PtrService($store, $sync, $logger);

return compact('config', 'store', 'auth', 'access', 'logger', 'sync', 'roles', 'ipAllowlist', 'ptr');
