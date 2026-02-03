<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/tasks.php';

// Initialize CSRF token for forms
csrf_token();

$config = require __DIR__ . '/config.php';
if (!is_dir($config['upload_dir'])) {
    mkdir($config['upload_dir'], 0755, true);
}
