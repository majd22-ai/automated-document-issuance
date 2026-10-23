# Images and Attachments Management Implementation Plan

> Execute inline using test-driven-development and verification-before-completion. User supplied the detailed task specification and explicitly requested implementation in the current project.

**Goal:** private citizen uploads, protected preview/download, safe replacement and request binding using existing eligibility checks.
**Architecture:** one current attachment per citizen/service/document/context; staged files have no request until transactional submission binds them. Existing requests can change attachments only in requires_correction. Employee-only attachment review never transitions a request. Existing evidence contract gets a local attachment adapter; other evidence remains unavailable.
**Stack:** Laravel12/PHP8.2, Eloquent/MySQL, private Flysystem local disk, GD/fileinfo. No new libraries. Optional configured PDF parser; reject PDFs if a real readability check is unavailable.
**Spec:** user attachment Task6 dated2026-10-22, all fourteen sections.

## Constraints and review focus
- Preserve existing uncommitted work, auth, request states, unique keys and ownership checks. No migrate:fresh, public links or invented service rules.
- Uploaded is not officially approved; current service rules, trusted reviewer, document dates and unchanged content must be checked at final submission.
- Protect against foreign request IDs, status/path spoofing, corrupt images/PDFs, double uploads, replacement failure and stale previews.
- Fail closed on missing/corrupt storage, unavailable PDF parser or required review/expiry.
- Delete previous bytes only after committed successful replacement; audit identifiers/actions only.

## Task1: private upload and access
- [x] Write and run failing HTTP tests for upload, types/size/corruption, role/owner isolation and private previews.
- [x] Add migration/model/policy/config, rule resolver, technical validator and transactional upload service/controller/FormRequests.
- [x] Run targeted tests green.

## Task2: evidence, binding and lifecycle
- [x] Tests for pending/approved/rejected evidence, final recheck, request binding, no cross-request reuse, replacement/deletion limits and failed storage preservation.
- [x] Implement local evidence adapter, explicit attachment-only employee review, content fingerprints in preview and transactional bind in ServiceRequestSubmission.
- [x] Run targeted and existing submission/requirements tests.

## Task3: UI and verification
- [x] Add Arabic RTL upload/list/preview/replacement UI and employee review list; integrate links into current service/start/request pages.
- [x] Run full SQLite/MySQL suites, parallel submission tests, Pint, Blade compilation and existing frontend checks.
- [x] Apply additive migration, inspect nonprivate counts, independent focused review and document actual results/limitations.

Verification: SQLite and MySQL full suites98/733 each; concurrency2/11; frontend5; Pint and Blade pass. Applied additive migrations; main counts1user/7services/0requests/0attachments. Independent review fixes have RED-GREEN regressions. PDF remains fail-closed pending trusted parser configuration. Ruling: preserve the user's current uncommitted workspace and implement inline as explicitly requested; no branch changes or unrelated commits. Ruling: attachment-only employee review is within Task6, with no request transition rights. Ruling: optional:true defines an optional upload without weakening required_documents. See docs/images-and-attachments-management.md for the complete delivered files and limits.
