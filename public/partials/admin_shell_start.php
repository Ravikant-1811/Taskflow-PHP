<?php
if (!isset($pageTitle)) {
    $pageTitle = 'Admin';
}
if (!isset($activePage)) {
    $activePage = 'overview';
}
if (!isset($canAdmin)) {
    $canAdmin = false;
}
if (!isset($dashboardUrl)) {
    $dashboardUrl = '/admin.php';
}
if (!isset($tasksUrl)) {
    $tasksUrl = '/admin-tasks.php';
}
if (!isset($reportsUrl)) {
    $reportsUrl = '/admin-reports.php';
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title><?= htmlspecialchars($pageTitle) ?> - TaskFlow</title>
    <link rel="stylesheet" href="/styles.css">
</head>
<body>
<div class="container admin admin-layout">
    <aside class="sidebar">
        <div class="sidebar-brand">
            <span class="brand-mark">TF</span>
            <div>
                <strong>TaskFlow</strong>
                <div class="muted">Company Console</div>
            </div>
        </div>
        <nav class="sidebar-nav">
            <a href="<?= htmlspecialchars($dashboardUrl) ?>" class="sidebar-link <?= $activePage === 'overview' ? 'active' : '' ?>">Overview</a>
            <?php if ($canAdmin): ?>
                <a href="/admin-users.php" class="sidebar-link <?= $activePage === 'users' ? 'active' : '' ?>">Users</a>
                <a href="/admin-teams.php" class="sidebar-link <?= $activePage === 'teams' ? 'active' : '' ?>">Teams</a>
                <a href="/admin-projects.php" class="sidebar-link <?= $activePage === 'projects' ? 'active' : '' ?>">Projects</a>
            <?php endif; ?>
            <a href="<?= htmlspecialchars($tasksUrl) ?>" class="sidebar-link <?= $activePage === 'tasks' ? 'active' : '' ?>">Tasks</a>
            <a href="<?= htmlspecialchars($reportsUrl) ?>" class="sidebar-link <?= $activePage === 'reports' ? 'active' : '' ?>">Reports</a>
        </nav>
        <a class="button secondary sidebar-logout" href="/logout.php">Logout</a>
    </aside>

    <main class="admin-content">
