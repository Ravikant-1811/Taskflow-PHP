<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

$manager = require_manager_page();
$tenantId = (int)$manager['tenant_id'];
$canAdmin = false;

$teamIds = fetch_team_ids_for_user((int)$manager['id']);
$teamUsers = fetch_users_for_teams($tenantId, $teamIds);
$teamUserIds = array_map(fn($row) => (int)$row['id'], $teamUsers);

$tasks = !empty($teamUserIds) ? fetch_tasks_for_assignees($tenantId, $teamUserIds) : [];

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

$pageTitle = 'Manager Tasks';
$activePage = 'tasks';
$dashboardUrl = '/manager.php';
$tasksUrl = '/manager-tasks.php';
$reportsUrl = '/manager-reports.php';
require __DIR__ . '/partials/admin_shell_start.php';
?>
    <div class="header admin-header">
        <div>
            <span class="pill">Manager</span>
            <h1>Team Tasks</h1>
            <p class="subtitle">Tasks assigned to your team members.</p>
        </div>
    </div>

    <section class="card admin-card">
        <div class="card-header">
            <div>
                <h2>All Team Tasks</h2>
                <p class="muted">Monitor progress for your team.</p>
            </div>
            <div class="task-filters">
                <span class="chip">Open <?= $openTasks ?></span>
                <span class="chip warning">In Progress <?= $inProgressTasks ?></span>
                <span class="chip done">Completed <?= $completedTasks ?></span>
            </div>
        </div>
        <?php if (empty($tasks)): ?>
            <p class="muted">No tasks found for your team.</p>
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
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
<?php require __DIR__ . '/partials/admin_shell_end.php'; ?>
