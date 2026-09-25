<div class="toolbar">
    <a class="btn btn-primary" href="<?= e(url('r=accounts&action=create')) ?>">Add account</a>
</div>

<section class="panel">
    <?php if (empty($accounts)): ?>
        <p class="muted">No Cloudflare accounts configured.</p>
    <?php else: ?>
        <table class="table">
            <thead>
            <tr>
                <th>Name</th>
                <th>Token</th>
                <th>Domains</th>
                <th>Status</th>
                <th>Last sync</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($accounts as $account): ?>
                <?php
                $aid = $account['id'] ?? '';
                $zoneCount = count($zonesByAccount[$aid] ?? []);
                $m = $meta[$aid] ?? [];
                ?>
                <tr>
                    <td><strong><?= e($account['name'] ?? '') ?></strong></td>
                    <td><code><?= e(mask_token((string) ($account['api_token'] ?? ''))) ?></code></td>
                    <td><?= (int) $zoneCount ?></td>
                    <td><span class="badge badge-<?= e(($account['status'] ?? '') === 'ok' ? 'ok' : 'warn') ?>"><?= e($account['status'] ?? 'unknown') ?></span></td>
                    <td><?= e($m['last_sync_iso'] ?? ($account['last_sync'] ?? 'Never')) ?></td>
                    <td class="actions">
                        <form method="post" action="<?= e(url('r=accounts')) ?>" class="inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="form_action" value="sync">
                            <input type="hidden" name="id" value="<?= e((string) $aid) ?>">
                            <button class="btn btn-small" type="submit">Sync</button>
                        </form>
                        <a class="btn btn-small btn-secondary" href="<?= e(url('r=domains&account_id=' . urlencode((string) $aid))) ?>">Domains</a>
                        <a class="btn btn-small btn-secondary" href="<?= e(url('r=accounts&action=edit&id=' . urlencode((string) $aid))) ?>">Edit</a>
                        <form method="post" action="<?= e(url('r=accounts')) ?>" class="inline" data-confirm="Delete this Cloudflare account and its cached data?">
                            <?= csrf_field() ?>
                            <input type="hidden" name="form_action" value="delete">
                            <input type="hidden" name="id" value="<?= e((string) $aid) ?>">
                            <button class="btn btn-small btn-danger" type="submit">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>
