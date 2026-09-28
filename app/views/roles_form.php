<section class="panel narrow">
    <form method="post" action="<?= e(url('r=roles')) ?>" class="stack">
        <?= csrf_field() ?>
        <input type="hidden" name="form_action" value="save">
        <?php if ($role): ?>
            <input type="hidden" name="existing_id" value="<?= e((string) $role['id']) ?>">
        <?php endif; ?>

        <label>
            <span>Role ID<?= !empty($role['system']) ? ' (locked)' : '' ?></span>
            <input type="text" name="id" required pattern="[a-z0-9_\-]+"
                   value="<?= e((string) ($role['id'] ?? '')) ?>"
                <?= !empty($role['system']) ? 'readonly' : '' ?>
                   placeholder="e.g. support">
        </label>

        <label>
            <span>Display name</span>
            <input type="text" name="name" required value="<?= e((string) ($role['name'] ?? '')) ?>" placeholder="Support lead">
        </label>

        <label>
            <span>Description</span>
            <input type="text" name="description" value="<?= e((string) ($role['description'] ?? '')) ?>">
        </label>

        <label>
            <span>Rank (1–100, higher = more power)</span>
            <input type="number" name="rank" min="1" max="100" required value="<?= e((string) ($role['rank'] ?? '30')) ?>">
        </label>

        <label>
            <span>Max assignable rank (when managing operators)</span>
            <input type="number" name="max_assignable_rank" min="0" max="100"
                   value="<?= e((string) ($role['max_assignable_rank'] ?? '0')) ?>">
        </label>
        <p class="muted small">Example: set to 40 so this level can create Editor (40) and Viewer (20), but not Manager (60).</p>

        <?php foreach ($permissionGroups as $groupName => $groupPerms): ?>
            <fieldset class="domain-access">
                <legend><?= e($groupName) ?></legend>
                <?php foreach ($groupPerms as $key => $label): ?>
                    <label class="checkbox">
                        <input type="checkbox" name="permissions[<?= e($key) ?>]" value="1"
                            <?= !empty($role['permissions'][$key]) ? 'checked' : '' ?>>
                        <span><?= e($label) ?></span>
                    </label>
                <?php endforeach; ?>
            </fieldset>
        <?php endforeach; ?>

        <div class="toolbar">
            <button class="btn btn-primary" type="submit">Save access level</button>
            <a class="btn btn-ghost" href="<?= e(url('r=roles')) ?>">Cancel</a>
        </div>
    </form>
</section>
