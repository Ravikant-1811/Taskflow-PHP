<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

$manager = require_manager_page();
$tenantId = (int)$manager['tenant_id'];
$teamIds = fetch_team_ids_for_user((int)$manager['id']);
$teamUsers = fetch_users_for_teams($tenantId, $teamIds);
$teamUserIds = array_map(fn($u) => (int)$u['id'], $teamUsers);
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate($_POST['csrf_token'] ?? null);
    $action = $_POST['action'] ?? '';
    if ($action === 'leave_status') {
        $leaveId = (int)($_POST['leave_id'] ?? 0);
        $status = trim((string)($_POST['status'] ?? 'pending'));
        if ($leaveId <= 0) {
            $error = 'Invalid leave request.';
        } else {
            update_leave_request_status($tenantId, $leaveId, $status, (int)$manager['id']);
            $message = 'Leave request updated.';
        }
    }
}

$profilesAll = fetch_employee_profiles($tenantId);
$profiles = array_values(array_filter($profilesAll, fn($p) => in_array((int)$p['user_id'], $teamUserIds, true)));
$leaveAll = fetch_leave_requests_for_users($teamUserIds);

$pageTitle = 'Team HR';
$activePage = 'hr';
$dashboardUrl = '/manager.php';
$tasksUrl = '/manager-tasks.php';
$reportsUrl = '/manager-reports.php';
$hrUrl = '/manager-hr.php';
$aiUrl = '/ai-assistant.php';
require __DIR__ . '/partials/admin_shell_start.php';
?>
    <div class="header admin-header">
        <div>
            <span class="pill">Manager</span>
            <h1>Team HR</h1>
            <p class="subtitle">Team profiles, capacity, and leave approvals.</p>
        </div>
        <div class="header-actions">
            <a class="button secondary" href="/manager-daily-reports.php">Daily reports</a>
            <a class="button secondary" href="/ai-assistant.php">AI assistant</a>
        </div>
    </div>

    <?php if ($message): ?><div class="alert success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <section class="admin-summary">
        <div class="summary-card"><p class="summary-label">Team Members</p><h3><?= count($teamUsers) ?></h3></div>
        <div class="summary-card"><p class="summary-label">Profiles Configured</p><h3><?= count($profiles) ?></h3></div>
        <div class="summary-card"><p class="summary-label">Leave Requests</p><h3><?= count($leaveAll) ?></h3></div>
    </section>

    <section class="card admin-card">
        <h2>Team Profiles</h2>
        <?php if (empty($profiles)): ?>
            <p class="muted">No team profiles configured.</p>
        <?php else: ?>
            <div class="table">
                <div class="table-row table-head"><div>Employee</div><div>Department</div><div>Designation</div><div>Location</div><div>Weekly Target</div><div>Manager</div></div>
                <?php foreach ($profiles as $p): ?>
                    <div class="table-row"><div><?= htmlspecialchars($p['user_name']) ?></div><div><?= htmlspecialchars($p['department']) ?></div><div><?= htmlspecialchars($p['designation']) ?></div><div><?= htmlspecialchars($p['location']) ?></div><div><?= htmlspecialchars((string)$p['weekly_hour_target']) ?></div><div><?= htmlspecialchars((string)$p['manager_name']) ?></div></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="card admin-card">
        <h2>Leave Requests</h2>
        <?php if (empty($leaveAll)): ?>
            <p class="muted">No leave requests from your team yet.</p>
        <?php else: ?>
            <div class="table">
                <div class="table-row table-head"><div>Employee</div><div>Type</div><div>Period</div><div>Days</div><div>Status</div><div>Action</div></div>
                <?php foreach ($leaveAll as $lr): ?>
                    <div class="table-row">
                        <div><?= htmlspecialchars($lr['user_name']) ?></div>
                        <div><?= htmlspecialchars($lr['leave_type']) ?></div>
                        <div><?= htmlspecialchars($lr['start_date']) ?> to <?= htmlspecialchars($lr['end_date']) ?></div>
                        <div><?= htmlspecialchars((string)$lr['total_days']) ?></div>
                        <div><?= htmlspecialchars($lr['status']) ?></div>
                        <div class="actions">
                            <form method="post" class="inline">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                                <input type="hidden" name="action" value="leave_status">
                                <input type="hidden" name="leave_id" value="<?= (int)$lr['id'] ?>">
                                <input type="hidden" name="status" value="approved">
                                <button type="submit" class="button small">Approve</button>
                            </form>
                            <form method="post" class="inline">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                                <input type="hidden" name="action" value="leave_status">
                                <input type="hidden" name="leave_id" value="<?= (int)$lr['id'] ?>">
                                <input type="hidden" name="status" value="rejected">
                                <button type="submit" class="button small danger">Reject</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
<?php require __DIR__ . '/partials/admin_shell_end.php'; ?>
