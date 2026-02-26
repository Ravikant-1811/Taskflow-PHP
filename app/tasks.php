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

function remove_team_member(int $teamId, int $userId): void
{
    $stmt = db()->prepare('DELETE FROM team_members WHERE team_id = :team_id AND user_id = :user_id');
    $stmt->execute([':team_id' => $teamId, ':user_id' => $userId]);
}

function delete_team(int $tenantId, int $teamId): void
{
    $stmt = db()->prepare('DELETE FROM teams WHERE id = :id AND tenant_id = :tenant_id');
    $stmt->execute([':id' => $teamId, ':tenant_id' => $tenantId]);
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

function fetch_team_ids_for_user(int $userId): array
{
    $stmt = db()->prepare('SELECT team_id FROM team_members WHERE user_id = :user_id');
    $stmt->execute([':user_id' => $userId]);
    return array_map(fn($row) => (int)$row['team_id'], $stmt->fetchAll());
}

function fetch_users_for_teams(int $tenantId, array $teamIds): array
{
    if (empty($teamIds)) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($teamIds), '?'));
    $sql = "SELECT DISTINCT u.id, u.name, u.email
            FROM users u
            JOIN team_members tm ON tm.user_id = u.id
            WHERE u.tenant_id = ?
              AND tm.team_id IN ($placeholders)
            ORDER BY u.name";
    $stmt = db()->prepare($sql);
    $stmt->execute(array_merge([$tenantId], $teamIds));
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

function fetch_tasks_for_assignees(int $tenantId, array $assigneeIds): array
{
    if (empty($assigneeIds)) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($assigneeIds), '?'));
    $sql = "SELECT t.*, u1.name AS created_by_name, u2.name AS assigned_to_name, p.name AS project_name
            FROM tasks t
            JOIN users u1 ON u1.id = t.created_by
            JOIN users u2 ON u2.id = t.assigned_to
            LEFT JOIN projects p ON p.id = t.project_id
            WHERE t.tenant_id = ?
              AND t.assigned_to IN ($placeholders)
            ORDER BY t.created_at DESC";
    $stmt = db()->prepare($sql);
    $stmt->execute(array_merge([$tenantId], $assigneeIds));
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

function upsert_daily_report(
    int $tenantId,
    int $userId,
    string $reportDate,
    ?string $startTime,
    ?string $endTime,
    float $totalHours,
    string $workSummary,
    string $blockers,
    string $nextPlan
): void {
    $stmt = db()->prepare(
        'INSERT INTO daily_reports (
            tenant_id, user_id, report_date, start_time, end_time, total_hours,
            work_summary, blockers, next_plan, status, created_at, updated_at
        ) VALUES (
            :tenant_id, :user_id, :report_date, :start_time, :end_time, :total_hours,
            :work_summary, :blockers, :next_plan, "submitted", datetime("now"), datetime("now")
        )
        ON CONFLICT(user_id, report_date) DO UPDATE SET
            start_time = excluded.start_time,
            end_time = excluded.end_time,
            total_hours = excluded.total_hours,
            work_summary = excluded.work_summary,
            blockers = excluded.blockers,
            next_plan = excluded.next_plan,
            status = "submitted",
            updated_at = datetime("now")'
    );
    $stmt->execute([
        ':tenant_id' => $tenantId,
        ':user_id' => $userId,
        ':report_date' => $reportDate,
        ':start_time' => $startTime,
        ':end_time' => $endTime,
        ':total_hours' => $totalHours,
        ':work_summary' => $workSummary,
        ':blockers' => $blockers,
        ':next_plan' => $nextPlan,
    ]);
}

