# Design: Procest Integration

## Summary

Add `caseReference` to the Project entity and `zaakUuid` to the Task entity. Render a case badge on project list/detail and a case link on task detail. For V1, add a dedicated `BridgeController` + `BridgeService` for Procest-initiated project creation and task-completion mirroring.

## Motivation

Users switching between Planix and Procest have no contextual link between the two apps. Adding bridge fields and visual indicators removes the need to manually search for related records, and the V1 bridge API enables automated workflows.

## Approach

### Data model changes

Both changes are **additive** — new optional fields added to existing schemas. No existing data is affected.

**Project schema** (`caseReference`):
- Type: `string`
- Format: UUID pattern `^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$`
- Required: false
- Description: UUID of the linked Procest case

**Task schema** (`zaakUuid`):
- Type: `string`
- Format: UUID pattern (same as above)
- Required: false
- Description: UUID of the Procest case this task belongs to (for tasks not in a case-project)

Both fields are schema-standard (`lib/Settings/planix_register.json`). The existing `CnFormDialog` will auto-generate the input fields from the updated schema — no custom form code needed.

### MVP frontend — case badge

`ProjectCard.vue` and `ProjectDetail.vue` check `project.caseReference`. If set, render a `CnStatusBadge` (or `NcChip` from `@conduction/nextcloud-vue`) with label `t('planix', 'Case: {caseNumber}', { caseNumber })`.

The Procest case URL is constructed from the admin-configured Procest base URL (or a sensible default). If no URL is configured, the badge shows plain text only (no hyperlink).

`TaskDetail.vue` checks `task.zaakUuid`. If set, a read-only "Case" row is shown in the detail grid with a link to the Procest case.

### V1 backend — BridgeController / BridgeService

`BridgeController` is a dedicated controller (NOT part of the OpenRegister CRUD API):
- Route: `POST /planix/api/bridge/project`
- Auth: `#[PublicPage]` + manual shared-token validation in `BridgeService::validateToken()`
- Thin: extracts request body, delegates to `BridgeService`, returns JSON response

`BridgeService`:
- `createProjectFromCase(string $caseUuid, string $caseNumber): array` — uses `ObjectService::saveObject()` to create a Project with `title = "Case {caseNumber}"`, `caseReference = $caseUuid`, and the default column set
- `mirrorTaskCompletion(string $zaakUuid, string $planixTaskId): void` — sends a PATCH to the Procest InterneTaak endpoint; catches all Throwables, logs warning on failure, never rethrows
- `validateToken(string $headerToken): bool` — compares against `IAppConfig` stored token (constant-time comparison)

Task mirroring is triggered from the existing task-update service method when `status === 'done'` and `zaakUuid` is set and the bridge is enabled. It is an optional side-effect — failure must never fail the task update.

### VNG InterneTaak field mapping

| Planix Task field | VNG InterneTaak field        |
|-------------------|------------------------------|
| `title`           | `gevraagdeHandeling`         |
| `assignedTo`      | `toegewezenAanGebruikersnaam`|
| `dueDate`         | `gevraagdeDatum`             |
| `status` (done)   | triggers `afhandelingsdatum` |
| `completedAt`     | `afhandelingsdatum`          |

### Admin settings (V1)

New fields in `AdminSettings.php` / admin settings Vue component:
- **Enable Procest bridge** — boolean toggle (stored in `IAppConfig` as `bridge_enabled`)
- **Procest base URL** — string (stored as `procest_base_url`, used to build case deep-links)
- **Bridge API token** — string, sensitive (stored as `bridge_token` with `sensitive: true`)

---

## Reuse Analysis

