<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate($_POST['csrf_token'] ?? null);

    $email = trim((string)($_POST['email'] ?? ''));
    $companySlug = strtolower(trim((string)($_POST['company_slug'] ?? '')));
    $password = (string)($_POST['password'] ?? '');

    if ($email === '' || $password === '' || $companySlug === '') {
        $error = 'Please provide company code, email, and password.';
    } else {
        $tenant = fetch_tenant_by_slug($companySlug);
        if (!$tenant) {
            $error = 'Company code not found.';
        } else {
            $stmt = db()->prepare('SELECT id, password_hash FROM users WHERE tenant_id = :tenant_id AND email = :email');
            $stmt->execute([':tenant_id' => (int)$tenant['id'], ':email' => $email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                login_user((int)$user['id']);
                $roleStmt = db()->prepare('SELECT role FROM users WHERE id = :id');
                $roleStmt->execute([':id' => (int)$user['id']]);
                $roleRow = $roleStmt->fetch();
                $role = $roleRow['role'] ?? 'user';
                if ($role === 'admin') {
                    header('Location: /admin.php');
                } elseif ($role === 'manager') {
                    header('Location: /manager.php');
                } else {
                    header('Location: /dashboard.php');
                }
                exit;
            }

            $error = 'Invalid login details.';
        }
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
        <p class="subtitle">Plan, assign, and complete work across your company.</p>
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
        <p class="admin-note">Use your company code to sign in. Admins and managers use the same login.</p>
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
                Company code
                <input type="text" name="company_slug" placeholder="acme-co" required>
            </label>
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
