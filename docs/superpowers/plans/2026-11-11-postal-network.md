# Postal simulation phase 1 implementation plan

**Goal:** Three clearly synthetic Sana'a offices with isolated collector identities and administrator management.

**Architecture:** Add `postal_offices` and `postal_collectors`; use a separate session guard/provider. Keep existing users, roles and review grants unchanged. The `can_collect` capability is preparatory only; no payment functionality exists in this phase. Disabled by default, local/testing environments only.

**Tech stack:** Existing Laravel 12/PHP/MySQL; isolated SQLite in-memory feature tests.

**Spec:** User's phase 1 request in this session. No real database migration or secret environment edits authorized.

## Constraints and inspection

- Existing user roles: citizen, employee, admin; review permissions refer to users.
- No postal models/migrations found. Read-only MySQL inspection attempted, connection refused (2002); actual deployed schema remains unverified. Reinspect before approved deployment.
- Existing login audit listeners require User, so filter them to the web guard before adding another identity provider.
- No invoices, payments, receipts, fee changes or card workflow modifications.

## Tasks

- [x] Write/run failing tests for feature disablement, account/office relationships, safe seeding, administrator management and collector isolation.
- [x] Add additive migration, models, configuration and safe explicit seeder. Refuse table collisions before creating either table. Never auto-register seeder in DatabaseSeeder.
- [x] Add feature middleware, independent login/session revocation, administrator validation and RTL views. Preserve current government authentication behavior.
- [x] Run isolated feature/regression tests and PHP formatting/syntax checks. Review diff for credentials and unintended changes.
- [x] Document activation, account provisioning, limitations and real-DB approval requirement; stop after report.

## Execution record

- Initial regression run failed because routes/seeder were absent; implementation then passed the targeted tests.
- Added password byte-length and no-store regressions; observed both fail, implemented them, and reran successfully.
- Targeted verification: 54 tests / 1109 assertions. Full suite: 492 tests / 5421 assertions, 5 known failures outside postal, 1 skipped; detailed in `docs/postal-network-phase-one.md`.
- Ruling: Keep actual MySQL changes pending explicit approval because the read-only connection failed (2002); verify migrations only in memory now. This leaves actual provisioning and MySQL verification pending, as requested.

## Review focus

- Disabled feature must not query missing tables.
- Postal authentication must never authenticate web users or enter the government audit with a postal identity.
- Disabling a collector/office, moving an account or rotating its password must revoke existing postal sessions.
- Rerunning fixtures must neither duplicate nor reset manually changed accounts; missing credentials must not create partial fixtures.
- Unique constraints and foreign keys protect office/account identity; all offices are explicitly experimental.
