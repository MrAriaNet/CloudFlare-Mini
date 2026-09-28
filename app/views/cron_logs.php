<div class="toolbar wrap">
    <form method="get" action="<?= e(url()) ?>" class="filters">
        <input type="hidden" name="r" value="cron_logs">
        <?php if (!empty($pagination['per_page']) && (int) $pagination['per_page'] !== 20): ?>
            <input type="hidden" name="per" value="<?= (int) $pagination['per_page'] ?>">
        <?php endif; ?>
        <input type="search" name="q" value="<?= e($q ?? '') ?>" placeholder="Search cron logs…">
        <button class="btn btn-secondary" type="submit">Search</button>
    </form>
    <?php if ($access->canClearCronLogs()): ?>
        <form method="post" action="<?= e(url('r=cron_logs')) ?>" data-confirm="Clear ALL cron logs? This cannot be undone.">
            <?= csrf_field() ?>
            <input type="hidden" name="form_action" value="clear">
            <button class="btn btn-danger" type="submit">Clear cron logs</button>
        </form>
    <?php endif; ?>
</div>

<section class="panel">
    <p class="muted">
        Background account/zone sync only.
        PTR cron audits: <?php if ($access->canViewPtrLogs()): ?><a href="<?= e(url('r=ptr_logs')) ?>">PTR Logs</a><?php else: ?>PTR Logs<?php endif; ?> (separate).
        Operator actions stay in Audit Logs.
    </p>

    <?php if (empty($logs)): ?>
        <p class="muted">No cron log entries yet.</p>
    <?php else: ?>
        <?php require __DIR__ . '/partials/pagination.php'; ?>
        <div class="table-wrap">
        <table class="table table-logs">
            <thead>
            <tr>
                <th>Time (UTC)</th>
                <th>Source</th>
                <th>Action</th>
                <th>IP</th>
                <th>Details</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($logs as $log): ?>
                <tr>
                    <td class="col-meta"><span class="badge badge-muted"><?= e($log['time'] ?? '') ?></span></td>
                    <td><span class="badge badge-brand"><?= e($log['actor_username'] ?? 'cron') ?></span></td>
                    <td class="col-status"><span class="badge badge-info"><?= e($log['action'] ?? '') ?></span></td>
                    <td class="col-meta"><span class="badge badge-muted"><?= e($log['ip'] ?? '') ?></span></td>
                    <td><?= log_context_chips($log['context'] ?? []) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php require __DIR__ . '/partials/pagination.php'; ?>
    <?php endif; ?>
</section>
