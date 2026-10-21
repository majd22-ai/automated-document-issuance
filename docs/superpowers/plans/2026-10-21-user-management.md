# User Management and Authentication Implementation Plan

Implement the user's first functional requirement in the existing Laravel 12 session application. Preserve the public pages, forms, existing route names and national-ID/username login. Do not implement document requests.

- [ ] Inspect actual schema and runtime: Laravel 12.65.0, PHP 8.2.12; SQLite currently has zero users and lacks username/role despite the original migration. No Breeze or Sanctum installed.
- [ ] Add feature tests for registration, hashing, validation, login/logout, disabled accounts, session/role access, self-only profile updates, admin-only role/status changes, redirects and public-page regression. Run them before implementation.
- [ ] Add a non-destructive migration that adds only missing username, role, phone and is_active columns. Keep the original users migration and unique email index.
- [ ] Extract current registration/login/logout handlers into an authentication controller, validate allowlists, force citizen/active registration, throttle attempts, preserve sessions and local redirects only.
- [ ] Add active-account middleware and role middleware to existing route groups. Add self-only profile GET/PATCH and protected admin account listing/PATCH.
- [ ] Preserve existing Blade form styling; add optional phone, profile form and usable admin account controls, plus profile/logout navigation for authenticated users.
- [ ] Run migrations and all tests on isolated in-memory SQLite. Configure and verify MySQL only after the target connection is established; never run migrate:fresh or remove existing database data.
- [ ] Verify HTTP pages, routes, template compilation and formatter. Document actual database choice, migration/test commands and remaining limitations.

Implementation is inline in the shared working tree as explicitly requested. Unrelated existing UI changes are preserved. No new packages are needed. Production database credentials must not appear in logs or documentation.
