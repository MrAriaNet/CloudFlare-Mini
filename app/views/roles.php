<div class="toolbar">
    <a class="btn btn-primary" href="<?= e(url('r=roles&action=create')) ?>">Add access level</a>
    <a class="btn btn-secondary" href="<?= e(url('r=operators')) ?>">Back to operators</a>
</div>

<section class="panel">
    <div class="panel-head">
        <h2>Access levels</h2>
        <span class="badge badge-info"><?= count($rolesList) ?> level<?= count($rolesList) === 1 ? '' : 's' ?></span>
    </div>
    <p class="muted">
        Rank controls hierarchy. A user can only create/edit operators with a lower rank, and never above their
        <strong>max assignable rank</strong>. Example: Manager (rank 60, max 40) can create Editor and Viewer only.
    </p>
    <div class="table-wrap">
    <table class="table">
        <thead>
        <tr>
            <th>ID</th>
            <th>Name</th>
            <th>Rank</th>
            <th>Max assignable</th>
            <th>Permissions</th>
            <th></th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($rolesList as $role): ?>
            <?php
            $perms = [];
            foreach ($permissionDefs as $key => $label) {
                if (!empty($role['permissions'][$key])) {
                    $perms[] = $label;
                }
            }
            $permCount = count($perms);
            ?>
            <tr>
                <td class="col-status">
                    <div class="meta-row">
                        <span class="badge badge-muted"><?= e($role['id'] ?? '') ?></span>
                        <?php if (!empty($role['system'])): ?>
                            <span class="badge badge-brand">system</span>
                        <?php endif; ?>
                    </div>
                </td>
                <td>
                    <strong><?= e($role['name'] ?? '') ?></strong>
                    <?php if (!empty($role['description'])): ?>
                        <div class="muted small"><?= e($role['description']) ?></div>
                    <?php endif; ?>
                </td>
                <td class="col-count"><span class="badge badge-info">rank <?= (int) ($role['rank'] ?? 0) ?></span></td>
                <td class="col-count"><span class="badge badge-info">max <?= (int) ($role['max_assignable_rank'] ?? 0) ?></span></td>
                <td>
                    <span class="badge badge-<?= $permCount > 0 ? 'ok' : 'muted' ?>">
                        <?= $permCount > 0 ? (int) $permCount . ' permission(s)' : 'None' ?>
                    </span>
                    <?php if ($permCount > 0): ?>
                        <div class="log-chips" style="margin-top:0.45rem;">
                            <?php foreach (array_slice($perms, 0, 5) as $label): ?>
                                <span class="log-chip"><?= e($label) ?></span>
                            <?php endforeach; ?>
                            <?php if ($permCount > 5): ?>
                                <span class="log-chip"><em>+<?= (int) ($permCount - 5) ?> more</em></span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </td>
                <td class="actions">
                    <a class="btn btn-small btn-secondary" href="<?= e(url('r=roles&action=edit&id=' . urlencode((string) $role['id']))) ?>">Edit</a>
                    <?php if (empty($role['system'])): ?>
                        <form method="post" action="<?= e(url('r=roles')) ?>" class="inline" data-confirm="Delete this access level?">
                            <?= csrf_field() ?>
                            <input type="hidden" name="form_action" value="delete">
                            <input type="hidden" name="id" value="<?= e((string) $role['id']) ?>">
                            <button class="btn btn-small btn-danger" type="submit">Delete</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</section>
