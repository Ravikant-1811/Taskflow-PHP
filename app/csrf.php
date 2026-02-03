<?php

declare(strict_types=1);

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
    }

    return $_SESSION['csrf_token'];
}

function csrf_validate(?string $token): void
{
    $stored = $_SESSION['csrf_token'] ?? '';
    if (!$token || !hash_equals($stored, $token)) {
        http_response_code(400);
        echo 'Invalid CSRF token';
        exit;
    }
}
