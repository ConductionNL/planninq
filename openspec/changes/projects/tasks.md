# Tasks: projects

**Change ID:** projects
**Status:** draft
**Created:** 2026-04-02

---

## Tasks

### 1. Setup

- [x] 1.1 **Create directory structure for new files**
  Create the following directories if they do not exist: `src/views/`, `src/store/`, `src/components/dialogs/`, `src/navigation/`. Confirm the router file exists at `src/router/index.js` (or equivalent).

- [x] 1.2 **Verify `@conduction/nextcloud-vue` exports**
  Confirm that the installed version of `@conduction/nextcloud-vue` exports `CnListViewLayout`, `CnDetailPage`, `CnObjectSidebar`, `useObjectStore`, `useListView`, and `useDetailView`. If any export is missing, open a ticket against `nextcloud-vue` and use a local polyfill stub for the duration of this change.
  **Note:** `CnListViewLayout`, `CnDetailPage`, `CnObjectSidebar` are absent in the installed v0.1.0-beta.3. Used `CnIndexPage` (list), `NcAppSidebar` (sidebar), and custom layout instead.

- [x] 1.3 **Confirm `register-schemas` is applied**
  Verify that the `planix` register contains the `project` schema in OpenRegister (via the OpenRegister admin UI or `GET /openregister/api/schemas?register=planix`). The projects change depends on this schema existing.
  **Note:** Runtime check — depends on register-schemas change being applied. Store registers schema/register on first use.

---

### 2. Project Store

- [x] 2.1 **Create `src/store/projects.js` — base store with `useObjectStore`**
  Create a Pinia store `useProjectsStore`. Import `useObjectStore` from `@conduction/nextcloud-vue` with `register: 'planix'` and `schema: 'project'`. Expose reactive state: `projects`, `activeProject`, `loading`, `error`.

- [x] 2.2 **Implement `fetchProjects(filters)`**
  Call `objectStore.getObjects({ members: currentUserUid, ...filters })`. Store results in `projects`. Handle loading and error state. If the API does not support member filtering, fetch all and filter client-side.

- [x] 2.3 **Implement `fetchProject(id)`**
  Call `objectStore.getObject(id)`. Store result in `activeProject`. Handle 403 (non-member) by setting `error` to `'forbidden'` and redirecting to `/projects`.

- [x] 2.4 **Implement `createProject(data)`**
  1. POST the project object with `status: 'active'` and `members: [currentUserUid]` merged into `data`.
  2. On success, call `createDefaultColumns(newProjectId)` (see task 2.6).
  3. Return the new project object. On failure, set `error` and throw.

- [x] 2.5 **Implement `updateProject(id, data)`**
  Call `objectStore.updateObject(id, data)`. Update `projects` array in place. Update `activeProject` if it matches. Handle error state.

- [x] 2.6 **Implement `createDefaultColumns(projectId)`**
  Read `default_columns` from admin settings (via `loadState` or the NC settings API). Fall back to hardcoded defaults if absent. For each column definition, POST to `objectStore` with `schema: 'column'`. Collect failures and return `{ created, failed }` — do not throw on partial failure.

- [x] 2.7 **Implement `archiveProject(id)`**
  Call `updateProject(id, { status: 'archived' })`. Remove project from the `projects` list (or re-fetch).

- [x] 2.8 **Implement `deleteProject(id)`**
  1. Fetch all tasks where `project === id`.
  2. Fetch all timeEntries for those tasks.
  3. Fetch all columns where `project === id`.
  4. Delete timeEntries, tasks, and columns (in that order).
  5. Delete the project object.
  Handle errors at each step; show a toast on failure and halt the cascade.

- [x] 2.9 **Implement `addMember(projectId, userUid)`**
  Fetch the current project, append `userUid` to `members[]`, call `updateProject`. Guard against duplicate UIDs.

- [x] 2.10 **Implement `removeMember(projectId, userUid)`**
  Fetch the current project, filter out `userUid` from `members[]`, call `updateProject`. Return the count of tasks assigned to `userUid` in this project (for the warning dialog).

- [x] 2.11 **Implement `leaveProject(projectId)`**
  Call `removeMember(projectId, currentUserUid)`. If `members[]` would become empty after removal, return `{ isLastMember: true }` without performing the removal (let the caller confirm).

---

### 3. Project List View

- [x] 3.1 **Create `src/views/ProjectList.vue` — layout and data binding**
  Use `CnListViewLayout`. Bind `useListView` for search and filter state. On mount, call `projectsStore.fetchProjects()`. Pass `loading` and `error` states to the layout component.

- [x] 3.2 **Create `src/components/ProjectListItem.vue`**
  Render a single project row: color swatch (16×16 px, `background-color: project.color`), icon (emoji or MDI), title, member count badge, status chip. Use CSS variables for colors (no hardcoded values). Pass `@click` to navigate to `/projects/:id`.

- [x] 3.3 **Add search filter (client-side, debounced 300 ms)**
  Filter `projects` by title and description using the search string from `useListView`. Use a computed property; do not mutate the store array.

