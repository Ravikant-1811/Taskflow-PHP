<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate($_POST['csrf_token'] ?? null);

    $name = trim((string)($_POST['name'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if ($name === '' || $email === '' || $password === '') {
        $error = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } else {
        $stmt = db()->prepare('SELECT id FROM users WHERE email = :email');
        $stmt->execute([':email' => $email]);
        if ($stmt->fetch()) {
            $error = 'Email already registered.';
        } else {
            $countStmt = db()->query('SELECT COUNT(*) AS total FROM users');
            $total = (int)($countStmt->fetch()['total'] ?? 0);
            $role = $total === 0 ? 'admin' : 'user';
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = db()->prepare(
                'INSERT INTO users (name, email, password_hash, role)
                 VALUES (:name, :email, :password_hash, :role)'
            );
            $stmt->execute([
                ':name' => $name,
                ':email' => $email,
                ':password_hash' => $hash,
                ':role' => $role,
            ]);
            login_user((int)db()->lastInsertId());
            header('Location: /dashboard.php');
            exit;
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Register - TaskFlow</title>
    <link rel="stylesheet" href="/styles.css">
</head>
<body>
<div class="container auth-layout">
    <section class="auth-hero">
        <div class="brand">
            <span class="brand-mark">TF</span>
            <div>
                <h1>TaskFlow</h1>
                <p class="subtitle">Bring clarity to team work in minutes.</p>
            </div>
        </div>
        <div class="hero-card">
            <h2>Get started fast</h2>
            <ul class="feature-list">
                <li>Create tasks and assign owners</li>
                <li>Track completion and timelines</li>
                <li>Admin dashboard included</li>
                <li>Secure session-based login</li>
            </ul>
        </div>
        <p class="admin-note">The first account created becomes the admin. You can promote others later.</p>
    </section>

    <section class="auth-form">
        <h2>Create your account</h2>
        <p class="subtitle">Start assigning tasks in seconds.</p>

        <?php if ($error): ?>
            <div class="alert"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="post" class="card">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
            <label>
                Full name
                <input type="text" name="name" required>
            </label>
            <label>
                Email
                <input type="email" name="email" required>
            </label>
            <label>
                Password
                <input type="password" name="password" minlength="6" required>
            </label>
            <button type="submit">Create account</button>
            <div class="form-links">
                <span class="muted">Already have an account? <a href="/login.php">Sign in</a>.</span>
            </div>
        </form>

        <footer class="auth-footer">
            <a href="/login.php">Sign in</a>
            <span class="dot">•</span>
            <a href="#">Privacy</a>
            <span class="dot">•</span>
            <a href="#">Terms</a>
        </footer>
    </section>
</div>
</body>
</html>
