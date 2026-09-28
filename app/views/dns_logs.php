<div class="toolbar wrap">
    <form method="get" action="<?= e(url()) ?>" class="filters">
        <input type="hidden" name="r" value="dns_logs">
        <?php if (!empty($pagination['per_page']) && (int) $pagination['per_page'] !== 20): ?>
            <input type="hidden" name="per" value="<?= (int) $pagination['per_page'] ?>">
        <?php endif; ?>
        <input type="search" name="q" value="<?= e($q ?? '') ?>" placeholder="Search user, domain, record…">
        <button class="btn btn-secondary" type="submit">Search</button>
    </form>
    <?php if ($access->canClearDnsLogs()): ?>
        <form method="post" action="<?= e(url('r=dns_logs')) ?>" data-confirm="Clear ALL DNS change logs? This cannot be undone.">
            <?= csrf_field() ?>
            <input type="hidden" name="form_action" value="clear">
            <button class="btn btn-danger" type="submit">Clear DNS logs</button>
        </form>
    <?php endif; ?>
</div>

<section class="panel">
    <p class="muted">
        Forward DNS create / edit / delete by operators.
        PTR create / delete / audit: <?php if ($access->canViewPtrLogs()): ?><a href="<?= e(url('r=ptr_logs')) ?>">PTR Logs</a><?php else: ?>PTR Logs<?php endif; ?> (separate).
        Other activity stays in Audit Logs.
    </p>
    <?php if (!$access->canViewAllLogs()): ?>
        <p class="muted">Showing your own DNS changes only.</p>
    <?php endif; ?>

    <?php if (empty($logs)): ?>
        <p class="muted">No DNS change logs yet.</p>
    <?php else: ?>
        <?php require __DIR__ . '/partials/pagination.php'; ?>
        <div class="table-wrap">
        <table class="table table-logs">
            <thead>
            <tr>
                <th>Time (UTC)</th>
                <th>User</th>
                <th>Action</th>
                <th>Domain</th>
                <th>IP</th>
                <th>Details</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($logs as $log): ?>
                <?php
                $ctx = is_array($log['context'] ?? null) ? $log['context'] : [];
                $zoneName = (string) ($ctx['zone_name'] ?? '');
                $action = (string) ($log['action'] ?? '');
                $badge = 'badge badge-info';
                if ($action === 'dns.create') {
                    $badge = 'badge badge-ok';
                } elseif ($action === 'dns.delete') {
                    $badge = 'badge badge-warn';
                }
                ?>
                <tr>
                    <td class="col-meta"><span class="badge badge-muted"><?= e($log['time'] ?? '') ?></span></td>
                    <td><strong><?= e($log['actor_username'] ?? '') ?></strong></td>
                    <td class="col-status"><span class="<?= e($badge) ?>"><?= e($action) ?></span></td>
                    <td><?= $zoneName !== '' ? '<span class="badge badge-brand">' . e($zoneName) . '</span>' : '<span class="muted">—</span>' ?></td>
                    <td class="col-meta"><span class="badge badge-muted"><?= e($log['ip'] ?? '') ?></span></td>
                    <td><?= log_context_chips($ctx) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php require __DIR__ . '/partials/pagination.php'; ?>
    <?php endif; ?>
</section>
