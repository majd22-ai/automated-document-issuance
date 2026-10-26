# Employee management, oversight and reporting implementation plan

**Goal:** Implement requirement 12 in the current Laravel 12/PHP 8.2 application without replacing authentication or per-service permissions.
**Spec:** User attachment `004d087b-fe87-466f-b4f0-d88e5b9ff0e2/Pasted text.txt`.
**Architecture:** Existing users retain citizen/employee/admin roles. Admin alone manages employees/departments and report grants. Explicit department-report permission allows an employee only aggregate reports for their active department; export is a separate grant. No department manager account-management privilege is implied. Use canonical tables for metrics and a new safe append-only audit journal for administrative/operational events. CSV streams aggregates with formula protection, no public files. Password resets use Laravel broker and configured non-log mail, not administrator-visible passwords.
**Constraints:** Additive schema only; no fictitious SLA, payments or historical audit; no requirement 13; no new packages. Preserve existing dirty changes. No cache for sensitive scopes. Request cohort is created_at, closures are completed_at, payments paid_at, issuance issued_recorded_at, attempts started_at; clearly separate each denominator. Department scope for operational data uses current assignment/current actor membership and must be labelled as such; journal captures actor department at event time. Missing SLA is unavailable, not zero delay. Historical activity cannot be reconstructed as new audit events.

- [x] Add failing employee/department/security/reset tests, then additive schema, protected models, Form Requests, controllers and real Arabic views.
- [x] Add failing central audit/auth/transaction/redaction tests, then observer/listener audit integration with immutable outcomes and safe allowlists.
- [x] Add failing report/cohort/currency/dedup/scope/export tests, then aggregate service, filters, server authorization and CSV streaming.
- [x] Replace dashboard placeholders; link existing grants and reports; document formulas, scope and external-mail requirements.
- [x] Run focused/full SQLite and MySQL tests sequentially, integration concurrency, Pint/Blade/JS/diff checks, independent review; apply additive migration with before/after counts.

Review focus: cross-department filter tampering; role/grant changes through old user/profile routes; zero rows and division by zero; duplicate relationships and mixed currencies; passwords/tokens/private notes in journal or CSV; disabled actor/session; report scope revocation at download; transaction rollback leaves no successful audit event.


Completed verification: SQLite/MySQL each 236 tests, 1638 assertions; isolated MySQL concurrency 14 tests,84 assertions; JS5; Pint/Blade/routes/diff passed. Additive main migration applied unchanged business counts 1 user/7 services/0 requests-documents-notifications-integrationattempts; zero departments/report grants. Independent review fixes have RED/GREEN regressions (charge date scoping, legacy sessions, description-only audit). No real mail or external provider configured. Full Arabic report docs/employee-management-and-reporting.md.
