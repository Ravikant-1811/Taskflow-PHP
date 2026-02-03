<?php

declare(strict_types=1);

function fetch_users(): array
{
    $stmt = db()->query('SELECT id, name, email FROM users ORDER BY name');
    return $stmt->fetchAll();
}

function fetch_tasks_for_user(int $userId): array
{
    $stmt = db()->prepare(
        'SELECT t.*, u1.name AS created_by_name, u2.name AS assigned_to_name
         FROM tasks t
         JOIN users u1 ON u1.id = t.created_by
         JOIN users u2 ON u2.id = t.assigned_to
         WHERE t.assigned_to = :user_id
         ORDER BY t.created_at DESC'
    );
    $stmt->execute([':user_id' => $userId]);
    return $stmt->fetchAll();
}

function fetch_tasks_created_by(int $userId): array
{
    $stmt = db()->prepare(
        'SELECT t.*, u1.name AS created_by_name, u2.name AS assigned_to_name
         FROM tasks t
         JOIN users u1 ON u1.id = t.created_by
         JOIN users u2 ON u2.id = t.assigned_to
         WHERE t.created_by = :user_id
         ORDER BY t.created_at DESC'
    );
    $stmt->execute([':user_id' => $userId]);
    return $stmt->fetchAll();
}

function create_task(int $creatorId, int $assigneeId, string $title, string $description): void
{
    $stmt = db()->prepare(
        'INSERT INTO tasks (title, description, created_by, assigned_to)
         VALUES (:title, :description, :created_by, :assigned_to)'
    );
    $stmt->execute([
        ':title' => $title,
        ':description' => $description,
        ':created_by' => $creatorId,
        ':assigned_to' => $assigneeId,
    ]);
}

function mark_task_complete(int $taskId, int $userId): void
{
    $stmt = db()->prepare(
        'UPDATE tasks
         SET status = "completed", completed_at = datetime("now")
         WHERE id = :id AND assigned_to = :user_id'
    );
    $stmt->execute([':id' => $taskId, ':user_id' => $userId]);
}
