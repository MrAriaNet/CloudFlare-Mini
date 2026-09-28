<?php

declare(strict_types=1);

/** @var PtrService $ptr */

if (is_post()) {
    verify_csrf();
    $formAction = (string) ($_POST['form_action'] ?? '');

    if ($formAction === 'settings') {
        $access->requirePermission('manage_ptr_settings');
        $ptr->saveSettings([
            'auto_check' => !empty($_POST['auto_check']),
            'auto_delete' => !empty($_POST['auto_delete']),
            'use_ping' => !empty($_POST['use_ping']),
            'audit_interval' => (int) ($_POST['audit_interval'] ?? 21600),
        ]);
        $logger->ptr('ptr.settings', $access->user(), $ptr->getSettings());
        flash('success', 'PTR audit settings saved.');
        redirect('r=ptr_audit');
    }

    if ($formAction === 'run') {
        $access->requirePermission('run_ptr_audit');
        @set_time_limit(300);
        $zoneAllowed = function (array $zone) use ($access): bool {
            return $access->canAccessZone((string) ($zone['id'] ?? ''));
        };
        $result = $ptr->runAudit($zoneAllowed, $access->user(), [
            // Manual run: full scan, ping only if enabled in settings
            'fast' => empty($ptr->getSettings()['use_ping']),
            'limit' => 0,
        ]);
        flash(
            'success',
            'PTR audit finished. Checked ' . $result['checked']
            . ', OK ' . $result['ok']
            . ', mismatched ' . $result['mismatched']
            . ', auto-deleted ' . $result['deleted']
            . ', open issues ' . $result['open_issues'] . '.'
        );
        redirect('r=ptr_audit');
    }

    if ($formAction === 'delete_selected') {
        $access->requirePermission('delete_broken_ptr');
        $ids = $_POST['issue_ids'] ?? [];
        if (!is_array($ids)) {
            $ids = [];
        }
        $ids = array_values(array_filter(array_map('strval', $ids)));
        if ($ids === []) {
            flash('error', 'Select at least one issue.');
            redirect('r=ptr_audit');
        }
        $result = $ptr->deleteIssues($ids, $access->user());
        flash(
            $result['failed'] > 0 ? 'error' : 'success',
            'Deleted ' . $result['deleted'] . ' PTR record(s)'
            . ($result['failed'] ? ', failed ' . $result['failed'] : '') . '.'
        );
        redirect('r=ptr_audit');
    }

    if ($formAction === 'dismiss_selected') {
        $access->requirePermission('view_ptr_audit');
        $ids = $_POST['issue_ids'] ?? [];
        if (!is_array($ids)) {
            $ids = [];
        }
        $ids = array_values(array_filter(array_map('strval', $ids)));
        $n = $ptr->dismissIssues($ids);
        flash('success', 'Dismissed ' . $n . ' issue(s) from the list (records not deleted).');
        redirect('r=ptr_audit');
    }
}

$access->requirePermission('view_ptr_audit');

$issues = $ptr->getIssues();
// Filter by zone access for non-full users
if (!$access->isAdmin()) {
    $issues = array_values(array_filter($issues, static function (array $issue) use ($access): bool {
        return $access->canAccessZone((string) ($issue['zone_id'] ?? ''));
    }));
}

$q = trim((string) ($_GET['q'] ?? ''));
if ($q !== '') {
    $issues = array_values(array_filter($issues, static function (array $issue) use ($q): bool {
        $hay = strtolower(
            ($issue['ip'] ?? '') . ' ' .
            ($issue['hostname'] ?? '') . ' ' .
            ($issue['zone_name'] ?? '') . ' ' .
            ($issue['detail'] ?? '')
        );
        return strpos($hay, strtolower($q)) !== false;
    }));
}

$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = resolve_per_page(20);
$pagination = paginate_items($issues, $page, $perPage);

render('ptr_audit', [
    'title' => 'PTR Audit',
    'user' => $access->user(),
    'access' => $access,
    'settings' => $ptr->getSettings(),
    'issues' => $pagination['items'],
    'pagination' => $pagination,
    'q' => $q,
    'routeName' => 'ptr_audit',
    'active_nav' => 'ptr_audit',
]);
