<div class="toolbar wrap">
    <form method="get" action="<?= e(url()) ?>" class="filters">
        <input type="hidden" name="r" value="domains">
        <input type="search" name="q" value="<?= e($q ?? '') ?>" placeholder="Search domains…">
        <select name="account_id">
            <option value="">All accounts</option>
            <?php foreach ($accounts as $account): ?>
                <option value="<?= e((string) $account['id']) ?>" <?= ($filterAccount ?? '') === ($account['id'] ?? '') ? 'selected' : '' ?>>
                    <?= e($account['name'] ?? '') ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button class="btn btn-secondary" type="submit">Filter</button>
    </form>
</div>

<?php if (empty($groups)): ?>
    <section class="panel">
        <p class="muted">No domains available for your access level.</p>
    </section>
<?php else: ?>
    <?php foreach ($groups as $group): ?>
        <section class="panel">
            <div class="panel-head">
                <h2><?= e($group['account']['name'] ?? 'Account') ?></h2>
                <span class="muted"><?= count($group['zones']) ?> domain(s)</span>
            </div>
            <?php if (empty($group['zones'])): ?>
                <p class="muted">No domains in this account<?= ($q ?? '') !== '' ? ' matching your search' : '' ?>.</p>
            <?php else: ?>
                <table class="table">
                    <thead>
                    <tr>
                        <th>Domain</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($group['zones'] as $zone): ?>
                        <tr>
                            <td><strong><?= e($zone['name'] ?? '') ?></strong></td>
                            <td><span class="badge badge-ok"><?= e($zone['status'] ?? '') ?></span></td>
                            <td class="actions">
                                <a class="btn btn-small btn-primary" href="<?= e(url('r=records&zone_id=' . urlencode((string) $zone['id']))) ?>">DNS records</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>
    <?php endforeach; ?>
<?php endif; ?>
