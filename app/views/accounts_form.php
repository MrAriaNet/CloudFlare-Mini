<section class="panel narrow">
    <form method="post" action="<?= e(url('r=accounts')) ?>" class="stack">
        <?= csrf_field() ?>
        <input type="hidden" name="form_action" value="<?= $account ? 'update' : 'create' ?>">
        <?php if ($account): ?>
            <input type="hidden" name="id" value="<?= e((string) $account['id']) ?>">
        <?php endif; ?>

        <label>
            <span>Display name</span>
            <input type="text" name="name" required value="<?= e((string) ($account['name'] ?? '')) ?>" placeholder="Production account">
        </label>

        <label>
            <span>API token<?= $account ? ' (leave blank to keep current)' : '' ?></span>
            <input type="password" name="api_token" <?= $account ? '' : 'required' ?> autocomplete="off" placeholder="Cloudflare API Token">
        </label>

        <?php if ($account): ?>
            <p class="muted">Current token: <code><?= e(mask_token((string) ($account['api_token'] ?? ''))) ?></code></p>
        <?php else: ?>
            <p class="muted">Create a Cloudflare API Token with Zone:Read and DNS:Edit permissions for the zones you manage.</p>
        <?php endif; ?>

        <div class="toolbar">
            <button class="btn btn-primary" type="submit"><?= $account ? 'Save changes' : 'Add account' ?></button>
            <a class="btn btn-ghost" href="<?= e(url('r=accounts')) ?>">Cancel</a>
        </div>
    </form>
</section>
