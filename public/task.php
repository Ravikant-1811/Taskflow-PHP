<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

$user = require_login();
$tenantId = (int)$user['tenant_id'];
$taskId = (int)($_GET['id'] ?? 0);

if ($taskId <= 0) {
    http_response_code(404);
    echo 'Task not found';
    exit;
}

$task = fetch_task($tenantId, $taskId);
if (!$task) {
    http_response_code(404);
    echo 'Task not found';
    exit;
}

$canView = is_manager($user) || (int)$task['assigned_to'] === (int)$user['id'] || (int)$task['created_by'] === (int)$user['id'];
if (!$canView) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate($_POST['csrf_token'] ?? null);
    $action = $_POST['action'] ?? '';

    if ($action === 'comment') {
        $body = trim((string)($_POST['body'] ?? ''));
        if ($body === '') {
            $error = 'Comment cannot be empty.';
        } else {
            create_comment($taskId, (int)$user['id'], $body);
            log_activity($taskId, (int)$user['id'], 'Added a comment');
            $message = 'Comment added.';
        }
    }

    if ($action === 'attach' && isset($_FILES['attachment'])) {
        $file = $_FILES['attachment'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $error = 'Upload failed.';
        } else {
            $config = require __DIR__ . '/../app/config.php';
            $uploadDir = $config['upload_dir'];
            $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $file['name']);
            $targetName = uniqid('file_', true) . '_' . $safeName;
            $targetPath = $uploadDir . '/' . $targetName;
            if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                add_attachment($taskId, (int)$user['id'], $file['name'], $targetName, (int)$file['size']);
                log_activity($taskId, (int)$user['id'], 'Uploaded an attachment');
                $message = 'Attachment uploaded.';
            } else {
                $error = 'Unable to save file.';
            }
        }
    }

    $task = fetch_task($tenantId, $taskId);
}

$comments = fetch_comments($taskId);
$activity = fetch_activity($taskId);
$attachments = fetch_attachments($taskId);
if (is_manager($user) && !is_admin($user)) {
    $backLink = '/manager-tasks.php';
} elseif (is_admin($user)) {
    $backLink = '/admin-tasks.php';
} else {
    $backLink = '/dashboard.php';
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Task Details - TaskFlow</title>
    <link rel="stylesheet" href="/styles.css">
</head>
<body>
<div class="container">
    <div class="header">
        <div>
            <h1><?= htmlspecialchars($task['title']) ?></h1>
            <p class="subtitle">Project: <?= htmlspecialchars($task['project_name'] ?? 'No project') ?></p>
        </div>
        <a class="button secondary" href="<?= htmlspecialchars($backLink) ?>">Back</a>
    </div>

    <?php if ($message): ?>
        <div class="alert success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <section class="card">
        <h2>Overview</h2>
        <p class="task-meta">
            Assigned to <?= htmlspecialchars($task['assigned_to_name']) ?> ·
            Created by <?= htmlspecialchars($task['created_by_name']) ?> ·
            Status <?= $task['status'] === 'in_progress' ? 'In Progress' : ucfirst($task['status']) ?> ·
            Priority <?= ucfirst($task['priority']) ?>
            <?php if (!empty($task['due_date'])): ?>
                · Due <?= htmlspecialchars($task['due_date']) ?>
            <?php endif; ?>
        </p>
        <?php if (!empty($task['description'])): ?>
            <p><?= nl2br(htmlspecialchars($task['description'])) ?></p>
        <?php endif; ?>
    </section>

    <section class="grid">
        <div class="card">
            <h2>Comments</h2>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                <input type="hidden" name="action" value="comment">
                <label>
                    Add comment
                    <textarea name="body" rows="3" required></textarea>
                </label>
                <button type="submit">Post comment</button>
            </form>
            <div class="stack">
                <?php foreach ($comments as $comment): ?>
                    <div class="note">
                        <strong><?= htmlspecialchars($comment['author_name']) ?></strong>
                        <span class="muted"><?= htmlspecialchars($comment['created_at']) ?></span>
                        <p><?= nl2br(htmlspecialchars($comment['body'])) ?></p>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($comments)): ?>
                    <p class="muted">No comments yet.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <h2>Attachments</h2>
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                <input type="hidden" name="action" value="attach">
                <label>
                    Upload file
                    <input type="file" name="attachment" required>
                </label>
                <button type="submit">Upload</button>
            </form>
            <ul class="file-list">
                <?php foreach ($attachments as $file): ?>
                    <li>
                        <?= htmlspecialchars($file['file_name']) ?>
                        <span class="muted">(<?= number_format($file['file_size'] / 1024, 1) ?> KB)</span>
                    </li>
                <?php endforeach; ?>
                <?php if (empty($attachments)): ?>
                    <li class="muted">No attachments yet.</li>
                <?php endif; ?>
            </ul>
        </div>
    </section>

    <section class="card">
        <h2>Activity Log</h2>
        <ul class="timeline">
            <?php foreach ($activity as $entry): ?>
                <li>
                    <strong><?= htmlspecialchars($entry['author_name']) ?></strong>
                    <?= htmlspecialchars($entry['action']) ?>
                    <span class="muted"><?= htmlspecialchars($entry['created_at']) ?></span>
                </li>
            <?php endforeach; ?>
            <?php if (empty($activity)): ?>
                <li class="muted">No activity yet.</li>
            <?php endif; ?>
        </ul>
    </section>
</div>
</body>
</html>
