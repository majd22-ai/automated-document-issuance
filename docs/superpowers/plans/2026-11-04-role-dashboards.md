# Role dashboards implementation plan

Goal: use the existing RTL workspace styles and one shared heading for each single-role dashboard, preserving existing routes and authorization.

- Reuse the admin report dashboard and all of its filters, metrics and exports. Do not replace the report controller.
- Reuse employee task cards and their existing permission conditions.
- Add citizen summary counts and recent requests through authenticated-user relationships only; retain the demo in an explicitly labelled collapsed section.
- Share account links and heading markup through an anonymous Blade component. Load existing workspace CSS for the citizen dashboard too.
- Verify heading rendering, role access denial and citizen data isolation with WorkspaceExperienceTest and existing authentication tests.

No schema changes, permission grants, dependencies or workflow changes. Mobile layout uses the existing auto-fit grid and horizontally scrollable tables. Browser viewport verification remains a separate check.
