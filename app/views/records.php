<div class="toolbar wrap">
    <div>
        <p class="muted">Account: <strong><?= e($account['name'] ?? '') ?></strong> · Domain: <strong><?= e($zone['name'] ?? '') ?></strong></p>
    </div>
    <div class="actions">
        <a class="btn btn-ghost" href="<?= e(url('r=domains&account_id=' . urlencode((string) ($zone['account_id'] ?? '')))) ?>">Back to domains</a>
        <?php if ($access->canRefreshDns()): ?>
        <form method="post" action="<?= e(url('r=records&zone_id=' . urlencode((string) $zone['id']))) ?>" class="inline">
            <?= csrf_field() ?>
            <input type="hidden" name="zone_id" value="<?= e((string) $zone['id']) ?>">
            <input type="hidden" name="form_action" value="refresh">
            <button class="btn btn-secondary" type="submit">Refresh from Cloudflare</button>
        </form>
        <?php endif; ?>
        <?php if ($access->canCreateDns()): ?>
            <a class="btn btn-secondary" href="<?= e(url('r=records&zone_id=' . urlencode((string) $zone['id']) . '&action=bulk_create')) ?>">Bulk add</a>
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

<?php if (!empty($showBulkCreate)): ?>
<section class="panel">
    <div class="panel-head">
        <h2>Bulk add DNS records</h2>
        <span class="badge badge-info">max 200 / submit</span>
    </div>
    <p class="muted">
        One record per line. Columns: <code>type,name,content,ttl,proxied,priority</code>
        (ttl / proxied / priority optional). Use CSV, <code>|</code>, or tab separators. Lines starting with <code>#</code> are ignored.
    </p>
    <pre class="log-json" style="max-width:none;margin:0 0 1rem;padding:0.75rem 1rem;background:#f3f6f8;border-radius:10px;border:1px solid var(--line-soft);"># examples
A,www,203.0.113.10,1,0,
A,api,203.0.113.11,1,1,
CNAME,blog,www.example.com,1,0,
MX,@,mail.example.com,1,0,10
TXT,@,"v=spf1 include:_spf.google.com ~all",1,0,
PTR,10,host.example.com,1,0,</pre>
    <form method="post" action="<?= e(url('r=records&zone_id=' . urlencode((string) $zone['id']))) ?>" class="stack">
        <?= csrf_field() ?>
        <input type="hidden" name="zone_id" value="<?= e((string) $zone['id']) ?>">
        <input type="hidden" name="form_action" value="bulk_create">
        <label>
            <span>Records</span>
            <textarea name="bulk_text" rows="14" required placeholder="A,www,1.2.3.4,1,0,"></textarea>
        </label>
        <div class="toolbar">
            <button class="btn btn-primary" type="submit">Create all</button>
            <a class="btn btn-secondary" href="<?= e(url('r=records&zone_id=' . urlencode((string) $zone['id']))) ?>">Cancel</a>
        </div>
    </form>
</section>
<?php endif; ?>

<?php if (!empty($showBulkEdit) && !empty($bulkEditRecords)): ?>
<section class="panel">
    <div class="panel-head">
        <h2>Bulk edit DNS records</h2>
        <span class="badge badge-info"><?= count($bulkEditRecords) ?> selected</span>
    </div>
    <p class="muted">Update fields below, then save. Empty priority is allowed for non-MX/SRV types.</p>
    <form method="post" action="<?= e(url('r=records&zone_id=' . urlencode((string) $zone['id']))) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="zone_id" value="<?= e((string) $zone['id']) ?>">
        <input type="hidden" name="form_action" value="bulk_update">
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th>Type</th>
                    <th>Name</th>
                    <th>Content</th>
                    <th>TTL</th>
                    <th>Proxied</th>
                    <th>Priority</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($bulkEditRecords as $record): ?>
                    <?php $rid = (string) ($record['id'] ?? ''); ?>
                    <tr>
                        <td>
                            <select name="rows[<?= e($rid) ?>][type]" required>
                                <?php foreach ($dnsTypes as $type): ?>
                                    <option value="<?= e($type) ?>" <?= ($record['type'] ?? '') === $type ? 'selected' : '' ?>><?= e($type) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td>
                            <input type="text" name="rows[<?= e($rid) ?>][name]" required value="<?= e((string) ($record['name'] ?? '')) ?>">
                        </td>
                        <td>
                            <input type="text" name="rows[<?= e($rid) ?>][content]" required value="<?= e((string) ($record['content'] ?? '')) ?>">
                        </td>
                        <td style="min-width:5.5rem;">
                            <input type="number" name="rows[<?= e($rid) ?>][ttl]" min="1" value="<?= e((string) ($record['ttl'] ?? '1')) ?>">
                        </td>
                        <td class="col-status">
                            <label class="checkbox" style="margin:0;">
                                <input type="checkbox" name="rows[<?= e($rid) ?>][proxied]" value="1" <?= !empty($record['proxied']) ? 'checked' : '' ?>>
                                <span>On</span>
                            </label>
                        </td>
                        <td style="min-width:5.5rem;">
                            <input type="number" name="rows[<?= e($rid) ?>][priority]" value="<?= e((string) ($record['priority'] ?? '')) ?>">
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="toolbar" style="margin-top:1rem;">
            <button class="btn btn-primary" type="submit">Save all changes</button>
            <a class="btn btn-secondary" href="<?= e(url('r=records&zone_id=' . urlencode((string) $zone['id']))) ?>">Cancel</a>
        </div>
    </form>
