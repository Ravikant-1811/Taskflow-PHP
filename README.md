# TaskFlow (Company Task Management)

A multi-tenant company task management tool for remote teams with roles, teams, projects, task workflow, daily reports, comments, attachments, and reporting.

## Features
- Multi-company (tenants) with company code login
- Roles: Admin, Manager, User
- Teams + team membership
- Projects with optional team assignment
- Task workflow: Open → In Progress → Done
- Priorities and due dates
- Employee daily report (time + summary + blockers + next plan)
- Manager and admin daily report review dashboards
- HR module (employee profiles + leave requests)
- OpenAI-powered assistant (task planning, daily draft, HR guidance)
- Comments, attachments, and activity log
- Admin/Manager dashboard with reporting

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
4. Register a company using a **company code** (e.g. `acme-co`).
5. The first account becomes **Admin** for that company.

## OpenAI Setup
Set your API key before starting the server:

```bash
export OPENAI_API_KEY=\"your_api_key_here\"
php -S localhost:8000 -t public
```

## Database
- The database auto-initializes on first run.
- SQLite file location: `storage/app.db`
- Schema: `database/schema.sql`

### Resetting the database (new schema)
If you previously ran the old schema, reset the DB:

```bash
php scripts/reset_db.php
```

## Notes
- This is a minimal MVP intended for learning and iteration.
- If you want MySQL instead of SQLite, I can convert the schema and connection config.
