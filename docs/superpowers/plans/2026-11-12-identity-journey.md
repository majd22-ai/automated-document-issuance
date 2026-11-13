# Identity journey implementation plan

> **For agentic workers:** Use superpowers:executing-plans to implement task by task. The user approved the design and explicitly requested continued execution.

**Goal:** Connect first identity issuance from preliminary acceptance through local postal payment, one staff appointment and visit, final approval and documented delivery.
**Architecture:** Version new identity forms; preserve legacy paths. Reuse charges, appointments, private attachments, fingerprint attempts, review decisions and document lifecycle. Add only postal collections and versioned visit evidence.
**Tech Stack:** Laravel, Blade RTL, SQLite isolated tests; MySQL concurrency only on an explicitly isolated testing database.
**Spec:** docs/identity-journey-design.md

## Global constraints

No production database writes, migrations or seeders; no .env/credentials edits; no real government integration or biometric templates; retain existing work and historical records. Native execution in the current dirty workspace preserves the user's uncommitted prerequisites; no broad commits or worktree copying that could lose them.

## Review focus

Duplicate collection after lost response; forged paid status; correction after approval/issuance; bypass through generic endpoints; accidental application to old requests or driving services. Each is covered in the journey tests and regression suite.

## Tasks

- [x] 1. Add isolated failing tests (`tests/Feature/IdentityJourneyTest.php`) for versioning, acceptance/invoice, payment proof/replay, appointment barriers and private evidence. Add additive migration, PostalCollection and IdentityVisitEvidence models, and `IdentityJourney::applies(CitizenServiceRequest): bool` / `paid(CitizenServiceRequest): bool`. Mark new forms before submission; defer charge only for that path.
- [x] 2. Add `PostalCollections::collect(PostalCollector, RequestCharge, array): PostalCollection`, atomic receipt/audit/notifications, postal lookup and confirmation routes/views; reuse postal session restrictions. Verify failure/retry and government permission separation.
- [x] 3. Integrate preliminary acceptance/invoice with transitionTo; stage eligibility and fingerprint requirements correctly. Reuse AppointmentBookings, enforce staff-only paid scheduling for every endpoint; notify with external blood-result instructions.
- [x] 4. Add private visit evidence uploads/review and camera UI to unified transaction view, versioned witnesses exception, payment/appointment/arrival gates; integrate administrative decisions with request approval/correction and preserve references.
- [x] 5. Require final approval/visit evidence at issuance, freeze card photo in document snapshot and require delivery before completion. Expose links/actions by current permissions, keep admin grant UI explicit.
- [x] 6. Run journey and legacy regression tests, PHP/Blade/frontend checks, independent final review; write docs/identity-journey-implementation.md with exact results, migration rollout and limitations.

## Execution ledger

2026-11-12: Design approved. Prior recorded baseline 58 tests/686 assertions; fresh focused runs will be recorded during implementation. No production database connection is needed. No .env reads/writes needed.

2026-11-12: Tasks 1–5 implemented. Initial RED: four missing journey_version schema errors. GREEN: focused regression 135 tests/1490 assertions; later identity/generic/tracking 55/360; final postal HTTP and attachment/requirements 57/343. Independent review finding on approved pre-issuance correction fixed; HTTP actor namespace defect fixed and reviewed. Full final regression and report verification in progress. No production migrations, seeders, connection changes or .env modifications.

2026-11-12: Task 6 complete. Final focused: 92 tests/628 assertions PASS; latest journey-only: 23/138 PASS. Full run 521/5593 had 7 failures and 1 skipped: 2 Blade failures fixed and verified in 92-test focused run; 5 prior unrelated failures documented, no claim of full-suite green. Frontend: 52 pass/1 existing mock failure/1 skipped. Independent source review and followups: no remaining significant finding. Report: docs/identity-journey-implementation.md. Remaining manual deployment: reviewed migration and operator setup, not authorized for execution here. No production DB writes or .env edits.
