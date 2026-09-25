<div class="toolbar wrap">
    <div>
        <p class="muted">Account: <strong><?= e($account['name'] ?? '') ?></strong> · Domain: <strong><?= e($zone['name'] ?? '') ?></strong></p>
    </div>
    <div class="actions">
        <a class="btn btn-ghost" href="<?= e(url('r=domains&account_id=' . urlencode((string) ($zone['account_id'] ?? '')))) ?>">Back to domains</a>
        <form method="post" action="<?= e(url('r=records&zone_id=' . urlencode((string) $zone['id']))) ?>" class="inline">
            <?= csrf_field() ?>
            <input type="hidden" name="zone_id" value="<?= e((string) $zone['id']) ?>">
            <input type="hidden" name="form_action" value="refresh">
            <button class="btn btn-secondary" type="submit">Refresh from Cloudflare</button>
        </form>
        <?php if ($access->canMutateDns()): ?>
            <a class="btn btn-primary" href="<?= e(url('r=records&zone_id=' . urlencode((string) $zone['id']) . '&action=create')) ?>">Add record</a>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($showForm)): ?>
<section class="panel narrow">
    <h2><?= $editRecord ? 'Edit DNS record' : 'Add DNS record' ?></h2>
    <form method="post" action="<?= e(url('r=records&zone_id=' . urlencode((string) $zone['id']))) ?>" class="stack">
        <?= csrf_field() ?>
        <input type="hidden" name="zone_id" value="<?= e((string) $zone['id']) ?>">
        <input type="hidden" name="form_action" value="<?= $editRecord ? 'update' : 'create' ?>">
        <?php if ($editRecord): ?>
            <input type="hidden" name="record_id" value="<?= e((string) $editRecord['id']) ?>">
        <?php endif; ?>

        <label>
            <span>Type</span>
            <select name="type" id="dns-type" required>
                <?php
                $selectedType = $editRecord['type'] ?? 'A';
                foreach ($dnsTypes as $type):
                ?>
                    <option value="<?= e($type) ?>" <?= $selectedType === $type ? 'selected' : '' ?>><?= e($type) ?></option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>
            <span>Name</span>
            <input type="text" name="name" required value="<?= e((string) ($editRecord['name'] ?? '')) ?>" placeholder="www or @">
        </label>

        <label>
            <span>Content</span>
            <input type="text" name="content" required value="<?= e((string) ($editRecord['content'] ?? '')) ?>" placeholder="IP, hostname, text, or PTR target">
        </label>

        <label>
            <span>TTL (1 = Auto)</span>
            <input type="number" name="ttl" min="1" value="<?= e((string) ($editRecord['ttl'] ?? '1')) ?>">
        </label>

        <label>
            <span>Priority (MX / SRV)</span>
            <input type="number" name="priority" value="<?= e((string) ($editRecord['priority'] ?? '')) ?>">
        </label>

        <label class="checkbox">
            <input type="checkbox" name="proxied" value="1" <?= !empty($editRecord['proxied']) ? 'checked' : '' ?>>
            <span>Proxied (orange cloud) for A / AAAA / CNAME</span>
        </label>

        <div class="toolbar">
            <button class="btn btn-primary" type="submit"><?= $editRecord ? 'Save record' : 'Create record' ?></button>
            <a class="btn btn-ghost" href="<?= e(url('r=records&zone_id=' . urlencode((string) $zone['id']))) ?>">Cancel</a>
        </div>
    </form>
</section>
<?php endif; ?>

<section class="panel">
    <h2>Records for <?= e($zone['name'] ?? '') ?></h2>
    <?php if (empty($records)): ?>
        <p class="muted">No DNS records cached for this domain.</p>
    <?php else: ?>
        <table class="table">
            <thead>
            <tr>
                <th>Type</th>
                <th>Name</th>
                <th>Content</th>
                <th>TTL</th>
                <th>Proxy</th>
                <th>Priority</th>
                <?php if ($access->canMutateDns()): ?><th></th><?php endif; ?>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($records as $record): ?>
                <tr>
                    <td><span class="badge"><?= e($record['type'] ?? '') ?></span></td>
                    <td><?= e($record['name'] ?? '') ?></td>
                    <td class="break"><?= e($record['content'] ?? '') ?></td>
                    <td><?= (int) ($record['ttl'] ?? 1) === 1 ? 'Auto' : (int) $record['ttl'] ?></td>
                    <td><?= !empty($record['proxied']) ? 'Proxied' : 'DNS only' ?></td>
                    <td><?= e($record['priority'] !== null && $record['priority'] !== '' ? (string) $record['priority'] : '—') ?></td>
                    <?php if ($access->canMutateDns()): ?>
                        <td class="actions">
                            <a class="btn btn-small btn-secondary" href="<?= e(url('r=records&zone_id=' . urlencode((string) $zone['id']) . '&action=edit&record_id=' . urlencode((string) $record['id']))) ?>">Edit</a>
                            <form method="post" action="<?= e(url('r=records&zone_id=' . urlencode((string) $zone['id']))) ?>" class="inline" data-confirm="Delete this DNS record?">
                                <?= csrf_field() ?>
                                <input type="hidden" name="zone_id" value="<?= e((string) $zone['id']) ?>">
                                <input type="hidden" name="form_action" value="delete">
                                <input type="hidden" name="record_id" value="<?= e((string) $record['id']) ?>">
                                <button class="btn btn-small btn-danger" type="submit">Delete</button>
                            </form>
                        </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>
