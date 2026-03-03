# AppFlowy-Inspired Restructure (TaskFlow PHP)

Reference source cloned locally:
- `/Users/ravikantupadhyay/Documents/New project/references/AppFlowy`
- commit: `bbe886f`

## Patterns adopted
- Workspace-first navigation with persistent left sidebar
- Multi-page work OS layout (overview, inbox, starred, tasks, projects, people, reports, time)
- Board + list task views
- Quick-capture task form in board module
- Focus workflow: inbox + starred queue

## Current implementation in this repository
- `public/portal.php`
- `public/portal-inbox.php`
- `public/portal-starred.php`
- `public/portal-tasks.php`
- `public/portal-projects.php`
- `public/portal-people.php`
- `public/portal-reports.php`
- `public/portal-time.php`
- `public/partials/portal_shell_start.php`
- `public/partials/portal_shell_end.php`

## Data model additions
- `starred_tasks` table via runtime migration in `app/db.php`
- helper methods in `app/tasks.php`:
  - `star_task`
  - `unstar_task`
  - `fetch_starred_task_ids`
  - `fetch_starred_tasks`

## Remaining gap vs AppFlowy (future)
- Real-time collaborative editing
- Rich block editor/documents database
- plugin architecture and offline sync
- desktop/mobile clients
