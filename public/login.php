<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate($_POST['csrf_token'] ?? null);

    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        $error = 'Please provide email and password.';
    } else {
        $stmt = db()->prepare('SELECT id, password_hash FROM users WHERE email = :email');
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            login_user((int)$user['id']);
            header('Location: /dashboard.php');
            exit;
        }

        $error = 'Invalid login details.';
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Login - TaskFlow</title>
    <link rel="stylesheet" href="/styles.css">
</head>
<body>
<div class="container">
    <h1>TaskFlow</h1>
    <p class="subtitle">Sign in to manage tasks.</p>

    <?php if ($error): ?>
        <div class="alert"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post" class="card">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
        <label>
            Email
            <input type="email" name="email" required>
        </label>
        <label>
            Password
            <input type="password" name="password" required>
        </label>
        <button type="submit">Login</button>
    </form>

    <p class="footnote">New here? <a href="/register.php">Create an account</a>.</p>
</div>
</body>
</html>
