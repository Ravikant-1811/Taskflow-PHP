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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate($_POST['csrf_token'] ?? null);
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'create_user' && $isAdmin) {
        $name = trim((string)($_POST['name'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $password = trim((string)($_POST['password'] ?? ''));
        $newRole = (string)($_POST['role'] ?? 'user');
        if ($name === '' || $email === '' || $password === '') {
            $error = 'Name, email and password are required.';
        } else {
            try {
                create_user($tenantId, $name, strtolower($email), $password, in_array($newRole, ['admin', 'manager', 'user'], true) ? $newRole : 'user');
                $message = 'User created.';
            } catch (Throwable $e) {
                $error = 'Unable to create user. Email may already exist.';
            }
        }
    }

    if ($action === 'create_team' && $isAdmin) {
        $name = trim((string)($_POST['team_name'] ?? ''));
        if ($name === '') {
            $error = 'Team name is required.';
        } else {
            try {
                create_team($tenantId, $name);
                $message = 'Team created.';
            } catch (Throwable $e) {
                $error = 'Unable to create team. It may already exist.';
            }
        }
    }

    if ($action === 'add_member' && $isAdmin) {
        $teamId = (int)($_POST['team_id'] ?? 0);
        $memberId = (int)($_POST['user_id'] ?? 0);
        if ($teamId > 0 && $memberId > 0) {
            add_team_member($teamId, $memberId, 'member');
            $message = 'Member added to team.';
        } else {
            $error = 'Invalid team or user.';
        }
    }
}

if ($isAdmin) {
    $users = fetch_users_with_roles($tenantId);
    $teams = fetch_teams($tenantId);
} elseif ($role === 'manager') {
    $teamIds = fetch_team_ids_for_user($userId);
    $users = fetch_users_for_teams($tenantId, $teamIds);
    $teams = array_filter(fetch_teams($tenantId), static fn($t) => in_array((int)$t['id'], $teamIds, true));
} else {
    $users = [$user];
    $teams = [];
}

$teamMembers = [];
foreach ($teams as $team) {
    $teamMembers[(int)$team['id']] = fetch_team_members((int)$team['id']);
}

$pageTitle = 'People & Teams';
$activePage = 'people';
require __DIR__ . '/partials/portal_shell_start.php';
?>
<?php if ($message): ?><div class="alert success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="portal-grid portal-grid-split">
    <section class="portal-card">
        <div class="card-header">
            <div>
                <h2>Users</h2>
                <p class="muted">Workforce in your scope.</p>
            </div>
            <span class="chip"><?= count($users) ?></span>
        </div>

        <?php if ($isAdmin): ?>
            <form method="post" class="stack-form">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                <input type="hidden" name="action" value="create_user">
                <input type="text" name="name" placeholder="Full name" required>
                <input type="email" name="email" placeholder="Email" required>
                <input type="password" name="password" placeholder="Password" required>
                <select name="role">
                    <option value="user">User</option>
                    <option value="manager">Manager</option>
                    <option value="admin">Admin</option>
                </select>
                <button type="submit">Add User</button>
            </form>
        <?php endif; ?>

        <div class="portal-list">
            <?php foreach ($users as $u): ?>
                <article class="portal-list-item">
                    <div>
                        <strong><?= htmlspecialchars((string)$u['name']) ?></strong>
                        <p class="muted"><?= htmlspecialchars((string)$u['email']) ?></p>
                    </div>
                    <span class="chip"><?= htmlspecialchars((string)($u['role'] ?? 'user')) ?></span>
                </article>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="portal-card">
        <div class="card-header">
            <div>
                <h2>Teams</h2>
                <p class="muted">Team structure and membership.</p>
            </div>
            <span class="chip"><?= count($teams) ?></span>
        </div>

        <?php if ($isAdmin): ?>
            <form method="post" class="stack-form">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                <input type="hidden" name="action" value="create_team">
                <input type="text" name="team_name" placeholder="Team name" required>
                <button type="submit">Create Team</button>
            </form>
        <?php endif; ?>

        <?php foreach ($teams as $team): ?>
            <article class="team-block">
                <h3><?= htmlspecialchars((string)$team['name']) ?></h3>
                <?php if ($isAdmin): ?>
                    <form method="post" class="stack-form inline-form">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                        <input type="hidden" name="action" value="add_member">
                        <input type="hidden" name="team_id" value="<?= (int)$team['id'] ?>">
                        <select name="user_id" required>
                            <option value="">Add member</option>
                            <?php foreach ($users as $u): ?>
                                <option value="<?= (int)$u['id'] ?>"><?= htmlspecialchars((string)$u['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit">Add</button>
                    </form>
                <?php endif; ?>
                <div class="team-members">
                    <?php $members = $teamMembers[(int)$team['id']] ?? []; ?>
                    <?php if (empty($members)): ?>
                        <p class="muted">No members.</p>
                    <?php else: ?>
                        <?php foreach ($members as $member): ?>
                            <span class="tag"><?= htmlspecialchars((string)$member['name']) ?></span>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </section>
</div>
<?php require __DIR__ . '/partials/portal_shell_end.php'; ?>
