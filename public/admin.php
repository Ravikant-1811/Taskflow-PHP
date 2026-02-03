<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

$admin = require_admin();
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate($_POST['csrf_token'] ?? null);

    $action = $_POST['action'] ?? '';

    if ($action === 'create_user') {
        $name = trim((string)($_POST['name'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $role = $_POST['role'] ?? 'user';
        if ($name === '' || $email === '' || $password === '') {
            $error = 'Name, email, and password are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email.';
        } elseif (!in_array($role, ['user', 'admin'], true)) {
            $error = 'Invalid role.';
        } else {
            $exists = db()->prepare('SELECT id FROM users WHERE email = :email');
            $exists->execute([':email' => $email]);
            if ($exists->fetch()) {
                $error = 'Email already registered.';
            } else {
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
                $message = 'User created successfully.';
            }
        }
    }

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

    if ($action === 'update_task') {
        $taskId = (int)($_POST['task_id'] ?? 0);
        $title = trim((string)($_POST['title'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));
        $assignedTo = (int)($_POST['assigned_to'] ?? 0);
        $status = $_POST['status'] ?? 'open';

        if ($taskId <= 0 || $assignedTo <= 0 || $title === '') {
            $error = 'Title and assignee are required.';
        } elseif (!in_array($status, ['open', 'completed'], true)) {
            $error = 'Invalid status.';
        } else {
            update_task_admin($taskId, $assignedTo, $title, $description, $status);
            $message = 'Task updated.';
        }
    }
}

$users = fetch_users_with_roles();
$tasks = fetch_all_tasks();
$totalUsers = count($users);
$totalTasks = count($tasks);
$completedTasks = count(array_filter($tasks, fn($task) => $task['status'] === 'completed'));
$openTasks = $totalTasks - $completedTasks;
$activeTab = $_GET['tab'] ?? 'users';

function task_date_label(string $dateKey): string
{
    $today = date('Y-m-d');
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    if ($dateKey === $today) {
        return 'Today';
    }
    if ($dateKey === $yesterday) {
        return 'Yesterday';
    }

    $date = DateTime::createFromFormat('Y-m-d', $dateKey);
    return $date ? $date->format('M j, Y') : $dateKey;
}

$tasksByDate = [];
foreach ($tasks as $task) {
    $dateKey = date('Y-m-d', strtotime($task['created_at']));
    $tasksByDate[$dateKey][] = $task;
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Admin Dashboard - TaskFlow</title>
    <link rel="stylesheet" href="/styles.css">
</head>
<body>
<div class="container admin">
    <div class="header admin-header">
        <div>
            <span class="pill">Admin</span>
            <h1>Admin Dashboard</h1>
            <p class="subtitle">Manage users and tasks.</p>
        </div>
        <div class="header-actions">
            <a class="button secondary" href="/logout.php">Logout</a>
        </div>
    </div>

    <section class="admin-summary">
        <div class="summary-card">
            <p class="summary-label">Total Users</p>
            <h3><?= $totalUsers ?></h3>
        </div>
        <div class="summary-card">
            <p class="summary-label">Open Tasks</p>
            <h3><?= $openTasks ?></h3>
        </div>
        <div class="summary-card">
            <p class="summary-label">Completed Tasks</p>
            <h3><?= $completedTasks ?></h3>
        </div>
        <div class="summary-card">
            <p class="summary-label">Total Tasks</p>
            <h3><?= $totalTasks ?></h3>
        </div>
    </section>

    <?php if ($message): ?>
        <div class="alert success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <section class="card admin-card" id="users">
        <div class="card-header">
            <div>
                <h2>Users</h2>
                <p class="muted">Promote admins or remove users.</p>
            </div>
            <button class="button" type="button" data-open-modal="create-user">Add new user</button>
        </div>
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
                        <div class="user-role <?= $user['role'] === 'admin' ? 'admin' : 'user' ?>">
                            <?= ucfirst($user['role']) ?>
                        </div>
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

    <section class="card admin-card" id="tasks">
        <div class="card-header">
            <div>
                <h2>All Tasks</h2>
                <p class="muted">Review task status and remove items.</p>
            </div>
            <div class="task-filters">
                <span class="chip">Open <?= $openTasks ?></span>
                <span class="chip done">Completed <?= $completedTasks ?></span>
            </div>
        </div>
        <?php if (empty($tasks)): ?>
            <p class="muted">No tasks found.</p>
        <?php else: ?>
            <div class="task-board">
                <?php foreach ($tasksByDate as $dateKey => $dateTasks): ?>
                    <div class="task-date"><?= htmlspecialchars(task_date_label($dateKey)) ?></div>
                    <?php foreach ($dateTasks as $task): ?>
                        <article class="task-card">
                            <div class="task-top">
                                <div>
                                    <h3><?= htmlspecialchars($task['title']) ?></h3>
                                    <p class="task-meta">
                                        Assigned to <?= htmlspecialchars($task['assigned_to_name']) ?> ·
                                        Created by <?= htmlspecialchars($task['created_by_name']) ?>
                                    </p>
                                </div>
                                <span class="status <?= $task['status'] === 'completed' ? 'done' : 'open' ?>">
                                    <?= ucfirst($task['status']) ?>
                                </span>
                            </div>
                            <?php if (!empty($task['description'])): ?>
                                <p class="task-desc"><?= nl2br(htmlspecialchars($task['description'])) ?></p>
                            <?php endif; ?>
                            <div class="task-footer">
                                <span class="task-time">Created <?= htmlspecialchars($task['created_at']) ?></span>
                                <div class="actions">
                                    <button
                                        type="button"
                                        class="button small"
                                        data-open-modal="edit-task"
                                        data-task-id="<?= (int)$task['id'] ?>"
                                        data-title="<?= htmlspecialchars($task['title'], ENT_QUOTES) ?>"
                                        data-description="<?= htmlspecialchars(str_replace(["\n", "\r"], ' ', $task['description']), ENT_QUOTES) ?>"
                                        data-assigned="<?= (int)$task['assigned_to'] ?>"
                                        data-status="<?= htmlspecialchars($task['status'], ENT_QUOTES) ?>"
                                    >
                                        Edit
                                    </button>
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
                        </article>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

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
                        <option value="admin">Admin</option>
                    </select>
                </label>
                <div class="modal-actions">
                    <button class="button" type="submit">Create user</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal" id="edit-task" aria-hidden="true">
        <div class="modal-backdrop" data-close-modal></div>
        <div class="modal-card">
            <div class="modal-header">
                <div>
                    <h2>Edit task</h2>
                    <p class="muted">Update title, assignee, and status.</p>
                </div>
                <button class="button small secondary" type="button" data-close-modal>Close</button>
            </div>
            <form method="post" class="modal-body">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                <input type="hidden" name="action" value="update_task">
                <input type="hidden" name="task_id" value="" data-field="task_id">
                <label>
                    Title
                    <input type="text" name="title" required data-field="title">
                </label>
                <label>
                    Description
                    <textarea name="description" rows="3" data-field="description"></textarea>
                </label>
                <label>
                    Assign to
                    <select name="assigned_to" required data-field="assigned_to">
                        <option value="">Select user</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?= (int)$user['id'] ?>"><?= htmlspecialchars($user['name']) ?> (<?= htmlspecialchars($user['email']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    Status
                    <select name="status" required data-field="status">
                        <option value="open">Open</option>
                        <option value="completed">Completed</option>
                    </select>
                </label>
                <div class="modal-actions">
                    <button class="button" type="submit">Save changes</button>
                </div>
            </form>
        </div>
    </div>
</div>
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

    const editModal = document.getElementById('edit-task');
    document.querySelectorAll('[data-open-modal=\"edit-task\"]').forEach((btn) => {
        btn.addEventListener('click', () => {
            if (!editModal) return;
            editModal.querySelector('[data-field=\"task_id\"]').value = btn.dataset.taskId || '';
            editModal.querySelector('[data-field=\"title\"]').value = btn.dataset.title || '';
            editModal.querySelector('[data-field=\"description\"]').value = btn.dataset.description || '';
            editModal.querySelector('[data-field=\"assigned_to\"]').value = btn.dataset.assigned || '';
            editModal.querySelector('[data-field=\"status\"]').value = btn.dataset.status || 'open';
        });
    });
</script>
</body>
</html>
