<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

$user = require_login();
$message = '';
$error = '';
$tenantId = (int)$user['tenant_id'];
$canManage = is_manager($user);
$isAdmin = is_admin($user);

$assignableUsers = [];
if ($canManage) {
    if ($isAdmin) {
        $assignableUsers = fetch_users($tenantId);
    } else {
        $teamIds = fetch_team_ids_for_user((int)$user['id']);
        $assignableUsers = fetch_users_for_teams($tenantId, $teamIds);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate($_POST['csrf_token'] ?? null);

    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        if (!$canManage) {
            $error = 'You do not have permission to create tasks.';
        } else {
            $title = trim((string)($_POST['title'] ?? ''));
            $description = trim((string)($_POST['description'] ?? ''));
            $assignedTo = (int)($_POST['assigned_to'] ?? 0);
            $projectId = (int)($_POST['project_id'] ?? 0);
            $priority = $_POST['priority'] ?? 'medium';
            $dueDate = trim((string)($_POST['due_date'] ?? ''));

            $assignableIds = array_map(fn($row) => (int)$row['id'], $assignableUsers);
            $canAssignToUser = $isAdmin || in_array($assignedTo, $assignableIds, true);

            if ($title === '' || $assignedTo <= 0) {
                $error = 'Title and assignee are required.';
            } elseif (!$canAssignToUser) {
                $error = 'Managers can only assign tasks to users in their teams.';
            } else {
                $projectId = $projectId > 0 ? $projectId : null;
                $dueDate = $dueDate !== '' ? $dueDate : null;
                $taskId = create_task($tenantId, (int)$user['id'], $assignedTo, $projectId, $title, $description, $priority, $dueDate);
                log_activity($taskId, (int)$user['id'], 'Created task');
                $message = 'Task created and assigned.';
            }
        }
    }

    if ($action === 'complete') {
        $taskId = (int)($_POST['task_id'] ?? 0);
        if ($taskId > 0) {
            mark_task_complete($tenantId, $taskId, (int)$user['id']);
            log_activity($taskId, (int)$user['id'], 'Marked task as done');
            $message = 'Task marked as completed.';
        }
    }
}

$users = $assignableUsers;
$projects = fetch_projects($tenantId);
$assignedTasks = fetch_tasks_for_user($tenantId, (int)$user['id']);
$createdTasks = fetch_tasks_created_by($tenantId, (int)$user['id']);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Dashboard - TaskFlow</title>
    <link rel="stylesheet" href="/styles.css">
