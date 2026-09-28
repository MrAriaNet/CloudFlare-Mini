<?php

declare(strict_types=1);

$access->requirePermission('view_cron_logs');

if (is_post()) {
    verify_csrf();
    if (($_POST['form_action'] ?? '') === 'clear') {
        $access->requirePermission('clear_cron_logs');
        $count = $logger->clearCron();
        // Write audit entry after clear so it remains
        $logger->log('logs.clear_cron', $access->user(), ['cleared' => $count]);
        flash('success', 'Cron logs cleared (' . $count . ' entries).');
        redirect('r=cron_logs');
    }
}

$logs = $logger->allCron();
$logs = array_values(array_filter($logs, static function (array $log) use ($logger): bool {
    $ctx = is_array($log['context'] ?? null) ? $log['context'] : [];
    return !$logger->isPtrAction((string) ($log['action'] ?? ''), $ctx);
}));

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

render('cron_logs', [
    'title' => 'Cron Logs',
    'user' => $access->user(),
    'access' => $access,
    'logs' => $pagination['items'],
    'pagination' => $pagination,
    'q' => $q,
    'routeName' => 'cron_logs',
    'active_nav' => 'cron_logs',
]);