</section>
<?php endif; ?>

<section class="panel">
    <div class="panel-head">
        <h2>Records for <?= e($zone['name'] ?? '') ?></h2>
        <span class="badge badge-info"><?= count($records) ?> record<?= count($records) === 1 ? '' : 's' ?></span>
    </div>
    <?php if (!empty($recordFilter)): ?>
        <p class="muted">Filtered by: <code><?= e($recordFilter) ?></code>
            · <a href="<?= e(url('r=records&zone_id=' . urlencode((string) $zone['id']))) ?>">Clear filter</a>
        </p>
    <?php endif; ?>
    <?php if (empty($records)): ?>
        <p class="muted">No DNS records cached for this domain.</p>
    <?php else: ?>
        <?php if ($access->canEditDns()): ?>
            <div class="toolbar wrap" style="margin-bottom:0.85rem;">
                <span class="muted">Select rows, then bulk edit</span>
                <button
                    class="btn btn-secondary"
                    type="button"
                    id="records-bulk-edit-btn"
                    data-zone-id="<?= e((string) $zone['id']) ?>"
                    data-base="<?= e(url('r=records')) ?>"
                >Bulk edit selected</button>
            </div>
        <?php endif; ?>
        <div class="table-wrap">
        <table class="table">
            <thead>
            <tr>
                <?php if ($access->canEditDns()): ?>
                    <th style="width:2rem;"><input type="checkbox" id="records-check-all" title="Select all"></th>
                <?php endif; ?>
                <th>Type</th>
                <th>Name</th>
                <th>Content</th>
                <th>TTL</th>
                <th>Proxy</th>
                <th>Priority</th>
                <?php if ($access->canEditDns() || $access->canDeleteDns()): ?>
                    <th></th>
                <?php endif; ?>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($records as $record): ?>
                <tr>
                    <?php if ($access->canEditDns()): ?>
                        <td>
                            <input type="checkbox" class="record-check" value="<?= e((string) ($record['id'] ?? '')) ?>">
                        </td>
                    <?php endif; ?>
                    <td class="col-status"><span class="badge badge-brand"><?= e($record['type'] ?? '') ?></span></td>
                    <td><?= e($record['name'] ?? '') ?></td>
                    <td class="break"><?= e($record['content'] ?? '') ?></td>
                    <td class="col-meta">
                        <span class="badge badge-muted"><?= (int) ($record['ttl'] ?? 1) === 1 ? 'Auto' : (int) $record['ttl'] ?></span>
                    </td>
                    <td class="col-status">
                        <?php if (!empty($record['proxied'])): ?>
                            <span class="badge badge-info">Proxied</span>
                        <?php else: ?>
                            <span class="badge badge-muted">DNS only</span>
                        <?php endif; ?>
                    </td>
                    <td class="col-meta">
                        <?php if ($record['priority'] !== null && $record['priority'] !== ''): ?>
                            <span class="badge badge-muted"><?= e((string) $record['priority']) ?></span>
                        <?php else: ?>
                            <span class="muted">—</span>
                        <?php endif; ?>
                    </td>
                    <?php if ($access->canEditDns() || $access->canDeleteDns()): ?>
                        <td class="actions">
                            <?php if ($access->canEditDns()): ?>
                            <a class="btn btn-small btn-secondary" href="<?= e(url('r=records&zone_id=' . urlencode((string) $zone['id']) . '&action=edit&record_id=' . urlencode((string) $record['id']))) ?>">Edit</a>
                            <?php endif; ?>
                            <?php if ($access->canDeleteDns()): ?>
                            <form method="post" action="<?= e(url('r=records&zone_id=' . urlencode((string) $zone['id']))) ?>" class="inline" data-confirm="Delete this DNS record?">
                                <?= csrf_field() ?>
                                <input type="hidden" name="zone_id" value="<?= e((string) $zone['id']) ?>">
                                <input type="hidden" name="form_action" value="delete">
                                <input type="hidden" name="record_id" value="<?= e((string) $record['id']) ?>">
                                <button class="btn btn-small btn-danger" type="submit">Delete</button>
                            </form>
                            <?php endif; ?>
                        </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</section>
