<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

$admin = require_admin_page();
$tenantId = (int)$admin['tenant_id'];
$reportDate = $_GET['date'] ?? date('Y-m-d');
$selectedUserId = (int)($_GET['user_id'] ?? 0);

$allUsers = fetch_users_with_roles($tenantId);
$reports = fetch_daily_reports_for_tenant($tenantId, $reportDate);
if ($selectedUserId > 0) {
    $reports = array_values(array_filter($reports, fn($row) => (int)$row['user_id'] === $selectedUserId));
}

$trackedUsers = $allUsers;
if ($selectedUserId > 0) {
    $trackedUsers = array_values(array_filter($allUsers, fn($u) => (int)$u['id'] === $selectedUserId));
}

$submittedCount = count($reports);
$totalHours = array_reduce($reports, fn($carry, $row) => $carry + (float)$row['total_hours'], 0.0);
$submittedUserIds = array_map(fn($row) => (int)$row['user_id'], $reports);
$pendingUsers = array_values(array_filter($trackedUsers, fn($u) => !in_array((int)$u['id'], $submittedUserIds, true)));
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Admin Daily Reports - TaskFlow</title>
    <link rel="stylesheet" href="/styles.css">
</head>
<body>
<div class="container">
    <div class="header">
        <div>
            <h1>Company Daily Reports</h1>
            <p class="subtitle">Daily updates across all employees.</p>
        </div>
        <a class="button secondary" href="/admin-reports.php">Back</a>
    </div>

    <section class="card">
        <form method="get" class="split">
            <label>
                Report date
                <input type="date" name="date" value="<?= htmlspecialchars($reportDate) ?>" required>
            </label>
            <label>
                Employee
                <select name="user_id">
                    <option value="0">All employees</option>
                    <?php foreach ($allUsers as $u): ?>
                        <option value="<?= (int)$u['id'] ?>" <?= $selectedUserId === (int)$u['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($u['name']) ?> (<?= htmlspecialchars($u['role']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button type="submit">Filter</button>
        </form>
    </section>

    <section class="admin-summary">
        <div class="summary-card">
            <p class="summary-label">Reports Submitted</p>
            <h3><?= $submittedCount ?></h3>
        </div>
        <div class="summary-card">
            <p class="summary-label">Total Hours</p>
            <h3><?= number_format($totalHours, 2) ?></h3>
        </div>
        <div class="summary-card">
            <p class="summary-label">Pending</p>
            <h3><?= count($pendingUsers) ?></h3>
        </div>
    </section>

    <section class="card">
        <h2>Reports</h2>
        <?php if (empty($reports)): ?>
            <p class="muted">No reports submitted for this date.</p>
        <?php else: ?>
            <div class="table">
                <div class="table-row table-head">
                    <div>Employee</div>
                    <div>Role</div>
                    <div>Hours</div>
                    <div>Summary</div>
                    <div>Blockers</div>
                    <div>Updated</div>
                </div>
                <?php foreach ($reports as $report): ?>
                    <div class="table-row">
                        <div><?= htmlspecialchars($report['user_name']) ?></div>
                        <div><?= htmlspecialchars($report['user_role']) ?></div>
                        <div><?= htmlspecialchars((string)$report['total_hours']) ?></div>
                        <div><?= htmlspecialchars($report['work_summary']) ?></div>
                        <div><?= htmlspecialchars($report['blockers']) ?></div>
                        <div><?= htmlspecialchars($report['updated_at']) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="card">
        <h2>Pending Employees</h2>
        <?php if (empty($pendingUsers)): ?>
            <p class="muted">All selected employees submitted their report.</p>
        <?php else: ?>
            <div class="tag-list">
                <?php foreach ($pendingUsers as $u): ?>
                    <span class="tag"><?= htmlspecialchars($u['name']) ?> (<?= htmlspecialchars($u['email']) ?>)</span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>
</body>
</html>
