# Test Plan: projects

## Test Cases

### TC-1: Project list renders for member projects
- **spec_ref**: `openspec/changes/projects/specs/projects/spec.md#requirement-project-list-ui-mvp`
- **type**: functional
- **persona**: any authenticated Nextcloud user who is a project member
- **preconditions**: User is logged in; at least 2 projects exist with the user as a member; each project has a color, icon, title, and member count
- **steps**: Navigate to `/apps/planix/projects`
- **expected result**: `CnListViewLayout` renders; each project item shows color swatch, icon, title, member count, and status badge; search bar and status filter chips are visible
- **test command**: /test-functional

### TC-2: Search filters projects in real time
- **spec_ref**: `openspec/changes/projects/specs/projects/spec.md#scenario-search-projects`
- **type**: functional
- **persona**: any authenticated Nextcloud user
- **preconditions**: Project list is rendered with at least 3 projects with distinct titles
- **steps**: Type a partial title substring into the search bar; wait up to 300 ms (debounce)
- **expected result**: Only projects whose title or description contains the typed string are shown; no page reload occurs
- **test command**: /test-functional

### TC-3: Filter by status shows only matching projects
- **spec_ref**: `openspec/changes/projects/specs/projects/spec.md#scenario-filter-by-status`
- **type**: functional
- **persona**: any authenticated Nextcloud user
- **preconditions**: Project list has projects with statuses Active, Archived, and Completed
- **steps**: Click the "Archived" status filter chip
- **expected result**: Only archived projects are shown; active and completed projects are hidden; the chip is in active/selected state
- **test command**: /test-functional

### TC-4: Empty state when user has no projects
- **spec_ref**: `openspec/changes/projects/specs/projects/spec.md#scenario-empty-project-list`
- **type**: functional
- **persona**: newly created Nextcloud user with zero projects
- **preconditions**: User has no projects (not a member of any)
- **steps**: Navigate to `/apps/planix/projects`
- **expected result**: `NcEmptyContent` is shown with title "No projects yet" and an action button "Create your first project"
- **test command**: /test-functional

### TC-5: Create project — field validation prevents submit without title
- **spec_ref**: `openspec/changes/projects/specs/projects/spec.md#scenario-create-project-field-validation`
- **type**: functional
- **persona**: any authenticated Nextcloud user
- **preconditions**: User is on the project list; creation dialog is open
- **steps**: Click "New project"; leave the title field empty; click the submit button
- **expected result**: Inline validation error "Title is required" is displayed; submit button remains disabled
- **test command**: /test-functional

### TC-6: Create project — default columns are created on success
- **spec_ref**: `openspec/changes/projects/specs/projects/spec.md#scenario-create-project-default-columns-created`
- **type**: functional
- **persona**: any authenticated Nextcloud user
- **preconditions**: User is on the project list; `default_columns` admin setting is not configured (uses fallback)
- **steps**: Open "New project" dialog; enter a valid title; click "Create"
- **expected result**: Dialog closes; router navigates to `/projects/{newId}`; 4 default columns exist on the project board: To Do (order 0), In Progress (order 1, WIP 3), Review (order 2, WIP 2), Done (order 3)
- **test command**: /test-functional

### TC-7: Create project — loading state during creation
- **spec_ref**: `openspec/changes/projects/specs/projects/spec.md#scenario-create-project-loading-state`
- **type**: functional
- **persona**: any authenticated Nextcloud user
- **preconditions**: Creation dialog is open with a valid title
- **steps**: Click "Create"; observe the dialog before the API responds
- **expected result**: Submit button shows loading spinner and is disabled; dialog cannot be closed while saving
- **test command**: /test-functional

### TC-8: Project settings sidebar opens and edits reflect immediately
- **spec_ref**: `openspec/changes/projects/specs/projects/spec.md#scenario-edit-project-metadata-immediate-reflection`
- **type**: functional
- **persona**: project creator / admin
- **preconditions**: User is on `/projects/:id`; the settings sidebar is accessible
- **steps**: Click the gear icon; edit the project title; click Save
- **expected result**: `ProjectSettingsSidebar` opens as `CnObjectSidebar`; updated title is reflected immediately in the page header and breadcrumb without a full reload
- **test command**: /test-functional

