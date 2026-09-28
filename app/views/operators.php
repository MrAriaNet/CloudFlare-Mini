<div class="toolbar">
    <?php if ($access->assignableRoles() !== []): ?>
        <a class="btn btn-primary" href="<?= e(url('r=operators&action=create')) ?>">Add operator</a>
    <?php endif; ?>
    <?php if ($access->canManageRoles()): ?>
        <a class="btn btn-secondary" href="<?= e(url('r=roles')) ?>">Access levels</a>
    <?php endif; ?>
</div>

<section class="panel">
    <p class="muted">You can manage operators whose access level is within your allowed rank limit.</p>
    <table class="table">
        <thead>
        <tr>
            <th>Username</th>
            <th>Access level</th>
            <th>Rank</th>
            <th>Domain access</th>
            <th>Active</th>
            <th></th>
        </tr>
        </thead>
        <tbody>
        <?php
        $zoneNames = [];
        foreach ($allZones as $z) {
            $zoneNames[$z['id']] = $z['name'] ?? $z['id'];
        }
        $currentId = $user['id'] ?? '';
        foreach ($operators as $operator):
            $roleDef = $rolesSvc->findOrFallback((string) ($operator['role'] ?? 'viewer'));
            $scope = ($roleDef['id'] ?? '') === 'admin' || ($operator['domain_access'] ?? '') === 'all'
                ? 'All domains'
                : (count($operator['allowed_zone_ids'] ?? []) . ' selected');
            $isSelf = ($operator['id'] ?? '') === $currentId;
            $canManage = $isSelf || $access->canManageOperator($operator);
        ?>
            <tr>
                <td><strong><?= e($operator['username'] ?? '') ?></strong><?= $isSelf ? ' <span class="muted">(you)</span>' : '' ?></td>
                <td class="col-status"><span class="badge badge-brand"><?= e($roleDef['name'] ?? ($operator['role'] ?? '')) ?></span></td>
                <td class="col-count"><span class="badge badge-info">rank <?= (int) ($roleDef['rank'] ?? 0) ?></span></td>
                <td>
                    <span class="badge badge-<?= (($roleDef['id'] ?? '') === 'admin' || ($operator['domain_access'] ?? '') === 'all') ? 'ok' : 'warn' ?>"><?= e($scope) ?></span>
                    <?php if (($operator['domain_access'] ?? '') === 'selected' && ($roleDef['id'] ?? '') !== 'admin'): ?>
                        <div class="muted small" style="margin-top:0.35rem;">
                            <?php
                            $names = [];
                            foreach ($operator['allowed_zone_ids'] ?? [] as $zid) {
                                $names[] = $zoneNames[$zid] ?? $zid;
                            }
                            echo e(implode(', ', $names) ?: 'None');
                            ?>
                        </div>
                    <?php endif; ?>
                </td>
                <td class="col-status">
                    <?php if (!empty($operator['active'])): ?>
                        <span class="badge badge-ok">Active</span>
                    <?php else: ?>
                        <span class="badge badge-danger">Inactive</span>
                    <?php endif; ?>
                </td>
                <td class="actions">
                    <?php if ($canManage): ?>
                        <a class="btn btn-small btn-secondary" href="<?= e(url('r=operators&action=edit&id=' . urlencode((string) $operator['id']))) ?>">Edit</a>
                    <?php endif; ?>
                    <?php if (!$isSelf && $access->canManageOperator($operator)): ?>
                        <form method="post" action="<?= e(url('r=operators')) ?>" class="inline" data-confirm="Delete this operator?">
                            <?= csrf_field() ?>
                            <input type="hidden" name="form_action" value="delete">
                            <input type="hidden" name="id" value="<?= e((string) $operator['id']) ?>">
                            <button class="btn btn-small btn-danger" type="submit">Delete</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>