</head>
<body>
<div class="container">
    <div class="header">
        <div>
            <h1>Welcome, <?= htmlspecialchars($user['name']) ?></h1>
            <p class="subtitle">Assign tasks and track completion.</p>
        </div>
        <div class="header-actions">
            <a class="button secondary" href="/daily-report.php">Daily report</a>
            <a class="button secondary" href="/my-hr.php">My HR</a>
            <?php if ($isAdmin): ?>
                <a class="button secondary" href="/admin.php">Admin dashboard</a>
                <a class="button secondary" href="/ai-assistant.php">AI assistant</a>
            <?php endif; ?>
            <?php if (!$isAdmin && $canManage): ?>
                <a class="button secondary" href="/manager.php">Manager dashboard</a>
                <a class="button secondary" href="/ai-assistant.php">AI assistant</a>
            <?php endif; ?>
            <a class="button secondary" href="/logout.php">Logout</a>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($canManage): ?>
        <section class="card">
            <h2>Create Task</h2>
            <?php if (!$isAdmin && empty($users)): ?>
                <p class="muted">You are not assigned to a team yet. Ask an admin to add you to a team to assign tasks to team members.</p>
            <?php endif; ?>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                <input type="hidden" name="action" value="create">
                <label>
                    Title
                    <input type="text" name="title" required>
                </label>
                <label>
                    Description
                    <textarea name="description" rows="3"></textarea>
                </label>
                <label>
                    Project
                    <select name="project_id">
                        <option value="">No project</option>
                        <?php foreach ($projects as $project): ?>
                            <option value="<?= (int)$project['id'] ?>"><?= htmlspecialchars($project['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    Assign to
                    <select name="assigned_to" required <?= (!$isAdmin && empty($users)) ? 'disabled' : '' ?>>
                        <option value="">Select a user</option>
                        <?php foreach ($users as $assignee): ?>
                            <option value="<?= (int)$assignee['id'] ?>">
                                <?= htmlspecialchars($assignee['name']) ?> (<?= htmlspecialchars($assignee['email']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    Priority
                    <select name="priority">
                        <option value="low">Low</option>
                        <option value="medium" selected>Medium</option>
                        <option value="high">High</option>
                    </select>
                </label>
                <label>
                    Due date
                    <input type="date" name="due_date">
                </label>
                <button type="submit" <?= (!$isAdmin && empty($users)) ? 'disabled' : '' ?>>Assign task</button>
            </form>
        </section>
    <?php endif; ?>

    <section class="grid">
        <div class="card">
            <h2>Your Assigned Tasks</h2>
            <?php if (empty($assignedTasks)): ?>
                <p class="muted">No tasks assigned to you yet.</p>
            <?php else: ?>
                <ul class="task-list">
                    <?php foreach ($assignedTasks as $task): ?>
                        <li class="task-item">
                            <div>
                                <strong><?= htmlspecialchars($task['title']) ?></strong>
                                <div class="meta">From <?= htmlspecialchars($task['created_by_name']) ?> · <?= htmlspecialchars($task['created_at']) ?></div>
                                <?php if ($task['description']): ?>
                                    <p><?= nl2br(htmlspecialchars($task['description'])) ?></p>
                                <?php endif; ?>
                                <div class="meta">
                                    <?= htmlspecialchars($task['project_name'] ?? 'No project') ?> · Priority <?= ucfirst($task['priority']) ?>
                                    <?php if (!empty($task['due_date'])): ?>
                                        · Due <?= htmlspecialchars($task['due_date']) ?>
                                    <?php endif; ?>
                                </div>
                                <span class="status <?= $task['status'] === 'done' ? 'done' : ($task['status'] === 'in_progress' ? 'progress' : 'open') ?>">
                                    <?= $task['status'] === 'in_progress' ? 'In Progress' : ucfirst($task['status']) ?>
                                </span>
                            </div>
                            <?php if ($task['status'] !== 'done'): ?>
                                <form method="post">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="complete">
                                    <input type="hidden" name="task_id" value="<?= (int)$task['id'] ?>">
                                    <button type="submit" class="button small">Mark complete</button>
                                </form>
                            <?php else: ?>
                                <span class="muted">Completed <?= htmlspecialchars($task['completed_at'] ?? '') ?></span>
                            <?php endif; ?>
                            <a class="button small secondary" href="/task.php?id=<?= (int)$task['id'] ?>">View</a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <div class="card">
            <h2>Tasks You Created</h2>
            <?php if (empty($createdTasks)): ?>
                <p class="muted">You have not created any tasks yet.</p>
            <?php else: ?>
                <ul class="task-list">
                    <?php foreach ($createdTasks as $task): ?>
                        <li class="task-item">
                            <div>
                                <strong><?= htmlspecialchars($task['title']) ?></strong>
                                <div class="meta">Assigned to <?= htmlspecialchars($task['assigned_to_name']) ?> · <?= htmlspecialchars($task['created_at']) ?></div>
                                <div class="meta">
                                    <?= htmlspecialchars($task['project_name'] ?? 'No project') ?> · Priority <?= ucfirst($task['priority']) ?>
                                    <?php if (!empty($task['due_date'])): ?>
                                        · Due <?= htmlspecialchars($task['due_date']) ?>
                                    <?php endif; ?>
                                </div>
                                <span class="status <?= $task['status'] === 'done' ? 'done' : ($task['status'] === 'in_progress' ? 'progress' : 'open') ?>">
                                    <?= $task['status'] === 'in_progress' ? 'In Progress' : ucfirst($task['status']) ?>
                                </span>
                            </div>
                            <?php if ($task['status'] === 'done'): ?>
                                <span class="muted">Completed <?= htmlspecialchars($task['completed_at'] ?? '') ?></span>
                            <?php endif; ?>
                            <a class="button small secondary" href="/task.php?id=<?= (int)$task['id'] ?>">View</a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </section>
</div>
</body>
</html>
