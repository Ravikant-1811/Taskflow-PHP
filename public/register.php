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
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = db()->prepare(
                'INSERT INTO users (name, email, password_hash)
                 VALUES (:name, :email, :password_hash)'
            );
            $stmt->execute([
                ':name' => $name,
                ':email' => $email,
                ':password_hash' => $hash,
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
<div class="container">
    <h1>TaskFlow</h1>
    <p class="subtitle">Create your account to start assigning tasks.</p>

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
    </form>

    <p class="footnote">Already have an account? <a href="/login.php">Sign in</a>.</p>
</div>
</body>
</html>
