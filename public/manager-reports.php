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

$today = date('Y-m-d');
$overdueTasks = count(array_filter($tasks, fn($task) => !empty($task['due_date']) && $task['due_date'] < $today && $task['status'] !== 'done'));
$dueSoonTasks = count(array_filter($tasks, fn($task) => !empty($task['due_date']) && $task['due_date'] >= $today && $task['due_date'] <= date('Y-m-d', strtotime('+7 day'))));

$pageTitle = 'Manager Reports';
$activePage = 'reports';
$dashboardUrl = '/manager.php';
$tasksUrl = '/manager-tasks.php';
$reportsUrl = '/manager-reports.php';
require __DIR__ . '/partials/admin_shell_start.php';
?>
    <div class="header admin-header">
        <div>
            <span class="pill">Manager</span>
            <h1>Team Reports</h1>
            <p class="subtitle">Task health metrics for your team.</p>
        </div>
    </div>

    <section class="admin-summary">
        <div class="summary-card">
            <p class="summary-label">Total Tasks</p>
            <h3><?= $totalTasks ?></h3>
        </div>
        <div class="summary-card">
            <p class="summary-label">Open</p>
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

    <section class="card admin-card">
        <div class="card-header">
            <div>
                <h2>Status Breakdown</h2>
                <p class="muted">Quick snapshot of your team status mix.</p>
            </div>
        </div>
        <div class="report-grid">
            <div class="report-card">
                <h3><?= $openTasks ?></h3>
                <p class="muted">Open</p>
            </div>
            <div class="report-card">
                <h3><?= $inProgressTasks ?></h3>
                <p class="muted">In Progress</p>
            </div>
            <div class="report-card">
                <h3><?= $completedTasks ?></h3>
                <p class="muted">Done</p>
            </div>
        </div>
    </section>
<?php require __DIR__ . '/partials/admin_shell_end.php'; ?>
