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
- [ ] Write and run failing HTTP tests for upload, types/size/corruption, role/owner isolation and private previews.
- [ ] Add migration/model/policy/config, rule resolver, technical validator and transactional upload service/controller/FormRequests.
- [ ] Run targeted tests green.

## Task2: evidence, binding and lifecycle
- [ ] Tests for pending/approved/rejected evidence, final recheck, request binding, no cross-request reuse, replacement/deletion limits and failed storage preservation.
- [ ] Implement local evidence adapter, explicit attachment-only employee review, content fingerprints in preview and transactional bind in ServiceRequestSubmission.
- [ ] Run targeted and existing submission/requirements tests.

## Task3: UI and verification
- [ ] Add Arabic RTL upload/list/preview/replacement UI and employee review list; integrate links into current service/start/request pages.
- [ ] Run full SQLite/MySQL suites, parallel submission tests, Pint, Blade compilation and existing frontend checks.
- [ ] Apply additive migration, inspect nonprivate counts, independent focused review and document actual results/limitations.
