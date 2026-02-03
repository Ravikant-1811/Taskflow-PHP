# TaskFlow Complete Process

## 1. User Authentication
- User registers with name, email, password.
- Password is hashed and stored.
- Login validates credentials and opens a session.

## 2. Task Creation & Assignment
- Logged-in user creates a task.
- Selects another user as assignee.
- Task stored with `created_by` and `assigned_to`.

## 3. Task Execution
- Assigned user sees task under “Your Assigned Tasks.”
- User clicks “Mark complete.”

## 4. Completion Tracking
- Task status becomes `completed`.
- `completed_at` timestamp is recorded.
- Creator sees completion in “Tasks You Created.”

## 5. Security
- CSRF tokens on all forms.
- Session-based authentication.
- Input validation and escaping.
