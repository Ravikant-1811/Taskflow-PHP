<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

$user = require_login();
$tenantId = (int)$user['tenant_id'];
$userId = (int)$user['id'];
$today = date('Y-m-d');

$tasks = fetch_tasks_for_user($tenantId, $userId);
$dueToday = array_values(array_filter($tasks, static fn($t) => !empty($t['due_date']) && $t['due_date'] === $today && ($t['status'] ?? 'open') !== 'done'));
$overdue = array_values(array_filter($tasks, static fn($t) => !empty($t['due_date']) && $t['due_date'] < $today && ($t['status'] ?? 'open') !== 'done'));
$nextUp = array_values(array_filter($tasks, static fn($t) => ($t['status'] ?? 'open') === 'open'));

$pageTitle = 'Inbox';
$activePage = 'inbox';
require __DIR__ . '/partials/portal_shell_start.php';
?>
<section class="portal-grid portal-grid-cards">
    <article class="portal-card metric"><small>Due Today</small><h2><?= count($dueToday) ?></h2></article>
    <article class="portal-card metric"><small>Overdue</small><h2><?= count($overdue) ?></h2></article>
    <article class="portal-card metric"><small>Open</small><h2><?= count($nextUp) ?></h2></article>
    <article class="portal-card metric"><small>Total Assigned</small><h2><?= count($tasks) ?></h2></article>
</section>

<section class="portal-card">
    <h2>My Queue</h2>
    <?php if (empty($tasks)): ?>
        <p class="muted">No assigned tasks yet.</p>
    <?php else: ?>
        <div class="portal-list">
            <?php foreach ($tasks as $task): ?>
                <article class="portal-list-item">
                    <div>
                        <strong><?= htmlspecialchars((string)$task['title']) ?></strong>
                        <p class="muted">
                            <?= htmlspecialchars((string)($task['project_name'] ?? 'No project')) ?> ·
                            <?= htmlspecialchars((string)($task['status'] ?? 'open')) ?>
                            <?php if (!empty($task['due_date'])): ?> · Due <?= htmlspecialchars((string)$task['due_date']) ?><?php endif; ?>
                        </p>
                    </div>
                    <a class="button small secondary" href="/task.php?id=<?= (int)$task['id'] ?>">Open</a>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/partials/portal_shell_end.php'; ?>
