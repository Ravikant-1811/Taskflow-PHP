<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

$admin = require_admin_page();
$tenantId = (int)$admin['tenant_id'];
$canAdmin = is_admin($admin);
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate($_POST['csrf_token'] ?? null);

    $action = $_POST['action'] ?? '';

    if ($action === 'create_user' && $canAdmin) {
        $name = trim((string)($_POST['name'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $role = $_POST['role'] ?? 'user';
        if ($name === '' || $email === '' || $password === '') {
            $error = 'Name, email, and password are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email.';
        } elseif (!in_array($role, ['user', 'manager', 'admin'], true)) {
            $error = 'Invalid role.';
        } else {
            $exists = db()->prepare('SELECT id FROM users WHERE tenant_id = :tenant_id AND email = :email');
            $exists->execute([':tenant_id' => $tenantId, ':email' => $email]);
            if ($exists->fetch()) {
                $error = 'Email already registered.';
            } else {
                create_user($tenantId, $name, $email, $password, $role);
                $message = 'User created successfully.';
            }
        }
    }

    if ($action === 'role' && $canAdmin) {
        $userId = (int)($_POST['user_id'] ?? 0);
        $role = $_POST['role'] ?? 'user';
        if ($userId > 0 && in_array($role, ['user', 'manager', 'admin'], true)) {
            if ($userId === (int)$admin['id']) {
                $error = 'You cannot change your own role.';
            } else {
                update_user_role($tenantId, $userId, $role);
                $message = 'User role updated.';
            }
        } else {
            $error = 'Invalid role update.';
        }
    }

    if ($action === 'delete_user' && $canAdmin) {
        $userId = (int)($_POST['user_id'] ?? 0);
        if ($userId === (int)$admin['id']) {
            $error = 'You cannot delete yourself.';
        } elseif ($userId > 0) {
            delete_user($tenantId, $userId);
            $message = 'User deleted.';
        } else {
            $error = 'Invalid user.';
        }
    }
}

$users = fetch_users_with_roles($tenantId);

$pageTitle = 'Users';
$activePage = 'users';
require __DIR__ . '/partials/admin_shell_start.php';
?>
    <div class="header admin-header">
        <div>
            <span class="pill">Admin</span>
            <h1>Users</h1>
            <p class="subtitle">Invite and manage company members.</p>
        </div>
        <?php if ($canAdmin): ?>
            <button class="button" type="button" data-open-modal="create-user">Add new user</button>
        <?php endif; ?>
    </div>

    <?php if ($message): ?>
        <div class="alert success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <section class="card admin-card" id="users">
        <?php if (empty($users)): ?>
            <p class="muted">No users found.</p>
        <?php else: ?>
            <div class="user-grid">
                <?php foreach ($users as $user): ?>
                    <div class="user-card">
                        <div class="user-main">
                            <div class="avatar"><?= strtoupper(substr($user['name'], 0, 1)) ?></div>
                            <div>
                                <div class="user-name"><?= htmlspecialchars($user['name']) ?></div>
                                <div class="user-email"><?= htmlspecialchars($user['email']) ?></div>
                                <div class="user-meta">Joined <?= htmlspecialchars($user['created_at']) ?></div>
                            </div>
                        </div>
                        <div class="user-role <?= $user['role'] === 'admin' ? 'admin' : ($user['role'] === 'manager' ? 'manager' : 'user') ?>">
                            <?= ucfirst($user['role']) ?>
                        </div>
                        <?php if ($canAdmin): ?>
                            <div class="actions">
                                <?php if ((int)$user['id'] !== (int)$admin['id']): ?>
                                    <?php if ($user['role'] !== 'admin'): ?>
                                        <form method="post" class="inline">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                                            <input type="hidden" name="action" value="role">
                                            <input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>">
                                            <input type="hidden" name="role" value="admin">
                                            <button type="submit" class="button small">Make Admin</button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if ($user['role'] !== 'manager'): ?>
                                        <form method="post" class="inline">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                                            <input type="hidden" name="action" value="role">
                                            <input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>">
                                            <input type="hidden" name="role" value="manager">
                                            <button type="submit" class="button small">Make Manager</button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if ($user['role'] !== 'user'): ?>
                                        <form method="post" class="inline">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                                            <input type="hidden" name="action" value="role">
                                            <input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>">
                                            <input type="hidden" name="role" value="user">
                                            <button type="submit" class="button small">Make User</button>
                                        </form>
                                    <?php endif; ?>
                                    <form method="post" class="inline">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                                        <input type="hidden" name="action" value="delete_user">
                                        <input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>">
                                        <button type="submit" class="button small danger" onclick="return confirm('Delete this user?');">
                                            Delete
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span class="muted">Current admin</span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <?php if ($canAdmin): ?>
        <div class="modal" id="create-user" aria-hidden="true">
            <div class="modal-backdrop" data-close-modal></div>
            <div class="modal-card">
                <div class="modal-header">
                    <div>
                        <h2>Add new user</h2>
                        <p class="muted">Create a login and assign a role.</p>
                    </div>
                    <button class="button small secondary" type="button" data-close-modal>Close</button>
                </div>
                <form method="post" class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                    <input type="hidden" name="action" value="create_user">
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
                    <label>
                        Role
                        <select name="role" required>
                            <option value="user">User</option>
                            <option value="manager">Manager</option>
                            <option value="admin">Admin</option>
                        </select>
                    </label>
                    <div class="modal-actions">
                        <button class="button" type="submit">Create user</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <script>
        const openButtons = document.querySelectorAll('[data-open-modal]');
        const closeButtons = document.querySelectorAll('[data-close-modal]');
        openButtons.forEach((btn) => {
            btn.addEventListener('click', () => {
                const id = btn.getAttribute('data-open-modal');
                const modal = document.getElementById(id);
                if (modal) {
                    modal.classList.add('is-open');
                    modal.setAttribute('aria-hidden', 'false');
                }
            });
        });
        closeButtons.forEach((btn) => {
            btn.addEventListener('click', () => {
                const modal = btn.closest('.modal');
                if (modal) {
                    modal.classList.remove('is-open');
                    modal.setAttribute('aria-hidden', 'true');
                }
            });
        });
    </script>
<?php require __DIR__ . '/partials/admin_shell_end.php'; ?>
