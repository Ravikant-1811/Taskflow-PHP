<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

$user = require_login();
$tenantId = (int)$user['tenant_id'];
$userId = (int)$user['id'];
$role = (string)($user['role'] ?? 'user');
$isAdmin = $role === 'admin';
$isManager = in_array($role, ['admin', 'manager'], true);

$message = '';
$error = '';

if ($isAdmin) {
    $teams = fetch_teams($tenantId);
} elseif ($role === 'manager') {
    $teamIds = fetch_team_ids_for_user($userId);
    $teams = array_filter(fetch_teams($tenantId), static fn($t) => in_array((int)$t['id'], $teamIds, true));
} else {
    $teams = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate($_POST['csrf_token'] ?? null);
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'create_project' && $isManager) {
        $name = trim((string)($_POST['name'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));
        $teamId = (int)($_POST['team_id'] ?? 0);

        if ($name === '') {
            $error = 'Project name is required.';
        } else {
            if (!$isAdmin && $teamId > 0) {
                $allowedTeamIds = array_map(static fn($t) => (int)$t['id'], $teams);
                if (!in_array($teamId, $allowedTeamIds, true)) {
                    $teamId = 0;
                }
            }

            create_project($tenantId, $teamId > 0 ? $teamId : null, $name, $description);
            $message = 'Project created.';
        }
    }
}

$projects = fetch_projects($tenantId);
if ($role === 'manager' && !$isAdmin) {
    $allowedTeamIds = array_map(static fn($t) => (int)$t['id'], $teams);
    $projects = array_values(array_filter($projects, static fn($p) => empty($p['team_id']) || in_array((int)$p['team_id'], $allowedTeamIds, true)));
}
if ($role === 'user') {
    $taskRows = fetch_tasks_for_user($tenantId, $userId);
    $projectIds = array_values(array_unique(array_map(static fn($t) => (int)($t['project_id'] ?? 0), $taskRows)));
    $projects = array_values(array_filter($projects, static fn($p) => in_array((int)$p['id'], $projectIds, true)));
}

$pageTitle = 'Projects';
$activePage = 'projects';
require __DIR__ . '/partials/portal_shell_start.php';
?>
<?php if ($message): ?><div class="alert success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<section class="portal-card">
    <div class="card-header">
        <div>
            <h2>Project Portfolio</h2>
            <p class="muted">Manage project spaces for teams and tasks.</p>
        </div>
        <span class="chip"><?= count($projects) ?></span>
    </div>

    <?php if ($isManager): ?>
    <form method="post" class="stack-form">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
        <input type="hidden" name="action" value="create_project">
        <input type="text" name="name" placeholder="Project name" required>
        <textarea name="description" rows="2" placeholder="Description"></textarea>
        <select name="team_id">
            <option value="">No team</option>
            <?php foreach ($teams as $team): ?>
                <option value="<?= (int)$team['id'] ?>"><?= htmlspecialchars((string)$team['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit">Create Project</button>
    </form>
    <?php endif; ?>

    <?php if (empty($projects)): ?>
        <p class="muted">No projects found.</p>
    <?php else: ?>
        <div class="portal-list">
            <?php foreach ($projects as $project): ?>
                <article class="portal-list-item">
                    <div>
                        <strong><?= htmlspecialchars((string)$project['name']) ?></strong>
                        <p class="muted"><?= htmlspecialchars((string)($project['description'] ?? '')) ?></p>
                    </div>
                    <span class="chip"><?= htmlspecialchars((string)($project['team_name'] ?? 'General')) ?></span>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/partials/portal_shell_end.php'; ?>
