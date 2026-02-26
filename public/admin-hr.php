<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

$admin = require_admin_page();
$tenantId = (int)$admin['tenant_id'];
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate($_POST['csrf_token'] ?? null);
    $action = $_POST['action'] ?? '';

    if ($action === 'profile') {
        $userId = (int)($_POST['user_id'] ?? 0);
        $department = trim((string)($_POST['department'] ?? ''));
        $designation = trim((string)($_POST['designation'] ?? ''));
        $employmentType = trim((string)($_POST['employment_type'] ?? 'full_time'));
        $location = trim((string)($_POST['location'] ?? ''));
        $managerUserId = (int)($_POST['manager_user_id'] ?? 0);
        $joiningDate = trim((string)($_POST['joining_date'] ?? ''));
        $weeklyHourTarget = (float)($_POST['weekly_hour_target'] ?? 40);

        if ($userId <= 0) {
            $error = 'User is required.';
        } else {
            upsert_employee_profile(
                $tenantId,
                $userId,
                $department,
                $designation,
                $employmentType,
                $location,
                $managerUserId > 0 ? $managerUserId : null,
                $joiningDate !== '' ? $joiningDate : null,
                $weeklyHourTarget > 0 ? $weeklyHourTarget : 40
            );
            $message = 'Employee profile saved.';
        }
    }

    if ($action === 'leave_status') {
        $leaveId = (int)($_POST['leave_id'] ?? 0);
        $status = trim((string)($_POST['status'] ?? 'pending'));
        if ($leaveId <= 0) {
            $error = 'Invalid leave request.';
        } else {
            update_leave_request_status($tenantId, $leaveId, $status, (int)$admin['id']);
            $message = 'Leave request updated.';
        }
    }
}

$users = fetch_users_with_roles($tenantId);
$profiles = fetch_employee_profiles($tenantId);
$leaveRequests = fetch_leave_requests_for_tenant($tenantId);

$pageTitle = 'HR Management';
$activePage = 'hr';
$hrUrl = '/admin-hr.php';
$aiUrl = '/ai-assistant.php';
require __DIR__ . '/partials/admin_shell_start.php';
?>
    <div class="header admin-header">
        <div>
            <span class="pill">Admin</span>
            <h1>HR Management</h1>
            <p class="subtitle">Employee directory, workload targets, and leave approvals.</p>
        </div>
        <div class="header-actions">
            <a class="button secondary" href="/admin-daily-reports.php">Daily reports</a>
            <a class="button secondary" href="/ai-assistant.php">AI assistant</a>
        </div>
    </div>

    <?php if ($message): ?><div class="alert success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <section class="card admin-card">
        <h2>Employee Profile Setup</h2>
        <form method="post" class="grid">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
            <input type="hidden" name="action" value="profile">
            <label>
                User
                <select name="user_id" required>
                    <option value="">Select user</option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?= (int)$u['id'] ?>"><?= htmlspecialchars($u['name']) ?> (<?= htmlspecialchars($u['role']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                Department
                <input type="text" name="department">
            </label>
            <label>
                Designation
                <input type="text" name="designation">
            </label>
            <label>
                Employment type
                <select name="employment_type">
                    <option value="full_time">Full-time</option>
                    <option value="part_time">Part-time</option>
                    <option value="contract">Contract</option>
                    <option value="intern">Intern</option>
                </select>
            </label>
            <label>
                Location
                <input type="text" name="location" placeholder="Remote / City">
            </label>
            <label>
                Reporting manager
                <select name="manager_user_id">
                    <option value="0">Not set</option>
                    <?php foreach ($users as $u): ?>
                        <?php if ($u['role'] === 'admin' || $u['role'] === 'manager'): ?>
                            <option value="<?= (int)$u['id'] ?>"><?= htmlspecialchars($u['name']) ?></option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                Joining date
                <input type="date" name="joining_date">
            </label>
            <label>
                Weekly hour target
                <input type="number" step="0.5" name="weekly_hour_target" value="40">
            </label>
            <button type="submit">Save profile</button>
        </form>
    </section>

    <section class="card admin-card">
        <h2>Employee Directory</h2>
        <?php if (empty($profiles)): ?>
            <p class="muted">No employee profiles yet.</p>
        <?php else: ?>
            <div class="table">
                <div class="table-row table-head"><div>Employee</div><div>Department</div><div>Designation</div><div>Manager</div><div>Type</div><div>Weekly Target</div></div>
                <?php foreach ($profiles as $p): ?>
                    <div class="table-row"><div><?= htmlspecialchars($p['user_name']) ?></div><div><?= htmlspecialchars($p['department']) ?></div><div><?= htmlspecialchars($p['designation']) ?></div><div><?= htmlspecialchars((string)$p['manager_name']) ?></div><div><?= htmlspecialchars($p['employment_type']) ?></div><div><?= htmlspecialchars((string)$p['weekly_hour_target']) ?></div></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="card admin-card">
        <h2>Leave Requests</h2>
        <?php if (empty($leaveRequests)): ?>
            <p class="muted">No leave requests yet.</p>
        <?php else: ?>
            <div class="table">
                <div class="table-row table-head"><div>Employee</div><div>Type</div><div>Period</div><div>Days</div><div>Status</div><div>Action</div></div>
                <?php foreach ($leaveRequests as $lr): ?>
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
