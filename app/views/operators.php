<div class="toolbar">
    <a class="btn btn-primary" href="<?= e(url('r=operators&action=create')) ?>">Add operator</a>
</div>

<section class="panel">
    <table class="table">
        <thead>
        <tr>
            <th>Username</th>
            <th>Role</th>
            <th>Domain access</th>
            <th>Active</th>
            <th>Created</th>
            <th></th>
        </tr>
        </thead>
        <tbody>
        <?php
        $zoneNames = [];
        foreach ($allZones as $z) {
            $zoneNames[$z['id']] = $z['name'] ?? $z['id'];
        }
        foreach ($operators as $operator):
            $scope = ($operator['role'] ?? '') === 'admin' || ($operator['domain_access'] ?? '') === 'all'
                ? 'All domains'
                : (count($operator['allowed_zone_ids'] ?? []) . ' selected');
        ?>
            <tr>
                <td><strong><?= e($operator['username'] ?? '') ?></strong></td>
                <td><span class="badge"><?= e(strtoupper((string) ($operator['role'] ?? ''))) ?></span></td>
                <td>
                    <?= e($scope) ?>
                    <?php if (($operator['domain_access'] ?? '') === 'selected' && ($operator['role'] ?? '') !== 'admin'): ?>
                        <div class="muted small">
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
                <td><?= !empty($operator['active']) ? 'Yes' : 'No' ?></td>
                <td><?= e($operator['created_at'] ?? '') ?></td>
                <td class="actions">
                    <a class="btn btn-small btn-secondary" href="<?= e(url('r=operators&action=edit&id=' . urlencode((string) $operator['id']))) ?>">Edit</a>
                    <form method="post" action="<?= e(url('r=operators')) ?>" class="inline" data-confirm="Delete this operator?">
                        <?= csrf_field() ?>
                        <input type="hidden" name="form_action" value="delete">
                        <input type="hidden" name="id" value="<?= e((string) $operator['id']) ?>">
                        <button class="btn btn-small btn-danger" type="submit">Delete</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>
