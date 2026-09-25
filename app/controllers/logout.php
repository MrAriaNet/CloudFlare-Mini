<?php

declare(strict_types=1);

$user = $access->user();
$logger->log('auth.logout', $user, []);
$auth->logout();
redirect('r=login');
