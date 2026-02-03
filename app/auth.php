<?php

declare(strict_types=1);

function current_user(): ?array
{
    if (empty($_SESSION['user_id']) || empty($_SESSION['tenant_id'])) {
        return null;
    }

    $stmt = db()->prepare('SELECT id, tenant_id, name, email, role FROM users WHERE id = :id AND tenant_id = :tenant_id');
    $stmt->execute([':id' => $_SESSION['user_id'], ':tenant_id' => $_SESSION['tenant_id']]);
    $user = $stmt->fetch();

    return $user ?: null;
}

function require_login(): array
{
    $user = current_user();
    if (!$user) {
        header('Location: /login.php');
        exit;
    }

    return $user;
}

function require_admin(): array
{
    $user = require_login();
    if (($user['role'] ?? 'user') !== 'admin') {
        http_response_code(403);
        echo 'Forbidden';
        exit;
    }

    return $user;
}

function is_admin(?array $user): bool
{
    if (!$user) {
        return false;
    }

    return ($user['role'] ?? 'user') === 'admin';
}

function login_user(int $userId): void
{
    $stmt = db()->prepare('SELECT tenant_id FROM users WHERE id = :id');
    $stmt->execute([':id' => $userId]);
    $row = $stmt->fetch();
    if (!$row) {
        return;
    }
    $_SESSION['user_id'] = $userId;
    $_SESSION['tenant_id'] = (int)$row['tenant_id'];
}

function logout_user(): void
{
    unset($_SESSION['user_id']);
    unset($_SESSION['tenant_id']);
}

function is_manager(?array $user): bool
{
    if (!$user) {
        return false;
    }

    return in_array(($user['role'] ?? 'user'), ['admin', 'manager'], true);
}

function require_role(array $roles): array
{
    $user = require_login();
    if (!in_array(($user['role'] ?? 'user'), $roles, true)) {
        http_response_code(403);
        echo 'Forbidden';
        exit;
    }

    return $user;
}
