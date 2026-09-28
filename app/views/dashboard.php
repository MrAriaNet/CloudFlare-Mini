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

<?php if ($access->canManageAccounts() || $access->canForceSync()): ?>
<div class="toolbar">
    <?php if ($access->canForceSync()): ?>
    <form method="post" action="<?= e(url('r=sync')) ?>">
        <?= csrf_field() ?>
        <button class="btn btn-secondary" type="submit">Force sync all accounts</button>
    </form>
    <?php endif; ?>
    <?php if ($access->canManageAccounts()): ?>
    <a class="btn btn-primary" href="<?= e(url('r=accounts&action=create')) ?>">Add account</a>
    <?php endif; ?>
</div>
<?php endif; ?>

<section class="panel">
    <h2>Account sync status</h2>
    <p class="muted">Pages load from cache only. Schedule <code>scripts/cron.php</code> (or <code>/cron.php?key=...</code>) every hour to keep data fresh. DNS create/edit/delete still apply to Cloudflare immediately.</p>
    <?php if (empty($accounts)): ?>
        <p class="muted">No Cloudflare accounts yet.<?= $access->canManageAccounts() ? ' Add an API token to get started.' : '' ?></p>
    <?php else: ?>
        <div class="table-wrap">
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
                <?php
                $m = $meta[$account['id']] ?? [];
                $lastSync = $m['last_sync_iso'] ?? ($account['last_sync'] ?? 'Never');
                $syncNever = ($lastSync === '' || $lastSync === 'Never');
                $err = $m['last_error'] ?? ($account['last_error'] ?? '');
                ?>
                <tr>
                    <td><strong><?= e($account['name'] ?? '') ?></strong></td>
                    <td class="col-status"><span class="badge badge-<?= e(($account['status'] ?? '') === 'ok' ? 'ok' : 'warn') ?>"><?= e($account['status'] ?? 'unknown') ?></span></td>
                    <td class="col-meta">
                        <span class="badge badge-<?= $syncNever ? 'warn' : 'info' ?>"><?= e($syncNever ? 'Never' : $lastSync) ?></span>
                    </td>
                    <td>
                        <?php if ($err !== '' && $err !== null): ?>
                            <span class="badge badge-danger"><?= e((string) $err) ?></span>
                        <?php else: ?>
                            <span class="badge badge-muted">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</section>
