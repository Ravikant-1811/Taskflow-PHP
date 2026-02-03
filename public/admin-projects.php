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

    if (!$canAdmin) {
        $error = 'Only admins can create projects.';
    } else {
        $name = trim((string)($_POST['project_name'] ?? ''));
        $description = trim((string)($_POST['project_description'] ?? ''));
        $teamId = (int)($_POST['project_team'] ?? 0);
        if ($name === '') {
            $error = 'Project name is required.';
        } else {
            $teamId = $teamId > 0 ? $teamId : null;
            create_project($tenantId, $teamId, $name, $description);
            $message = 'Project created.';
        }
    }
}

$teams = fetch_teams($tenantId);
$projects = fetch_projects($tenantId);

$pageTitle = 'Projects';
$activePage = 'projects';
require __DIR__ . '/partials/admin_shell_start.php';
?>
    <div class="header admin-header">
        <div>
            <span class="pill">Admin</span>
            <h1>Projects</h1>
            <p class="subtitle">Create projects and attach teams.</p>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($canAdmin): ?>
        <section class="card admin-card">
            <form method="post" class="card compact">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                <label>
                    Project name
                    <input type="text" name="project_name" required>
                </label>
                <label>
                    Description
                    <textarea name="project_description" rows="2"></textarea>
                </label>
                <label>
                    Team (optional)
                    <select name="project_team">
                        <option value="">No team</option>
                        <?php foreach ($teams as $team): ?>
                            <option value="<?= (int)$team['id'] ?>"><?= htmlspecialchars($team['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <button type="submit">Create project</button>
            </form>
        </section>
    <?php endif; ?>

    <section class="card admin-card">
        <?php if (empty($projects)): ?>
            <p class="muted">No projects yet.</p>
        <?php else: ?>
            <div class="project-grid">
                <?php foreach ($projects as $project): ?>
                    <div class="project-card">
                        <h3><?= htmlspecialchars($project['name']) ?></h3>
                        <p class="muted"><?= htmlspecialchars($project['team_name'] ?? 'No team') ?></p>
                        <?php if (!empty($project['description'])): ?>
                            <p><?= htmlspecialchars($project['description']) ?></p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
<?php require __DIR__ . '/partials/admin_shell_end.php'; ?>
