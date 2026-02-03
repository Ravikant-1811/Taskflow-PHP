# API (Page Endpoints)

## Public
- `GET /login.php`
- `POST /login.php`
- `GET /register.php`
- `POST /register.php`

## Authenticated
- `GET /dashboard.php`
- `POST /dashboard.php` (create / complete)
- `GET /logout.php`

## Actions (POST /dashboard.php)
- `action=create`
  - `title`, `description`, `assigned_to`
- `action=complete`
  - `task_id`
