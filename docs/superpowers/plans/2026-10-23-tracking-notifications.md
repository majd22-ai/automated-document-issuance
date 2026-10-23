# Requirement10 implementation plan

User requests inline implementation of detailed supplied specification. Reuse uncommitted integrated preceding requirements; no unrelated changes or approval gates.

Design: native Laravel Notifiable/DatabaseNotification with custom idempotent database channel; one notifications table with unique event_key, nullable request/document FK, recipient FK and durable optional mail-delivery state. No duplicate state-history table. Internal inbox joins business transaction; SMTP is exclusively asynchronous after commit, recoverable from pending inbox rows if dispatch fails. Disabled by default, verified unchanged email required, log/array mailers excluded, sync queue excluded. CAS queued→sending prevents concurrent sends; failed/uncertain sending is not automatically resent; explicit bounded retry command for failures. No SMTP exceptions/raw payload/PII logged.

Hooks: real submission, ReviewActions decisions, DocumentAudit issued/ready/preparing/out_for_delivery/delivered/failed/returned, due RequestCharge, configured appointment action on submission. Keys identify persisted source event, not just status, so genuine repeated returns notify separately while replay does not. Content uses only public reasons and generic instructions; no notes, addresses, receipt references or document numbers in emails.

Tracking: extend citizen filters and add scoped employee tracking routes with per-service grants; paginated SQL union timeline from actual submitted_at, ReviewDecision and DocumentEvent, no synthetic historical dates or internal notes. Preserve details/correction/action links and show current/next action plus issuance/delivery. Public inquiry demo routes lead to authenticated real tracking.

Expiry: explicit config offsets (default empty), statuses active, expired policy skip(default)/once. Closest reached threshold avoids backfill spam; unique document/date/threshold key. Owner→document locks recheck status/expiry/account; exclude superseded/cancelled regardless config. Chunk processing and scheduled withoutOverlapping plus unique DB constraint; manual command and scheduler/worker instructions. No expiry dates invented.

Tasks:
- [ ] RED functional tests ownership, real event notifications, replay, timeline privacy/chronology, filters/read/open.
- [ ] Add additive native notification schema, channel/event notification composer and transactional business hooks.
- [ ] Implement safe optional mail job, dispatch recovery/retry and email trust checks; fake transport tests.
- [ ] Implement configurable expiry command, scheduler and idempotent tests.
- [ ] Add native inbox/read counter and scoped paginated timeline/filter screens.
- [ ] Full SQLite/MySQL suites sequentially, existing/new concurrency, independent review, Pint/Blade/frontend; additive main migration and exact report.
