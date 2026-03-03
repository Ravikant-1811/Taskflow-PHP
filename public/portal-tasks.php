<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

$user = require_login();
$tenantId = (int)$user['tenant_id'];
$userId = (int)$user['id'];
$role = (string)($user['role'] ?? 'user');
$isAdmin = $role === 'admin';
$isManager = in_array($role, ['admin', 'manager'], true);
$canCreate = $isManager;

$projects = fetch_projects($tenantId);
$assignableUsers = [];
if ($canCreate) {
    if ($isAdmin) {
        $assignableUsers = fetch_users($tenantId);
    } else {
        $teamIds = fetch_team_ids_for_user($userId);
        $assignableUsers = fetch_users_for_teams($tenantId, $teamIds);
    }
}

$message = '';
$error = '';
$view = (string)($_GET['view'] ?? 'board');
if (!in_array($view, ['board', 'list'], true)) {
    $view = 'board';
}

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
            $priority = (string)($_POST['priority'] ?? 'medium');
            $dueDate = trim((string)($_POST['due_date'] ?? ''));

            $assignableIds = array_map(static fn($u) => (int)$u['id'], $assignableUsers);
            $canAssign = $isAdmin || in_array($assignedTo, $assignableIds, true);

            if ($title === '' || $assignedTo <= 0) {
                $error = 'Title and assignee are required.';
            } elseif (!$canAssign) {
                $error = 'You can assign tasks only to your allowed users.';
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
                log_activity($taskId, $userId, 'Created task from portal');
                $message = 'Task created.';
            }
        }
    }

    if ($action === 'update_status') {
        $taskId = (int)($_POST['task_id'] ?? 0);
        $status = (string)($_POST['status'] ?? 'open');
        if ($taskId <= 0 || !in_array($status, ['open', 'in_progress', 'done'], true)) {
            $error = 'Invalid update request.';
        } else {
            $task = fetch_task($tenantId, $taskId);
            if (!$task) {
                $error = 'Task not found.';
            } else {
                $canEdit = $isManager || (int)$task['assigned_to'] === $userId;
                if (!$canEdit) {
                    $error = 'Not allowed.';
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
                        !empty($task['due_date']) ? (string)$task['due_date'] : null
                    );
                    log_activity($taskId, $userId, 'Changed status to ' . $status);
                    $message = 'Task updated.';
                }
            }
        }
    }

    if ($action === 'star_task') {
        $taskId = (int)($_POST['task_id'] ?? 0);
        if ($taskId > 0) {
            star_task($userId, $taskId);
            $message = 'Task starred.';
        }
    }

    if ($action === 'unstar_task') {
        $taskId = (int)($_POST['task_id'] ?? 0);
        if ($taskId > 0) {
            unstar_task($userId, $taskId);
            $message = 'Task removed from starred.';
        }
    }
}

if ($isAdmin) {
    $tasks = fetch_all_tasks($tenantId);
} elseif ($role === 'manager') {
    $teamIds = fetch_team_ids_for_user($userId);
    $teamUsers = fetch_users_for_teams($tenantId, $teamIds);
    $teamUserIds = array_map(static fn($u) => (int)$u['id'], $teamUsers);
    $tasks = fetch_tasks_for_assignees($tenantId, $teamUserIds);
} else {
    $assigned = fetch_tasks_for_user($tenantId, $userId);
    $created = fetch_tasks_created_by($tenantId, $userId);
    $map = [];
    foreach ($assigned as $row) {
        $map[(int)$row['id']] = $row;
    }
    foreach ($created as $row) {
        $map[(int)$row['id']] = $row;
    }
    $tasks = array_values($map);
}

$q = trim((string)($_GET['q'] ?? ''));
if ($q !== '') {
    $needle = mb_strtolower($q);
    $tasks = array_values(array_filter($tasks, static function (array $row) use ($needle): bool {
        $blob = mb_strtolower((string)($row['title'] ?? '') . ' ' . (string)($row['description'] ?? '') . ' ' . (string)($row['assigned_to_name'] ?? ''));
        return str_contains($blob, $needle);
    }));
}

$starredIds = array_flip(fetch_starred_task_ids($userId));

