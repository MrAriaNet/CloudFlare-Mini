<section class="stats">
    <div class="stat">
        <span>Accounts</span>
        <strong><?= (int) ($accounts_total ?? 0) ?></strong>
    </div>
    <div class="stat">
        <span>Domains</span>
        <strong><?= (int) ($zone_count ?? 0) ?></strong>
    </div>
    <div class="stat">
        <span>DNS Records</span>
        <strong><?= (int) ($record_count ?? 0) ?></strong>
    </div>
</section>

<?php if ($access->canManageAccounts()): ?>
<div class="toolbar">
    <form method="post" action="<?= e(url('r=sync')) ?>">
        <?= csrf_field() ?>
        <button class="btn btn-secondary" type="submit">Force sync all accounts</button>
    </form>
    <a class="btn btn-primary" href="<?= e(url('r=accounts&action=create')) ?>">Add account</a>
</div>
<?php endif; ?>

<section class="panel">
    <h2>Account sync status</h2>
    <?php if (empty($accounts)): ?>
        <p class="muted">No Cloudflare accounts yet.<?= $access->canManageAccounts() ? ' Add an API token to get started.' : '' ?></p>
    <?php else: ?>
        <table class="table">
            <thead>
            <tr>
                <th>Account</th>
                <th>Status</th>
                <th>Last sync</th>
                <th>Last error</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($accounts as $account): ?>
                <?php $m = $meta[$account['id']] ?? []; ?>
                <tr>
                    <td><?= e($account['name'] ?? '') ?></td>
                    <td><span class="badge badge-<?= e(($account['status'] ?? '') === 'ok' ? 'ok' : 'warn') ?>"><?= e($account['status'] ?? 'unknown') ?></span></td>
                    <td><?= e($m['last_sync_iso'] ?? ($account['last_sync'] ?? 'Never')) ?></td>
                    <td class="muted"><?= e($m['last_error'] ?? ($account['last_error'] ?? '—')) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>
