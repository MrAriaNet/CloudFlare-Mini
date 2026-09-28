<?php

declare(strict_types=1);

if (!$access->canViewPtrLogs()) {
    http_response_code(403);
    exit('Forbidden.');
}

if (is_post()) {
    verify_csrf();
    if (($_POST['form_action'] ?? '') === 'clear') {
        if (!$access->canClearPtrLogs()) {
            http_response_code(403);
            exit('Forbidden.');
        }
        $count = $logger->clearPtr();
        $logger->ptr('ptr.logs.clear', $access->user(), ['cleared' => $count]);
        flash('success', 'PTR logs cleared (' . $count . ' entries).');
        redirect('r=ptr_logs');
    }
}

$logs = $logger->allPtr();
if (!$access->canViewAllLogs()) {
    $uid = $access->user()['id'] ?? '';
    $logs = array_values(array_filter($logs, static function (array $log) use ($uid): bool {
        // Allow system/cron PTR rows for users who can view PTR audit
        $actor = (string) ($log['actor_username'] ?? '');
        if (in_array($actor, ['ptr_cron', 'ptr_audit', 'ptr', 'cron', 'system'], true)) {
            return true;
        }
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

render('ptr_logs', [
    'title' => 'PTR Logs',
    'user' => $access->user(),
    'access' => $access,
    'logs' => $pagination['items'],
    'pagination' => $pagination,
    'q' => $q,
    'routeName' => 'ptr_logs',
    'active_nav' => 'ptr_logs',
]);
