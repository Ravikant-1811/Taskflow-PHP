<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

$user = require_login();
$tenantId = (int)$user['tenant_id'];
$userId = (int)$user['id'];
$selectedDate = $_GET['date'] ?? date('Y-m-d');
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate($_POST['csrf_token'] ?? null);

    $selectedDate = (string)($_POST['report_date'] ?? date('Y-m-d'));
    $startTime = trim((string)($_POST['start_time'] ?? ''));
    $endTime = trim((string)($_POST['end_time'] ?? ''));
    $workSummary = trim((string)($_POST['work_summary'] ?? ''));
    $blockers = trim((string)($_POST['blockers'] ?? ''));
    $nextPlan = trim((string)($_POST['next_plan'] ?? ''));

    if ($workSummary === '') {
        $error = 'Work summary is required.';
    } else {
        $totalHours = calculate_hours_from_times($startTime !== '' ? $startTime : null, $endTime !== '' ? $endTime : null);
        upsert_daily_report(
            $tenantId,
            $userId,
            $selectedDate,
            $startTime !== '' ? $startTime : null,
            $endTime !== '' ? $endTime : null,
            $totalHours,
            $workSummary,
            $blockers,
            $nextPlan
        );
        $message = 'Daily report saved.';
    }
}

$report = fetch_daily_report_for_user_date($tenantId, $userId, $selectedDate);
$weeklyReports = [];
for ($i = 0; $i < 7; $i++) {
    $date = date('Y-m-d', strtotime("-$i day"));
    $row = fetch_daily_report_for_user_date($tenantId, $userId, $date);
    if ($row) {
        $weeklyReports[] = $row;
    }
}

$homeUrl = '/dashboard.php';
if (($user['role'] ?? 'user') === 'manager') {
    $homeUrl = '/manager.php';
} elseif (($user['role'] ?? 'user') === 'admin') {
    $homeUrl = '/admin.php';
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Daily Report - TaskFlow</title>
    <link rel="stylesheet" href="/styles.css">
</head>
<body>
<div class="container">
    <div class="header">
        <div>
            <h1>Daily Report</h1>
            <p class="subtitle">Log your remote work hours and daily progress.</p>
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
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
            <label>
                Report date
                <input type="date" name="report_date" value="<?= htmlspecialchars($selectedDate) ?>" required>
            </label>
            <label>
                Start time
                <input type="time" name="start_time" value="<?= htmlspecialchars((string)($report['start_time'] ?? '')) ?>">
            </label>
            <label>
                End time
                <input type="time" name="end_time" value="<?= htmlspecialchars((string)($report['end_time'] ?? '')) ?>">
            </label>
            <label>
                Work summary
                <textarea name="work_summary" rows="4" required><?= htmlspecialchars((string)($report['work_summary'] ?? '')) ?></textarea>
            </label>
            <label>
                Blockers
                <textarea name="blockers" rows="2"><?= htmlspecialchars((string)($report['blockers'] ?? '')) ?></textarea>
            </label>
            <label>
                Plan for next day
                <textarea name="next_plan" rows="2"><?= htmlspecialchars((string)($report['next_plan'] ?? '')) ?></textarea>
            </label>
            <?php if ($report): ?>
                <p class="muted">Total hours: <?= htmlspecialchars((string)$report['total_hours']) ?></p>
            <?php endif; ?>
            <button type="submit">Save report</button>
        </form>
    </section>

    <section class="card">
        <h2>Last 7 Days</h2>
        <?php if (empty($weeklyReports)): ?>
            <p class="muted">No reports submitted yet.</p>
        <?php else: ?>
            <div class="table">
                <div class="table-row table-head">
                    <div>Date</div>
                    <div>Hours</div>
                    <div>Summary</div>
                    <div>Status</div>
                    <div>Updated</div>
                    <div>Action</div>
                </div>
                <?php foreach ($weeklyReports as $item): ?>
                    <div class="table-row">
                        <div><?= htmlspecialchars($item['report_date']) ?></div>
                        <div><?= htmlspecialchars((string)$item['total_hours']) ?></div>
                        <div><?= htmlspecialchars(strlen((string)$item['work_summary']) > 80 ? substr((string)$item['work_summary'], 0, 77) . '...' : (string)$item['work_summary']) ?></div>
                        <div><?= htmlspecialchars($item['status']) ?></div>
                        <div><?= htmlspecialchars($item['updated_at']) ?></div>
                        <div><a href="/daily-report.php?date=<?= urlencode((string)$item['report_date']) ?>">Open</a></div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>
</body>
</html>
