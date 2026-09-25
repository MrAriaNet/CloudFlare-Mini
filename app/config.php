<?php

declare(strict_types=1);

return [
    'app_name' => 'Cloudflare Mini DNS Panel',
    'base_path' => dirname(__DIR__),
    'data_path' => dirname(__DIR__) . DIRECTORY_SEPARATOR . 'data',
    'sync_interval' => 3600,
    'session_name' => 'cf_mini_session',
    'default_admin' => [
        'username' => 'admin',
        'password' => 'admin123',
    ],
    'dns_types' => ['A', 'AAAA', 'CNAME', 'TXT', 'MX', 'NS', 'SRV', 'CAA', 'PTR'],
    'login_max_attempts' => 8,
    'login_lock_seconds' => 300,
];
