<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

$user = require_login();
$tenantId = (int)$user['tenant_id'];
$userId = (int)$user['id'];
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate($_POST['csrf_token'] ?? null);
    $action = $_POST['action'] ?? '';

    if ($action === 'leave') {
        $leaveType = trim((string)($_POST['leave_type'] ?? 'paid'));
        $startDate = trim((string)($_POST['start_date'] ?? ''));
        $endDate = trim((string)($_POST['end_date'] ?? ''));
        $reason = trim((string)($_POST['reason'] ?? ''));

        if ($startDate === '' || $endDate === '') {
            $error = 'Start and end date are required.';
        } elseif ($endDate < $startDate) {
            $error = 'End date must be on or after start date.';
        } else {
            create_leave_request($tenantId, $userId, $leaveType, $startDate, $endDate, $reason);
            $message = 'Leave request submitted.';
        }
    }
}

$profile = fetch_employee_profile_for_user($tenantId, $userId);
$allMine = array_values(array_filter(fetch_leave_requests_for_tenant($tenantId), fn($r) => (int)$r['user_id'] === $userId));
$homeUrl = '/dashboard.php';
if (($user['role'] ?? 'user') === 'admin') {
    $homeUrl = '/admin.php';
} elseif (($user['role'] ?? 'user') === 'manager') {
    $homeUrl = '/manager.php';
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>My HR - TaskFlow</title>
    <link rel="stylesheet" href="/styles.css">
</head>
<body>
<div class="container">
    <div class="header">
        <div>
            <h1>My HR</h1>
            <p class="subtitle">Profile, workload target, and leave requests.</p>
        </div>
        <a class="button secondary" href="<?= htmlspecialchars($homeUrl) ?>">Back</a>
    </div>

    <?php if ($message): ?>
        <div class="alert success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <section class="card">
        <h2>Profile</h2>
        <?php if (!$profile): ?>
            <p class="muted">Profile not configured yet. Ask HR/admin to set it up.</p>
        <?php else: ?>
            <div class="table">
                <div class="table-row"><div>Department</div><div><?= htmlspecialchars($profile['department']) ?></div><div>Designation</div><div><?= htmlspecialchars($profile['designation']) ?></div><div>Type</div><div><?= htmlspecialchars($profile['employment_type']) ?></div></div>
                <div class="table-row"><div>Location</div><div><?= htmlspecialchars($profile['location']) ?></div><div>Manager</div><div><?= htmlspecialchars((string)($profile['manager_name'] ?? '')) ?></div><div>Weekly Target</div><div><?= htmlspecialchars((string)$profile['weekly_hour_target']) ?>h</div></div>
            </div>
        <?php endif; ?>
    </section>

    <section class="card">
        <h2>Request Leave</h2>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
            <input type="hidden" name="action" value="leave">
            <label>
                Leave type
                <select name="leave_type">
                    <option value="paid">Paid</option>
                    <option value="sick">Sick</option>
                    <option value="casual">Casual</option>
                    <option value="unpaid">Unpaid</option>
                </select>
            </label>
            <label>
                Start date
                <input type="date" name="start_date" required>
            </label>
            <label>
                End date
                <input type="date" name="end_date" required>
            </label>
            <label>
                Reason
                <textarea name="reason" rows="2"></textarea>
            </label>
            <button type="submit">Submit request</button>
        </form>
    </section>

    <section class="card">
        <h2>My Leave Requests</h2>
        <?php if (empty($allMine)): ?>
            <p class="muted">No leave requests found.</p>
        <?php else: ?>
            <div class="table">
                <div class="table-row table-head"><div>Type</div><div>Period</div><div>Days</div><div>Status</div><div>Reason</div><div>Decision</div></div>
                <?php foreach ($allMine as $lr): ?>
                    <div class="table-row"><div><?= htmlspecialchars($lr['leave_type']) ?></div><div><?= htmlspecialchars($lr['start_date']) ?> to <?= htmlspecialchars($lr['end_date']) ?></div><div><?= htmlspecialchars((string)$lr['total_days']) ?></div><div><?= htmlspecialchars($lr['status']) ?></div><div><?= htmlspecialchars($lr['reason']) ?></div><div><?= htmlspecialchars((string)($lr['decided_by_name'] ?? '')) ?></div></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>
</body>
</html>
