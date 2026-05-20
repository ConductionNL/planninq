# Tasks: Projects Specification

## Deduplication Check

- [ ] Verify no overlap with existing OpenRegister `ObjectService`, `useObjectStore`, `CnListViewLayout`, `CnObjectSidebar`, or `useListView` before writing any custom code — document findings (expected: no overlap; all CRUD, search, and sidebar features are provided by the platform)

---

## Seed Data

- [ ] Add 5 seed `project` objects to `lib/Settings/planix_register.json` under `components.objects[]` using `@self` envelope (`register: "planix"`, `schema: "project"`, unique `slug`) — use Dutch realistic values (see `design.md` for the 5 objects: Webportaal Gemeente Utrecht, API Koppeling Kadaster, Interne Tooling v2, Burgerzaken Digitalisering, Security Audit 2025)
- [ ] Confirm seed import is idempotent: re-running `importFromApp()` skips objects already matched by slug

---

## Pinia Stores

- [ ] Create `src/store/modules/projects.js` using `createObjectStore('projects')` with `useObjectStore` registered against schema `project`, register `planix` — expose `fetchProjects`, `createProject`, `updateProject`, `deleteProject`, `archiveProject` actions
- [ ] Create `src/store/modules/columns.js` using `createObjectStore('columns')` registered against schema `column`, register `planix` — expose `createDefaultColumns(projectId)` action that:
  - Reads `default_columns` from admin settings (falls back to hardcoded: To Do order 0, In Progress order 1 WIP 3, Review order 2 WIP 2, Done order 3 type `done`)
  - POSTs each column sequentially; catches partial failures and shows a non-blocking `NcToast` warning
- [ ] Ensure all store actions wrap `await` calls in `try/finally` for `loading` flag and `try/catch` for `error` state

---

## OpenRegister Gate (App.vue)

- [ ] In `App.vue` `created()`, call the settings API and check the `openRegisters` flag
- [ ] When `openRegisters` is `false`, render `NcEmptyContent` (no sidebar, no navigation) with:
  - Title: `t('planix', 'OpenRegister is required')`
  - Description: `t('planix', 'Planix requires OpenRegister to store its data.')`
  - Action button (admin only, `isAdmin` from settings): `t('planix', 'Install OpenRegister')` linking to the Nextcloud App Store
- [ ] When `openRegisters` is `true`, render the full app layout (MainMenu + `NcAppContent` + `<router-view />`)

---

## Project List View

- [ ] Create `src/views/ProjectList.vue` using `CnListViewLayout` + `useListView` composable
  - Fetch projects filtered by `members` contains current user UID on mount
  - Each list item shows: color swatch, icon, title, member count, status badge
- [ ] Implement client-side search filtering by `title` and `description`, debounced at 300 ms (via `useListView`)
- [ ] Implement status filter chips: `Active`, `Archived`, `Completed` (filter state managed by `useListView`)
- [ ] Show `NcEmptyContent` with title `t('planix', 'No projects yet')` and action button `t('planix', 'Create your first project')` when user has zero projects
- [ ] Show `NcEmptyContent` with error message and "Retry" button when the OpenRegister API returns an error; log the error to the browser console
- [ ] Show skeleton/loading state in `CnListViewLayout` while fetch is in-flight; keep "New project" button enabled during loading

---

## Project Creation Dialog

- [ ] Create `src/dialogs/ProjectCreationDialog.vue` as an `NcDialog` modal (NOT a route)
  - Fields: `title` (required), `description`, `color` (hex picker), `icon` (emoji or MDI name)
  - Inline validation: show `t('planix', 'Title is required')` and disable submit button when title is empty
  - Loading state: spinner on submit button; dialog NOT closeable while save is in-flight
- [ ] On successful project POST, call `createDefaultColumns(newProjectId)` from the columns store
- [ ] On column creation partial failure, show a non-blocking `NcToast` warning; do NOT block project access
- [ ] On full success, close dialog and navigate router to `/projects/{newId}`
- [ ] On OpenRegister API error during project POST, show `NcToast` error and keep dialog open with user input preserved
- [ ] Register `ProjectCreationDialog.vue` in the project list view and open it when "New project" is clicked

---

## Project Settings Sidebar

- [ ] Create `src/sidebars/ProjectSettingsSidebar.vue` as a `CnObjectSidebar`
  - **Details section**: editable fields for `title`, `description`, `color`, `icon`, `defaultAssignee`
  - **Members section**: list of current members with remove action; Nextcloud user search to add new members
  - **Danger Zone section**: "Archive project" and "Delete project" buttons — each requires a confirmation step
- [ ] On save in Details section, PATCH the full project object (include all existing fields, especially `members`) to prevent data loss
- [ ] Reflect updated `title`, `color`, `icon` immediately in the page header, breadcrumb, and project list cache (no full page reload)
- [ ] Open settings sidebar from gear icon in the project board page header

---

## Member Management

- [ ] Add member: use `NcSelect` with Nextcloud user search; on select, PATCH project with updated `members` array; added user gains immediate access
- [ ] Remove member: before PATCH, count tasks where `assignedTo == removedUserUID` in the project; if count > 0, show warning: `t('planix', '{user} has {n} assigned tasks in this project')` — proceed on confirm; tasks remain assigned after removal
- [ ] Leave project: show `NcDialog` confirmation; if user is the last member, show additional warning: `t('planix', 'You are the last member. Leave anyway?')` before confirming

---

## Project Lifecycle Operations

- [ ] **Archive**: PATCH project `status` to `archived` from Danger Zone; hidden from default project list; accessible via Archived filter
- [ ] **Delete**: show confirmation dialog stating `t('planix', 'This will permanently delete {n} tasks and all their time entries. This cannot be undone.')` — on confirm, delete project, all tasks, all linked TimeEntries, and all columns; remove from all members' lists

---

## i18n

- [ ] Wrap all user-visible strings in project components with `t('planix', '...')`
- [ ] Add all new keys to `l10n/en.json` with English values
- [ ] Add all new keys to `l10n/nl.json` with human-readable Dutch translations (not English copies)
- [ ] Verify no untranslated hardcoded strings remain in any project component before merging

---

## Router Integration

- [ ] Register the `/projects` route pointing to `ProjectList.vue` in `src/router/index.js`
- [ ] Register the `/projects/:id` route pointing to `ProjectBoard.vue` (or confirm it exists) with `projectId` prop from route params
- [ ] Add `Projects` navigation item to `MainMenu.vue` using `:to` prop (NOT `@click` + `$router.push`)

---

## Testing

- [ ] Verify project creation end-to-end: create project → 4 default columns created → router navigates to `/projects/{id}`
- [ ] Verify member access control: non-member receives 403; project absent from non-member list
- [ ] Verify archive flow: project disappears from Active list; appears under Archived filter; tasks intact
- [ ] Verify delete flow: confirmation dialog shows correct task count; project + tasks + columns + TimeEntries deleted
- [ ] Verify OpenRegister gate: with OpenRegister absent, app shows `NcEmptyContent`; admin sees install button
- [ ] Verify PATCH preserves `members` array: edit title/color, confirm `members` unchanged in OpenRegister response
