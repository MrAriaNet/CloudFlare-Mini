<div class="toolbar">
    <form method="get" action="<?= e(url()) ?>" class="filters">
        <input type="hidden" name="r" value="logs">
        <input type="search" name="q" value="<?= e($q ?? '') ?>" placeholder="Search actions, users, details…">
        <button class="btn btn-secondary" type="submit">Search</button>
    </form>
</div>

<section class="panel">
    <?php if (!$access->canViewAllLogs()): ?>
        <p class="muted">Showing your own actions only.</p>
    <?php endif; ?>

    <?php if (empty($logs)): ?>
        <p class="muted">No log entries found.</p>
    <?php else: ?>
        <table class="table">
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
                <tr>
                    <td><?= e($log['time'] ?? '') ?></td>
                    <td><?= e($log['actor_username'] ?? '') ?></td>
                    <td><code><?= e($log['action'] ?? '') ?></code></td>
                    <td><?= e($log['ip'] ?? '') ?></td>
                    <td class="break"><pre class="log-json"><?= e(json_encode($log['context'] ?? new stdClass(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></pre></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>
