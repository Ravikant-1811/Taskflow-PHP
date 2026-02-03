<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

logout_user();
header('Location: /login.php');
exit;
