<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

$user = require_login();
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate($_POST['csrf_token'] ?? null);

    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $title = trim((string)($_POST['title'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));
        $assignedTo = (int)($_POST['assigned_to'] ?? 0);

        if ($title === '' || $assignedTo <= 0) {
            $error = 'Title and assignee are required.';
        } else {
            create_task((int)$user['id'], $assignedTo, $title, $description);
            $message = 'Task created and assigned.';
        }
    }

    if ($action === 'complete') {
        $taskId = (int)($_POST['task_id'] ?? 0);
        if ($taskId > 0) {
            mark_task_complete($taskId, (int)$user['id']);
            $message = 'Task marked as completed.';
        }
    }
}

$users = fetch_users();
$assignedTasks = fetch_tasks_for_user((int)$user['id']);
$createdTasks = fetch_tasks_created_by((int)$user['id']);
$isAdmin = is_admin($user);
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
            <?php if ($isAdmin): ?>
                <a class="button secondary" href="/admin.php">Admin dashboard</a>
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

    <section class="card">
        <h2>Create Task</h2>
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
                Assign to
                <select name="assigned_to" required>
                    <option value="">Select a user</option>
                    <?php foreach ($users as $assignee): ?>
                        <option value="<?= (int)$assignee['id'] ?>">
                            <?= htmlspecialchars($assignee['name']) ?> (<?= htmlspecialchars($assignee['email']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button type="submit">Assign task</button>
        </form>
    </section>

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
                                <span class="status <?= $task['status'] === 'completed' ? 'done' : 'open' ?>">
                                    <?= ucfirst($task['status']) ?>
                                </span>
                            </div>
                            <?php if ($task['status'] !== 'completed'): ?>
                                <form method="post">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="complete">
                                    <input type="hidden" name="task_id" value="<?= (int)$task['id'] ?>">
                                    <button type="submit" class="button small">Mark complete</button>
                                </form>
                            <?php else: ?>
                                <span class="muted">Completed <?= htmlspecialchars($task['completed_at'] ?? '') ?></span>
                            <?php endif; ?>
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
                                <span class="status <?= $task['status'] === 'completed' ? 'done' : 'open' ?>">
                                    <?= ucfirst($task['status']) ?>
                                </span>
                            </div>
                            <?php if ($task['status'] === 'completed'): ?>
                                <span class="muted">Completed <?= htmlspecialchars($task['completed_at'] ?? '') ?></span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </section>
</div>
</body>
</html>
