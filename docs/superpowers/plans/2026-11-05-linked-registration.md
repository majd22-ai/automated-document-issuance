# Linked local registration simulation

Reuse users, registration_attempts, citizen_activation_requests, private attachments, inbox notifications and immutable audit events. Add only missing linked synthetic family/person/card/birth records and face attempt evidence. Enable the new path only in local/testing; never contact external registries or messaging providers. Preserve legacy accounts and official first-issuance constraints.

Implement registration as registry lookup → mock OTP → password → login. Card accounts remain pending until deterministic fixture-driven face/liveness simulation or authorized review. No-card accounts retain initial issuance restrictions; unmatched records receive a limited tracking account and review reference. Add an admin-only fixture explorer with dialogs and explicit synthetic labels. Test negative authorization, OTP expiry/reuse/limits, duplicate linkage, private photos, face scenarios and reviewer transitions.
