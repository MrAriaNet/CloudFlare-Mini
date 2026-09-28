<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(($title ?? 'Panel') . ' — ' . (string) config('app_name')) ?></title>
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body>
<?php if (!empty($user)): ?>
<div class="app-shell">
    <aside class="sidebar">
        <div class="brand">
            <span class="brand-mark">CF</span>
            <div>
                <strong>Cloudflare Mini</strong>
                <small>DNS Panel</small>
            </div>
        </div>
        <nav class="nav">
            <div class="nav-group">
                <div class="nav-label">Overview</div>
                <a class="<?= ($active_nav ?? '') === 'dashboard' ? 'active' : '' ?>" href="<?= e(url('r=dashboard')) ?>">Dashboard</a>
                <?php if ($access->canManageAccounts()): ?>
                    <a class="<?= ($active_nav ?? '') === 'accounts' ? 'active' : '' ?>" href="<?= e(url('r=accounts')) ?>">Accounts</a>
                <?php endif; ?>
                <?php if ($access->canViewDomains()): ?>
                    <a class="<?= ($active_nav ?? '') === 'domains' ? 'active' : '' ?>" href="<?= e(url('r=domains')) ?>">Domains</a>
                <?php endif; ?>
            </div>

            <?php if ($access->canViewPtrAudit() || $access->canViewPtrLogs()): ?>
            <div class="nav-group">
                <div class="nav-label">PTR</div>
                <?php if ($access->canViewPtrAudit()): ?>
                    <a class="<?= ($active_nav ?? '') === 'ptr_audit' ? 'active' : '' ?>" href="<?= e(url('r=ptr_audit')) ?>">PTR Audit</a>
                <?php endif; ?>
                <?php if ($access->canViewPtrLogs()): ?>
                    <a class="<?= ($active_nav ?? '') === 'ptr_logs' ? 'active' : '' ?>" href="<?= e(url('r=ptr_logs')) ?>">PTR Logs</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if ($access->canManageIpAllowlist() || $access->canManageOperators() || $access->canManageRoles()): ?>
            <div class="nav-group">
                <div class="nav-label">Access</div>
                <?php if ($access->canManageIpAllowlist()): ?>
                    <a class="<?= ($active_nav ?? '') === 'ip_allowlist' ? 'active' : '' ?>" href="<?= e(url('r=ip_allowlist')) ?>">IP Allowlist</a>
                <?php endif; ?>
                <?php if ($access->canManageOperators()): ?>
                    <a class="<?= ($active_nav ?? '') === 'operators' ? 'active' : '' ?>" href="<?= e(url('r=operators')) ?>">Operators</a>
                <?php endif; ?>
                <?php if ($access->canManageRoles()): ?>
                    <a class="<?= ($active_nav ?? '') === 'roles' ? 'active' : '' ?>" href="<?= e(url('r=roles')) ?>">Access Levels</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if ($access->canViewAuditLogs() || $access->canViewDnsLogs() || $access->canViewCronLogs()): ?>
            <div class="nav-group">
                <div class="nav-label">Logs</div>
                <?php if ($access->canViewAuditLogs()): ?>
                    <a class="<?= ($active_nav ?? '') === 'logs' ? 'active' : '' ?>" href="<?= e(url('r=logs')) ?>">Audit Logs</a>
                <?php endif; ?>
                <?php if ($access->canViewDnsLogs()): ?>
                    <a class="<?= ($active_nav ?? '') === 'dns_logs' ? 'active' : '' ?>" href="<?= e(url('r=dns_logs')) ?>">DNS Change Logs</a>
                <?php endif; ?>
                <?php if ($access->canViewCronLogs()): ?>
                    <a class="<?= ($active_nav ?? '') === 'cron_logs' ? 'active' : '' ?>" href="<?= e(url('r=cron_logs')) ?>">Cron Logs</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <div class="nav-group">
                <div class="nav-label">Account</div>
                <a class="<?= ($active_nav ?? '') === 'password' ? 'active' : '' ?>" href="<?= e(url('r=password')) ?>">Change Password</a>
            </div>
        </nav>
        <div class="sidebar-footer">
            <div class="user-chip">
                <strong><?= e($user['username'] ?? '') ?></strong>
                <span><?= e($access->role()['name'] ?? strtoupper((string) ($user['role'] ?? ''))) ?></span>
            </div>
            <a class="btn btn-ghost btn-block" href="<?= e(url('r=logout')) ?>">Sign out</a>
        </div>
    </aside>
    <main class="main">
        <header class="page-header">
            <h1><?= e($title ?? '') ?></h1>
        </header>
        <?php foreach (get_flashes() as $flash): ?>
            <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
        <?php endforeach; ?>
        <?php require dirname(__DIR__) . '/views/' . $content_view . '.php'; ?>
    </main>
</div>
<?php else: ?>
    <?php require dirname(__DIR__) . '/views/' . $content_view . '.php'; ?>
<?php endif; ?>
<script src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
