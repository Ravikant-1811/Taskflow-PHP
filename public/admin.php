<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

$admin = require_role(['admin', 'manager']);
$message = '';
$error = '';
$tenantId = (int)$admin['tenant_id'];
$canAdmin = is_admin($admin);

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

    if ($action === 'create_team' && $canAdmin) {
        $name = trim((string)($_POST['team_name'] ?? ''));
        if ($name === '') {
            $error = 'Team name is required.';
        } else {
            create_team($tenantId, $name);
            $message = 'Team created.';
        }
    }

    if ($action === 'add_team_member' && $canAdmin) {
        $teamId = (int)($_POST['team_id'] ?? 0);
        $userId = (int)($_POST['member_id'] ?? 0);
        if ($teamId > 0 && $userId > 0) {
            add_team_member($teamId, $userId);
            $message = 'Team member added.';
        } else {
            $error = 'Select a team and user.';
        }
    }

    if ($action === 'create_project' && $canAdmin) {
        $name = trim((string)($_POST['project_name'] ?? ''));
        $description = trim((string)($_POST['project_description'] ?? ''));
        $teamId = (int)($_POST['project_team'] ?? 0);
        if ($name === '') {
            $error = 'Project name is required.';
        } else {
            $teamId = $teamId > 0 ? $teamId : null;
            create_project($tenantId, $teamId, $name, $description);
            $message = 'Project created.';
        }
    }

    if ($action === 'delete_task') {
        $taskId = (int)($_POST['task_id'] ?? 0);
        if ($taskId > 0) {
            delete_task($tenantId, $taskId);
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
        $priority = $_POST['priority'] ?? 'medium';
        $dueDate = trim((string)($_POST['due_date'] ?? ''));
        $projectId = (int)($_POST['project_id'] ?? 0);

        if ($taskId <= 0 || $assignedTo <= 0 || $title === '') {
            $error = 'Title and assignee are required.';
        } elseif (!in_array($status, ['open', 'in_progress', 'done'], true)) {
            $error = 'Invalid status.';
        } else {
            $projectId = $projectId > 0 ? $projectId : null;
            $dueDate = $dueDate !== '' ? $dueDate : null;
            update_task_admin($tenantId, $taskId, $assignedTo, $projectId, $title, $description, $status, $priority, $dueDate);
            log_activity($taskId, (int)$admin['id'], 'Updated task details');
            $message = 'Task updated.';
        }
    }
}

$users = fetch_users_with_roles($tenantId);
$teams = fetch_teams($tenantId);
$projects = fetch_projects($tenantId);
$tasks = fetch_all_tasks($tenantId);

$totalUsers = count($users);
$totalTasks = count($tasks);
$completedTasks = count(array_filter($tasks, fn($task) => $task['status'] === 'done'));
$inProgressTasks = count(array_filter($tasks, fn($task) => $task['status'] === 'in_progress'));
$openTasks = $totalTasks - $completedTasks - $inProgressTasks;

