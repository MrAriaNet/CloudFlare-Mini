<?php

declare(strict_types=1);

$access->requirePermission('manage_ip_allowlist');

/** @var IpAllowlist $ipAllowlist */

if (is_post()) {
    verify_csrf();
    $enabled = !empty($_POST['enabled']);
    $raw = (string) ($_POST['entries'] ?? '');
    $lines = preg_split('/\r\n|\r|\n/', $raw) ?: [];
    $currentIp = client_ip();

    try {
        if ($enabled) {
            $parsed = [];
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || strpos($line, '#') === 0) {
                    continue;
                }
                $parsed[] = $line;
            }
            if ($parsed === []) {
                flash('error', 'Enable IP allowlist only after adding at least one IP or subnet.');
                redirect('r=ip_allowlist');
            }
            // Safety: current admin IP must remain allowed
            $tempOk = false;
            foreach ($parsed as $entry) {
                if ($ipAllowlist->isValidEntry($entry) && $ipAllowlist->match($currentIp, $entry)) {
                    $tempOk = true;
                    break;
                }
            }
            if (!$tempOk) {
                flash('error', 'Refused to save: your current IP (' . $currentIp . ') is not covered. Add it first to avoid lockout.');
                redirect('r=ip_allowlist');
            }
        }

        $ipAllowlist->save($enabled, $lines);
        $logger->log('ip_allowlist.update', $access->user(), [
            'enabled' => $enabled,
            'entries_count' => count($ipAllowlist->get()['entries']),
            'ip' => $currentIp,
        ]);
        flash('success', 'IP allowlist saved.');
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
    }
    redirect('r=ip_allowlist');
}

$settings = $ipAllowlist->get();

render('ip_allowlist', [
    'title' => 'IP Allowlist',
    'user' => $access->user(),
    'access' => $access,
    'settings' => $settings,
    'current_ip' => client_ip(),
    'active_nav' => 'ip_allowlist',
]);
