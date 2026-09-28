<section class="panel narrow">
    <form method="post" action="<?= e(url('r=operators')) ?>" class="stack" id="operator-form">
        <?= csrf_field() ?>
        <input type="hidden" name="form_action" value="<?= $operator ? 'update' : 'create' ?>">
        <?php if ($operator): ?>
            <input type="hidden" name="id" value="<?= e((string) $operator['id']) ?>">
        <?php endif; ?>

        <label>
            <span>Username</span>
            <input type="text" name="username" required value="<?= e((string) ($operator['username'] ?? '')) ?>">
        </label>

        <label>
            <span>Password<?= $operator ? ' (leave blank to keep current)' : '' ?></span>
            <input type="password" name="password" <?= $operator ? '' : 'required' ?> autocomplete="new-password">
        </label>

        <label>
            <span>Access level</span>
            <select name="role" id="operator-role" required>
                <?php
                $selectedRole = $operator['role'] ?? (($assignableRoles[0]['id'] ?? 'viewer'));
                foreach ($assignableRoles as $roleOption):
                    $rid = (string) ($roleOption['id'] ?? '');
                    $label = ($roleOption['name'] ?? $rid) . ' (rank ' . (int) ($roleOption['rank'] ?? 0) . ')';
                    if (!empty($roleOption['description'])) {
                        $label .= ' — ' . $roleOption['description'];
                    }
                ?>
                    <option value="<?= e($rid) ?>"
                        data-rank="<?= (int) ($roleOption['rank'] ?? 0) ?>"
                        <?= $selectedRole === $rid ? 'selected' : '' ?>>
                        <?= e($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <p class="muted small">You can only assign levels at or below your allowed maximum. Higher levels are hidden.</p>

        <fieldset class="domain-access" id="domain-access-box">
            <legend>Domain access</legend>
            <?php $domainAccess = $operator['domain_access'] ?? 'selected'; ?>
            <label class="checkbox">
                <input type="radio" name="domain_access" value="all" <?= $domainAccess === 'all' ? 'checked' : '' ?>>
                <span>All domains</span>
            </label>
            <label class="checkbox">
                <input type="radio" name="domain_access" value="selected" <?= $domainAccess !== 'all' ? 'checked' : '' ?>>
                <span>Selected domains only</span>
            </label>

            <div class="zone-picker" id="zone-picker">
                <?php if (empty($allZones)): ?>
                    <p class="muted">No domains synced yet. Add a Cloudflare account and sync first.</p>
                <?php else: ?>
                    <?php
                    $allowed = $operator['allowed_zone_ids'] ?? [];
                    foreach ($allZones as $zone):
                    ?>
                        <label class="checkbox">
                            <input type="checkbox" name="allowed_zone_ids[]" value="<?= e((string) $zone['id']) ?>"
                                <?= in_array($zone['id'], $allowed, true) ? 'checked' : '' ?>>
                            <span><?= e($zone['name'] ?? '') ?> <em class="muted">(<?= e($zone['account_name'] ?? '') ?>)</em></span>
                        </label>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </fieldset>

        <label class="checkbox">
            <input type="checkbox" name="active" value="1" <?= !isset($operator) || !empty($operator['active']) ? 'checked' : '' ?>>
            <span>Active</span>
        </label>

        <div class="toolbar">
            <button class="btn btn-primary" type="submit"><?= $operator ? 'Save operator' : 'Create operator' ?></button>
            <a class="btn btn-ghost" href="<?= e(url('r=operators')) ?>">Cancel</a>
        </div>
    </form>
</section>
