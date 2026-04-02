# Change Proposal: procest-integration

**Change ID:** procest-integration
**Status:** proposed
**Created:** 2026-04-02
**Author:** Conduction Development Team

---

## Why

Planix is a sister app to Procest (case management). When a Procest case requires task tracking on a kanban board, Planix provides the board. The data model for this bridge — `caseReference` on Project and `zaakUuid` on Task — was already defined in the `register-schemas` change. However, no UI currently surfaces these fields: they cannot be set via the edit forms, and projects/tasks with these values already populated show no visual indicator.

This change implements the MVP display layer: case badges, case links, and edit form fields. It makes the bridge fields usable for teams that manually link Planix projects and tasks to Procest cases today, without requiring the full V1 bridge API.

---

## What Changes

### Case Badge on Project

Projects with a `caseReference` value display a "Case: {caseNumber}" badge in both the project list and the project detail view. The badge provides at-a-glance context that the project is linked to a Procest case.

A "View in Procest" link appears in the project detail view, navigating the user to `/apps/procest/#/cases/{caseReference}`.

### Case Reference Field in Project Edit Form

The project settings sidebar gains a "Case reference" text input (UUID format). Users can manually enter or clear a Procest case UUID. On save, the value is written to `caseReference` on the project object via the standard `updateProject` store action.

### Case Link on Task Detail

Tasks with a `zaakUuid` value display a read-only "Case" field in the task detail panel. The field renders as a link: "View case" pointing to `/apps/procest/#/cases/{zaakUuid}`.

### Case UUID Field in Task Edit Form

The task edit form gains a "Case UUID" text input (UUID format). Users can manually enter or clear a Procest case UUID. On save, the value is written to `zaakUuid` on the task object via the standard `updateTask` store action.

### Bridge-Disabled Behavior

When the Procest bridge is disabled or not yet configured (the default state), `caseReference` and `zaakUuid` fields are still stored and displayed in the UI as read-only metadata. No Procest API calls are made. This is the expected behavior for MVP.

---

## Capabilities

### Modified Capabilities

- **`procest-integration`** — implementing the MVP display layer of the procest-integration capability defined in `openspec/specs/procest-integration.md`. This change brings caseReference and zaakUuid from schema-only fields to surfaced UI fields (badge, link, edit form inputs).

No new capabilities are introduced. The `procest-integration` capability was declared in the spec and the fields were prepared by `register-schemas`; this change makes them visible and editable.

---

## Impact

### Files Changed

| File | Change |
|------|--------|
| `src/components/CaseBadge.vue` | New — reusable badge component for case references |
| `src/components/CaseLink.vue` | New — reusable link component for Procest case navigation |
| `src/components/ProjectSettingsSidebar.vue` | Modified — add Case Reference field to Details section |
| `src/views/ProjectList.vue` | Modified — render `CaseBadge` on list items with `caseReference` |
| `src/views/ProjectBoard.vue` | Modified — render `CaseBadge` and `CaseLink` in project detail header |
| `src/components/TaskDetail.vue` | Modified — render read-only Case field with `CaseLink` when `zaakUuid` is set |
| `src/components/dialogs/TaskEditDialog.vue` | Modified — add Case UUID field |
| `l10n/en.json` | Modified — add case badge and case link i18n strings |
| `l10n/nl.json` | Modified — add Dutch translations for case badge and case link strings |

### Screens / Views Affected

| View | Change |
|------|--------|
| Project list | Case badge appears on project list items with `caseReference` |
| Project detail | Case badge + "View in Procest" link in project header/detail area |
| Project settings sidebar | New "Case reference" text input in Details section |
| Task detail panel | Read-only "Case" field with link when `zaakUuid` is set |
| Task edit dialog | New "Case UUID" text input |

### Dependencies

| Change | Relationship |
|--------|-------------|
| `register-schemas` | Fields `caseReference` and `zaakUuid` must exist in the schema — this change requires that dependency to be applied first |
| `projects` | Project settings sidebar (`ProjectSettingsSidebar.vue`) must exist — this change modifies it |
| `tasks` | Task detail and edit form components must exist — this change modifies them |

### Out of Scope (V1)

- Bridge API (`POST /planix/api/bridge/project`)
- Task completion mirroring to Procest (`PATCH` to InterneTaak endpoint)
- Bridge authentication (shared token)
- Procest API calls of any kind
- Admin setting `procest_base_url` (case link URL is hardcoded to `/apps/procest/#/cases/{uuid}` in MVP)
