<?php

declare(strict_types=1);

$config = require __DIR__ . '/../app/config.php';
$dbPath = $config['db_path'];

if (file_exists($dbPath)) {
    unlink($dbPath);
}

$pdo = new PDO('sqlite:' . $dbPath, null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$schema = file_get_contents(__DIR__ . '/../database/schema.sql');
$pdo->exec($schema);

echo "Database reset complete.\n";
