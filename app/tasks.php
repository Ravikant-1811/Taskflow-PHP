<?php

declare(strict_types=1);

function fetch_tenant_by_slug(string $slug): ?array
{
    $stmt = db()->prepare('SELECT id, name, slug FROM tenants WHERE slug = :slug');
    $stmt->execute([':slug' => $slug]);
    $tenant = $stmt->fetch();
    return $tenant ?: null;
}

function create_tenant(string $name, string $slug): int
{
    $stmt = db()->prepare('INSERT INTO tenants (name, slug) VALUES (:name, :slug)');
    $stmt->execute([':name' => $name, ':slug' => $slug]);
    return (int)db()->lastInsertId();
}

function fetch_users(int $tenantId): array
{
    $stmt = db()->prepare('SELECT id, name, email FROM users WHERE tenant_id = :tenant_id ORDER BY name');
    $stmt->execute([':tenant_id' => $tenantId]);
    return $stmt->fetchAll();
}

function fetch_users_with_roles(int $tenantId): array
{
    $stmt = db()->prepare('SELECT id, name, email, role, created_at FROM users WHERE tenant_id = :tenant_id ORDER BY name');
    $stmt->execute([':tenant_id' => $tenantId]);
    return $stmt->fetchAll();
}

function create_user(int $tenantId, string $name, string $email, string $password, string $role): void
{
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = db()->prepare(
        'INSERT INTO users (tenant_id, name, email, password_hash, role)
         VALUES (:tenant_id, :name, :email, :password_hash, :role)'
    );
    $stmt->execute([
        ':tenant_id' => $tenantId,
        ':name' => $name,
        ':email' => $email,
        ':password_hash' => $hash,
        ':role' => $role,
    ]);
}

function update_user_role(int $tenantId, int $userId, string $role): void
{
    $stmt = db()->prepare('UPDATE users SET role = :role WHERE id = :id AND tenant_id = :tenant_id');
    $stmt->execute([':role' => $role, ':id' => $userId, ':tenant_id' => $tenantId]);
}

function delete_user(int $tenantId, int $userId): void
{
    $stmt = db()->prepare('DELETE FROM users WHERE id = :id AND tenant_id = :tenant_id');
    $stmt->execute([':id' => $userId, ':tenant_id' => $tenantId]);
}

function fetch_teams(int $tenantId): array
{
    $stmt = db()->prepare('SELECT id, name, created_at FROM teams WHERE tenant_id = :tenant_id ORDER BY name');
    $stmt->execute([':tenant_id' => $tenantId]);
    return $stmt->fetchAll();
}

function create_team(int $tenantId, string $name): void
{
    $stmt = db()->prepare('INSERT INTO teams (tenant_id, name) VALUES (:tenant_id, :name)');
    $stmt->execute([':tenant_id' => $tenantId, ':name' => $name]);
}

function add_team_member(int $teamId, int $userId, string $role = 'member'): void
{
    $stmt = db()->prepare(
        'INSERT OR IGNORE INTO team_members (team_id, user_id, role)
         VALUES (:team_id, :user_id, :role)'
    );
    $stmt->execute([':team_id' => $teamId, ':user_id' => $userId, ':role' => $role]);
}

function fetch_team_members(int $teamId): array
{
    $stmt = db()->prepare(
        'SELECT u.id, u.name, u.email, tm.role
         FROM team_members tm
         JOIN users u ON u.id = tm.user_id
         WHERE tm.team_id = :team_id
         ORDER BY u.name'
    );
    $stmt->execute([':team_id' => $teamId]);
    return $stmt->fetchAll();
}

function fetch_projects(int $tenantId): array
{
    $stmt = db()->prepare(
        'SELECT p.*, t.name AS team_name
         FROM projects p
         LEFT JOIN teams t ON t.id = p.team_id
         WHERE p.tenant_id = :tenant_id
         ORDER BY p.created_at DESC'
    );
    $stmt->execute([':tenant_id' => $tenantId]);
    return $stmt->fetchAll();
}

