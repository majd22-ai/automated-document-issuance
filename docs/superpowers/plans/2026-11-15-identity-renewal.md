# Identity renewal implementation plan

Approved flow: select an owned prior identity document, retain identity data, update residence/contact, attach current portrait and prior-card copy, employee preliminary review, postal payment, appointment, attendance and fingerprint, final approval, issuance linked to the selected prior card, delivery before completion. Ordinary renewal excludes witnesses and neighborhood attestation. Core identity corrections use a separate procedure.

- [x] Add renewal-specific draft controller/view and validation, reusing private attachment storage and portrait validation. Lock identity from the account and freeze the selected prior document.
- [x] Extend versioned IdentityJourney to renewal without affecting historical requests. Revalidate prior document on submission and issuance. Review current portrait and prior-card copy using revision-bound decisions.
- [x] Share postal-payment, appointment, arrival, fingerprint, issuance and delivery gates. Require card portrait, without repeating blood evidence for ordinary renewal.
- [x] Test ownership, file requirements, stale previews, corrections, selected-document consistency, review and lifecycle gates on isolated SQLite. Run first-issue regressions and cache views.

Implementation files: IdentityRenewals service and controller/view; IdentityCardForm model; IdentityCardForms submission; InitialIdentityReview tasks; IdentityJourney gates; PreviewServiceRequest; issuance selection; public service entry links; renewal feature tests.

Validation: 120 feature tests / 1476 assertions passed across renewal, first issue, review, fingerprint, issuance and catalog. After the final attachment-correction fix, 15 targeted feature tests / 103 assertions passed; 9 phone/location JavaScript tests passed. Blade cache, PHP syntax and diff whitespace checks passed. Local MySQL and development server started; renewal catalog returned HTTP 200 with the configured 6500 YER project-guide fee. Local setup changes only identity-renewal configuration; no schema migrations or .env changes.
