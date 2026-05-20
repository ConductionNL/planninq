# Projects Specification (Delta)

**Status**: in-progress
**Scope**: planix
**OpenSpec changes**:
- [projects](../../) — implements the full project management UI

## Purpose

Implements the project entity as the top-level container for tasks and the kanban board in Planix. Projects group related work, define a team (members), and provide the kanban board (columns) that tasks flow through. Each project has exactly one implicit kanban board. Tasks without a column are in the project's backlog. Projects can optionally be linked to a Procest case via `caseReference`.

---

## ADDED Requirements

### Requirement: REQ-PRJ-001 — Project Lifecycle [MVP]

The system MUST allow authenticated users to create, read, update, and archive projects.

#### Scenario: Create a project
- GIVEN a user is authenticated
- WHEN the user creates a project with a title
- THEN the system MUST store the project with `status` set to `active`
- AND the system MUST create the default column set (To Do, In Progress, Review, Done) as configured in admin settings
- AND the creating user MUST be added as the first entry in `members`

#### Scenario: Archive a project
- GIVEN a project has `status` set to `active`
- WHEN an admin or the project creator archives the project
- THEN the system MUST set the project's `status` to `archived`
- AND the project MUST be hidden from the default project list
- AND all tasks within the project MUST remain accessible via the archived project view

#### Scenario: Edit project metadata
- GIVEN a project creator or admin is viewing a project
- WHEN they edit the `title`, `description`, `color`, or `icon` and save
- THEN the system MUST PATCH the project in OpenRegister with all existing fields preserved (especially `members`)
- AND the updated `title`, `color`, and `icon` MUST be reflected in the sidebar and project list immediately

#### Scenario: Procest bridge — case creates project
- GIVEN the Procest bridge is enabled in admin settings
- WHEN a Procest case creates a linked Planix project
- THEN the project MUST have `caseReference` set to the Procest case UUID
- AND a label `[Case: {caseNumber}]` MUST be applied to the project's `labels` array

#### Scenario: OpenRegister not installed
- GIVEN Planix is installed but OpenRegister is not
- WHEN any user opens Planix
- THEN the system MUST show a centred `NcEmptyContent` (no sidebar, no navigation) with title "OpenRegister is required" and description "Planix requires OpenRegister to store its data."
- AND admin users MUST see an "Install OpenRegister" action button linking to the Nextcloud App Store

---

### Requirement: REQ-PRJ-002 — Member Access Control [MVP]

The system MUST enforce project membership as the authorization boundary for project data.

#### Scenario: Non-member access denied
- GIVEN a project has `members` set to `[userA, userB]`
- WHEN userC (not in `members`) attempts to view the project board
- THEN the system MUST return a 403 Forbidden response
- AND the project MUST NOT appear in userC's project list

#### Scenario: Member gains access on add
- GIVEN a project creator opens the project settings
- WHEN they search for a Nextcloud user and click "Add member"
- THEN the system MUST add the user UID to the project's `members` array
- AND the user MUST immediately be able to access the project board and tasks

---

### Requirement: REQ-PRJ-003 — Member Management [MVP]

The system MUST allow project creators and admins to add and remove members after project creation.

#### Scenario: Remove a member with assigned tasks
- GIVEN a project has `members` set to `[userA, userB]` and userB has tasks assigned
- WHEN the project creator removes userB
- THEN the system MUST remove userB from `members`
- AND userB MUST no longer see the project in their project list
- AND tasks assigned to userB MUST remain assigned to them (no auto-reassignment)
- AND a warning MUST be shown: "userB has N assigned tasks in this project"

#### Scenario: Leave a project
- GIVEN a non-creator project member is viewing a project
- WHEN the member clicks "Leave project"
- THEN the system MUST remove them from `members`
- AND if the user is the last member, the system MUST show a confirmation: "You are the last member. Leave anyway?"

---

### Requirement: REQ-PRJ-004 — Project Deletion [MVP]

