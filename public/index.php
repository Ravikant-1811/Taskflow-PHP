<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

$user = current_user();
if ($user) {
    if (($user['role'] ?? 'user') === 'admin') {
        header('Location: /admin.php');
    } elseif (($user['role'] ?? 'user') === 'manager') {
        header('Location: /manager.php');
    } else {
        header('Location: /dashboard.php');
    }
    exit;
}

header('Location: /login.php');
exit;
