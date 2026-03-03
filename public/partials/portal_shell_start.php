<?php
if (!isset($pageTitle)) {
    $pageTitle = 'Portal';
}
if (!isset($activePage)) {
    $activePage = 'overview';
}
if (!isset($user) || !is_array($user)) {
    $user = require_login();
}

$role = (string)($user['role'] ?? 'user');
$isAdmin = $role === 'admin';
$isManager = in_array($role, ['admin', 'manager'], true);

$dailyUrl = $isAdmin ? '/admin-daily-reports.php' : ($role === 'manager' ? '/manager-daily-reports.php' : '/daily-report.php');
$hrUrl = $isAdmin ? '/admin-hr.php' : ($role === 'manager' ? '/manager-hr.php' : '/my-hr.php');
$legacyOverview = $isAdmin ? '/admin-tasks.php' : ($role === 'manager' ? '/manager-tasks.php' : '/daily-report.php');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title><?= htmlspecialchars($pageTitle) ?> - TaskFlow</title>
    <link rel="stylesheet" href="/styles.css">
</head>
<body>
<div class="portal-shell">
    <aside class="portal-sidebar">
        <a class="portal-brand" href="/portal.php">
            <span class="portal-brand-mark">TF</span>
            <span>
                <strong>TaskFlow</strong>
                <small>Work OS</small>
            </span>
        </a>

        <nav class="portal-nav">
            <a class="portal-link <?= $activePage === 'overview' ? 'active' : '' ?>" href="/portal.php">Overview</a>
            <a class="portal-link <?= $activePage === 'tasks' ? 'active' : '' ?>" href="/portal-tasks.php">Tasks</a>
            <a class="portal-link <?= $activePage === 'projects' ? 'active' : '' ?>" href="/portal-projects.php">Projects</a>
            <a class="portal-link <?= $activePage === 'people' ? 'active' : '' ?>" href="/portal-people.php">People</a>
            <a class="portal-link <?= $activePage === 'reports' ? 'active' : '' ?>" href="/portal-reports.php">Reports</a>
            <a class="portal-link <?= $activePage === 'time' ? 'active' : '' ?>" href="/portal-time.php">Time & Daily</a>
            <?php if ($isManager): ?>
                <a class="portal-link" href="/ai-assistant.php">AI Assistant</a>
            <?php endif; ?>
            <a class="portal-link" href="<?= htmlspecialchars($legacyOverview) ?>">Classic Module</a>
        </nav>

        <div class="portal-sidebar-foot">
            <div class="portal-user">
                <strong><?= htmlspecialchars((string)$user['name']) ?></strong>
                <small><?= htmlspecialchars($role) ?></small>
            </div>
            <a class="button secondary" href="/logout.php">Logout</a>
        </div>
    </aside>

    <main class="portal-main">
        <header class="portal-topbar">
            <div>
                <h1><?= htmlspecialchars($pageTitle) ?></h1>
                <p class="subtitle">Company-ready workspace for tasks, teams and reports.</p>
            </div>
            <div class="header-actions">
                <a class="button secondary" href="<?= htmlspecialchars($dailyUrl) ?>">Daily Reports</a>
                <a class="button secondary" href="<?= htmlspecialchars($hrUrl) ?>">HR</a>
            </div>
        </header>
