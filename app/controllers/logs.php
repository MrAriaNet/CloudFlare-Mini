<?php

declare(strict_types=1);

if (!$access->canViewLogs()) {
    http_response_code(403);
    exit('Forbidden.');
}

$logs = $logger->all();
if (!$access->canViewAllLogs()) {
    $uid = $access->user()['id'] ?? '';
    $logs = array_values(array_filter($logs, static function (array $log) use ($uid): bool {
        return ($log['actor_id'] ?? '') === $uid;
    }));
}

$q = trim((string) ($_GET['q'] ?? ''));
if ($q !== '') {
    $logs = array_values(array_filter($logs, static function (array $log) use ($q): bool {
        $hay = strtolower(
            ($log['action'] ?? '') . ' ' .
            ($log['actor_username'] ?? '') . ' ' .
            json_encode($log['context'] ?? [])
        );
        return strpos($hay, strtolower($q)) !== false;
    }));
}

$logs = array_slice($logs, 0, 300);

render('logs', [
    'title' => 'Audit Logs',
    'user' => $access->user(),
    'access' => $access,
    'logs' => $logs,
    'q' => $q,
    'active_nav' => 'logs',
]);
