# Role workspaces implementation plan

Goal: clear Arabic RTL employee and user dashboards, with one navigation source and service-scoped access.
Architecture: EmployeeWorkspace maps existing grants to modules and service IDs. Existing policies remain record-level authority; tighten broad queries and direct access. No new permissions, roles, tables, or grants.

- [ ] Add tests for no-grant employee, reviewer, verifier, issuer, and neighborhood official isolation, including cross-service records and navigation.
- [ ] Implement shared allowed modules and enforce employee route access; scope citizen data, attachments, appointments and payments by permitted services.
- [ ] Replace repeated employee dashboard cards/legacy demo with a task summary and service scope; build citizen dashboard with latest requests and one set of actions. Simplify header within workspaces and keep sidebar as section navigation.
- [ ] Organize admin user management and employee pages through shared styles; preserve form actions and validation.
- [ ] Run focused and full tests, JS checks, build, Blade compilation. Document known unrelated failures and validation limits.

Existing activation changes in the working tree belong to the preceding task and must be preserved. Neighborhood access is exclusively its own assigned attestations unless the administrator explicitly grants additional existing service permissions. Never expose another service's citizen documents through citizen search.
