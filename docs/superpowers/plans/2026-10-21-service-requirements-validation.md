# Requirement 5 — extend existing ServiceEligibility

Reuse citizen/service assessments, document metadata, request ownership and transactional submission. Replace bool-only reasons implementation with structured RequirementResult/EvaluationResult and four states (met/unmet/needs_review/unverifiable), retaining reasons() compatibility. Keep mandatory checks fail-closed and identify citizen/staff/external actions. No uploads, exams management, review permissions or invented legal defaults.

Add optional verification_rules to government_services: per-existing-required-document MIME/review rules, optional driving medical requirement, renewal validity policy/authenticity and manual/external restriction source. Rules changes version/invalidate assessment and preview. Missing rule/evidence/source never means met. Keep assessment block_reason private; add explicit public_block_reason. Add request result+checked_at snapshot, and safe audit logging of check codes/states only.

EvidenceSource interface + unavailable default is the integration boundary for task6/medical/registry. Trusted adapters must return owner/service/request-scoped, dated evidence with trusted provenance. Catch source failures as unverifiable, never pass. No fake runtime providers. Tests inject explicit evidence fixtures only.

Wire structured results into existing detail/start/preview/final submission and authorized request recheck. Exclude current request from duplicate check on recheck. Recheck before future acceptance/approval/processing inside existing transition primitive without granting any current role transition rights. Save current result without rewriting historical request/service snapshots.

TDD for all-met, missing core data, expired/incomplete/invalid document, missing and pending/wrong MIME attachment, trusted/failed/expired medical result, source outage, restrictions privacy, HTTP spoof/owner isolation, reevaluation and future acceptance guard. Run full SQLite/MySQL and previous parallel submission tests, peer review, additive migrate, views/Pint and report actual limitations.