function create_project(int $tenantId, ?int $teamId, string $name, string $description): void
{
    $stmt = db()->prepare(
        'INSERT INTO projects (tenant_id, team_id, name, description)
         VALUES (:tenant_id, :team_id, :name, :description)'
    );
    $stmt->execute([
        ':tenant_id' => $tenantId,
        ':team_id' => $teamId,
        ':name' => $name,
        ':description' => $description,
    ]);
}

function fetch_tasks_for_user(int $tenantId, int $userId): array
{
    $stmt = db()->prepare(
        'SELECT t.*, u1.name AS created_by_name, u2.name AS assigned_to_name, p.name AS project_name
         FROM tasks t
         JOIN users u1 ON u1.id = t.created_by
         JOIN users u2 ON u2.id = t.assigned_to
         LEFT JOIN projects p ON p.id = t.project_id
         WHERE t.tenant_id = :tenant_id AND t.assigned_to = :user_id
         ORDER BY t.created_at DESC'
    );
    $stmt->execute([':tenant_id' => $tenantId, ':user_id' => $userId]);
    return $stmt->fetchAll();
}

function fetch_tasks_created_by(int $tenantId, int $userId): array
{
    $stmt = db()->prepare(
        'SELECT t.*, u1.name AS created_by_name, u2.name AS assigned_to_name, p.name AS project_name
         FROM tasks t
         JOIN users u1 ON u1.id = t.created_by
         JOIN users u2 ON u2.id = t.assigned_to
         LEFT JOIN projects p ON p.id = t.project_id
         WHERE t.tenant_id = :tenant_id AND t.created_by = :user_id
         ORDER BY t.created_at DESC'
    );
    $stmt->execute([':tenant_id' => $tenantId, ':user_id' => $userId]);
    return $stmt->fetchAll();
}

function fetch_all_tasks(int $tenantId): array
{
    $stmt = db()->prepare(
        'SELECT t.*, u1.name AS created_by_name, u2.name AS assigned_to_name, p.name AS project_name
         FROM tasks t
         JOIN users u1 ON u1.id = t.created_by
         JOIN users u2 ON u2.id = t.assigned_to
         LEFT JOIN projects p ON p.id = t.project_id
         WHERE t.tenant_id = :tenant_id
         ORDER BY t.created_at DESC'
    );
    $stmt->execute([':tenant_id' => $tenantId]);
    return $stmt->fetchAll();
}

function fetch_task(int $tenantId, int $taskId): ?array
{
    $stmt = db()->prepare(
        'SELECT t.*, u1.name AS created_by_name, u2.name AS assigned_to_name, p.name AS project_name
         FROM tasks t
         JOIN users u1 ON u1.id = t.created_by
         JOIN users u2 ON u2.id = t.assigned_to
         LEFT JOIN projects p ON p.id = t.project_id
         WHERE t.tenant_id = :tenant_id AND t.id = :id'
    );
    $stmt->execute([':tenant_id' => $tenantId, ':id' => $taskId]);
    $task = $stmt->fetch();
    return $task ?: null;
}

function create_task(int $tenantId, int $creatorId, int $assigneeId, ?int $projectId, string $title, string $description, string $priority, ?string $dueDate): int
{
    $stmt = db()->prepare(
        'INSERT INTO tasks (tenant_id, project_id, title, description, priority, due_date, created_by, assigned_to)
         VALUES (:tenant_id, :project_id, :title, :description, :priority, :due_date, :created_by, :assigned_to)'
    );
    $stmt->execute([
        ':tenant_id' => $tenantId,
        ':project_id' => $projectId,
        ':title' => $title,
        ':description' => $description,
        ':priority' => $priority,
        ':due_date' => $dueDate,
        ':created_by' => $creatorId,
        ':assigned_to' => $assigneeId,
    ]);

    return (int)db()->lastInsertId();
}

