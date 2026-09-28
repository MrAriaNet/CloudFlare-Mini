<?php

declare(strict_types=1);

$app = require dirname(__DIR__) . '/app/bootstrap.php';
/** @var Auth $auth */
/** @var Access $access */
/** @var JsonStore $store */
/** @var Logger $logger */
/** @var SyncService $sync */
/** @var IpAllowlist $ipAllowlist */
extract($app);

$route = $_GET['r'] ?? 'dashboard';
$route = is_string($route) ? preg_replace('/[^a-z0-9_\-]/i', '', $route) : 'dashboard';

$publicRoutes = ['login'];

// IP allowlist applies to the whole panel (login + authenticated pages)
if (!$ipAllowlist->isAllowed()) {
    if ($route === 'login' || is_post()) {
        $logger->log('auth.ip_blocked', null, [
            'ip' => client_ip(),
            'route' => $route,
        ]);
    }
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Access denied: your IP address is not allowed.');
}

if (!in_array($route, $publicRoutes, true)) {
    $auth->requireLogin();
}

$controllerFile = dirname(__DIR__) . '/app/controllers/' . $route . '.php';
if (!is_file($controllerFile)) {
    http_response_code(404);
    exit('Page not found.');
}

require $controllerFile;
