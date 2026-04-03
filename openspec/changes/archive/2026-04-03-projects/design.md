# Design: projects

**Change ID:** projects
**Status:** draft
**Created:** 2026-04-02

---

## Context

The `register-schemas` change defined the Project schema in `planix_register.json` and loaded 3 seed projects into OpenRegister. Planix is a thin client: it owns no database tables and performs all data operations through the OpenRegister REST API. This change builds the Vue frontend and minimal PHP routing needed to make projects usable.

All UI components follow the `@conduction/nextcloud-vue` shared library patterns used across Conduction apps: `CnListViewLayout` for list pages, `CnDetailPage` + `CnObjectSidebar` for detail/settings pages, and `useObjectStore` for all OpenRegister data operations.

---

## Goals

- Project list view with search and status filter.
- Project creation dialog (title required; description, color, icon optional).
- Project detail route (`/projects/:id`) with a board placeholder shell and settings sidebar.
- Member management (add/remove members, leave project) within the settings sidebar.
- Default column creation (4 columns) when a new project is created.
- MainMenu navigation entry and Vue Router routes for all project views.
- Full i18n coverage (en + nl).

## Non-Goals

- Task CRUD (separate `tasks` change).
- Kanban board implementation (separate `kanban-board` change).
- Time tracking (separate `time-tracking` change).
- Backlog view implementation beyond a route placeholder (separate `backlog` change).
- Procest bridge logic — `caseReference` field is stored but no bridge UI is built here (separate `procest-integration` change).
- PHP controllers for project data — OpenRegister is queried directly from the frontend.

---

## Decisions

### Decision 1: Frontend-initiated default column creation

**Options considered:**
1. PHP hook: a `ProjectCreated` event listener creates columns server-side (chosen for `kanban-board`, not here).
2. Frontend-initiated: the Pinia store creates columns after the project is saved (chosen for this change).

**Rationale:** Planix has no PHP controller for projects — data flows directly between the Vue store and OpenRegister. Adding a PHP event listener would require a new PHP layer not budgeted for this change. The frontend can perform sequential `POST /objects` calls to create each column after the project is created. If a column creation call fails, the user sees an error toast and can retry (columns are idempotent on title+project+order). The `kanban-board` change may add a server-side fallback.

### Decision 2: Members managed as an array field on the project object

**Options considered:**
1. Separate membership entity (e.g. `projectMember` schema).
2. `members[]` string array on the project object (chosen).

**Rationale:** The data model already defines `members` as a string array of Nextcloud UIDs on the project object. A separate entity adds complexity (separate schema, separate store, extra API calls) for no benefit at MVP scale. Array manipulation (add/remove) is a single `PATCH` to the project object.

### Decision 3: Access control — frontend filter + OpenRegister RBAC

**Options considered:**
1. PHP middleware gate.
2. Frontend filters by membership; OpenRegister RBAC enforces at API level (chosen).

**Rationale:** OpenRegister enforces its own access control. The frontend additionally filters the project list to show only projects where `currentUser` is in `members[]`. This gives a clean UX (non-member projects never appear) and the OpenRegister layer provides security enforcement. A dedicated PHP gate is not warranted at MVP — it would duplicate OpenRegister's own enforcement.

### Decision 4: Project list uses `CnListViewLayout` with `useListView`

The project list follows the standard Conduction list-view pattern:
- `useObjectStore('planix', 'project')` fetches all project objects from OpenRegister.
- `useListView` manages search string, active filters (status), and pagination state.
- `CnListViewLayout` renders the header (search bar, filter chips, action button) and the item list.
- Each list item is a `ProjectListItem` sub-component showing color swatch, icon, title, member count, and status badge.

### Decision 5: Project detail uses `CnDetailPage` + `CnObjectSidebar`

The `/projects/:id` route renders `ProjectBoard.vue` which:
- Uses `CnDetailPage` as the outer shell (header with project title/icon/color, breadcrumb).
- Renders a `ProjectBoard` content area (placeholder "Board coming soon" until the `kanban-board` change).
- Opens `ProjectSettingsSidebar.vue` (a `CnObjectSidebar`) via a gear icon in the page header.
- The sidebar has sections: Details (title, description, color, icon), Members, Danger Zone (archive, delete).

### Decision 6: Default column configuration sourced from admin settings

The 4 default columns are defined in the admin settings key `default_columns` (as JSON). The store reads this setting at project creation time. If the setting is absent, the hardcoded fallback is:

| Title | Order | WIP Limit | Type |
|-------|-------|-----------|------|
| To Do | 0 | — | active |
| In Progress | 1 | 3 | active |
| Review | 2 | 2 | active |
| Done | 3 | — | done |

### Decision 7: `ProjectBoard.vue` renders a placeholder until `kanban-board`

The `/projects/:id` route must exist so navigation works. `ProjectBoard.vue` renders a `NcEmptyContent` placeholder ("Board view coming soon — tasks visible in Backlog") with a link to `/projects/:id/backlog`. This allows the projects change to be merged and tested independently of the kanban board.