$today = date('Y-m-d');
$overdueTasks = count(array_filter($tasks, fn($task) => !empty($task['due_date']) && $task['due_date'] < $today && $task['status'] !== 'done'));
$dueSoonTasks = count(array_filter($tasks, fn($task) => !empty($task['due_date']) && $task['due_date'] >= $today && $task['due_date'] <= date('Y-m-d', strtotime('+7 day'))));

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
            <span class="pill"><?= $canAdmin ? 'Admin' : 'Manager' ?></span>
            <h1>Company Dashboard</h1>
            <p class="subtitle">Manage users, teams, projects, and tasks.</p>
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
            <p class="summary-label">In Progress</p>
            <h3><?= $inProgressTasks ?></h3>
        </div>
        <div class="summary-card">
            <p class="summary-label">Completed</p>
            <h3><?= $completedTasks ?></h3>
        </div>
        <div class="summary-card">
            <p class="summary-label">Overdue</p>
            <h3><?= $overdueTasks ?></h3>
        </div>
        <div class="summary-card">
            <p class="summary-label">Due This Week</p>
            <h3><?= $dueSoonTasks ?></h3>
        </div>
    </section>

    <?php if ($message): ?>
        <div class="alert success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($canAdmin): ?>
        <section class="card admin-card" id="users">
            <div class="card-header">
                <div>
                    <h2>Users</h2>
                    <p class="muted">Invite and manage company members.</p>
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
                            <div class="user-role <?= $user['role'] === 'admin' ? 'admin' : ($user['role'] === 'manager' ? 'manager' : 'user') ?>">
                                <?= ucfirst($user['role']) ?>
                            </div>
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
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="card admin-card">
            <div class="card-header">
                <div>
                    <h2>Teams</h2>
                    <p class="muted">Group users into teams.</p>
                </div>
            </div>
            <div class="split">
                <form method="post" class="card compact">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                    <input type="hidden" name="action" value="create_team">
                    <label>
                        Team name
                        <input type="text" name="team_name" required>
                    </label>
                    <button type="submit">Create team</button>
                </form>
                <form method="post" class="card compact">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                    <input type="hidden" name="action" value="add_team_member">
                    <label>
                        Team
                        <select name="team_id" required>
                            <option value="">Select team</option>
                            <?php foreach ($teams as $team): ?>
                                <option value="<?= (int)$team['id'] ?>"><?= htmlspecialchars($team['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>
                        User
                        <select name="member_id" required>
                            <option value="">Select user</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?= (int)$user['id'] ?>"><?= htmlspecialchars($user['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <button type="submit">Add member</button>
                </form>
            </div>
            <?php if (!empty($teams)): ?>
                <div class="tag-list">
                    <?php foreach ($teams as $team): ?>
                        <span class="tag"><?= htmlspecialchars($team['name']) ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="card admin-card">
            <div class="card-header">
                <div>
                    <h2>Projects</h2>
                    <p class="muted">Create projects and attach teams.</p>
                </div>
            </div>
            <form method="post" class="card compact">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                <input type="hidden" name="action" value="create_project">
                <label>
                    Project name
                    <input type="text" name="project_name" required>
                </label>
                <label>
                    Description
                    <textarea name="project_description" rows="2"></textarea>
                </label>
                <label>
                    Team (optional)
                    <select name="project_team">
                        <option value="">No team</option>
                        <?php foreach ($teams as $team): ?>
                            <option value="<?= (int)$team['id'] ?>"><?= htmlspecialchars($team['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <button type="submit">Create project</button>
            </form>
            <?php if (!empty($projects)): ?>
                <div class="project-grid">
                    <?php foreach ($projects as $project): ?>
                        <div class="project-card">
                            <h3><?= htmlspecialchars($project['name']) ?></h3>
                            <p class="muted"><?= htmlspecialchars($project['team_name'] ?? 'No team') ?></p>
                            <?php if (!empty($project['description'])): ?>
                                <p><?= htmlspecialchars($project['description']) ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <section class="card admin-card" id="tasks">
        <div class="card-header">
            <div>
                <h2>All Tasks</h2>
                <p class="muted">Review task status and update work.</p>
            </div>
            <div class="task-filters">
                <span class="chip">Open <?= $openTasks ?></span>
                <span class="chip warning">In Progress <?= $inProgressTasks ?></span>
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
                                        <?= htmlspecialchars($task['project_name'] ?? 'No project') ?> ·
                                        Assigned to <?= htmlspecialchars($task['assigned_to_name']) ?> ·
                                        Priority <?= ucfirst($task['priority']) ?>
                                        <?php if (!empty($task['due_date'])): ?>
                                            · Due <?= htmlspecialchars($task['due_date']) ?>
                                        <?php endif; ?>
                                    </p>
                                </div>
                                <span class="status <?= $task['status'] === 'done' ? 'done' : ($task['status'] === 'in_progress' ? 'progress' : 'open') ?>">
                                    <?= $task['status'] === 'in_progress' ? 'In Progress' : ucfirst($task['status']) ?>
                                </span>
                            </div>
                            <?php if (!empty($task['description'])): ?>
                                <p class="task-desc"><?= nl2br(htmlspecialchars($task['description'])) ?></p>
                            <?php endif; ?>
                            <div class="task-footer">
                                <span class="task-time">Created <?= htmlspecialchars($task['created_at']) ?></span>
                                <div class="actions">
                                    <a class="button small" href="/task.php?id=<?= (int)$task['id'] ?>">View</a>
                                    <button
                                        type="button"
                                        class="button small"
                                        data-open-modal="edit-task"
                                        data-task-id="<?= (int)$task['id'] ?>"
                                        data-title="<?= htmlspecialchars($task['title'], ENT_QUOTES) ?>"
                                        data-description="<?= htmlspecialchars(str_replace(["\n", "\r"], ' ', $task['description']), ENT_QUOTES) ?>"
                                        data-assigned="<?= (int)$task['assigned_to'] ?>"
                                        data-status="<?= htmlspecialchars($task['status'], ENT_QUOTES) ?>"
                                        data-priority="<?= htmlspecialchars($task['priority'], ENT_QUOTES) ?>"
                                        data-due-date="<?= htmlspecialchars($task['due_date'] ?? '', ENT_QUOTES) ?>"
                                        data-project="<?= (int)($task['project_id'] ?? 0) ?>"
                                    >
                                        Edit
                                    </button>
                                    <?php if ($canAdmin): ?>
                                        <form method="post" class="inline">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                                            <input type="hidden" name="action" value="delete_task">
                                            <input type="hidden" name="task_id" value="<?= (int)$task['id'] ?>">
                                            <button type="submit" class="button small danger" onclick="return confirm('Delete this task?');">
                                                Delete
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
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
                    Project
                    <select name="project_id" data-field="project_id">
                        <option value="">No project</option>
                        <?php foreach ($projects as $project): ?>
                            <option value="<?= (int)$project['id'] ?>"><?= htmlspecialchars($project['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    Priority
                    <select name="priority" required data-field="priority">
                        <option value="low">Low</option>
                        <option value="medium">Medium</option>
                        <option value="high">High</option>
                    </select>
                </label>
                <label>
                    Status
                    <select name="status" required data-field="status">
                        <option value="open">Open</option>
                        <option value="in_progress">In Progress</option>
                        <option value="done">Done</option>
                    </select>
                </label>
                <label>
                    Due date
                    <input type="date" name="due_date" data-field="due_date">
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
    document.querySelectorAll('[data-open-modal="edit-task"]').forEach((btn) => {
        btn.addEventListener('click', () => {
            if (!editModal) return;
            editModal.querySelector('[data-field="task_id"]').value = btn.dataset.taskId || '';
            editModal.querySelector('[data-field="title"]').value = btn.dataset.title || '';
            editModal.querySelector('[data-field="description"]').value = btn.dataset.description || '';
            editModal.querySelector('[data-field="assigned_to"]').value = btn.dataset.assigned || '';
            editModal.querySelector('[data-field="status"]').value = btn.dataset.status || 'open';
            editModal.querySelector('[data-field="priority"]').value = btn.dataset.priority || 'medium';
            editModal.querySelector('[data-field="due_date"]').value = btn.dataset.dueDate || '';
            editModal.querySelector('[data-field="project_id"]').value = btn.dataset.project || '';
        });
    });
</script>
</body>
</html>