$columns = ['open' => [], 'in_progress' => [], 'done' => []];
foreach ($tasks as $task) {
    $status = (string)($task['status'] ?? 'open');
    if (!isset($columns[$status])) {
        $status = 'open';
    }
    $columns[$status][] = $task;
}

$pageTitle = 'Tasks';
$activePage = 'tasks';
require __DIR__ . '/partials/portal_shell_start.php';
?>
<?php if ($message): ?><div class="alert success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<section class="portal-card">
    <form method="get" class="workspace-search">
        <input type="hidden" name="view" value="<?= htmlspecialchars($view) ?>">
        <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Search tasks by title, description, assignee">
        <button type="submit">Search</button>
    </form>
    <div class="header-actions">
        <a class="button secondary" href="/portal-tasks.php?view=board<?= $q !== '' ? '&q=' . urlencode($q) : '' ?>">Board</a>
        <a class="button secondary" href="/portal-tasks.php?view=list<?= $q !== '' ? '&q=' . urlencode($q) : '' ?>">List</a>
    </div>
</section>

<?php if ($canCreate): ?>
<section class="portal-card">
    <h2>Quick Create</h2>
    <form method="post" class="workspace-create-form">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
        <input type="hidden" name="action" value="create">
        <input type="text" name="title" placeholder="Task title" required>
        <select name="assigned_to" required>
            <option value="">Assign user</option>
            <?php foreach ($assignableUsers as $u): ?>
                <option value="<?= (int)$u['id'] ?>"><?= htmlspecialchars((string)$u['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="project_id">
            <option value="">Project</option>
            <?php foreach ($projects as $p): ?>
                <option value="<?= (int)$p['id'] ?>"><?= htmlspecialchars((string)$p['name']) ?></option>
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

<?php if ($view === 'board'): ?>
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
                            <strong><?= htmlspecialchars((string)$task['title']) ?></strong>
                            <span class="chip <?= htmlspecialchars((string)$task['priority']) ?>"><?= ucfirst((string)$task['priority']) ?></span>
                        </div>
                        <p class="muted"><?= htmlspecialchars((string)($task['project_name'] ?? 'No project')) ?> · <?= htmlspecialchars((string)($task['assigned_to_name'] ?? '')) ?></p>
                        <?php if (!empty($task['due_date'])): ?><p class="muted">Due: <?= htmlspecialchars((string)$task['due_date']) ?></p><?php endif; ?>
                        <div class="workspace-actions">
                            <a class="button small secondary" href="/task.php?id=<?= (int)$task['id'] ?>">Open</a>
                            <form method="post" class="inline star-form">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                                <input type="hidden" name="task_id" value="<?= (int)$task['id'] ?>">
                                <input type="hidden" name="action" value="<?= isset($starredIds[(int)$task['id']]) ? 'unstar_task' : 'star_task' ?>">
                                <button type="submit"><?= isset($starredIds[(int)$task['id']]) ? '★' : '☆' ?></button>
                            </form>
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
<?php else: ?>
<section class="portal-card">
    <div class="portal-list">
        <?php foreach ($tasks as $task): ?>
            <article class="portal-list-item">
                <div>
                    <strong><?= htmlspecialchars((string)$task['title']) ?></strong>
                    <p class="muted">
                        <?= htmlspecialchars((string)($task['project_name'] ?? 'No project')) ?> ·
                        <?= htmlspecialchars((string)($task['assigned_to_name'] ?? '')) ?> ·
                        <?= htmlspecialchars((string)($task['status'] ?? 'open')) ?>
                    </p>
                </div>
                <div class="portal-item-right">
                    <form method="post" class="inline star-form">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                        <input type="hidden" name="task_id" value="<?= (int)$task['id'] ?>">
                        <input type="hidden" name="action" value="<?= isset($starredIds[(int)$task['id']]) ? 'unstar_task' : 'star_task' ?>">
                        <button type="submit"><?= isset($starredIds[(int)$task['id']]) ? '★' : '☆' ?></button>
                    </form>
                    <a class="button small secondary" href="/task.php?id=<?= (int)$task['id'] ?>">Open</a>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>
<?php require __DIR__ . '/partials/portal_shell_end.php'; ?>