function mark_task_complete(int $tenantId, int $taskId, int $userId): void
{
    $stmt = db()->prepare(
        'UPDATE tasks
         SET status = "done", completed_at = datetime("now")
         WHERE id = :id AND assigned_to = :user_id AND tenant_id = :tenant_id'
    );
    $stmt->execute([':id' => $taskId, ':user_id' => $userId, ':tenant_id' => $tenantId]);
}

function update_task_admin(int $tenantId, int $taskId, int $assigneeId, ?int $projectId, string $title, string $description, string $status, string $priority, ?string $dueDate): void
{
    $status = in_array($status, ['open', 'in_progress', 'done'], true) ? $status : 'open';
    $priority = in_array($priority, ['low', 'medium', 'high'], true) ? $priority : 'medium';
    $completedAt = $status === 'done' ? date('Y-m-d H:i:s') : null;

    $stmt = db()->prepare(
        'UPDATE tasks
         SET title = :title,
             description = :description,
             assigned_to = :assigned_to,
             project_id = :project_id,
             status = :status,
             priority = :priority,
             due_date = :due_date,
             completed_at = :completed_at
         WHERE id = :id AND tenant_id = :tenant_id'
    );
    $stmt->execute([
        ':title' => $title,
        ':description' => $description,
        ':assigned_to' => $assigneeId,
        ':project_id' => $projectId,
        ':status' => $status,
        ':priority' => $priority,
        ':due_date' => $dueDate,
        ':completed_at' => $completedAt,
        ':id' => $taskId,
        ':tenant_id' => $tenantId,
    ]);
}

function delete_task(int $tenantId, int $taskId): void
{
    $stmt = db()->prepare('DELETE FROM tasks WHERE id = :id AND tenant_id = :tenant_id');
    $stmt->execute([':id' => $taskId, ':tenant_id' => $tenantId]);
}

function create_comment(int $taskId, int $userId, string $body): void
{
    $stmt = db()->prepare('INSERT INTO task_comments (task_id, user_id, body) VALUES (:task_id, :user_id, :body)');
    $stmt->execute([':task_id' => $taskId, ':user_id' => $userId, ':body' => $body]);
}

function fetch_comments(int $taskId): array
{
    $stmt = db()->prepare(
        'SELECT c.*, u.name AS author_name
         FROM task_comments c
         JOIN users u ON u.id = c.user_id
         WHERE c.task_id = :task_id
         ORDER BY c.created_at DESC'
    );
    $stmt->execute([':task_id' => $taskId]);
    return $stmt->fetchAll();
}

function log_activity(int $taskId, int $userId, string $action): void
{
    $stmt = db()->prepare('INSERT INTO task_activity (task_id, user_id, action) VALUES (:task_id, :user_id, :action)');
    $stmt->execute([':task_id' => $taskId, ':user_id' => $userId, ':action' => $action]);
}

function fetch_activity(int $taskId): array
{
    $stmt = db()->prepare(
        'SELECT a.*, u.name AS author_name
         FROM task_activity a
         JOIN users u ON u.id = a.user_id
         WHERE a.task_id = :task_id
         ORDER BY a.created_at DESC'
    );
    $stmt->execute([':task_id' => $taskId]);
    return $stmt->fetchAll();
}

function add_attachment(int $taskId, int $userId, string $fileName, string $filePath, int $fileSize): void
{
    $stmt = db()->prepare(
        'INSERT INTO task_attachments (task_id, user_id, file_name, file_path, file_size)
         VALUES (:task_id, :user_id, :file_name, :file_path, :file_size)'
    );
    $stmt->execute([
        ':task_id' => $taskId,
        ':user_id' => $userId,
        ':file_name' => $fileName,
        ':file_path' => $filePath,
        ':file_size' => $fileSize,
    ]);
}

function fetch_attachments(int $taskId): array
{
    $stmt = db()->prepare(
        'SELECT a.*, u.name AS uploader
         FROM task_attachments a
         JOIN users u ON u.id = a.user_id
         WHERE a.task_id = :task_id
         ORDER BY a.created_at DESC'
    );
    $stmt->execute([':task_id' => $taskId]);
    return $stmt->fetchAll();
}
