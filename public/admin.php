<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

$admin = require_admin();
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate($_POST['csrf_token'] ?? null);

    $action = $_POST['action'] ?? '';

    if ($action === 'role') {
        $userId = (int)($_POST['user_id'] ?? 0);
        $role = $_POST['role'] ?? 'user';
        if ($userId > 0 && in_array($role, ['user', 'admin'], true)) {
            if ($userId === (int)$admin['id']) {
                $error = 'You cannot change your own role.';
            } else {
                update_user_role($userId, $role);
                $message = 'User role updated.';
            }
        } else {
            $error = 'Invalid role update.';
        }
    }

    if ($action === 'delete_user') {
        $userId = (int)($_POST['user_id'] ?? 0);
        if ($userId === (int)$admin['id']) {
            $error = 'You cannot delete yourself.';
        } elseif ($userId > 0) {
            delete_user($userId);
            $message = 'User deleted.';
        } else {
            $error = 'Invalid user.';
        }
    }

    if ($action === 'delete_task') {
        $taskId = (int)($_POST['task_id'] ?? 0);
        if ($taskId > 0) {
            delete_task($taskId);
            $message = 'Task deleted.';
        } else {
            $error = 'Invalid task.';
        }
    }
}

$users = fetch_users_with_roles();
$tasks = fetch_all_tasks();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Admin Dashboard - TaskFlow</title>
    <link rel="stylesheet" href="/styles.css">
</head>
<body>
<div class="container">
    <div class="header">
        <div>
            <h1>Admin Dashboard</h1>
            <p class="subtitle">Manage users and tasks.</p>
        </div>
        <div class="header-actions">
            <a class="button secondary" href="/dashboard.php">User dashboard</a>
            <a class="button secondary" href="/logout.php">Logout</a>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <section class="card">
        <h2>Users</h2>
        <?php if (empty($users)): ?>
            <p class="muted">No users found.</p>
        <?php else: ?>
            <div class="table">
                <div class="table-row table-head">
                    <div>Name</div>
                    <div>Email</div>
                    <div>Role</div>
                    <div>Joined</div>
                    <div>Actions</div>
                </div>
                <?php foreach ($users as $user): ?>
                    <div class="table-row">
                        <div><?= htmlspecialchars($user['name']) ?></div>
                        <div><?= htmlspecialchars($user['email']) ?></div>
                        <div><?= htmlspecialchars($user['role']) ?></div>
                        <div><?= htmlspecialchars($user['created_at']) ?></div>
                        <div class="actions">
                            <?php if ((int)$user['id'] !== (int)$admin['id']): ?>
                                <form method="post" class="inline">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="role">
                                    <input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>">
                                    <input type="hidden" name="role" value="<?= $user['role'] === 'admin' ? 'user' : 'admin' ?>">
                                    <button type="submit" class="button small">
                                        Make <?= $user['role'] === 'admin' ? 'User' : 'Admin' ?>
                                    </button>
                                </form>
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
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="card">
        <h2>All Tasks</h2>
        <?php if (empty($tasks)): ?>
            <p class="muted">No tasks found.</p>
        <?php else: ?>
            <div class="table">
                <div class="table-row table-head">
                    <div>Title</div>
                    <div>Assigned To</div>
                    <div>Created By</div>
                    <div>Status</div>
                    <div>Created</div>
                    <div>Actions</div>
                </div>
                <?php foreach ($tasks as $task): ?>
                    <div class="table-row">
                        <div><?= htmlspecialchars($task['title']) ?></div>
                        <div><?= htmlspecialchars($task['assigned_to_name']) ?></div>
                        <div><?= htmlspecialchars($task['created_by_name']) ?></div>
                        <div>
                            <span class="status <?= $task['status'] === 'completed' ? 'done' : 'open' ?>">
                                <?= ucfirst($task['status']) ?>
                            </span>
                        </div>
                        <div><?= htmlspecialchars($task['created_at']) ?></div>
                        <div class="actions">
                            <form method="post" class="inline">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                                <input type="hidden" name="action" value="delete_task">
                                <input type="hidden" name="task_id" value="<?= (int)$task['id'] ?>">
                                <button type="submit" class="button small danger" onclick="return confirm('Delete this task?');">
                                    Delete
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>
</body>
</html>
