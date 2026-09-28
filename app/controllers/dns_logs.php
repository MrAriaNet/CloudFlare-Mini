<?php

declare(strict_types=1);

$access->requirePermission('view_dns_logs');

if (is_post()) {
    verify_csrf();
    if (($_POST['form_action'] ?? '') === 'clear') {
        $access->requirePermission('clear_dns_logs');
        $count = $logger->clearDns();
        $logger->log('logs.clear_dns', $access->user(), ['cleared' => $count]);
        flash('success', 'DNS change logs cleared (' . $count . ' entries).');
        redirect('r=dns_logs');
    }
}

$logs = $logger->allDns();
$logs = array_values(array_filter($logs, static function (array $log) use ($logger): bool {
    $ctx = is_array($log['context'] ?? null) ? $log['context'] : [];
    return !$logger->isPtrAction((string) ($log['action'] ?? ''), $ctx);
}));
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

$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = resolve_per_page(20);
$pagination = paginate_items($logs, $page, $perPage);

render('dns_logs', [
    'title' => 'DNS Change Logs',
    'user' => $access->user(),
    'access' => $access,
    'logs' => $pagination['items'],
    'pagination' => $pagination,
    'q' => $q,
    'routeName' => 'dns_logs',
    'active_nav' => 'dns_logs',
]);
