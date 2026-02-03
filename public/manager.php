<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

$manager = require_manager_page();
$tenantId = (int)$manager['tenant_id'];
$canAdmin = false;

$teamIds = fetch_team_ids_for_user((int)$manager['id']);
$teamUsers = fetch_users_for_teams($tenantId, $teamIds);
$teamUserIds = array_map(fn($row) => (int)$row['id'], $teamUsers);

$assignedTasks = fetch_tasks_for_user($tenantId, (int)$manager['id']);
$createdTasks = fetch_tasks_created_by($tenantId, (int)$manager['id']);
$teamTasks = !empty($teamUserIds) ? fetch_tasks_for_assignees($tenantId, $teamUserIds) : [];

$totalTeamMembers = count($teamUsers);
$totalTeamTasks = count($teamTasks);
$openTeamTasks = count(array_filter($teamTasks, fn($task) => $task['status'] !== 'done'));
$doneTeamTasks = count(array_filter($teamTasks, fn($task) => $task['status'] === 'done'));

$pageTitle = 'Manager Dashboard';
$activePage = 'overview';
$dashboardUrl = '/manager.php';
$tasksUrl = '/manager-tasks.php';
$reportsUrl = '/manager-reports.php';
require __DIR__ . '/partials/admin_shell_start.php';
?>
    <div class="header admin-header">
        <div>
            <span class="pill">Manager</span>
            <h1>Manager Dashboard</h1>
            <p class="subtitle">Manage your team workload and track progress.</p>
        </div>
    </div>

    <?php if (empty($teamIds)): ?>
        <div class="alert">
            You are not assigned to a team yet. Ask an admin to add you to a team to see team metrics.
        </div>
    <?php endif; ?>

    <section class="admin-summary">
        <div class="summary-card">
            <p class="summary-label">Team Members</p>
            <h3><?= $totalTeamMembers ?></h3>
        </div>
        <div class="summary-card">
            <p class="summary-label">Team Tasks</p>
            <h3><?= $totalTeamTasks ?></h3>
        </div>
        <div class="summary-card">
            <p class="summary-label">Open Tasks</p>
            <h3><?= $openTeamTasks ?></h3>
        </div>
        <div class="summary-card">
            <p class="summary-label">Completed</p>
            <h3><?= $doneTeamTasks ?></h3>
        </div>
    </section>

    <section class="card admin-card">
        <div class="card-header">
            <div>
                <h2>Your Created Tasks</h2>
                <p class="muted">Tasks you assigned to others.</p>
            </div>
            <a class="button" href="/dashboard.php">Create task</a>
        </div>
        <?php if (empty($createdTasks)): ?>
            <p class="muted">No tasks created yet.</p>
        <?php else: ?>
            <div class="task-board">
                <?php foreach (array_slice($createdTasks, 0, 5) as $task): ?>
                    <article class="task-card">
                        <div class="task-top">
                            <div>
                                <h3><?= htmlspecialchars($task['title']) ?></h3>
                                <p class="task-meta">
                                    Assigned to <?= htmlspecialchars($task['assigned_to_name']) ?> ·
                                    Priority <?= ucfirst($task['priority']) ?>
                                </p>
                            </div>
                            <span class="status <?= $task['status'] === 'done' ? 'done' : ($task['status'] === 'in_progress' ? 'progress' : 'open') ?>">
                                <?= $task['status'] === 'in_progress' ? 'In Progress' : ucfirst($task['status']) ?>
                            </span>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="card admin-card">
        <div class="card-header">
            <div>
                <h2>Team Tasks</h2>
                <p class="muted">Latest work from your team.</p>
            </div>
            <a class="button secondary" href="/manager-tasks.php">View all tasks</a>
        </div>
        <?php if (empty($teamTasks)): ?>
            <p class="muted">No team tasks yet.</p>
        <?php else: ?>
            <div class="task-board">
                <?php foreach (array_slice($teamTasks, 0, 5) as $task): ?>
                    <article class="task-card">
                        <div class="task-top">
                            <div>
                                <h3><?= htmlspecialchars($task['title']) ?></h3>
                                <p class="task-meta">
                                    Assigned to <?= htmlspecialchars($task['assigned_to_name']) ?> ·
                                    <?= htmlspecialchars($task['project_name'] ?? 'No project') ?>
                                </p>
                            </div>
                            <span class="status <?= $task['status'] === 'done' ? 'done' : ($task['status'] === 'in_progress' ? 'progress' : 'open') ?>">
                                <?= $task['status'] === 'in_progress' ? 'In Progress' : ucfirst($task['status']) ?>
                            </span>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
<?php require __DIR__ . '/partials/admin_shell_end.php'; ?>
