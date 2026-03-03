# TaskFlow PHP (AppFlowy-Inspired Company Work OS)

TaskFlow is a multi-tenant company task management system built with PHP + SQLite.
It includes role-based access, team/project management, task workflow, daily reporting, HR tools, and an AppFlowy-inspired workspace UI.

## Core Features
- Multi-company tenancy (company code / slug based login)
- Roles: `admin`, `manager`, `user`
- Teams and team membership
- Projects (with optional team scope)
- Task workflow: `open -> in_progress -> done`
- Task priority + due dates
- Task comments, activity log, attachments
- Daily reports (time, summary, blockers, next plan)
- HR module (employee profile + leave requests)
- AI assistant integration (OpenAI key based)

## AppFlowy-Inspired Portal (v2)
Unified workspace with left sidebar + role-aware modules:
- `Overview` → `/portal.php`
- `Inbox` → `/portal-inbox.php`
- `Starred` → `/portal-starred.php`
- `Tasks` (Board + List) → `/portal-tasks.php`
- `Projects` → `/portal-projects.php`
- `People` (Users + Teams) → `/portal-people.php`
- `Reports` → `/portal-reports.php`
- `Time & Daily` → `/portal-time.php`

## Tech Stack
- PHP 8+
- SQLite (PDO)
- Plain CSS/HTML/JS (no heavy framework)

## Run Locally
1. Install PHP 8+.
2. Start server from repo root:

```bash
php -S localhost:8000 -t public
```

3. Open [http://localhost:8000](http://localhost:8000)
4. Register your company (example company code: `acme-co`)
5. First user becomes `admin` for that tenant.

## OpenAI Setup (Optional)
Set your API key before starting server:

```bash
export OPENAI_API_KEY="your_api_key_here"
php -S localhost:8000 -t public
```

## Database
- Auto-initializes on first run
- Main DB file: `storage/app.db`
- Base schema: `database/schema.sql`
- Runtime migrations are applied in `app/db.php`

## Reset Database

```bash
php scripts/reset_db.php
```

## Demo Login (if seeded manually)
You can create accounts from UI or seed manually via DB/scripts.

## Repository
- GitHub: [https://github.com/Ravikant-1811/Taskflow-PHP](https://github.com/Ravikant-1811/Taskflow-PHP)

## Notes
- This is production-style structure for SMB/company use, but still lightweight for easy customization.
- For enterprise-scale real-time collaboration (Notion/AppFlowy parity), next step is full realtime backend + rich editor service.
