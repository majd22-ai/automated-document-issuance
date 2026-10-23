# Requirement8 implementation plan

Execute inline using TDD and independent review. User supplied a detailed specification and explicitly requested implementation in this workspace. Preserve existing work and the public auth/operations/attachments flows.

## Design
- Reuse ServiceRequestStatus, transitionTo, ServiceEligibility, private attachments and operational prerequisites. No document issuance, new roles or external notifications.
- Add per-service employee review/approval/assignment grants configured by admin, default denied; retain existing operational data permissions. Review list/detail/internal notes require explicit review grants. Actual reviewer decisions require assignment; approval requires its own grant and optionally a different approver when service policy explicitly requires it.
- Add assignment fields and monotonically increasing review_version to requests. One append-only review decision log includes UUID idempotency key and immutable from/to/actor/reasons/internal notes and check snapshot. Add structured deficiencies with separate citizen response/staff resolution, no citizen self-resolution.
- Claim/start, accept, return, reject, approve and citizen resubmit use owner→service→request locks, current authorization, expected version/rules, fresh evidence fingerprint and transactional history. Direct model transitions retain their API but must use same checks/logging. Runtime processing/completed transitions remain denied until issuance implementation.
- Approval requires accepted decision + no unresolved deficiencies and all configured prerequisites, including any deferred processing requirements. No reopening rejected requests. Replaying the same decision token is idempotent; a conflicting action or stale form is rejected.
- Citizen correction edits only explicitly returned form fields against frozen form schema, preserving original_request_data. Existing attachments/profile/operations remain the way to fix their own data. Resubmit validates server-side and returns submitted, preserving return history; staff resolves submitted deficiencies before acceptance.
- Paginated employee queue with reference/service/status/date/assignee and cached requirement-state filters labeled as time-bound snapshots. Details always evaluate fresh and use protected links.

## Tasks
- [x] RED HTTP tests for permission grants, claim/assignment, acceptance/approval, bypass attempts, missing requirements, privacy, correction/resubmit and immutable/idempotent history.
- [x] Add additive schema/models/grant policy and admin UI without default grants or fake records.
- [x] Extend central transition primitive with transactional decisions/deficiencies/corrections and stale-state protection; reuse phase eligibility.
- [x] Implement review queue/detail and citizen correction/history screens, scoped forms and administrative grants.
- [x] Run targeted/full SQLite/MySQL serial suites, actual competing decisions, existing concurrency tests, Pint/Blade/frontend checks; independent review; additive migration and report actual limits.
