<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

$user = require_login();
$tenantId = (int)$user['tenant_id'];
$userId = (int)$user['id'];
$role = (string)($user['role'] ?? 'user');
$isManager = in_array($role, ['admin', 'manager'], true);

$selectedDate = trim((string)($_GET['date'] ?? date('Y-m-d')));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate)) {
    $selectedDate = date('Y-m-d');
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate($_POST['csrf_token'] ?? null);
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'save_daily') {
        $reportDate = trim((string)($_POST['report_date'] ?? $selectedDate));
        $startTime = trim((string)($_POST['start_time'] ?? ''));
        $endTime = trim((string)($_POST['end_time'] ?? ''));
        $workSummary = trim((string)($_POST['work_summary'] ?? ''));
        $blockers = trim((string)($_POST['blockers'] ?? ''));
        $nextPlan = trim((string)($_POST['next_plan'] ?? ''));

        $hours = calculate_hours_from_times($startTime !== '' ? $startTime : null, $endTime !== '' ? $endTime : null);
        upsert_daily_report(
            $tenantId,
            $userId,
            $reportDate,
            $startTime !== '' ? $startTime : null,
            $endTime !== '' ? $endTime : null,
            $hours,
            $workSummary,
            $blockers,
            $nextPlan
        );
        $message = 'Daily report saved.';
        $selectedDate = $reportDate;
    }
}

$myReport = fetch_daily_report_for_user_date($tenantId, $userId, $selectedDate);

if ($role === 'admin') {
    $teamReports = fetch_daily_reports_for_tenant($tenantId, $selectedDate);
} elseif ($role === 'manager') {
    $teamIds = fetch_team_ids_for_user($userId);
    $teamUsers = fetch_users_for_teams($tenantId, $teamIds);
    $teamUserIds = array_map(static fn($u) => (int)$u['id'], $teamUsers);
    $teamReports = fetch_daily_reports_for_users($teamUserIds, $selectedDate);
} else {
    $teamReports = [];
}

$pageTitle = 'Time & Daily';
$activePage = 'time';
require __DIR__ . '/partials/portal_shell_start.php';
?>
<?php if ($message): ?><div class="alert success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="portal-grid portal-grid-split">
    <section class="portal-card">
        <h2>Submit Daily Report</h2>
        <form method="post" class="stack-form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
            <input type="hidden" name="action" value="save_daily">

            <label>
                Report Date
                <input type="date" name="report_date" value="<?= htmlspecialchars($selectedDate) ?>" required>
            </label>
            <label>
                Start Time
                <input type="time" name="start_time" value="<?= htmlspecialchars((string)($myReport['start_time'] ?? '')) ?>">
            </label>
            <label>
                End Time
                <input type="time" name="end_time" value="<?= htmlspecialchars((string)($myReport['end_time'] ?? '')) ?>">
            </label>
            <label>
                Work Summary
                <textarea name="work_summary" rows="3"><?= htmlspecialchars((string)($myReport['work_summary'] ?? '')) ?></textarea>
            </label>
            <label>
                Blockers
                <textarea name="blockers" rows="2"><?= htmlspecialchars((string)($myReport['blockers'] ?? '')) ?></textarea>
            </label>
            <label>
                Next Plan
                <textarea name="next_plan" rows="2"><?= htmlspecialchars((string)($myReport['next_plan'] ?? '')) ?></textarea>
            </label>
            <button type="submit">Save Report</button>
        </form>
    </section>

    <?php if ($isManager): ?>
    <section class="portal-card">
        <div class="card-header">
            <div>
                <h2>Team Reports</h2>
                <p class="muted">Submitted reports for <?= htmlspecialchars($selectedDate) ?>.</p>
            </div>
            <form method="get" class="inline-form">
                <input type="date" name="date" value="<?= htmlspecialchars($selectedDate) ?>">
                <button type="submit">Load</button>
            </form>
        </div>

        <?php if (empty($teamReports)): ?>
            <p class="muted">No team reports for this date.</p>
        <?php else: ?>
            <div class="portal-list">
                <?php foreach ($teamReports as $report): ?>
                    <article class="portal-list-item">
                        <div>
                            <strong><?= htmlspecialchars((string)$report['user_name']) ?></strong>
                            <p class="muted"><?= htmlspecialchars((string)$report['work_summary']) ?></p>
                        </div>
                        <span class="chip"><?= htmlspecialchars((string)$report['total_hours']) ?>h</span>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/partials/portal_shell_end.php'; ?>
