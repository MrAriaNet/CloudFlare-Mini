<section class="panel narrow">
    <p class="muted">Update the password for your account (<strong><?= e($user['username'] ?? '') ?></strong>).</p>
    <form method="post" action="<?= e(url('r=password')) ?>" class="stack">
        <?= csrf_field() ?>

        <label>
            <span>Current password</span>
            <input type="password" name="current_password" required autocomplete="current-password">
        </label>

        <label>
            <span>New password</span>
            <input type="password" name="new_password" required minlength="6" autocomplete="new-password">
        </label>

        <label>
            <span>Confirm new password</span>
            <input type="password" name="confirm_password" required minlength="6" autocomplete="new-password">
        </label>

        <div class="toolbar">
            <button class="btn btn-primary" type="submit">Update password</button>
        </div>
    </form>
</section>