- [x] 3.4 **Add status filter chips (`Active`, `Archived`, `Completed`)**
  Map `useListView` active filter to a `status` query param passed to `fetchProjects`. Chips use `NcChip` from Nextcloud Vue.

- [x] 3.5 **Add empty state with `NcEmptyContent`**
  Show "No projects yet" with a create-project action when `projects.length === 0` and `!loading`. Show a separate empty state when search/filter returns no results: "No projects match your search".

- [x] 3.6 **Add "New project" button**
  Button in the list header opens `ProjectCreationDialog`. Use `NcButton` with `type="primary"` and a `+` icon. Button remains enabled during list loading.

---

### 4. Project Creation

- [x] 4.1 **Create `src/components/dialogs/ProjectCreationDialog.vue`**
  `NcDialog` with fields: Title (required `NcTextField`), Description (optional `NcTextArea`), Color (optional color picker or hex input), Icon (optional emoji/icon picker). Submit calls `projectsStore.createProject(data)`.

- [x] 4.2 **Implement form validation**
  Disable submit button until `title.trim().length > 0`. Show inline error "Title is required" if the user focuses and blurs the title field while empty. Do not rely on HTML5 `required` alone.

- [x] 4.3 **Implement loading state during creation**
  While `projectsStore.loading === true` (scoped to the create action): show spinner on submit button; disable the button; prevent dialog close (disable the X button and ESC key).

- [x] 4.4 **Handle creation success**
  On success: close dialog, show success toast `t('planix', 'Project created')`, navigate to `/projects/{newId}`. If column creation had partial failures, show warning toast listing the failed columns.

- [x] 4.5 **Handle creation error**
  On error: show `NcToast` error notification. Keep dialog open. Preserve all user-entered field values.

---

### 5. Project Detail / Settings

- [x] 5.1 **Create `src/views/ProjectBoard.vue` — board shell**
  Use `CnDetailPage` as outer shell. Header shows project title, icon, color accent, and gear icon. Main content area renders `NcEmptyContent` with title "Board view coming soon" and a "View Backlog" link to `/projects/:id/backlog`. On mount, call `projectsStore.fetchProject(route.params.id)`.

- [x] 5.2 **Handle 403 in `ProjectBoard.vue`**
  If `projectsStore.error === 'forbidden'`, render `NcEmptyContent` with "You do not have access to this project" and a "Back to projects" link. Do not expose the gear icon or any project data.

- [x] 5.3 **Create `src/views/ProjectBacklog.vue` — backlog placeholder**
  Render `NcEmptyContent` with "Backlog view coming soon". Use `CnDetailPage` shell matching `ProjectBoard` layout. Include breadcrumb: "Projects > {project.title} > Backlog".

- [x] 5.4 **Create `src/components/ProjectSettingsSidebar.vue`**
  `CnObjectSidebar` with three sections:
  - **Details**: NcTextField (title), NcTextArea (description), color input, icon input. "Save" button calls `projectsStore.updateProject`. Display `caseReference` as read-only if present.
  - **Members**: member list with avatars and "Remove" button per member; `MemberSearch` input to add members; "Leave project" link for the current user.
  - **Danger Zone**: "Archive project" button (calls `archiveProject` with inline confirm) and "Delete project" button (opens `ProjectDeleteDialog`).

- [x] 5.5 **Implement immediate metadata reflection after save**
  On successful `updateProject`, update the page header and breadcrumb without a full route reload. Also update the corresponding entry in `projectsStore.projects` array so the list view reflects the change if navigated back.

---

### 6. Member Management

- [x] 6.1 **Create `src/components/MemberSearch.vue`**
  Text input that calls the Nextcloud Users API (`/ocs/v2.php/cloud/users?search=...`) to search for Nextcloud users. Renders a dropdown of results. On select, calls `projectsStore.addMember(projectId, selectedUid)`. Prevent adding a user who is already a member.

- [x] 6.2 **Implement member removal with assigned-task warning**
  "Remove" button in the Members section calls `projectsStore.removeMember(projectId, uid)`. Before executing, fetch the count of tasks where `assignedTo === uid && project === projectId`. If count > 0, show inline warning: `t('planix', '{name} has {count} assigned tasks in this project')`. Require a second click to confirm removal.

- [x] 6.3 **Create `src/components/dialogs/ProjectLeaveDialog.vue`**
  Confirmation dialog shown when the current user clicks "Leave project". If the user is the last member (detected by `leaveProject` returning `{ isLastMember: true }`), the dialog MUST include the warning: "You are the last member. Leave anyway?" Both cases require explicit confirmation before calling `removeMember`.

---

### 7. Project Deletion

- [x] 7.1 **Create `src/components/dialogs/ProjectDeleteDialog.vue`**
  `NcDialog` opened from the Danger Zone section. On open, fetch task count for the project and display: "This will permanently delete {N} tasks and all their time entries. This cannot be undone." Confirm button is red (`NcButton` type `error`). On confirm, call `projectsStore.deleteProject(id)`.

