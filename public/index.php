<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

$user = current_user();
if ($user) {
    header('Location: /workspace.php');
    exit;
}

header('Location: /login.php');
exit;
