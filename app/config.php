<?php

declare(strict_types=1);

return [
    'app_name' => 'Cloudflare Mini DNS Panel',
    'base_path' => dirname(__DIR__),
    'data_path' => dirname(__DIR__) . DIRECTORY_SEPARATOR . 'data',
    'sync_interval' => 3600,
    // Never block page loads with Cloudflare API sync. Use cron instead.
    'web_lazy_sync' => false,
    // Secret for HTTP cron: /cron.php?key=YOUR_SECRET
    'cron_key' => 'change-this-cron-secret',
    // Max seconds a cron lock is considered valid (stale lock is broken after this)
    'cron_lock_ttl' => 900,
    // PHP time limit for a cron run
    'cron_time_limit' => 600,
    // Max PTR records checked per auto cron run (rotating batches)
    'ptr_audit_batch' => 300,
    'session_name' => 'cf_mini_session',
    'default_admin' => [
        'username' => 'admin',
        'password' => 'admin123',
    ],
    'dns_types' => ['A', 'AAAA', 'CNAME', 'TXT', 'MX', 'NS', 'SRV', 'CAA', 'PTR'],
    'login_max_attempts' => 8,
    'login_lock_seconds' => 300,
];
