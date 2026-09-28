<div class="toolbar wrap">
    <form method="get" action="<?= e(url()) ?>" class="filters">
        <input type="hidden" name="r" value="domains">
        <input type="search" name="q" value="<?= e($q ?? '') ?>" placeholder="Search domains or IP range…">
        <select name="account_id">
            <option value="">All accounts</option>
            <?php foreach ($accounts as $account): ?>
                <option value="<?= e((string) $account['id']) ?>" <?= ($filterAccount ?? '') === ($account['id'] ?? '') ? 'selected' : '' ?>>
                    <?= e($account['name'] ?? '') ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button class="btn btn-secondary" type="submit">Filter</button>
    </form>
    <form method="get" action="<?= e(url()) ?>" class="filters">
        <input type="hidden" name="r" value="domains">
        <?php if (($filterAccount ?? '') !== ''): ?>
            <input type="hidden" name="account_id" value="<?= e($filterAccount) ?>">
        <?php endif; ?>
        <input type="search" name="ip" value="<?= e($ipSearch ?? '') ?>" placeholder="Lookup IP e.g. 192.168.1.10" inputmode="decimal">
        <button class="btn btn-primary" type="submit">Find PTR</button>
    </form>
</div>

<p class="meta-row" style="margin-bottom:1rem;">
    <span class="badge badge-brand"><?= (int) ($forwardCount ?? 0) ?> forward</span>
    <span class="badge badge-warn"><?= (int) ($reverseCount ?? 0) ?> reverse (PTR)</span>
    <?php if ($access->canViewPtrAudit()): ?>
        <a class="badge badge-info" href="<?= e(url('r=ptr_audit')) ?>">PTR Audit</a>
    <?php endif; ?>
</p>

<?php if (!empty($ipError)): ?>
    <div class="alert alert-error"><?= e($ipError) ?></div>
<?php endif; ?>

<?php if (($ipSearch ?? '') !== '' && empty($ipError)): ?>
<section class="panel">
            <div class="panel-head">
                <h2>PTR lookup for <code><?= e($ipSearch) ?></code></h2>
                <span class="badge badge-info"><?= count($ipResults) ?> match(es)</span>
            </div>

    <?php if (!empty($ipResults)): ?>
        <table class="table">
            <thead>
            <tr>
                <th>IP</th>
                <th>Hostname (PTR)</th>
                <th>Zone / IP range</th>
                <th>Account</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($ipResults as $hit): ?>
                <tr>
                    <td><code><?= e($hit['ip']) ?></code></td>
                    <td><strong><?= e($hit['hostname']) ?></strong></td>
                    <td><?= e($hit['zone']['name'] ?? '') ?></td>
                    <td><?= e($hit['zone']['account_name'] ?? '') ?></td>
                    <td class="actions">
                        <?php if ($access->canViewDns()): ?>
                            <a class="btn btn-small btn-primary" href="<?= e(url('r=records&zone_id=' . urlencode((string) ($hit['zone']['id'] ?? '')) . '&q=' . urlencode((string) ($hit['record']['name'] ?? '')))) ?>">Open record</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <div class="alert alert-error" style="background:#fff1df;color:#b54708;">
            No PTR record found for <code><?= e($ipSearch) ?></code>.
        </div>

        <?php if ($access->canCreateDns() && !empty($createZones)): ?>
            <h3>Create PTR now</h3>
            <p class="muted">Pick the reverse zone and hostname that should point to this IP.</p>
            <form method="post" action="<?= e(url('r=domains')) ?>" class="stack narrow-form">
                <?= csrf_field() ?>
                <input type="hidden" name="form_action" value="create_ptr">
                <input type="hidden" name="ip" value="<?= e($ipSearch) ?>">

                <label>
                    <span>Reverse zone</span>
                    <select name="zone_id" required>
                        <?php foreach ($createZones as $cz): ?>
                            <option value="<?= e((string) ($cz['id'] ?? '')) ?>">
                                <?= e(($cz['name'] ?? '') . (!empty($cz['ip_range']) ? ' — ' . $cz['ip_range'] : '') . ' (' . ($cz['account_name'] ?? '') . ')') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label>
                    <span>Hostname (PTR target)</span>
                    <input type="text" name="hostname" required value="<?= e($suggestedHostname ?? '') ?>" placeholder="mail.example.com">
                </label>

                <div class="toolbar">
                    <button class="btn btn-primary" type="submit">Create PTR record</button>
                </div>
            </form>
        <?php elseif ($access->canCreateDns()): ?>
            <p class="muted">No accessible reverse zone covers this IP. Sync accounts or assign domain access first.</p>
        <?php else: ?>
            <p class="muted">You do not have permission to create DNS records.</p>
        <?php endif; ?>
    <?php endif; ?>
</section>
<?php endif; ?>

<?php if (empty($groups)): ?>
    <section class="panel">
        <p class="muted">No domains available for your access level.</p>
    </section>
<?php else: ?>
    <?php foreach ($groups as $group): ?>
        <section class="panel">
            <div class="panel-head">
                <h2><?= e($group['account']['name'] ?? 'Account') ?></h2>
                <span class="badge badge-brand"><?= count($group['zones']) ?> domain(s)</span>
            </div>
            <?php if (empty($group['zones'])): ?>
                <p class="muted">No domains in this account<?= ($q ?? '') !== '' ? ' matching your search' : '' ?>.</p>
            <?php else: ?>
                <div class="table-wrap">
                <table class="table">
                    <thead>
                    <tr>
                        <th>Domain</th>
                        <th>Records</th>
                        <th>IP range</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($group['zones'] as $zone): ?>
                        <tr>
                            <td><strong><?= e($zone['name'] ?? '') ?></strong></td>
                            <td class="col-count">
                                <span class="badge badge-info"><?= (int) ($zone['record_count'] ?? 0) ?> records</span>
                            </td>
                            <td>
                                <?php if (!empty($zone['is_reverse']) && !empty($zone['ip_range'])): ?>
                                    <span class="badge badge-muted"><?= e($zone['ip_range']) ?></span>
                                <?php else: ?>
                                    <span class="muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="col-status">
                                <?php if (!empty($zone['is_reverse'])): ?>
                                    <span class="badge badge-warn">Reverse (PTR)</span>
                                <?php else: ?>
                                    <span class="badge badge-brand">Forward</span>
                                <?php endif; ?>
                            </td>
                            <td class="col-status"><span class="badge badge-ok"><?= e($zone['status'] ?? '') ?></span></td>
                            <td class="actions">
                                <?php if ($access->canViewDns()): ?>
                                    <a class="btn btn-small btn-primary" href="<?= e(url('r=records&zone_id=' . urlencode((string) $zone['id']))) ?>">DNS records</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            <?php endif; ?>
        </section>
    <?php endforeach; ?>
<?php endif; ?>
