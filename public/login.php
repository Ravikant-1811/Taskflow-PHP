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
            $roleStmt = db()->prepare('SELECT role FROM users WHERE id = :id');
            $roleStmt->execute([':id' => (int)$user['id']]);
            $roleRow = $roleStmt->fetch();
            $role = $roleRow['role'] ?? 'user';
            header('Location: ' . ($role === 'admin' ? '/admin.php' : '/dashboard.php'));
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
<div class="container auth-layout">
    <section class="auth-hero">
        <div class="brand">
            <span class="brand-mark">TF</span>
            <div>
                <h1>TaskFlow</h1>
                <p class="subtitle">Plan, assign, and complete work together.</p>
            </div>
        </div>
        <div class="hero-card">
            <h2>Everything in one place</h2>
            <ul class="feature-list">
                <li>Assign tasks to any teammate</li>
                <li>Track status in real time</li>
                <li>Admin dashboard for control</li>
                <li>Simple, secure session login</li>
            </ul>
        </div>
        <p class="admin-note">Admins use the same login. First user is automatically an admin.</p>
    </section>

    <section class="auth-form">
        <h2>Welcome back</h2>
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
            <div class="form-links">
                <a href="#" class="muted">Forgot password?</a>
                <span class="muted">New here? <a href="/register.php">Create an account</a>.</span>
            </div>
        </form>

        <footer class="auth-footer">
            <a href="/register.php">Create account</a>
            <span class="dot">•</span>
            <a href="#">Privacy</a>
            <span class="dot">•</span>
            <a href="#">Terms</a>
        </footer>
    </section>
</div>
</body>
</html>
