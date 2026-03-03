<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

$user = require_login();
$tenantId = (int)$user['tenant_id'];
$userId = (int)$user['id'];
$role = (string)($user['role'] ?? 'user');
$isAdmin = $role === 'admin';
$isManager = in_array($role, ['admin', 'manager'], true);

if ($isAdmin) {
    $tasks = fetch_all_tasks($tenantId);
    $members = fetch_users_with_roles($tenantId);
} elseif ($role === 'manager') {
    $teamIds = fetch_team_ids_for_user($userId);
    $teamUsers = fetch_users_for_teams($tenantId, $teamIds);
    $memberIds = array_map(static fn($u) => (int)$u['id'], $teamUsers);
    $tasks = fetch_tasks_for_assignees($tenantId, $memberIds);
    $members = $teamUsers;
} else {
    $assigned = fetch_tasks_for_user($tenantId, $userId);
    $created = fetch_tasks_created_by($tenantId, $userId);
    $taskMap = [];
    foreach ($assigned as $row) {
        $taskMap[(int)$row['id']] = $row;
    }
    foreach ($created as $row) {
        $taskMap[(int)$row['id']] = $row;
    }
    $tasks = array_values($taskMap);
    $members = [$user];
}

$totalTasks = count($tasks);
$openTasks = count(array_filter($tasks, static fn($t) => ($t['status'] ?? 'open') === 'open'));
$progressTasks = count(array_filter($tasks, static fn($t) => ($t['status'] ?? 'open') === 'in_progress'));
$doneTasks = count(array_filter($tasks, static fn($t) => ($t['status'] ?? 'open') === 'done'));
$today = date('Y-m-d');
$overdue = count(array_filter($tasks, static fn($t) => !empty($t['due_date']) && $t['due_date'] < $today && ($t['status'] ?? 'open') !== 'done'));

$recentTasks = $tasks;
usort($recentTasks, static fn($a, $b) => strcmp((string)$b['created_at'], (string)$a['created_at']));
$recentTasks = array_slice($recentTasks, 0, 6);

$pageTitle = 'Overview';
$activePage = 'overview';
require __DIR__ . '/partials/portal_shell_start.php';
?>
<section class="portal-grid portal-grid-cards">
    <article class="portal-card metric">
        <small>Total Tasks</small>
        <h2><?= $totalTasks ?></h2>
    </article>
    <article class="portal-card metric">
        <small>Open</small>
        <h2><?= $openTasks ?></h2>
    </article>
    <article class="portal-card metric">
        <small>In Progress</small>
        <h2><?= $progressTasks ?></h2>
    </article>
    <article class="portal-card metric">
        <small>Done</small>
        <h2><?= $doneTasks ?></h2>
    </article>
    <article class="portal-card metric">
        <small>Overdue</small>
        <h2><?= $overdue ?></h2>
    </article>
    <article class="portal-card metric">
        <small>People Scope</small>
        <h2><?= count($members) ?></h2>
    </article>
</section>

<section class="portal-card">
    <div class="card-header">
        <div>
            <h2>Recent Tasks</h2>
            <p class="muted">Latest activity in your scope.</p>
        </div>
        <a class="button" href="/portal-tasks.php">Open Tasks</a>
    </div>

    <?php if (empty($recentTasks)): ?>
        <p class="muted">No tasks available yet.</p>
    <?php else: ?>
        <div class="portal-list">
            <?php foreach ($recentTasks as $task): ?>
                <article class="portal-list-item">
                    <div>
                        <strong><?= htmlspecialchars((string)$task['title']) ?></strong>
                        <p class="muted"><?= htmlspecialchars((string)($task['project_name'] ?? 'No project')) ?> · <?= htmlspecialchars((string)($task['assigned_to_name'] ?? 'Unassigned')) ?></p>
                    </div>
                    <div class="portal-item-right">
                        <span class="status <?= $task['status'] === 'done' ? 'done' : ($task['status'] === 'in_progress' ? 'progress' : 'open') ?>">
                            <?= $task['status'] === 'in_progress' ? 'In Progress' : ucfirst((string)$task['status']) ?>
                        </span>
                        <a class="button small secondary" href="/task.php?id=<?= (int)$task['id'] ?>">View</a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/partials/portal_shell_end.php'; ?>
