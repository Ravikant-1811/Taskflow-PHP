<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

require_login();
header('Location: /portal-tasks.php');
exit;
