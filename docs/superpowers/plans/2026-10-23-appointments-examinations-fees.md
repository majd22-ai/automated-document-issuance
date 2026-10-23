# Requirement7 Implementation Plan

Execute inline with TDD and a focused independent review, using the user's supplied specification. Preserve the existing uncommitted implementation and auth/request approval boundaries.

## Design and constraints
- Existing fee/currency fields and immutable request snapshots are the sole fee definition; new request charge snapshots and append-only manual receipts use DECIMAL/string comparisons, unique request and receipt keys. No card data, gateway, refunds or invented amounts.
- New service-specific appointment slots and request bookings use owner→service→request→sorted slot locks; unique active request booking, capacity checks inside transactions. Only submitted/under_review/requires_correction requests can book/change. Citizen/employee scope follows current request view policy; admin configures service rules, employees operational slots/results/receipts.
- Examinations are request-scoped and entered by employees against trusted manual source references. Version token prevents stale updates. Prior versions are retained. Citizen cannot alter results or read private medical notes.
- Central RequestPrerequisites integrates stage-specific workflow_rules into existing ServiceEligibility/transitionTo, preserving default pre-submission medical checks unless an explicit later medical_stage is configured. Explicit stages permit creating a request before a request-scoped exam; no automatic pass for a missing exam at its required stage. Default payment stage for a positive recorded fee is processing; explicit rules can require it earlier. Zero fees must be explicitly recorded; unknown fees never become zero.
- All new operational actions preserve request state. Audit uses internal identifiers/actions, never personal/medical/payment contents. No external integrations or destructive migrations.

## Tasks
- [x] RED tests: owned appointment booking, past/full/duplicate/cross-service/foreign access and role boundaries.
- [x] Implement additive schema/models/policies, slot administration and transaction-safe booking/cancel/reschedule/state transitions.
- [x] RED tests: request-scoped manual exams, trusted source/dates/status/version protection, medical deferral and phase recheck.
- [x] Implement exam recording and existing evidence adapter integration.
- [x] RED tests: charge snapshot/idempotency, exact amount/reference collision, citizen spoofing, zero/unknown fees, stage blockers.
- [x] Implement charge/receipt service and central stage prerequisites; wire submit and transition hooks.
- [x] Add Arabic RTL citizen/employee/admin forms and existing route/view integration, no framework changes.
- [x] Run targeted + full SQLite/MySQL tests, actual booking/submission concurrency tests, Pint, Blade and frontend checks. Apply additive migration, document actual results and limits, independent review.

Ruling: accepted/approved requests may still complete operational prerequisites before processing; closed/processing requests cannot mutate operations. Booking/financial/exam actions do not grant request transition authority.
Verification: full SQLite116/848 and MySQL116/848; targeted18/115 both; real concurrency4/23; frontend5; Blade compiled. Migrations applied; main1user/7services/0requests and no fabricated operations. Focused independent review's stale-medical-policy finding fixed with RED-GREEN regression. Run full suites sequentially because pre-existing Storage::fake folder is shared; concurrent suite execution caused test-file collisions, resolved by serial verification. Detailed report docs/appointments-examinations-fees.md.
