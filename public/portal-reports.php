<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

$user = require_login();
$tenantId = (int)$user['tenant_id'];
$userId = (int)$user['id'];
$role = (string)($user['role'] ?? 'user');
$isAdmin = $role === 'admin';

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

$today = date('Y-m-d');
$weekEnd = date('Y-m-d', strtotime('+7 days'));
$open = 0;
$progress = 0;
$done = 0;
$overdue = 0;
$dueSoon = 0;
$priority = ['low' => 0, 'medium' => 0, 'high' => 0];

foreach ($tasks as $task) {
    $status = (string)($task['status'] ?? 'open');
    if ($status === 'done') {
        $done++;
    } elseif ($status === 'in_progress') {
        $progress++;
    } else {
        $open++;
    }

    $p = (string)($task['priority'] ?? 'medium');
    if (isset($priority[$p])) {
        $priority[$p]++;
    }

    $due = (string)($task['due_date'] ?? '');
    if ($due !== '' && $status !== 'done' && $due < $today) {
        $overdue++;
    }
    if ($due !== '' && $due >= $today && $due <= $weekEnd) {
        $dueSoon++;
    }
}

$pageTitle = 'Reports';
$activePage = 'reports';
require __DIR__ . '/partials/portal_shell_start.php';
?>
<section class="portal-grid portal-grid-cards">
    <article class="portal-card metric"><small>Open</small><h2><?= $open ?></h2></article>
    <article class="portal-card metric"><small>In Progress</small><h2><?= $progress ?></h2></article>
    <article class="portal-card metric"><small>Done</small><h2><?= $done ?></h2></article>
    <article class="portal-card metric"><small>Overdue</small><h2><?= $overdue ?></h2></article>
    <article class="portal-card metric"><small>Due in 7 Days</small><h2><?= $dueSoon ?></h2></article>
    <article class="portal-card metric"><small>Total Scope</small><h2><?= count($tasks) ?></h2></article>
</section>

<section class="portal-card">
    <div class="card-header">
        <div>
            <h2>Priority Distribution</h2>
            <p class="muted">Current task mix by priority.</p>
        </div>
    </div>
    <div class="report-grid">
        <div class="report-card"><h3><?= $priority['high'] ?></h3><p class="muted">High</p></div>
        <div class="report-card"><h3><?= $priority['medium'] ?></h3><p class="muted">Medium</p></div>
        <div class="report-card"><h3><?= $priority['low'] ?></h3><p class="muted">Low</p></div>
    </div>
</section>
<?php require __DIR__ . '/partials/portal_shell_end.php'; ?>
