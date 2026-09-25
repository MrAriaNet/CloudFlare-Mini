<?php

declare(strict_types=1);

$access->requireAdmin();

if (is_post()) {
    verify_csrf();
    $sync->ensureFresh(null, true);
    $logger->log('sync.all', $access->user(), []);
    flash('success', 'Forced sync completed for all accounts.');
}

redirect('r=dashboard');
