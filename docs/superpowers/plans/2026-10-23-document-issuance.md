# Requirement 9 — implementation plan and design

User explicitly requested implementation of the supplied detailed specification, inline in the existing workspace. Preserve preceding requirements and unrelated work. No additional approval gate or worktree that would lose the uncommitted integrated features.

## Design

Reuse CitizenDocument, ApprovalReadiness, ServiceEligibility, shared owner→service→request locks, scoped employee grants and immutable ReviewDecision. Add can_issue/can_deliver to the existing grant table; default deny. Keep admin role for configuring policy, not issuing by default.

Add nullable historical links and issuance metadata to citizen_documents. Global unique document_number is added only after non-destructive duplicate preflight; existing collisions produce an actionable failure, never data deletion. Unique request link and unique previous_document_id enforce one issuance per request and one successor per prior document. Internal numbers always start INT, optional configured prefix/year, then ULID; no claimed official numbering.

Trusted per-service issuance_rules: enabled, source_reference; expiry.required and explicit validity_days if required; number_prefix/year; electronic_copy internal_html or none; citizen_download; delivery.methods pickup/address and explicit pickup centers; completion_stage issuance/delivery. Missing policy fails closed; no validity periods invented. Freeze policy on the issued document. Settings use the existing admin service flow and rules version. Unsupported replacement/reissue explicitly blocked; renewal uses the existing renewal evaluator against the selected, owner/type-matched prior document and preserves it as superseded only within successful issuance transaction.

Separate document validity (active/superseded), request (approved→processing→completed) and delivery status. Central request transitions allow processing/completed only with actual matching issued record, required intact copy and completion policy. Citizen cannot issue or confirm delivery. No generic status endpoint.

Internal electronic copies use Laravel Blade HTML, one template per document type, printable Arabic RTL, explicitly internal academic record, never government-approved or legal document. No PDF dependency is necessary for an HTML electronic copy. Store versioned UUID files under a new private local disk with checksum/length validation, protected download plus restrictive CSP, no public URLs or QR. File creation and DB issuance are coordinated: failures roll back records/renewal/status/events and delete only the new file; retry safely. Regeneration makes a new immutable copy version, retains prior copy, logs action. Downloads check existence/integrity every time.

Single delivery row per issued document, encrypted address, minimal receipt confirmation reference. Citizen or scoped employee chooses configured method/center/address before dispatch; citizen never changes delivery status. Staff transitions ready→preparing→out_for_delivery(address)→delivered; pickup permits ready/preparing→delivered. Failure/returned transitions and explicit reasoned retry; no method change after dispatch/failure. Completion according to frozen policy, never before mandatory copy/delivery. Immutable document event history, UUID idempotency and optimistic workflow_version, fresh issuance fingerprint and current authorization under locks.

## Tasks

- [x] Write RED functional tests for issue, permission/ownership, missing approval/config/prerequisites, uniqueness, copy failures/integrity, regeneration, renewal conflicts, delivery and completion.
- [x] Add additive migration, relationships, policy, configurable validated service issuance rules, existing grant extensions and admin controls.
- [x] Implement number/copy/issue/lifecycle/delivery services with transaction and audit invariants; integrate central transitions.
- [x] Replace placeholder employee issuance/delivery views and add protected citizen document details/download/method selection; preserve legacy document display.
- [x] Verify target/full SQLite and MySQL suites sequentially, actual competing issuance and renewal, Blade/Pint/frontend checks; independent review and regression fixes.
- [x] Apply additive migration after tests; verify unchanged production data, document exact files/settings/limits/results.

## Verification ledger

165 tests / 1233 assertions on each SQLite and MySQL; 9 real MySQL concurrency tests / 54 assertions, frontend5, Blade/routes/Pint pass. Additive migration applied to main DB; users1/services7/requests0/documents0 unchanged. Testing DB fixture rows cleaned.

Ruling: Freeze the printed holder name (encrypted) despite avoiding redundant citizen data generally — immutable electronic copies must not change holder after profile edits. Independent reviewer found this and selected-document authenticity gap; both had failing regression tests before fixes and passed afterward. Exact selected-document authenticity is checked via the existing evidence adapter. No official external adapter is fabricated.