| Capability needed | OpenRegister / Conduction component reused |
|-------------------|--------------------------------------------|
| Project + Task CRUD | `ObjectService.saveObject()` / `findAll()` |
| Schema-driven edit forms | `CnFormDialog` — reads updated schema, auto-generates `caseReference` + `zaakUuid` inputs |
| Detail page case link row | `CnDetailGrid` label-value pair |
| Case badge | `CnStatusBadge` from `@conduction/nextcloud-vue` |
| Admin settings layout | `CnSettingsSection` + `CnVersionInfoCard` |
| HTTP client for bridge calls | `IClientService` (Nextcloud framework) |
| Audit trail for mirroring | `AuditTrailService` (automatic via ObjectService) |

No new custom CRUD controllers, search endpoints, or Pinia stores are needed.

---

## Seed Data

The following seed objects are added to `lib/Settings/planix_register.json` under `components.objects[]` using the `@self` envelope. All values are Dutch-locale realistic fictional data.

### Projects (with `caseReference`)

```json
{
  "@self": { "register": "planix", "schema": "project", "slug": "project-grachtenbuurt-aanvraag" },
  "title": "Case 2024-0042 – Omgevingsvergunning Grachtenbuurt",
  "description": "Implementatietaken voor de behandeling van omgevingsvergunningaanvraag grachtenbuurt.",
  "caseReference": "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
  "status": "active"
}
```

```json
{
  "@self": { "register": "planix", "schema": "project", "slug": "project-bezwaar-parkeervergunning" },
  "title": "Case 2024-0117 – Bezwaar parkeervergunning Centrum",
  "description": "Taken voor de afhandeling van bezwaar ingediend door burger t.a.v. parkeervergunning.",
  "caseReference": "b2c3d4e5-f6a7-8901-bcde-f12345678901",
  "status": "active"
}
```

```json
{
  "@self": { "register": "planix", "schema": "project", "slug": "project-herziening-bestemmingsplan" },
  "title": "Case 2024-0203 – Herziening bestemmingsplan Westpoort",
  "description": "Coördinatietaken rondom de partiële herziening van het bestemmingsplan.",
  "caseReference": "c3d4e5f6-a7b8-9012-cdef-123456789012",
  "status": "active"
}
```

### Tasks (with `zaakUuid`)

```json
{
  "@self": { "register": "planix", "schema": "task", "slug": "task-advies-brandweer" },
  "title": "Advies brandweer ophalen",
  "assignedTo": "j.vandenberg",
  "dueDate": "2026-06-10",
  "status": "open",
  "zaakUuid": "a1b2c3d4-e5f6-7890-abcd-ef1234567890"
}
```

```json
{
  "@self": { "register": "planix", "schema": "task", "slug": "task-buurtbewoners-informeren" },
  "title": "Buurtbewoners informeren over procedure",
  "assignedTo": "s.devries",
  "dueDate": "2026-06-15",
  "status": "open",
  "zaakUuid": "b2c3d4e5-f6a7-8901-bcde-f12345678901"
}
```

```json
{
  "@self": { "register": "planix", "schema": "task", "slug": "task-juridische-toets" },
  "title": "Juridische toets bestemmingsplan",
  "assignedTo": "m.janssen",
  "dueDate": "2026-06-20",
  "status": "in-progress",
  "zaakUuid": "c3d4e5f6-a7b8-9012-cdef-123456789012"
}
```

---

## Scope

- `lib/Settings/planix_register.json` — add `caseReference` to Project schema; add `zaakUuid` to Task schema; add seed data objects
- `src/views/ProjectCard.vue` — add `CnStatusBadge` for case reference
- `src/views/ProjectDetail.vue` — add case badge + case deep-link
- `src/views/TaskDetail.vue` — add read-only case UUID link row
- V1: `lib/Controller/BridgeController.php` (new)
- V1: `lib/Service/BridgeService.php` (new)
- V1: `lib/Settings/AdminSettings.php` — add bridge configuration fields
- V1: `src/components/AdminSettings/BridgeSection.vue` (new settings section)
- V1: `lib/Service/TaskService.php` — hook task-done event to `BridgeService::mirrorTaskCompletion()`
