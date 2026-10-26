# Local registration OTP
User selected local testing. Add opt-in REGISTRATION_LOCAL_OTP guarded by exact local environment. Reuse RegistrationSms and existing OTP hashing, expiration, attempts and resend limits. Keep display only in current private session/page; never log codes. Mark local attempts in encrypted payload so resulting accounts do not claim phone ownership. Clear session code on cancellation/completion. Production fails closed even when flag is enabled.

1. Add feature tests for local completion and production refusal; observe failure.
2. Add guarded local transport, binding/config and session-specific view output; preserve real flow.
3. Run focused registration/auth tests, syntax and Blade checks. Enable flag only in local .env; document real SMS still needed.
