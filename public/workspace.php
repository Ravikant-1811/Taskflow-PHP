<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

$user = require_login();
$tenantId = (int)$user['tenant_id'];
$userId = (int)$user['id'];
$isAdmin = is_admin($user);
$isManager = (($user['role'] ?? 'user') === 'manager');
$canCreate = is_manager($user);

$allUsers = fetch_users($tenantId);
$projects = fetch_projects($tenantId);
$assignableUsers = [];
if ($canCreate) {
    if ($isAdmin) {
        $assignableUsers = $allUsers;
    } else {
        $teamIds = fetch_team_ids_for_user($userId);
        $assignableUsers = fetch_users_for_teams($tenantId, $teamIds);
    }
}

$message = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate($_POST['csrf_token'] ?? null);
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        if (!$canCreate) {
            $error = 'You do not have permission to create tasks.';
        } else {
            $title = trim((string)($_POST['title'] ?? ''));
            $description = trim((string)($_POST['description'] ?? ''));
            $assignedTo = (int)($_POST['assigned_to'] ?? 0);
            $projectId = (int)($_POST['project_id'] ?? 0);
            $priority = $_POST['priority'] ?? 'medium';
            $dueDate = trim((string)($_POST['due_date'] ?? ''));

            $assignableIds = array_map(fn($r) => (int)$r['id'], $assignableUsers);
            $canAssign = $isAdmin || in_array($assignedTo, $assignableIds, true);
            if ($title === '' || $assignedTo <= 0) {
                $error = 'Title and assignee are required.';
            } elseif (!$canAssign) {
                $error = 'You can assign only to allowed users.';
            } else {
                $taskId = create_task(
                    $tenantId,
                    $userId,
                    $assignedTo,
                    $projectId > 0 ? $projectId : null,
                    $title,
                    $description,
                    $priority,
                    $dueDate !== '' ? $dueDate : null
                );
                log_activity($taskId, $userId, 'Created task from workspace board');
                $message = 'Task created.';
            }
        }
    }

    if ($action === 'update_status') {
        $taskId = (int)($_POST['task_id'] ?? 0);
        $status = trim((string)($_POST['status'] ?? 'open'));
        if (!in_array($status, ['open', 'in_progress', 'done'], true) || $taskId <= 0) {
            $error = 'Invalid status update.';
        } else {
            $task = fetch_task($tenantId, $taskId);
            if (!$task) {
                $error = 'Task not found.';
            } else {
                $canEdit = $isAdmin || $isManager || (int)$task['assigned_to'] === $userId;
                if (!$canEdit) {
                    $error = 'Not allowed to update this task.';
                } else {
                    update_task_admin(
                        $tenantId,
                        $taskId,
                        (int)$task['assigned_to'],
                        isset($task['project_id']) ? (int)$task['project_id'] : null,
                        (string)$task['title'],
                        (string)$task['description'],
                        $status,
                        (string)$task['priority'],
                        isset($task['due_date']) ? (string)$task['due_date'] : null
                    );
                    log_activity($taskId, $userId, 'Moved task to ' . $status);
                    $message = 'Task status updated.';
                }
            }
        }
    }
}

$tasks = [];
if ($isAdmin) {
    $tasks = fetch_all_tasks($tenantId);
} elseif ($isManager) {
    $teamIds = fetch_team_ids_for_user($userId);
    $teamUsers = fetch_users_for_teams($tenantId, $teamIds);
    $teamUserIds = array_map(fn($u) => (int)$u['id'], $teamUsers);
    $tasks = fetch_tasks_for_assignees($tenantId, $teamUserIds);
} else {
    $assigned = fetch_tasks_for_user($tenantId, $userId);
    $created = fetch_tasks_created_by($tenantId, $userId);
    $byId = [];
    foreach ($assigned as $t) {
        $byId[(int)$t['id']] = $t;
    }
    foreach ($created as $t) {
        $byId[(int)$t['id']] = $t;
    }
    $tasks = array_values($byId);
}

$query = trim((string)($_GET['q'] ?? ''));
if ($query !== '') {
    $q = mb_strtolower($query);
    $tasks = array_values(array_filter($tasks, function ($t) use ($q) {
        $hay = mb_strtolower(($t['title'] ?? '') . ' ' . ($t['description'] ?? '') . ' ' . ($t['assigned_to_name'] ?? ''));
        return str_contains($hay, $q);
    }));
}