The system MUST allow admins and project creators to permanently delete a project and all its associated data.

#### Scenario: Delete a project
- GIVEN a project exists with tasks
- WHEN a Nextcloud admin or the project creator clicks "Delete project" and confirms
- THEN the system MUST delete the project, all its tasks, all linked TimeEntries, and all columns
- AND the project MUST be removed from all members' project lists immediately

#### Scenario: Delete confirmation with task count
- GIVEN a project has N tasks
- WHEN the user initiates project deletion
- THEN the confirmation dialog MUST state: "This will permanently delete {N} tasks and all their time entries. This cannot be undone."

---

### Requirement: REQ-PRJ-005 — Project List UI [MVP]

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
- THEN the list MUST filter in real-time (client-side) by project `title` and `description`
- AND the search input MUST be debounced at 300 ms

#### Scenario: Filter by status
- GIVEN the project list is rendered
- WHEN the user selects a status filter chip (`Active`, `Archived`, `Completed`)
- THEN the list MUST show only projects matching that status
- AND the filter state MUST be managed by `useListView`

#### Scenario: Empty project list
- GIVEN the user is a member of zero projects
- WHEN the list renders
- THEN the view MUST show `NcEmptyContent` with title "No projects yet" and action button "Create your first project"

---

### Requirement: REQ-PRJ-006 — Project Creation Dialog [MVP]

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

### Requirement: REQ-PRJ-007 — Project Settings Sidebar [MVP]

Project settings MUST be accessible via `CnObjectSidebar` within the project detail view.

#### Scenario: Open settings sidebar
- GIVEN the user is on `/projects/:id`
- WHEN the user clicks the gear icon in the page header
- THEN `ProjectSettingsSidebar.vue` MUST open as a `CnObjectSidebar`

#### Scenario: Edit project metadata — immediate reflection
- GIVEN the settings sidebar is open with the Details section active
- WHEN the user edits the `title`, `color`, or `icon` and saves
- THEN the updated values MUST be reflected immediately in the page header and breadcrumb
- AND the project list item (if in cache) MUST also update without a full page reload
- AND the PATCH request MUST include all existing fields (especially `members`) to prevent data loss

#### Scenario: Sidebar Danger Zone
- GIVEN the settings sidebar is open
- WHEN the user scrolls to the "Danger Zone" section
- THEN "Archive project" and "Delete project" actions MUST be shown
- AND both MUST require a confirmation step before executing

---

### Requirement: REQ-PRJ-008 — Default Column Creation [MVP]

Default columns MUST be created by the frontend store immediately after project creation.

#### Scenario: Default column fallback
- GIVEN the admin has not configured `default_columns` in Planix admin settings
- WHEN a project is created
- THEN the store MUST use the hardcoded fallback: To Do (order 0), In Progress (order 1, WIP 3), Review (order 2, WIP 2), Done (order 3, type `done`)

#### Scenario: Default column from admin setting
- GIVEN an admin has set `default_columns` in Planix admin settings
- WHEN a project is created
- THEN the store MUST read the admin setting and use that column definition instead of the fallback

---

### Requirement: REQ-PRJ-009 — Loading and Error States [MVP]

All async operations in the project store MUST surface loading and error states to the UI.

#### Scenario: Loading state on list fetch
- GIVEN the user navigates to `/projects`
- WHILE the OpenRegister API call is in-flight
- THEN `CnListViewLayout` MUST show a skeleton/loading state
- AND the "New project" button MUST remain enabled during loading

#### Scenario: Error state on list fetch
- GIVEN the OpenRegister API returns an error on project list fetch
- WHEN the project list tries to render
- THEN the view MUST show `NcEmptyContent` with an error message and a "Retry" button
- AND the error MUST be logged to the browser console (not swallowed silently)

#### Scenario: Error state on project create
- GIVEN the user submits the creation dialog
- WHEN the OpenRegister API returns an error
- THEN a `NcToast` error notification MUST be shown
- AND the dialog MUST remain open with the user's input preserved

