<div class="toolbar wrap">
    <form method="get" action="<?= e(url()) ?>" class="filters">
        <input type="hidden" name="r" value="logs">
        <?php if (!empty($pagination['per_page']) && (int) $pagination['per_page'] !== 20): ?>
            <input type="hidden" name="per" value="<?= (int) $pagination['per_page'] ?>">
        <?php endif; ?>
        <input type="search" name="q" value="<?= e($q ?? '') ?>" placeholder="Search actions, users, details…">
        <button class="btn btn-secondary" type="submit">Search</button>
    </form>
    <?php if ($access->canClearAuditLogs()): ?>
        <form method="post" action="<?= e(url('r=logs')) ?>" data-confirm="Clear ALL audit logs? This cannot be undone.">
            <?= csrf_field() ?>
            <input type="hidden" name="form_action" value="clear">
            <button class="btn btn-danger" type="submit">Clear audit logs</button>
        </form>
    <?php endif; ?>
</div>

<section class="panel">
    <div class="panel-head">
        <h2>Audit activity</h2>
        <?php if (!empty($pagination['total'])): ?>
            <span class="badge badge-info"><?= (int) $pagination['total'] ?> entr<?= ((int) $pagination['total'] === 1) ? 'y' : 'ies' ?></span>
        <?php endif; ?>
    </div>
    <p class="muted">
        Auth, operators, accounts, and role activity.
        <?php if ($access->canViewDnsLogs()): ?>
            DNS: <a href="<?= e(url('r=dns_logs')) ?>">DNS Change Logs</a>.
        <?php endif; ?>
        <?php if ($access->canViewPtrLogs()): ?>
            PTR: <a href="<?= e(url('r=ptr_logs')) ?>">PTR Logs</a>.
        <?php endif; ?>
        <?php if ($access->canViewCronLogs()): ?>
            Cron: <a href="<?= e(url('r=cron_logs')) ?>">Cron Logs</a>.
        <?php endif; ?>
    </p>
    <?php if (!$access->canViewAllLogs()): ?>
        <p class="muted">Showing your own actions only.</p>
    <?php endif; ?>

    <?php if (empty($logs)): ?>
        <p class="muted">No log entries found.</p>
    <?php else: ?>
        <?php require __DIR__ . '/partials/pagination.php'; ?>
        <div class="table-wrap">
        <table class="table table-logs">
            <thead>
            <tr>
                <th>Time (UTC)</th>
                <th>Actor</th>
                <th>Action</th>
                <th>IP</th>
                <th>Details</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($logs as $log): ?>
                <?php
                $action = (string) ($log['action'] ?? '');
                $actionClass = 'badge-info';
                if (strpos($action, 'failed') !== false || strpos($action, 'blocked') !== false) {
                    $actionClass = 'badge-danger';
                } elseif (strpos($action, 'delete') !== false || strpos($action, 'logout') !== false) {
                    $actionClass = 'badge-warn';
                } elseif (strpos($action, 'login') !== false || strpos($action, 'create') !== false || strpos($action, 'sync') !== false) {
                    $actionClass = 'badge-ok';
                } elseif (strpos($action, 'clear') !== false) {
                    $actionClass = 'badge-danger';
                }
                ?>
                <tr>
                    <td class="col-meta"><span class="badge badge-muted"><?= e(format_log_time($log['time'] ?? null)) ?></span></td>
                    <td class="col-status"><span class="badge badge-brand"><?= e($log['actor_username'] ?? 'system') ?></span></td>
                    <td class="col-status"><span class="badge <?= e($actionClass) ?>"><?= e($action) ?></span></td>
                    <td class="col-meta"><span class="badge badge-info"><?= e($log['ip'] ?? '—') ?></span></td>
                    <td><?= log_context_chips($log['context'] ?? []) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php require __DIR__ . '/partials/pagination.php'; ?>
    <?php endif; ?>
</section>