---

## Component Architecture

```
src/
  views/
    ProjectList.vue          # /projects — CnListViewLayout + useListView
    ProjectBoard.vue         # /projects/:id — CnDetailPage + board placeholder
    ProjectBacklog.vue       # /projects/:id/backlog — backlog placeholder
  components/
    ProjectListItem.vue      # Single row in the project list
    dialogs/
      ProjectCreationDialog.vue   # NcDialog modal for creating a project
      ProjectDeleteDialog.vue     # Confirmation dialog with task count
      ProjectLeaveDialog.vue      # Last-member leave warning
    ProjectSettingsSidebar.vue    # CnObjectSidebar with Details / Members / Danger Zone
    MemberSearch.vue              # User search input using NC user-picker pattern
  store/
    projects.js              # Pinia store (useObjectStore wrapper)
  router/
    index.js                 # Modified — add project routes
  navigation/
    MainMenu.vue             # Modified — add Projects nav entry
```

---

## Pinia Store: `useProjectsStore`

```js
// src/store/projects.js
import { defineStore } from 'pinia'
import { useObjectStore } from '@conduction/nextcloud-vue'

export const useProjectsStore = defineStore('projects', () => {
  const objectStore = useObjectStore('planix', 'project')

  // State
  const projects = ref([])
  const activeProject = ref(null)
  const loading = ref(false)
  const error = ref(null)

  // Actions
  async function fetchProjects(filters = {}) { ... }
  async function fetchProject(id) { ... }
  async function createProject(data) {
    // 1. POST project object
    // 2. Add current user to members[]
    // 3. Create default columns (sequential POSTs to 'column' schema)
  }
  async function updateProject(id, data) { ... }
  async function archiveProject(id) { ... }   // PATCH status: 'archived'
  async function deleteProject(id) {
    // 1. Fetch all tasks for project, delete each
    // 2. Fetch all columns for project, delete each
    // 3. Fetch all timeEntries for tasks, delete each
    // 4. Delete project
  }
  async function addMember(projectId, userUid) { ... }
  async function removeMember(projectId, userUid) { ... }
  async function leaveProject(projectId) { ... }

  return { projects, activeProject, loading, error,
           fetchProjects, fetchProject, createProject, updateProject,
           archiveProject, deleteProject, addMember, removeMember, leaveProject }
})
```

---

## Vue Router Routes

| Path | Name | Component | Notes |
|------|------|-----------|-------|
| `/projects` | `Projects` | `ProjectList` | Default project list |
| `/projects/:id` | `ProjectBoard` | `ProjectBoard` | Board shell (placeholder) |
| `/projects/:id/backlog` | `ProjectBacklog` | `ProjectBacklog` | Backlog placeholder |

---

## PHP Routing

One catch-all entry in `appinfo/routes.php` ensures the Nextcloud server serves the SPA shell for all project paths:

```php
['name' => 'page#projects', 'url' => '/projects', 'verb' => 'GET'],
['name' => 'page#project', 'url' => '/projects/{id}', 'verb' => 'GET'],
['name' => 'page#project_backlog', 'url' => '/projects/{id}/backlog', 'verb' => 'GET'],
```

---

## i18n String Inventory

All strings are passed through `t('planix', '...')`. Key strings:

| Key context | Example string |
|-------------|---------------|
| Navigation | `Projects` |
| List header | `My Projects`, `New project` |
| Empty list | `No projects yet`, `Create your first project to get started` |
| Create dialog title | `Create project` |
| Create dialog fields | `Title`, `Description`, `Color`, `Icon` |
| Create dialog actions | `Create`, `Cancel` |
| Archive action | `Archive project` |
| Delete action | `Delete project` |
| Delete confirm | `This will permanently delete {count} tasks and all their time entries. This cannot be undone.` |
| Member section | `Members`, `Add member`, `Remove`, `Leave project` |
| Remove warning | `{name} has {count} assigned tasks in this project` |
| Leave last-member | `You are the last member. Leave anyway?` |
| Status filter | `Active`, `Archived`, `Completed` |
| Board placeholder | `Board view coming soon`, `View Backlog` |
| OpenRegister missing | `OpenRegister is required`, `Install OpenRegister` |
| Error states | `Failed to load projects`, `Failed to create project`, `Failed to delete project` |

---

## Risks and Trade-offs

| Risk | Likelihood | Mitigation |
|------|-----------|-----------|
| Column creation partially fails on project create | Low | Each column POST is attempted; failures are collected and shown as a warning toast. The project itself is created successfully. User can retry via project settings. |
| Cascade delete is slow for large projects | Low | MVP projects are small. Delete shows a loading state. Future: batch delete API in OpenRegister. |
| OpenRegister member filtering relies on `members` array query | Medium | Confirm OpenRegister supports `?members[]=currentUser` filter. Fallback: fetch all projects and filter client-side (acceptable at MVP scale). |
| `useObjectStore` API surface may change between `@conduction/nextcloud-vue` versions | Low | Pin to a specific minor version in `package.json`; bump deliberately. |
