<?php if ($access->canManagePtrSettings()): ?>
<section class="panel">
    <h2>PTR audit settings</h2>
    <p class="muted">
        Checks each PTR hostname with forward DNS. If it does not resolve back to the same IP, it appears in the list below.
        Use a <strong>separate PTR cron</strong> (not the sync cron) so jobs do not overlap or duplicate.
        <?php if ($access->canViewPtrLogs()): ?>
            Audit / delete / create history: <a href="<?= e(url('r=ptr_logs')) ?>">PTR Logs</a>.
        <?php endif; ?>
    </p>
    <div class="alert alert-success" style="background:#e8eef3;color:#15202b;">
        <strong>Recommended crons (hourly):</strong><br>
        Sync: <code>php scripts/cron.php</code> or <code>/cron.php?key=YOUR_SECRET</code><br>
        PTR: <code>php scripts/cron_ptr.php</code> or <code>/cron_ptr.php?key=YOUR_SECRET</code>
    </div>
    <form method="post" action="<?= e(url('r=ptr_audit')) ?>" class="stack">
        <?= csrf_field() ?>
        <input type="hidden" name="form_action" value="settings">
        <label class="checkbox">
            <input type="checkbox" name="auto_check" value="1" <?= !empty($settings['auto_check']) ? 'checked' : '' ?>>
            <span>Enable auto PTR audit (honored by <code>cron_ptr</code> only)</span>
        </label>
        <label>
            <span>Auto-check interval (seconds, min 3600)</span>
            <input type="number" name="audit_interval" min="3600" max="604800" value="<?= (int) ($settings['audit_interval'] ?? 21600) ?>">
        </label>
        <p class="muted small">Default 21600 = every 6 hours. PTR cron may be scheduled hourly; it will no-op until the interval passes.</p>
        <label class="checkbox">
            <input type="checkbox" name="auto_delete" value="1" <?= !empty($settings['auto_delete']) ? 'checked' : '' ?>>
            <span>Auto-delete mismatched PTR records when PTR cron/audit runs</span>
        </label>
        <label class="checkbox">
            <input type="checkbox" name="use_ping" value="1" <?= !empty($settings['use_ping']) ? 'checked' : '' ?>>
            <span>Use ICMP ping on <em>manual</em> audit only (PTR cron always uses fast DNS-only)</span>
        </label>
        <div class="toolbar">
            <button class="btn btn-primary" type="submit">Save settings</button>
        </div>
    </form>
    <?php if (!empty($settings['last_run_at'])): ?>
        <p class="muted small">
            Last run: <?= e((string) $settings['last_run_at']) ?>
            <?php if (!empty($settings['last_run_summary']) && is_array($settings['last_run_summary'])): ?>
                — checked <?= (int) ($settings['last_run_summary']['checked'] ?? 0) ?>,
                mismatched <?= (int) ($settings['last_run_summary']['mismatched'] ?? 0) ?>,
                deleted <?= (int) ($settings['last_run_summary']['deleted'] ?? 0) ?>
            <?php endif; ?>
        </p>
    <?php endif; ?>
</section>
<?php endif; ?>

<div class="toolbar wrap">
    <form method="get" action="<?= e(url()) ?>" class="filters">
        <input type="hidden" name="r" value="ptr_audit">
        <?php if (!empty($pagination['per_page']) && (int) $pagination['per_page'] !== 20): ?>
            <input type="hidden" name="per" value="<?= (int) $pagination['per_page'] ?>">
        <?php endif; ?>
        <input type="search" name="q" value="<?= e($q ?? '') ?>" placeholder="Search IP, hostname, zone…">
        <button class="btn btn-secondary" type="submit">Search</button>
    </form>
    <?php if ($access->canRunPtrAudit()): ?>
        <form method="post" action="<?= e(url('r=ptr_audit')) ?>" data-confirm="Run PTR audit now? This may take a while on large reverse zones.">
            <?= csrf_field() ?>
            <input type="hidden" name="form_action" value="run">
            <button class="btn btn-primary" type="submit">Run audit now</button>
        </form>
    <?php endif; ?>
</div>

<section class="panel">
    <h2>Mismatched PTR records</h2>
    <p class="muted">PTR hostname does not resolve (or ping) back to the reverse IP.</p>

    <?php if (empty($issues)): ?>
        <p class="muted">No open PTR issues.</p>
    <?php else: ?>
        <?php require __DIR__ . '/partials/pagination.php'; ?>
        <form method="post" action="<?= e(url('r=ptr_audit')) ?>" id="ptr-issues-form">
            <?= csrf_field() ?>
            <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th style="width:2rem;"><input type="checkbox" id="ptr-check-all"></th>
                    <th>IP</th>
                    <th>PTR hostname</th>
                    <th>Zone</th>
                    <th>Resolved / detail</th>
                    <th>Detected</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($issues as $issue): ?>
                    <tr>
                        <td><input type="checkbox" name="issue_ids[]" value="<?= e((string) ($issue['id'] ?? '')) ?>" class="ptr-issue-check"></td>
                        <td class="col-meta"><span class="badge badge-brand"><?= e($issue['ip'] ?? '') ?></span></td>
                        <td><strong><?= e($issue['hostname'] ?? '') ?></strong></td>
                        <td>
                            <span class="badge badge-info"><?= e($issue['zone_name'] ?? '') ?></span>
                            <div class="muted small"><?= e($issue['account_name'] ?? '') ?></div>
                        </td>
                        <td class="break small">
                            <?php if (!empty($issue['resolved_ips']) && is_array($issue['resolved_ips'])): ?>
                                <div>Got: <code><?= e(implode(', ', $issue['resolved_ips'])) ?></code></div>
                            <?php endif; ?>
                            <div class="muted"><?= e($issue['detail'] ?? '') ?></div>
                        </td>
                        <td class="col-meta"><span class="badge badge-muted"><?= e($issue['detected_at'] ?? '') ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <div class="toolbar" style="padding:0.85rem 0 0;">
                <?php if ($access->canDeleteBrokenPtr()): ?>
                    <button class="btn btn-danger" type="submit" name="form_action" value="delete_selected"
                            data-confirm-btn="Delete selected PTR records from Cloudflare?">Delete selected PTR records</button>
                <?php endif; ?>
                <button class="btn btn-secondary" type="submit" name="form_action" value="dismiss_selected">Dismiss from list</button>
            </div>
        </form>
        <?php require __DIR__ . '/partials/pagination.php'; ?>
    <?php endif; ?>
</section>
