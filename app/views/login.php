<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(($title ?? 'Login') . ' — ' . (string) ($app_name ?? config('app_name'))) ?></title>
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body class="login-body">
<div class="login-wrap">
    <div class="login-card">
        <div class="login-brand">
            <span class="brand-mark">CF</span>
            <div>
                <h1>Cloudflare Mini</h1>
                <p>Sign in to manage DNS records</p>
            </div>
        </div>
        <?php if (!empty($error)): ?>
            <div class="alert alert-error"><?= e($error) ?></div>
        <?php endif; ?>
        <form method="post" action="<?= e(url('r=login')) ?>" class="stack">
            <?= csrf_field() ?>
            <label>
                <span>Username</span>
                <input type="text" name="username" required autocomplete="username" autofocus>
            </label>
            <label>
                <span>Password</span>
                <input type="password" name="password" required autocomplete="current-password">
            </label>
            <button class="btn btn-primary" type="submit">Sign in</button>
        </form>
    </div>
</div>
</body>
</html>
