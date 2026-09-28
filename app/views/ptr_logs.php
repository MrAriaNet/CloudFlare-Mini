<div class="toolbar wrap">
    <form method="get" action="<?= e(url()) ?>" class="filters">
        <input type="hidden" name="r" value="ptr_logs">
        <?php if (!empty($pagination['per_page']) && (int) $pagination['per_page'] !== 20): ?>
            <input type="hidden" name="per" value="<?= (int) $pagination['per_page'] ?>">
        <?php endif; ?>
        <input type="search" name="q" value="<?= e($q ?? '') ?>" placeholder="Search PTR audit, delete, create…">
        <button class="btn btn-secondary" type="submit">Search</button>
    </form>
    <?php if ($access->canClearPtrLogs()): ?>
        <form method="post" action="<?= e(url('r=ptr_logs')) ?>" data-confirm="Clear ALL PTR logs? This cannot be undone.">
            <?= csrf_field() ?>
            <input type="hidden" name="form_action" value="clear">
            <button class="btn btn-danger" type="submit">Clear PTR logs</button>
        </form>
    <?php endif; ?>
</div>

<section class="panel">
    <p class="muted">
        Only PTR-related activity: audits, auto/manual deletes, creates from IP search, and settings changes.
        These entries do <strong>not</strong> appear in Audit / DNS / Cron logs.
        <?php if ($access->canViewPtrAudit()): ?>
            · <a href="<?= e(url('r=ptr_audit')) ?>">PTR Audit</a>
        <?php endif; ?>
    </p>

    <?php if (empty($logs)): ?>
        <p class="muted">No PTR log entries yet.</p>
    <?php else: ?>
        <?php require __DIR__ . '/partials/pagination.php'; ?>
        <div class="table-wrap">
        <table class="table table-logs">
            <thead>
            <tr>
                <th>Time (UTC)</th>
                <th>Actor</th>
                <th>Action</th>
                <th>IP / Host</th>
                <th>Details</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($logs as $log): ?>
                <?php
                $ctx = is_array($log['context'] ?? null) ? $log['context'] : [];
                $ipHost = trim(
                    (string) ($ctx['ip'] ?? '') .
                    (!empty($ctx['hostname']) ? ' → ' . $ctx['hostname'] : '')
                );
                $action = (string) ($log['action'] ?? '');
                $actionClass = 'badge-info';
                if (strpos($action, 'delete') !== false) {
                    $actionClass = 'badge-warn';
                } elseif (strpos($action, 'create') !== false) {
                    $actionClass = 'badge-ok';
                }
                ?>
                <tr>
                    <td class="col-meta"><span class="badge badge-muted"><?= e($log['time'] ?? '') ?></span></td>
                    <td><strong><?= e($log['actor_username'] ?? '') ?></strong></td>
                    <td class="col-status"><span class="badge <?= e($actionClass) ?>"><?= e($action) ?></span></td>
                    <td class="break">
                        <?= $ipHost !== '' ? '<span class="badge badge-brand">' . e($ipHost) . '</span>' : '<span class="muted">—</span>' ?>
                    </td>
                    <td><?= log_context_chips($ctx) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php require __DIR__ . '/partials/pagination.php'; ?>
    <?php endif; ?>
</section>
