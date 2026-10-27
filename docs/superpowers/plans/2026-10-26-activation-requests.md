# Citizen-requested activation

Approved workflow: citizen submits their own activation request; employees explicitly granted can_activate review and use the existing confirmation service. Add one user-unique request with pending/approved/review_required states. Reuse InboxChannel; notify qualified employees, and notify on new permission grant. Add staff inbox with owner-scoped reads and current authorization checked on open. No automatic approval, mock proof or real outbound messages. Preserve existing confirmation workflow and citizen notification.

Test own submission/idempotence, role restrictions, scoped inbox, approval resolution and grant notifications first. Implement additive migration, service, controller actions and RTL UI. Run activation/integration/auth tests, apply only additive migration, preserve all existing data. Existing citizens submit explicitly; do not invent historic requests.
