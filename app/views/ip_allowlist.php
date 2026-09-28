<section class="panel narrow">
    <p class="muted">
        Restrict panel login and access to specific IPs or CIDR subnets.
        When disabled, all IPs are allowed. Cron URL (<code>/cron.php</code>) is not affected.
    </p>
    <p><strong>Your current IP:</strong> <code><?= e($current_ip) ?></code></p>

    <form method="post" action="<?= e(url('r=ip_allowlist')) ?>" class="stack">
        <?= csrf_field() ?>

        <label class="checkbox">
            <input type="checkbox" name="enabled" value="1" <?= !empty($settings['enabled']) ? 'checked' : '' ?>>
            <span>Enable IP allowlist</span>
        </label>

        <label>
            <span>Allowed IPs / subnets (one per line)</span>
            <textarea name="entries" rows="12" placeholder="203.0.113.10&#10;192.168.1.0/24&#10;2001:db8::/32"><?= e(implode("\n", $settings['entries'] ?? [])) ?></textarea>
        </label>
        <p class="muted small">
            Examples: <code>203.0.113.10</code> (single IP) · <code>10.0.0.0/8</code> (subnet) · lines starting with <code>#</code> are comments.
            Always include your current IP before enabling.
        </p>

        <?php if (!empty($settings['updated_at'])): ?>
            <p class="muted small">Last updated: <?= e((string) $settings['updated_at']) ?></p>
        <?php endif; ?>

        <div class="toolbar">
            <button class="btn btn-primary" type="submit">Save allowlist</button>
        </div>
    </form>
</section>
