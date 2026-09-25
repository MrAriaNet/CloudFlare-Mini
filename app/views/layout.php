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
            <a class="<?= ($active_nav ?? '') === 'dashboard' ? 'active' : '' ?>" href="<?= e(url('r=dashboard')) ?>">Dashboard</a>
            <?php if ($access->canManageAccounts()): ?>
                <a class="<?= ($active_nav ?? '') === 'accounts' ? 'active' : '' ?>" href="<?= e(url('r=accounts')) ?>">Accounts</a>
            <?php endif; ?>
            <a class="<?= ($active_nav ?? '') === 'domains' ? 'active' : '' ?>" href="<?= e(url('r=domains')) ?>">Domains</a>
            <?php if ($access->canManageOperators()): ?>
                <a class="<?= ($active_nav ?? '') === 'operators' ? 'active' : '' ?>" href="<?= e(url('r=operators')) ?>">Operators</a>
            <?php endif; ?>
            <a class="<?= ($active_nav ?? '') === 'logs' ? 'active' : '' ?>" href="<?= e(url('r=logs')) ?>">Audit Logs</a>
            <a class="<?= ($active_nav ?? '') === 'password' ? 'active' : '' ?>" href="<?= e(url('r=password')) ?>">Change Password</a>
        </nav>
        <div class="sidebar-footer">
            <div class="user-chip">
                <strong><?= e($user['username'] ?? '') ?></strong>
                <span><?= e(strtoupper((string) ($user['role'] ?? ''))) ?></span>
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