$columns = [
    'open' => [],
    'in_progress' => [],
    'done' => [],
];
foreach ($tasks as $task) {
    $status = (string)($task['status'] ?? 'open');
    if (!isset($columns[$status])) {
        $status = 'open';
    }
    $columns[$status][] = $task;
}

$titlePrefix = $isAdmin ? 'Admin' : ($isManager ? 'Manager' : 'My');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Workspace - TaskFlow</title>
    <link rel="stylesheet" href="/styles.css">
</head>
<body>
<div class="container workspace-container">
    <div class="workspace-topbar">
        <div>
            <h1><?= htmlspecialchars($titlePrefix) ?> Workspace</h1>
            <p class="subtitle">Board-first daily execution, AppFlowy style.</p>
        </div>
        <div class="header-actions">
            <a class="button secondary" href="/daily-report.php">Daily report</a>
            <a class="button secondary" href="/my-hr.php">My HR</a>
            <?php if ($isAdmin): ?><a class="button secondary" href="/admin.php">Admin</a><?php endif; ?>
            <?php if ($isManager): ?><a class="button secondary" href="/manager.php">Manager</a><?php endif; ?>
            <?php if ($canCreate): ?><a class="button secondary" href="/ai-assistant.php">AI</a><?php endif; ?>
            <a class="button secondary" href="/logout.php">Logout</a>
        </div>
    </div>

    <?php if ($message): ?><div class="alert success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <section class="card workspace-command">
        <form method="get" class="workspace-search">
            <input type="text" name="q" value="<?= htmlspecialchars($query) ?>" placeholder="Search tasks, assignee, description...">
            <button type="submit">Search</button>
        </form>
        <p class="muted">Tip: Use this as your quick command bar for task discovery.</p>
    </section>

    <?php if ($canCreate): ?>
    <section class="card workspace-create">
        <h2>Quick Create</h2>
        <form method="post" class="workspace-create-form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
            <input type="hidden" name="action" value="create">
            <input type="text" name="title" placeholder="Task title" required>
            <select name="assigned_to" required>
                <option value="">Assign user</option>
                <?php foreach ($assignableUsers as $u): ?>
                    <option value="<?= (int)$u['id'] ?>"><?= htmlspecialchars($u['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="project_id">
                <option value="">Project</option>
                <?php foreach ($projects as $p): ?>
                    <option value="<?= (int)$p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="priority">
                <option value="low">Low</option>
                <option value="medium" selected>Medium</option>
                <option value="high">High</option>
            </select>
            <input type="date" name="due_date">
            <textarea name="description" rows="2" placeholder="Description"></textarea>
            <button type="submit">Create</button>
        </form>
    </section>
    <?php endif; ?>

    <section class="workspace-board">
        <?php foreach ($columns as $status => $items): ?>
            <div class="workspace-column">
                <div class="workspace-column-head">
                    <h3><?= $status === 'in_progress' ? 'In Progress' : ucfirst($status) ?></h3>
                    <span><?= count($items) ?></span>
                </div>
                <div class="workspace-cards">
                    <?php foreach ($items as $task): ?>
                        <article class="workspace-task">
                            <div class="workspace-task-top">
                                <strong><?= htmlspecialchars($task['title']) ?></strong>
                                <span class="chip <?= htmlspecialchars($task['priority']) ?>"><?= ucfirst((string)$task['priority']) ?></span>
                            </div>
                            <p class="muted">
                                <?= htmlspecialchars($task['project_name'] ?? 'No project') ?> ·
                                <?= htmlspecialchars($task['assigned_to_name'] ?? '') ?>
                            </p>
                            <?php if (!empty($task['due_date'])): ?>
                                <p class="muted">Due: <?= htmlspecialchars((string)$task['due_date']) ?></p>
                            <?php endif; ?>
                            <div class="workspace-actions">
                                <a class="button small secondary" href="/task.php?id=<?= (int)$task['id'] ?>">Open</a>
                                <form method="post" class="inline">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="update_status">
                                    <input type="hidden" name="task_id" value="<?= (int)$task['id'] ?>">
                                    <select name="status" onchange="this.form.submit()">
                                        <option value="open" <?= $task['status'] === 'open' ? 'selected' : '' ?>>Open</option>
                                        <option value="in_progress" <?= $task['status'] === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                                        <option value="done" <?= $task['status'] === 'done' ? 'selected' : '' ?>>Done</option>
                                    </select>
                                </form>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </section>
</div>
</body>
</html>
