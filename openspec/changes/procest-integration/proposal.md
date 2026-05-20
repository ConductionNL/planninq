# Proposal: Procest Integration

## Summary

Add `caseReference` to the Project entity and `zaakUuid` to the Task entity so Planix projects and tasks can be manually linked to Procest cases (VNG ZGW InterneTaak). Show a case badge in project lists and a read-only case link in task details. Lay the groundwork for a V1 bridge API that lets Procest create projects and mirror task completions.

## Motivation

Planix is a sister app to Procest (case management). When a Procest case requires task tracking on a kanban board, Planix provides the board. Currently there is no connection between the two apps — a case handler who opens Planix has no way to see which case a board belongs to, and a case manager viewing a Procest case has no visibility into the related Planix tasks. Adding bridge fields and a small case badge closes this gap immediately (MVP), while the full bridge API (V1) enables automated project creation and status mirroring.

## Affected Projects

- [x] Project: `planix` — Schema changes (Project + Task), frontend badge/link, bridge controller (V1), admin settings toggle (V1)

## Scope

### In Scope

**MVP:**
- `caseReference` field on the Project entity (string, Procest case UUID)
- `zaakUuid` field on the Task entity (string, Procest case UUID)
- Case badge ("Case: {caseNumber}") rendered on the project list and project detail when `caseReference` is set
- Read-only case link on the task detail when `zaakUuid` is set
- Manual entry of both fields via existing project and task edit forms
- When the Procest bridge is not configured, fields are stored and displayed but no Procest API calls are made

**V1:**
- Bridge controller at `POST /planix/api/bridge/project` — Procest creates a Planix project for a case
- Task completion hook: when a task with `zaakUuid` is marked done and the bridge is enabled, Planix sends a PATCH to the Procest InterneTaak endpoint
- Graceful degradation: if Procest is unreachable the task update succeeds and a warning is logged
- Shared API token authentication for bridge endpoints (configured in admin settings)
- Admin settings toggle to enable/disable the Procest bridge

### Out of Scope

- Real-time case status synchronisation from Procest → Planix
- Creating Procest cases from within Planix
- Displaying Procest case metadata (status, assignee) inside Planix — Planix only stores the UUID and links to it
- BRP/BAG/KVK data enrichment
- Retry queue for failed mirroring (V1 logs a warning; full retry queue is a future change)

## Approach

**MVP**: Add two optional string fields to the existing Project and Task OpenRegister schemas. Render a small `CnStatusBadge` (or `NcChip`) in `ProjectCard.vue` and `ProjectDetail.vue` when `caseReference` is set. Render a read-only link in `TaskDetail.vue` when `zaakUuid` is set. Expose both fields in the schema-driven `CnFormDialog` for project and task edit.

**V1**: Add a dedicated `BridgeController` (thin — routing + token validation only) that delegates to `BridgeService`. `BridgeService` handles project creation and task mirroring. Mirroring is called from the existing task-update code path via an event hook; failures are caught, logged, and do not propagate. Bridge credentials (URL + token) are stored in `IAppConfig` with `sensitive: true`.

No custom CRUD controllers or search endpoints are needed — all CRUD flows through the existing OpenRegister `ObjectService` pipeline.

## New Dependencies

None for MVP. V1 requires HTTP client calls to Procest (using `IClientService` from the Nextcloud framework — already available).

## Impact

- `lib/Settings/planix_register.json` — add `caseReference` to Project schema; add `zaakUuid` to Task schema
- `src/views/ProjectCard.vue` / `ProjectDetail.vue` — add case badge
- `src/views/TaskDetail.vue` — add case link
- V1: `lib/Controller/BridgeController.php` (new), `lib/Service/BridgeService.php` (new)
- V1: `lib/Settings/AdminSettings.php` — bridge enable toggle + URL + token fields

## Cross-Project Dependencies

- **Procest** — V1 bridge requires agreement on the InterneTaak PATCH endpoint URL and payload format. MVP has no cross-project dependency.

## Risks

### Risk 1: Procest InterneTaak API not yet stable (V1)
**Severity:** Medium — **Mitigation:** V1 is scoped separately from MVP. The bridge service wraps the HTTP call; if the endpoint spec changes, only `BridgeService` needs updating. Graceful degradation ensures Planix is never blocked.

### Risk 2: UUID validation — user enters a malformed value in the edit form
**Severity:** Low — **Mitigation:** Add UUID format validation on the schema field (pattern: `^[0-9a-f-]{36}$`). Frontend shows a validation message; backend rejects on schema validation.

### Risk 3: Shared API token stored insecurely
**Severity:** Low — **Mitigation:** Token stored via `IAppConfig` with `sensitive: true` flag — encrypted at rest, never returned to the frontend, never logged.

## Rollback Strategy

**MVP**: Removing `caseReference` and `zaakUuid` from schemas is a breaking change once data exists. Rollback by disabling the badge/link rendering (feature flag in settings) rather than removing the fields. Schema fields are additive and non-breaking.

**V1**: The bridge controller can be disabled via the admin toggle without a code rollback. If a code rollback is needed, remove `BridgeController` and `BridgeService` — task updates are unaffected because mirroring is an optional side-effect, not part of the main save path.
