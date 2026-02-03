# TaskFlow (PHP + SQL)

A simple multi-user task management system with login, task assignment, and completion tracking.

## Features
- Multi-user registration and login (sessions)
- Assign tasks to any user
- Track tasks assigned to you and tasks you created
- Mark tasks complete

## Tech
- PHP (no framework required)
- SQLite (SQL database via PDO)

## Quick Start
1. Make sure PHP is installed (8.x recommended).
2. From this repo root, run:

```bash
php -S localhost:8000 -t public
```

3. Open http://localhost:8000
4. Create an account, then assign tasks to other users.

## Database
- The database auto-initializes on first run.
- SQLite file location: `storage/app.db`
- Schema: `database/schema.sql`

## Notes
- This is a minimal MVP intended for learning and iteration.
- If you want MySQL instead of SQLite, I can convert the schema and connection config.
