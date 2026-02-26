<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

$admin = require_admin_page();
$tenantId = (int)$admin['tenant_id'];
$canAdmin = is_admin($admin);

$users = fetch_users_with_roles($tenantId);
$tasks = fetch_all_tasks($tenantId);

$totalUsers = count($users);
$totalTasks = count($tasks);
$completedTasks = count(array_filter($tasks, fn($task) => $task['status'] === 'done'));
$inProgressTasks = count(array_filter($tasks, fn($task) => $task['status'] === 'in_progress'));
$openTasks = $totalTasks - $completedTasks - $inProgressTasks;

$today = date('Y-m-d');
$overdueTasks = count(array_filter($tasks, fn($task) => !empty($task['due_date']) && $task['due_date'] < $today && $task['status'] !== 'done'));
$dueSoonTasks = count(array_filter($tasks, fn($task) => !empty($task['due_date']) && $task['due_date'] >= $today && $task['due_date'] <= date('Y-m-d', strtotime('+7 day'))));

$pageTitle = 'Company Dashboard';
$activePage = 'overview';
$hrUrl = '/admin-hr.php';
$aiUrl = '/ai-assistant.php';
require __DIR__ . '/partials/admin_shell_start.php';
?>
    <div class="header admin-header">
        <div>
            <span class="pill"><?= $canAdmin ? 'Admin' : 'Manager' ?></span>
            <h1>Company Dashboard</h1>
            <p class="subtitle">Overview of your company workspace.</p>
        </div>
        <div class="header-actions">
            <a class="button secondary" href="/admin-hr.php">HR</a>
            <a class="button secondary" href="/ai-assistant.php">AI assistant</a>
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

    <section class="card admin-card">
        <div class="card-header">
            <div>
                <h2>Reports Snapshot</h2>
                <p class="muted">Quick health checks for this company.</p>
            </div>
        </div>
        <div class="report-grid">
            <div class="report-card">
                <h3><?= $overdueTasks ?></h3>
                <p class="muted">Overdue tasks</p>
            </div>
            <div class="report-card">
                <h3><?= $dueSoonTasks ?></h3>
                <p class="muted">Due in 7 days</p>
            </div>
            <div class="report-card">
                <h3><?= $openTasks ?></h3>
                <p class="muted">Open tasks</p>
            </div>
            <div class="report-card">
                <h3><?= $inProgressTasks ?></h3>
                <p class="muted">In progress</p>
            </div>
        </div>
    </section>
<?php require __DIR__ . '/partials/admin_shell_end.php'; ?>