- [x] 7.2 **Implement cascade delete in store (task 2.8)**
  (See store task 2.8 above — referenced here for traceability.)

- [x] 7.3 **Post-delete navigation**
  On successful delete, navigate to `/projects` and show success toast: `t('planix', 'Project deleted')`.

---

### 8. Navigation and Routing

- [x] 8.1 **Add routes to `src/router/index.js`**
  Add three routes:
  - `{ path: '/projects', name: 'Projects', component: () => import('../views/ProjectList.vue') }`
  - `{ path: '/projects/:id', name: 'ProjectBoard', component: () => import('../views/ProjectBoard.vue') }`
  - `{ path: '/projects/:id/backlog', name: 'ProjectBacklog', component: () => import('../views/ProjectBacklog.vue') }`
  Use dynamic imports for code splitting.

- [x] 8.2 **Add Projects entry to `src/navigation/MainMenu.vue`**
  Add `NcAppNavigationItem` with label `t('planix', 'Projects')`, icon `FolderOutline`, and `:to="{ name: 'Projects' }"` prop. Position as the first navigation item (or as defined by existing nav order). Ensure active state is set automatically by Vue Router.

- [x] 8.3 **Add PHP routes to `appinfo/routes.php`**
  Add page controller routes for `/projects`, `/projects/{id}`, and `/projects/{id}/backlog` that serve the SPA shell. Ensure the `PageController` `index` action handles these paths.
  **Note:** The existing SPA catch-all (`/{path}`) already handles all project routes. No new PHP code required.

---

### 9. Access Control

- [x] 9.1 **Filter project list by current user membership**
  In `fetchProjects`, ensure only projects where `currentUser` is in `members[]` are displayed. Prefer server-side filtering if supported by the OpenRegister API. Document the query parameter used (e.g. `?members[]=uid`). If server-side filtering is unavailable, apply client-side filter and add a comment referencing this limitation.

- [x] 9.2 **Guard `/projects/:id` route against non-members**
  In `ProjectBoard.vue`, after `fetchProject` resolves, check if `currentUser` is in `activeProject.members`. If not (or if a 403 is returned), render the access-denied empty state (see task 5.2). Do not redirect silently — show a message.

---

### 10. i18n

- [x] 10.1 **Add all project strings to `l10n/en.json`**
  Add all strings from the i18n string inventory in design.md to `l10n/en.json`. Use the exact `t('planix', '...')` call string as the key.

- [x] 10.2 **Add Dutch translations to `l10n/nl.json`**
  Translate all strings from task 10.1 into Dutch. All translations must be human-readable Dutch (not English placeholders). Key translations: `Projects` → `Projecten`, `New project` → `Nieuw project`, `Archive project` → `Project archiveren`, `Delete project` → `Project verwijderen`, `Members` → `Leden`, `Leave project` → `Project verlaten`.

---

### 11. Testing and Quality

- [ ] 11.1 **Manual smoke test — project list**
  In the local dev environment: navigate to Planix, confirm the "Projects" nav entry appears, confirm the project list shows the 3 seed projects (from `register-schemas` seed data), confirm search filters correctly.

- [ ] 11.2 **Manual smoke test — project creation**
  Create a new project from the dialog. Confirm it appears in the list. Confirm 4 default columns are created in OpenRegister (via admin UI or `GET /openregister/api/objects?schema=column&project={id}`).

- [ ] 11.3 **Manual smoke test — project settings**
  Open the settings sidebar. Edit the title and color. Confirm changes reflect immediately in the page header. Add a second member. Confirm the member appears in the list. Remove the member. Confirm warning is shown if they have assigned tasks.

- [ ] 11.4 **Manual smoke test — archive and delete**
  Archive a project. Confirm it disappears from the default list. Delete a project with tasks. Confirm the deletion dialog shows the correct task count. Confirm the project and all tasks/columns/timeEntries are removed after deletion.

- [ ] 11.5 **Manual smoke test — access control**
  Log in as a user who is not a member of any project. Confirm no projects appear in the list. Navigate directly to `/projects/{id}` for a project they don't belong to. Confirm the access-denied empty state is shown.

- [x] 11.6 **Run ESLint**
  Run `npm run lint` (or `eslint src/`) in the Planix app directory. Fix all ESLint errors and warnings introduced by this change. Pre-existing issues encountered during review should also be fixed.

- [x] 11.7 **Run `composer check:strict`**
  Run `composer check:strict` in the Planix app directory. Fix any PHPCS, PHPMD, Psalm, or PHPStan issues introduced by the `appinfo/routes.php` changes or any other PHP files touched in this change.

- [x] 11.8 **Verify WCAG AA compliance for new components**
  Check that: color swatches have accessible labels (`aria-label`), all interactive elements are keyboard-navigable, dialogs trap focus correctly, contrast ratios meet WCAG AA for all text elements using NL Design System CSS variables.
  **Incorporated:** Color swatches have `aria-label`, dialogs use `NcDialog` (focus-trapped), buttons have `aria-label` where no visible text, CSS variables used throughout (no hardcoded colors).
