# Delta Spec: projects

**Capability:** projects
**Change ID:** projects
**Delta type:** implementation
**Base spec:** [openspec/specs/projects.md](../../../../specs/projects.md)
**Status:** draft
**Created:** 2026-04-02

---

## Summary

This delta captures implementation-specific requirements added when building the project management UI. The base spec (`openspec/specs/projects.md`) defines all business requirements, scenarios, user stories, and acceptance criteria. The delta below documents:

1. UI component patterns required by the implementation architecture.
2. Loading/error state requirements not explicit in the base spec.
3. i18n requirements.
4. Constraints introduced by the `thin-client` + `useObjectStore` architecture.

All base spec requirements are implemented as-is. No base spec requirement is modified or removed.

---

## ADDED Requirements

### Requirement: Project List UI [MVP]

The project list view MUST be implemented using `CnListViewLayout` from `@conduction/nextcloud-vue`.

#### Scenario: Render project list
- GIVEN the user navigates to `/projects`
- WHEN the component mounts
- THEN the view MUST fetch all projects where `members` contains the current user's UID
- AND display them using `CnListViewLayout` with a search bar and status filter chips
- AND each list item MUST show: color swatch, icon, title, member count, and status badge

#### Scenario: Search projects
- GIVEN the project list is rendered with multiple projects
- WHEN the user types in the search bar
- THEN the list MUST filter in real-time (client-side) by project title and description
- AND the search input MUST be debounced (300 ms)

#### Scenario: Filter by status
- GIVEN the project list is rendered
- WHEN the user selects a status filter chip (`Active`, `Archived`, `Completed`)
- THEN the list MUST show only projects matching that status
- AND the filter state MUST be managed by `useListView`

#### Scenario: Empty project list
- GIVEN the user has no projects (member of zero projects)
- WHEN the list renders
- THEN the view MUST show `NcEmptyContent` with title "No projects yet" and action button "Create your first project"

---

### Requirement: Project Creation Dialog [MVP]

Project creation MUST be implemented as a modal dialog (`NcDialog`), not a separate route.

#### Scenario: Open creation dialog
- GIVEN the user is on the project list
- WHEN the user clicks "New project"
- THEN `ProjectCreationDialog.vue` MUST open as a modal over the current view

#### Scenario: Create project — field validation
- GIVEN the creation dialog is open
- WHEN the user submits without a title
- THEN the form MUST display an inline validation error: "Title is required"
- AND the submit button MUST remain disabled until a title is provided

#### Scenario: Create project — default columns created
- GIVEN the user submits the creation dialog with a valid title
- WHEN the Pinia store creates the project
- THEN the store MUST subsequently create 4 default columns via sequential OpenRegister POSTs
- AND partial column creation failures MUST show a non-blocking warning toast (not block project access)
- AND on success the dialog MUST close and the router MUST navigate to `/projects/{newId}`

#### Scenario: Create project — loading state
- GIVEN the user has clicked "Create" in the dialog
- WHILE the OpenRegister requests are in-flight
- THEN the submit button MUST show a loading spinner and be disabled
- AND the dialog MUST NOT be closeable while saving

---

### Requirement: Project Settings Sidebar [MVP]

Project settings MUST be accessible via `CnObjectSidebar` within the project detail view.

#### Scenario: Open settings sidebar
- GIVEN the user is on `/projects/:id`
- WHEN the user clicks the gear icon in the page header
- THEN `ProjectSettingsSidebar.vue` MUST open as a `CnObjectSidebar`

#### Scenario: Edit project metadata — immediate reflection
- GIVEN the settings sidebar is open with the Details section active
- WHEN the user edits the title, color, or icon and saves
- THEN the updated values MUST be reflected immediately in the page header and breadcrumb
- AND the project list item (if in cache) MUST also update without a full page reload

#### Scenario: Sidebar Danger Zone
- GIVEN the settings sidebar is open
- WHEN the user scrolls to the "Danger Zone" section
- THEN "Archive project" and "Delete project" actions MUST be shown
- AND both MUST require a confirmation step before executing

---

### Requirement: Default Column Creation [MVP]

Default columns MUST be created by the frontend store immediately after project creation.

#### Scenario: Default column fallback
- GIVEN the admin has not configured `default_columns` in app settings
- WHEN a project is created
- THEN the store MUST use the hardcoded fallback: To Do (order 0), In Progress (order 1, WIP 3), Review (order 2, WIP 2), Done (order 3, type "done")

#### Scenario: Default column configuration
- GIVEN an admin has set `default_columns` in Planix admin settings
- WHEN a project is created
- THEN the store MUST read the admin setting and use that column definition instead of the fallback

---

### Requirement: i18n Coverage [MVP]

All user-visible strings in project-related components MUST be wrapped in `t('planix', '...')`.

#### Scenario: Translation completeness
- GIVEN a developer adds a new user-visible string to any project component
- THEN the string MUST be added to both `l10n/en.json` and `l10n/nl.json` before the change is merged
- AND the Dutch translation MUST be a human-readable translation (not a placeholder copy of English)

---

### Requirement: Loading and Error States [MVP]

All async operations in the project store MUST surface loading and error states to the UI.

#### Scenario: Loading state on list fetch
- GIVEN the user navigates to `/projects`
- WHILE the OpenRegister API call is in-flight
- THEN `CnListViewLayout` MUST show a skeleton/loading state
- AND the "New project" button MUST remain enabled during loading

#### Scenario: Error state on list fetch
- GIVEN the OpenRegister API returns an error
- WHEN the project list tries to render
- THEN the view MUST show `NcEmptyContent` with an error message and a "Retry" button
- AND the error MUST be logged to the browser console (not swallowed silently)

#### Scenario: Error state on project create
- GIVEN the user submits the creation dialog
- WHEN the OpenRegister API returns an error
- THEN a `NcToast` error notification MUST be shown
- AND the dialog MUST remain open with the user's input preserved

---

### Requirement: OpenRegister Gate [MVP]

(Confirmed as per base spec — implementation detail added here.)

The OpenRegister gate check MUST occur in the App root component (`App.vue`) before any project view is rendered. This is already specified in the base spec; this delta confirms the implementation location.

#### Scenario: OpenRegister absent
- GIVEN `App.vue` checks for OpenRegister on mount
- WHEN OpenRegister is not installed
- THEN the entire app MUST render `NcEmptyContent` (no sidebar, no navigation) with:
  - Title: "OpenRegister is required"
  - Description: "Planix requires OpenRegister to store its data."
  - Action button (admin only): "Install OpenRegister" linking to the Nextcloud App Store

---

## CONFIRMED (unchanged from base spec)

The following base spec requirements are implemented as-is by this change:

- Project Lifecycle: create, read, update, archive — all via `useObjectStore` PATCH/POST
- Member Management: add, remove, leave — all via PATCH on `members[]` array
- Project Deletion: cascade delete (tasks, columns, timeEntries) initiated by frontend store
- Non-member access control: frontend filters; OpenRegister RBAC enforces at API level
- `caseReference` field: stored and displayed as read-only in the settings sidebar (no bridge UI in this change)
- Procest bridge label (`[Case: {caseNumber}]`): rendered as a read-only label chip if present; bridge creation logic deferred to `procest-integration`
