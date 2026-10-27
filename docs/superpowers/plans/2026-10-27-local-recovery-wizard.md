# Local recovery wizard

User specified popup with copy/X, code entry, new password/confirmation and redirect to login. Extend existing local recovery service; do not change password on email submission. Reuse Laravel broker tokens bound to a session OTP (hash, ten-minute expiry, five attempts, current auth_version). One-use encrypted preview shows synthetic delivery only locally. Session locks and transactional user lock serialize reset, revoke remembered/old sessions via existing observer, and consume broker token. Production gate on every action.

Test full path, no premature reset, skip/replay/wrong/expired codes, mismatched passwords, production and disabled accounts. Test JS copying, clipboard denial fallback, X/focus and absent modal. Compile views, run auth/registration tests, preserve real user credentials. No migration, provider or dependencies.
