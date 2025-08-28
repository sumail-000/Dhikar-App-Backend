# WARP.md

This file provides guidance to WARP (warp.dev) when working with code in this repository.

Repository overview
- Stack: Laravel 12 (PHP >= 8.2) with Sanctum for token-based auth. API-only backend; no SPA build pipeline required.
- Data model (high-level):
  - Users: local auth; optional avatar stored on the public disk.
  - Groups: created by a user; can be public/private; types include "khitma" (primary) and "dhikr".
  - Memberships: users join groups; roles are admin/member.
  - Invites: join via time-limited token.
  - Khitma assignments: for khitma groups, 30 Juz entries are pre-seeded and assigned automatically or manually.
- Important behavior:
  - Auth: register/login issue Sanctum tokens; protected routes use auth:sanctum.
  - Password reset: custom 6-digit code flow using password_reset_codes table and an email (logged by default).
  - Storage: user avatars saved to storage/app/public and served via public/storage.

Common commands
Setup (first run)
```bash path=null start=null
composer install
cp .env.example .env
php artisan key:generate
# If using SQLite (default), create the DB file:
# macOS/Linux:
touch database/database.sqlite
# Windows (PowerShell):
New-Item -ItemType File -Path database/database.sqlite -Force | Out-Null
php artisan migrate
# Enable public storage symlink for avatars
php artisan storage:link
```

Run the API locally
```bash path=null start=null
php artisan serve
# Optional multi-process dev harness (server, queue, logs; requires Node for npx):
composer run dev
```

Testing
```bash path=null start=null
# All tests
php artisan test

# Single file
php artisan test tests/Feature/ExampleTest.php

# Filter by test class or method name (regex)
php artisan test --filter AuthController
php artisan test --filter "AuthController::test_user_can_log_in"

# Using phpunit directly
php vendor/bin/phpunit --filter AuthController
```

Linting / formatting (Laravel Pint)
```bash path=null start=null
# Check only (CI-friendly)
php vendor/bin/pint --test
# Auto-fix
php vendor/bin/pint
```

Database maintenance
```bash path=null start=null
# Fresh DB and re-run migrations (destructive)
php artisan migrate:fresh
# Seed (if/when DatabaseSeeder is populated)
php artisan db:seed
```

Queues and logs (optional)
```bash path=null start=null
# Database-backed queue worker (QUEUE_CONNECTION=database)
php artisan queue:listen --tries=1
# Tail application logs interactively
php artisan pail --timeout=0
```

Architecture overview (big picture)
- Routing: All public API endpoints live in routes/api.php and are auto-prefixed with /api.
  - Health/info: GET /api returns status/app/version JSON.
  - Auth (public): POST /api/auth/register, /api/auth/login, password reset request/verify/reset.
  - Authenticated (auth:sanctum): user profile, group and khitma operations.
- Controllers:
  - AuthController: registration/login, token lifecycle (create on login, revoke on logout), account deletion flow with password verification.
  - PasswordResetController: 6-digit OTP via email; codes stored in password_reset_codes with 10-minute expiry; reset invalidates code.
  - ProfileController: update name/username and avatar upload; avatars live on public disk; deleteAvatar removes stored file and clears path.
  - GroupController: create/list/show groups, invite tokens (time-bound), join/leave/remove member, and khitma assignment flows:
    - Creation (khitma): seeds 30 unassigned rows (Juz 1..30).
    - Auto-assign: round-robin assignments across members.
    - Manual-assign: admin provides user_id + juz_numbers; validated for uniqueness per request.
    - Update assignment: owner or admin can update status and pages_read; only admins can unassign.
- Models and relations (Eloquent):
  - User: standard auth model with HasApiTokens; password hashed cast.
  - Group: belongsTo creator (User); hasMany members, assignments, invites; helper isAdmin(User) checks ownership or admin membership.
  - GroupMember: belongsTo Group and User; role enum admin|member; joined_at timestamp.
  - InviteToken: belongsTo Group; static generateForGroup creates a 48-char token with optional expiry.
  - KhitmaAssignment: belongsTo Group and (optional) User; fields juz_number (1..30), status enum unassigned|assigned|completed, pages_read.
- Persistence:
  - Migrations define groups, group_members (unique group_id+user_id), invite_tokens (unique token), khitma_assignments (unique per group+juz), and password_reset_codes.
  - Default DB is SQLite (database/database.sqlite) as in .env.example; switch via DB_CONNECTION/host creds if needed.
- AuthN/AuthZ:
  - Sanctum bearer tokens (no expiry by default). Include Authorization: Bearer <token> on protected routes.
  - Sanctum stateful domains configured for common localhost patterns; primarily relevant if later paired with a SPA.
- Mail:
  - MAIL_MAILER=log by default; reset codes are logged rather than actually sent.
- I18n:
  - Validation and UI strings in lang/en/messages.php and lang/ar/messages.php; controllers reference keys like messages.username_invalid.

Endpoint usage notes
- Base URL: during local dev, php artisan serve defaults to http://127.0.0.1:8000; APIs are under /api.
- Authentication flow:
  1) POST /api/auth/register → returns user + token.
  2) Subsequently send Authorization: Bearer <token> for protected endpoints.
- Password reset flow (public):
  1) POST /api/password/forgot with email → generates code and logs an email.
  2) POST /api/password/verify with email + code → validates code.
  3) POST /api/password/reset with email + code + password + password_confirmation → updates password and invalidates code.

Notes for future changes
- If you enable real email delivery, update MAIL_* in .env, switch MAIL_MAILER from log, and ensure queue worker runs if you queue mails.
- For avatars to resolve under public/storage, keep the storage symlink current (php artisan storage:link) and use the public disk.

