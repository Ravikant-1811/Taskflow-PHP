<?php

declare(strict_types=1);

function fetch_users(): array
{
    $stmt = db()->query('SELECT id, name, email FROM users ORDER BY name');
    return $stmt->fetchAll();
}

function fetch_users_with_roles(): array
{
    $stmt = db()->query('SELECT id, name, email, role, created_at FROM users ORDER BY name');
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

function fetch_all_tasks(): array
{
    $stmt = db()->query(
        'SELECT t.*, u1.name AS created_by_name, u2.name AS assigned_to_name
         FROM tasks t
         JOIN users u1 ON u1.id = t.created_by
         JOIN users u2 ON u2.id = t.assigned_to
         ORDER BY t.created_at DESC'
    );
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

function update_user_role(int $userId, string $role): void
{
    $stmt = db()->prepare('UPDATE users SET role = :role WHERE id = :id');
    $stmt->execute([':role' => $role, ':id' => $userId]);
}

function delete_user(int $userId): void
{
    $stmt = db()->prepare('DELETE FROM users WHERE id = :id');
    $stmt->execute([':id' => $userId]);
}

function delete_task(int $taskId): void
{
    $stmt = db()->prepare('DELETE FROM tasks WHERE id = :id');
    $stmt->execute([':id' => $taskId]);
}

function update_task_admin(int $taskId, int $assigneeId, string $title, string $description, string $status): void
{
    $status = $status === 'completed' ? 'completed' : 'open';
    $completedAt = $status === 'completed' ? date('Y-m-d H:i:s') : null;

    $stmt = db()->prepare(
        'UPDATE tasks
         SET title = :title,
             description = :description,
             assigned_to = :assigned_to,
             status = :status,
             completed_at = :completed_at
         WHERE id = :id'
    );
    $stmt->execute([
        ':title' => $title,
        ':description' => $description,
        ':assigned_to' => $assigneeId,
        ':status' => $status,
        ':completed_at' => $completedAt,
        ':id' => $taskId,
    ]);
}
