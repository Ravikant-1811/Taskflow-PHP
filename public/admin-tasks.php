<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

$admin = require_role(['admin', 'manager']);
$tenantId = (int)$admin['tenant_id'];
$canAdmin = is_admin($admin);
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate($_POST['csrf_token'] ?? null);

    $action = $_POST['action'] ?? '';

    if ($action === 'delete_task' && $canAdmin) {
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
$projects = fetch_projects($tenantId);
$tasks = fetch_all_tasks($tenantId);

$totalTasks = count($tasks);
$completedTasks = count(array_filter($tasks, fn($task) => $task['status'] === 'done'));
$inProgressTasks = count(array_filter($tasks, fn($task) => $task['status'] === 'in_progress'));
$openTasks = $totalTasks - $completedTasks - $inProgressTasks;

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

$pageTitle = 'Tasks';
$activePage = 'tasks';
require __DIR__ . '/partials/admin_shell_start.php';
?>
    <div class="header admin-header">
        <div>
            <span class="pill"><?= $canAdmin ? 'Admin' : 'Manager' ?></span>
            <h1>Tasks</h1>
            <p class="subtitle">Review, update, and track tasks.</p>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert"><?= htmlspecialchars($error) ?></div>
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
<?php require __DIR__ . '/partials/admin_shell_end.php'; ?>