function fetch_daily_report_for_user_date(int $tenantId, int $userId, string $reportDate): ?array
{
    $stmt = db()->prepare(
        'SELECT *
         FROM daily_reports
         WHERE tenant_id = :tenant_id AND user_id = :user_id AND report_date = :report_date'
    );
    $stmt->execute([
        ':tenant_id' => $tenantId,
        ':user_id' => $userId,
        ':report_date' => $reportDate,
    ]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function fetch_daily_reports_for_tenant(int $tenantId, string $reportDate): array
{
    $stmt = db()->prepare(
        'SELECT dr.*, u.name AS user_name, u.email AS user_email, u.role AS user_role
         FROM daily_reports dr
         JOIN users u ON u.id = dr.user_id
         WHERE dr.tenant_id = :tenant_id
           AND dr.report_date = :report_date
         ORDER BY u.name'
    );
    $stmt->execute([
        ':tenant_id' => $tenantId,
        ':report_date' => $reportDate,
    ]);
    return $stmt->fetchAll();
}

function fetch_daily_reports_for_users(array $userIds, string $reportDate): array
{
    if (empty($userIds)) {
        return [];
    }
    $placeholders = implode(',', array_fill(0, count($userIds), '?'));
    $sql = "SELECT dr.*, u.name AS user_name, u.email AS user_email, u.role AS user_role
            FROM daily_reports dr
            JOIN users u ON u.id = dr.user_id
            WHERE dr.user_id IN ($placeholders)
              AND dr.report_date = ?
            ORDER BY u.name";
    $stmt = db()->prepare($sql);
    $stmt->execute(array_merge($userIds, [$reportDate]));
    return $stmt->fetchAll();
}

function calculate_hours_from_times(?string $startTime, ?string $endTime): float
{
    if (!$startTime || !$endTime) {
        return 0.0;
    }
    $start = strtotime($startTime);
    $end = strtotime($endTime);
    if ($start === false || $end === false || $end <= $start) {
        return 0.0;
    }
    return round(($end - $start) / 3600, 2);
}

function upsert_employee_profile(
    int $tenantId,
    int $userId,
    string $department,
    string $designation,
    string $employmentType,
    string $location,
    ?int $managerUserId,
    ?string $joiningDate,
    float $weeklyHourTarget
): void {
    $stmt = db()->prepare(
        'INSERT INTO employee_profiles (
            tenant_id, user_id, department, designation, employment_type, location,
            manager_user_id, joining_date, weekly_hour_target, created_at, updated_at
        ) VALUES (
            :tenant_id, :user_id, :department, :designation, :employment_type, :location,
            :manager_user_id, :joining_date, :weekly_hour_target, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
        )
        ON CONFLICT(user_id) DO UPDATE SET
            department = excluded.department,
            designation = excluded.designation,
            employment_type = excluded.employment_type,
            location = excluded.location,
            manager_user_id = excluded.manager_user_id,
            joining_date = excluded.joining_date,
            weekly_hour_target = excluded.weekly_hour_target,
            updated_at = CURRENT_TIMESTAMP'
    );
    $stmt->execute([
        ':tenant_id' => $tenantId,
        ':user_id' => $userId,
        ':department' => $department,
        ':designation' => $designation,
        ':employment_type' => $employmentType,
        ':location' => $location,
        ':manager_user_id' => $managerUserId,
        ':joining_date' => $joiningDate,
        ':weekly_hour_target' => $weeklyHourTarget,
    ]);
}

function fetch_employee_profiles(int $tenantId): array
{
    $stmt = db()->prepare(
        'SELECT ep.*, u.name AS user_name, u.email AS user_email, u.role AS user_role, m.name AS manager_name
         FROM employee_profiles ep
         JOIN users u ON u.id = ep.user_id
         LEFT JOIN users m ON m.id = ep.manager_user_id
         WHERE ep.tenant_id = :tenant_id
         ORDER BY u.name'
    );
    $stmt->execute([':tenant_id' => $tenantId]);
    return $stmt->fetchAll();
}

function fetch_employee_profile_for_user(int $tenantId, int $userId): ?array
{
    $stmt = db()->prepare(
        'SELECT ep.*, u.name AS user_name, u.email AS user_email, u.role AS user_role, m.name AS manager_name
         FROM employee_profiles ep
         JOIN users u ON u.id = ep.user_id
         LEFT JOIN users m ON m.id = ep.manager_user_id
         WHERE ep.tenant_id = :tenant_id AND ep.user_id = :user_id'
    );
    $stmt->execute([':tenant_id' => $tenantId, ':user_id' => $userId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function create_leave_request(
    int $tenantId,
    int $userId,
    string $leaveType,
    string $startDate,
    string $endDate,
    string $reason
): void {
    $days = max(1.0, (float)((strtotime($endDate) - strtotime($startDate)) / 86400 + 1));
    $stmt = db()->prepare(
        'INSERT INTO leave_requests (
            tenant_id, user_id, leave_type, start_date, end_date, total_days, reason, status, created_at
        ) VALUES (
            :tenant_id, :user_id, :leave_type, :start_date, :end_date, :total_days, :reason, "pending", CURRENT_TIMESTAMP
        )'
    );
    $stmt->execute([
        ':tenant_id' => $tenantId,
        ':user_id' => $userId,
        ':leave_type' => $leaveType,
        ':start_date' => $startDate,
        ':end_date' => $endDate,
        ':total_days' => $days,
        ':reason' => $reason,
    ]);
}

function fetch_leave_requests_for_tenant(int $tenantId, ?string $status = null): array
{
    $sql = 'SELECT lr.*, u.name AS user_name, u.email AS user_email, d.name AS decided_by_name
            FROM leave_requests lr
            JOIN users u ON u.id = lr.user_id
            LEFT JOIN users d ON d.id = lr.decided_by
            WHERE lr.tenant_id = :tenant_id';
    if ($status !== null && $status !== '') {
        $sql .= ' AND lr.status = :status';
    }
    $sql .= ' ORDER BY lr.created_at DESC';
    $stmt = db()->prepare($sql);
    $params = [':tenant_id' => $tenantId];
    if ($status !== null && $status !== '') {
        $params[':status'] = $status;
    }
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function fetch_leave_requests_for_users(array $userIds): array
{
    if (empty($userIds)) {
        return [];
    }
    $placeholders = implode(',', array_fill(0, count($userIds), '?'));
    $sql = "SELECT lr.*, u.name AS user_name, u.email AS user_email, d.name AS decided_by_name
            FROM leave_requests lr
            JOIN users u ON u.id = lr.user_id
            LEFT JOIN users d ON d.id = lr.decided_by
            WHERE lr.user_id IN ($placeholders)
            ORDER BY lr.created_at DESC";
    $stmt = db()->prepare($sql);
    $stmt->execute($userIds);
    return $stmt->fetchAll();
}

function update_leave_request_status(int $tenantId, int $leaveId, string $status, int $decidedByUserId): void
{
    $safeStatus = in_array($status, ['approved', 'rejected', 'pending'], true) ? $status : 'pending';
    $stmt = db()->prepare(
        'UPDATE leave_requests
         SET status = :status,
             decided_by = :decided_by,
             decided_at = CURRENT_TIMESTAMP
         WHERE id = :id AND tenant_id = :tenant_id'
    );
    $stmt->execute([
        ':status' => $safeStatus,
        ':decided_by' => $decidedByUserId,
        ':id' => $leaveId,
        ':tenant_id' => $tenantId,
    ]);
}
