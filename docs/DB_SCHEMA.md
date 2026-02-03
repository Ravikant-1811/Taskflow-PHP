# Database Schema

## users
- `id` (PK)
- `name`
- `email` (unique)
- `password_hash`
- `role`
- `created_at`

## tasks
- `id` (PK)
- `title`
- `description`
- `status` (open/completed)
- `created_by` (FK -> users.id)
- `assigned_to` (FK -> users.id)
- `created_at`
- `completed_at`
