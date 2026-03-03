<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

$user = require_login();
$tenantId = (int)$user['tenant_id'];
$userId = (int)$user['id'];

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate($_POST['csrf_token'] ?? null);
    $taskId = (int)($_POST['task_id'] ?? 0);
    if ($taskId > 0) {
        unstar_task($userId, $taskId);
        $message = 'Removed from starred.';
    }
}

$tasks = fetch_starred_tasks($tenantId, $userId);

$pageTitle = 'Starred';
$activePage = 'starred';
require __DIR__ . '/partials/portal_shell_start.php';
?>
<?php if ($message): ?><div class="alert success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
<section class="portal-card">
    <div class="card-header">
        <div>
            <h2>Starred Tasks</h2>
            <p class="muted">Your pinned focus list.</p>
        </div>
        <span class="chip"><?= count($tasks) ?></span>
    </div>

    <?php if (empty($tasks)): ?>
        <p class="muted">No starred tasks.</p>
    <?php else: ?>
        <div class="portal-list">
            <?php foreach ($tasks as $task): ?>
                <article class="portal-list-item">
                    <div>
                        <strong><?= htmlspecialchars((string)$task['title']) ?></strong>
                        <p class="muted">
                            <?= htmlspecialchars((string)($task['project_name'] ?? 'No project')) ?> ·
                            <?= htmlspecialchars((string)($task['status'] ?? 'open')) ?>
                        </p>
                    </div>
                    <div class="portal-item-right">
                        <a class="button small secondary" href="/task.php?id=<?= (int)$task['id'] ?>">Open</a>
                        <form method="post" class="inline">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                            <input type="hidden" name="task_id" value="<?= (int)$task['id'] ?>">
                            <button class="button small" type="submit">Unstar</button>
                        </form>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/partials/portal_shell_end.php'; ?>