### TC-9: Danger Zone — archive and delete require confirmation
- **spec_ref**: `openspec/changes/projects/specs/projects/spec.md#scenario-sidebar-danger-zone`
- **type**: functional
- **persona**: project creator
- **preconditions**: Project settings sidebar is open
- **steps**: Scroll to the "Danger Zone" section; click "Archive project" (observe confirmation step); cancel; click "Delete project" (observe confirmation step); cancel
- **expected result**: Both "Archive project" and "Delete project" buttons are visible; each requires a confirmation step before executing; cancellation leaves the project intact
- **test command**: /test-functional

### TC-10: Default column configuration is used when admin setting is set
- **spec_ref**: `openspec/changes/projects/specs/projects/spec.md#scenario-default-column-configuration`
- **type**: functional
- **persona**: Nextcloud admin
- **preconditions**: Admin has configured `default_columns` in Planix admin settings with a custom column set
- **steps**: Create a new project
- **expected result**: Columns are created matching the admin-configured set, not the hardcoded fallback
- **test command**: /test-functional

### TC-11: OpenRegister gate renders error when OpenRegister is absent
- **spec_ref**: `openspec/changes/projects/specs/projects/spec.md#scenario-openregister-absent`
- **type**: functional
- **persona**: any authenticated Nextcloud user (admin sees Install button)
- **preconditions**: OpenRegister app is not installed or not enabled
- **steps**: Navigate to `/apps/planix`
- **expected result**: The entire app renders `NcEmptyContent` with title "OpenRegister is required" and description text; admin users see an "Install OpenRegister" action button; no sidebar or navigation is rendered
- **test command**: /test-functional

### TC-12: Error state on project list fetch
- **spec_ref**: `openspec/changes/projects/specs/projects/spec.md#scenario-error-state-on-list-fetch`
- **type**: functional
- **persona**: any authenticated Nextcloud user
- **preconditions**: OpenRegister API is unreachable or returns a 500 error
- **steps**: Navigate to `/apps/planix/projects`
- **expected result**: `NcEmptyContent` is shown with an error message and a "Retry" button; the error is logged to the browser console; clicking Retry re-triggers the fetch
- **test command**: /test-functional

### TC-13: Create project error preserves dialog state
- **spec_ref**: `openspec/changes/projects/specs/projects/spec.md#scenario-error-state-on-project-create`
- **type**: functional
- **persona**: any authenticated Nextcloud user
- **preconditions**: OpenRegister returns an error during project creation
- **steps**: Submit the creation dialog with a valid title when the API is set to error
- **expected result**: A `NcToast` error notification is shown; the dialog remains open with the user's title preserved
- **test command**: /test-functional

### TC-14: Partial column creation failure shows non-blocking warning
- **spec_ref**: `openspec/changes/projects/specs/projects/spec.md#scenario-create-project-default-columns-created`
- **type**: functional
- **persona**: any authenticated Nextcloud user
- **preconditions**: Project creation succeeds but one column POST fails
- **steps**: Create a project when the third column POST is set to return an error
- **expected result**: A non-blocking warning toast appears; user is still navigated to the new project; the project is accessible; the successfully created columns are shown
- **test command**: /test-functional

## Coverage Summary

| Requirement | Scenarios Covered | Test Cases |
|-------------|-------------------|------------|
| Project List UI [MVP] | Render, search, filter, empty state | TC-1, TC-2, TC-3, TC-4 |
| Project Creation Dialog [MVP] | Validation, default columns, loading state | TC-5, TC-6, TC-7, TC-13, TC-14 |
| Project Settings Sidebar [MVP] | Open, edit, danger zone | TC-8, TC-9 |
| Default Column Creation [MVP] | Fallback, admin config | TC-6, TC-10 |
| OpenRegister Gate [MVP] | Absent OpenRegister | TC-11 |
| Loading and Error States [MVP] | List error, create error | TC-12, TC-13 |
| i18n Coverage [MVP] | Not covered in browser test (see Out of Scope) | — |

## Out of Scope

- i18n translation completeness check — verified via `composer check:strict` / build-time linting, not a browser test
- Member management (add/remove/leave) — depends on multi-user test setup; covered by API tests
- Cascade delete of tasks and columns on project deletion — deferred to integration/regression tests after `tasks` and `kanban-board` changes are applied