---

### Requirement: REQ-PRJ-010 — OpenRegister Gate [MVP]

The OpenRegister gate check MUST occur in the `App.vue` root component before any project view is rendered.

#### Scenario: OpenRegister absent
- GIVEN `App.vue` checks for OpenRegister on mount
- WHEN OpenRegister is not installed
- THEN the entire app MUST render `NcEmptyContent` (no sidebar, no navigation) with:
  - Title: "OpenRegister is required"
  - Description: "Planix requires OpenRegister to store its data."
  - Action button (admin only): "Install OpenRegister" linking to the Nextcloud App Store

---

### Requirement: REQ-PRJ-011 — i18n Coverage [MVP]

All user-visible strings in project-related components MUST be wrapped in `t('planix', '...')`.

#### Scenario: Translation completeness
- GIVEN a developer adds a new user-visible string to any project component
- THEN the string MUST be added to both `l10n/en.json` and `l10n/nl.json` before the change is merged
- AND the Dutch translation MUST be a human-readable translation (not a placeholder copy of English)

---

## Non-Functional Requirements

- **Performance**: Project list fetch MUST use OpenRegister filtering (`members` contains current user UID) — do not fetch all projects and filter client-side.
- **Accessibility**: All form inputs MUST have associated labels. Color swatches MUST include a text label or aria-label. Badge text MUST be readable by screen readers (WCAG 1.4.1 — color is not the sole indicator).
- **Internationalization**: All user-visible strings MUST support Dutch and English translations (ADR-007). Dutch translations MUST be human-readable, not English copies.
- **Security**: Non-members MUST receive a 403 response from OpenRegister; the frontend MUST also hide projects from non-member lists. Never rely on frontend-only access control.
- **Data integrity**: PATCH requests to update project metadata MUST include the full object to prevent `members` array data loss.

---

## Acceptance Criteria

- [ ] A project can be created with a title; all other fields are optional
- [ ] Creating a project auto-generates default columns (configurable in admin settings, falls back to hardcoded set)
- [ ] The creating user is automatically added as the first project member
- [ ] Non-members cannot view or access a project's board or tasks (403 + hidden from list)
- [ ] Archiving a project removes it from the default list but preserves all tasks
- [ ] The project list shows projects filtered to the current user's memberships, sorted by recent activity
- [ ] Projects are color-coded with the chosen hex color in the sidebar and list
- [ ] The OpenRegister dependency check shows `NcEmptyContent` when OpenRegister is absent
- [ ] `caseReference` links the project back to its Procest case (if applicable); `[Case: N]` label is applied
- [ ] Project `title`, `description`, `color`, and `icon` can be edited; changes reflect immediately in sidebar and list
- [ ] Members can be added by searching Nextcloud users; added members gain immediate board access
- [ ] Removing a member shows a warning if they have assigned tasks; tasks remain assigned after removal
- [ ] A member can leave a project via "Leave project"; last-member warning shown before confirming
- [ ] Deleting a project requires admin or creator permission and a confirmation dialog stating task and entry count
- [ ] Project deletion cascades to all tasks, columns, and TimeEntries
- [ ] All user-visible strings are wrapped in `t('planix', '...')` with Dutch translations in `l10n/nl.json`
- [ ] All async operations surface loading and error states to the UI

---

## Notes

- Project deletion is a destructive operation — requires confirmation dialog and admin or creator permission.
- Procest bridge (V1): the `caseReference` field allows a Procest case to own a Planix project; task completions may mirror back to the case status in a future change.
- Admin setting `default_columns` defines which columns are created when a new project is initialized; hardcoded fallback used when not set.
- Future: project templates (V1) will pre-populate columns, labels, and default tasks from a template.
- Implementation uses `thin-client` + `useObjectStore` architecture: all state is fetched from OpenRegister; no local database tables.
