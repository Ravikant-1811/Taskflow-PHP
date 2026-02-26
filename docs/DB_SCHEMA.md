# Database Schema

## tenants
- `id` (PK)
- `name`
- `slug` (unique)
- `created_at`

## users
- `id` (PK)
- `tenant_id` (FK)
- `name`
- `email` (unique per tenant)
- `password_hash`
- `role` (admin/manager/user)
- `created_at`

## teams
- `id` (PK)
- `tenant_id` (FK)
- `name`
- `created_at`

## team_members
- `team_id` (FK)
- `user_id` (FK)
- `role`
- `created_at`

## projects
- `id` (PK)
- `tenant_id` (FK)
- `team_id` (FK optional)
- `name`
- `description`
- `status`
- `created_at`

## tasks
- `id` (PK)
- `tenant_id` (FK)
- `project_id` (FK optional)
- `title`
- `description`
- `status` (open/in_progress/done)
- `priority` (low/medium/high)
- `due_date`
- `created_by` (FK users)
- `assigned_to` (FK users)
- `created_at`
- `completed_at`

## task_comments
- `id` (PK)
- `task_id` (FK)
- `user_id` (FK)
- `body`
- `created_at`

## task_activity
- `id` (PK)
- `task_id` (FK)
- `user_id` (FK)
- `action`
- `created_at`

## task_attachments
- `id` (PK)
- `task_id` (FK)
- `user_id` (FK)
- `file_name`
- `file_path`
- `file_size`
- `created_at`

## daily_reports
- `id` (PK)
- `tenant_id` (FK)
- `user_id` (FK)
- `report_date` (unique per user per date)
- `start_time`
- `end_time`
- `total_hours`
- `work_summary`
- `blockers`
- `next_plan`
- `status`
- `created_at`
- `updated_at`

## employee_profiles
- `id` (PK)
- `tenant_id` (FK)
- `user_id` (FK, unique)
- `department`
- `designation`
- `employment_type`
- `location`
- `manager_user_id` (FK users, nullable)
- `joining_date`
- `weekly_hour_target`
- `created_at`
- `updated_at`

## leave_requests
- `id` (PK)
- `tenant_id` (FK)
- `user_id` (FK)
- `leave_type`
- `start_date`
- `end_date`
- `total_days`
- `reason`
- `status` (pending/approved/rejected)
- `decided_by` (FK users, nullable)
- `decided_at`
- `created_at`
