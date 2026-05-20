# Proposal: Projects Specification

## Summary

Implement the full project management UI for Planix — the top-level container for tasks and the kanban board. This change delivers project creation, listing, settings, member management, archiving, and deletion as a complete Vue + Pinia thin-client on OpenRegister.

## Motivation

Planix needs a project management layer before any other feature can be used end-to-end. A project is the organizing unit around which kanban boards, task backlogs, member access, and Procest integration are built. Without this change, users cannot create or navigate to projects, columns cannot be initialized, and the kanban board has no context to render.

The Projects spec defines the complete data model and requirements. This change implements them: project list view using `CnListViewLayout`, project creation via `NcDialog`, project settings via `CnObjectSidebar`, and cascading deletion with a confirmation dialog. It also delivers the OpenRegister dependency gate in `App.vue`.

## Affected Projects

- [x] Project: `planix` — Full feature implementation: Vue components, Pinia store, OpenRegister schema usage, i18n strings

## Scope

### In Scope

- Project list view (`ProjectList.vue`) using `CnListViewLayout` with search, status filter chips, and empty state
- Project creation dialog (`ProjectCreationDialog.vue`) as `NcDialog` modal with title validation, default column creation, and loading state
- Project settings sidebar (`ProjectSettingsSidebar.vue`) as `CnObjectSidebar` with Details and Danger Zone sections
- Default column creation (4 columns: To Do, In Progress, Review, Done) immediately after project creation
- Member management: add member (Nextcloud user search), remove member (with assigned-task warning), leave project (with last-member warning)
- Project lifecycle: create (status → `active`), archive (status → `archived`), complete (status → `completed`), delete (cascade to tasks, columns, TimeEntries)
- OpenRegister gate check in `App.vue` (NcEmptyContent + Install button for admins when OpenRegister is absent)
- Member access control: 403 response for non-members; projects hidden from non-member project lists
- Procest bridge: `caseReference` field + `[Case: {caseNumber}]` label applied to bridge-created projects
- i18n: all user-visible strings in `t('planix', '...')` with Dutch translations in `l10n/nl.json`
- Loading and error states for all async operations (list fetch, create, update, delete)
- Seed data: 3–5 realistic Dutch project objects in `lib/Settings/planix_register.json`

### Out of Scope

- Project templates (V1 feature — pre-populate columns, labels, and tasks from a template)
- Automated two-way CalDAV VTODO sync for project metadata
- Procest bridge trigger logic — Procest app initiates; Planix reads `caseReference` passively
- Task status mirroring back to Procest case status (V1)
- Custom PHP database tables — all state lives in OpenRegister

## Approach

All state is managed via Pinia stores using `useObjectStore` from `@conduction/nextcloud-vue`, calling the OpenRegister REST API. No custom PHP controllers or database tables are needed.

Key architectural decisions:
- `ProjectCreationDialog.vue` lives in `src/dialogs/` — opened as `NcDialog` modal, not a route
- `ProjectSettingsSidebar.vue` lives in `src/sidebars/` — opened as `CnObjectSidebar`
- Project list uses `CnListViewLayout` + `useListView` composable for search, filter, and state
- Default columns created by the Pinia project store via sequential `POST` calls immediately after project creation
- OpenRegister gate implemented in `App.vue` `created()` hook via the `openRegisters` flag from the settings API

## New Dependencies

None — uses only existing `@conduction/nextcloud-vue` components and the OpenRegister API.

## Impact

- `src/views/ProjectList.vue` — new project list component
- `src/dialogs/ProjectCreationDialog.vue` — new creation modal
- `src/sidebars/ProjectSettingsSidebar.vue` — new settings sidebar
- `src/store/modules/projects.js` — Pinia project store (`useObjectStore` configuration)
- `src/store/modules/columns.js` — Pinia column store for default column creation
- `App.vue` — adds OpenRegister gate check in `created()`
- `l10n/en.json` + `l10n/nl.json` — new i18n strings for all project UI
- `lib/Settings/planix_register.json` — seed data objects for the `project` schema

## Cross-Project Dependencies

- **OpenRegister** — required at runtime for object storage; gate check on app load
- **Procest** (optional) — sends `caseReference` when creating bridge projects; Planix reads it passively

## Risks

### Risk 1: Default column creation partial failure
**Severity:** Medium — **Mitigation:** Column creation failures show a non-blocking warning toast; the project remains accessible. User can add columns manually via board settings. Partial failure does not block project access.

### Risk 2: PATCH overwriting members array
**Severity:** High — **Mitigation:** All PATCH requests MUST include the full object (especially the `members` array) to prevent data loss on partial updates. Enforced in `ProjectSettingsSidebar` edit flow.

### Risk 3: OpenRegister not installed
**Severity:** Medium — **Mitigation:** `App.vue` gate check catches absence before any view renders; shows `NcEmptyContent` with an actionable install link for admins only.

## Rollback Strategy

Revert the commit set. No database migrations, no schema changes — all data lives in OpenRegister's existing `project` schema (defined in the `register-schemas` change). Frontend-only additions are fully reversible.
