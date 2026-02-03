<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

$admin = require_admin_page();
$tenantId = (int)$admin['tenant_id'];
$canAdmin = is_admin($admin);
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate($_POST['csrf_token'] ?? null);

    $action = $_POST['action'] ?? '';

    if ($action === 'create_team' && $canAdmin) {
        $name = trim((string)($_POST['team_name'] ?? ''));
        if ($name === '') {
            $error = 'Team name is required.';
        } else {
            create_team($tenantId, $name);
            $message = 'Team created.';
        }
    }

    if ($action === 'add_team_member' && $canAdmin) {
        $teamId = (int)($_POST['team_id'] ?? 0);
        $userId = (int)($_POST['member_id'] ?? 0);
        if ($teamId > 0 && $userId > 0) {
            add_team_member($teamId, $userId);
            $message = 'Team member added.';
        } else {
            $error = 'Select a team and user.';
        }
    }

    if ($action === 'remove_team_member' && $canAdmin) {
        $teamId = (int)($_POST['team_id'] ?? 0);
        $userId = (int)($_POST['user_id'] ?? 0);
        if ($teamId > 0 && $userId > 0) {
            remove_team_member($teamId, $userId);
            $message = 'Team member removed.';
        } else {
            $error = 'Invalid team member.';
        }
    }

    if ($action === 'delete_team' && $canAdmin) {
        $teamId = (int)($_POST['team_id'] ?? 0);
        if ($teamId > 0) {
            delete_team($tenantId, $teamId);
            $message = 'Team deleted.';
        } else {
            $error = 'Invalid team.';
        }
    }
}

$users = fetch_users_with_roles($tenantId);
$teams = fetch_teams($tenantId);
$teamMembers = [];
foreach ($teams as $team) {
    $teamMembers[$team['id']] = fetch_team_members((int)$team['id']);
}

$pageTitle = 'Teams';
$activePage = 'teams';
require __DIR__ . '/partials/admin_shell_start.php';
?>
    <div class="header admin-header">
        <div>
            <span class="pill">Admin</span>
            <h1>Teams</h1>
            <p class="subtitle">Group users into teams and manage membership.</p>
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
            <div class="split">
                <form method="post" class="card compact">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                    <input type="hidden" name="action" value="create_team">
                    <label>
                        Team name
                        <input type="text" name="team_name" required>
                    </label>
                    <button type="submit">Create team</button>
                </form>
                <form method="post" class="card compact">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                    <input type="hidden" name="action" value="add_team_member">
                    <label>
                        Team
                        <select name="team_id" required>
                            <option value="">Select team</option>
                            <?php foreach ($teams as $team): ?>
                                <option value="<?= (int)$team['id'] ?>"><?= htmlspecialchars($team['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>
                        User
                        <select name="member_id" required>
                            <option value="">Select user</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?= (int)$user['id'] ?>"><?= htmlspecialchars($user['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <button type="submit">Add member</button>
                </form>
            </div>
        </section>
    <?php endif; ?>

    <section class="card admin-card">
        <?php if (empty($teams)): ?>
            <p class="muted">No teams yet.</p>
        <?php else: ?>
            <div class="team-grid">
                <?php foreach ($teams as $team): ?>
                    <div class="team-card">
                        <div class="team-header">
                            <div>
                                <h3><?= htmlspecialchars($team['name']) ?></h3>
                                <p class="muted">Members <?= count($teamMembers[$team['id']] ?? []) ?></p>
                            </div>
                            <?php if ($canAdmin): ?>
                                <form method="post" class="inline">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="delete_team">
                                    <input type="hidden" name="team_id" value="<?= (int)$team['id'] ?>">
                                    <button type="submit" class="button small danger" onclick="return confirm('Delete this team?');">
                                        Delete
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                        <div class="team-members">
                            <?php foreach (($teamMembers[$team['id']] ?? []) as $member): ?>
                                <div class="member-chip">
                                    <span><?= htmlspecialchars($member['name']) ?></span>
                                    <?php if ($canAdmin): ?>
                                        <form method="post" class="inline">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                                            <input type="hidden" name="action" value="remove_team_member">
                                            <input type="hidden" name="team_id" value="<?= (int)$team['id'] ?>">
                                            <input type="hidden" name="user_id" value="<?= (int)$member['id'] ?>">
                                            <button type="submit" class="icon-button" title="Remove">×</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                            <?php if (empty($teamMembers[$team['id']] ?? [])): ?>
                                <span class="muted">No members yet.</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
<?php require __DIR__ . '/partials/admin_shell_end.php'; ?>
