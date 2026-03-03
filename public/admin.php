<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

require_admin_page();
header('Location: /portal.php');
exit;
