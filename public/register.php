<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate($_POST['csrf_token'] ?? null);

    $name = trim((string)($_POST['name'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $company = trim((string)($_POST['company'] ?? ''));
    $companySlug = strtolower(trim((string)($_POST['company_slug'] ?? '')));
    $password = (string)($_POST['password'] ?? '');

    if ($name === '' || $email === '' || $password === '' || $company === '' || $companySlug === '') {
        $error = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } else {
        if (!preg_match('/^[a-z0-9-]+$/', $companySlug)) {
            $error = 'Company code can only include lowercase letters, numbers, and dashes.';
        } else {
            $tenant = fetch_tenant_by_slug($companySlug);
            if (!$tenant) {
                $tenantId = create_tenant($company, $companySlug);
                $role = 'admin';
            } else {
                $tenantId = (int)$tenant['id'];
                $role = 'user';
            }

            $stmt = db()->prepare('SELECT id FROM users WHERE tenant_id = :tenant_id AND email = :email');
            $stmt->execute([':tenant_id' => $tenantId, ':email' => $email]);
            if ($stmt->fetch()) {
                $error = 'Email already registered for this company.';
            } else {
                create_user($tenantId, $name, $email, $password, $role);
                $stmt = db()->prepare('SELECT id FROM users WHERE tenant_id = :tenant_id AND email = :email');
                $stmt->execute([':tenant_id' => $tenantId, ':email' => $email]);
                $user = $stmt->fetch();
                if ($user) {
                    login_user((int)$user['id']);
                    $isManager = in_array($role, ['admin', 'manager'], true);
                    header('Location: ' . ($isManager ? '/admin.php' : '/dashboard.php'));
                    exit;
                }
                $error = 'Unable to create account.';
            }
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
        <p class="subtitle">Create your company workspace in minutes.</p>
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
        <p class="admin-note">The first account for a company becomes the admin. Use the same company code to invite others.</p>
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
                Company name
                <input type="text" name="company" required>
            </label>
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
