# Change Proposal: projects

**Change ID:** projects
**Status:** proposed
**Created:** 2026-04-02
**Author:** Conduction Development Team

---

## Why

The `register-schemas` change established the Project schema in OpenRegister, and 3 seed projects are already loadable on a fresh install. However, there is no frontend or backend to interact with them — Planix currently renders a blank app shell with no navigation items and no usable views.

Projects are the foundational entity in Planix: every kanban board, task, and time entry belongs to a project. Nothing else in the roadmap is usable until users can create, view, and manage projects. This change builds the minimum viable project management surface so the rest of the roadmap can be developed and tested in a real, working UI.

---

## What Changes

Build the Vue frontend and minimal PHP backend wiring needed to:

1. **Project list view** — display all projects the current user is a member of, using `CnListViewLayout` with search, filter by status, and a "New project" action.
2. **Project creation dialog** — modal form (title required, description/color/icon optional) that creates a project in OpenRegister and auto-generates default columns.
3. **Project detail / board placeholder** — route `/projects/:id` renders a `ProjectBoard` view shell (placeholder content until the `kanban-board` change) with a `CnObjectSidebar` for project settings.
4. **Project settings sidebar** — edit title, description, color, icon; manage members (add/remove); archive and delete actions.
5. **Pinia project store** — wraps `useObjectStore` for the `project` schema; exposes `fetchProjects`, `createProject`, `updateProject`, `deleteProject`, `archiveProject`, `addMember`, `removeMember`.
6. **Default column creation** — on project creation, the store calls OpenRegister to create the 4 default columns (To Do, In Progress [WIP 3], Review [WIP 2], Done) in sequence.
7. **MainMenu navigation** — `Projects` entry in `MainMenu.vue` linking to `/projects`; active state follows Vue Router.
8. **Vue Router routes** — add `/projects`, `/projects/:id`, and `/projects/:id/backlog` routes to the router.
9. **PHP routing** — add `appinfo/routes.php` entry to serve the SPA shell for all `/projects*` paths.
10. **i18n strings** — all user-visible strings added to `l10n/en.json` and `l10n/nl.json`.

---

## Capabilities

### Modified Capabilities

- **`projects`** — implementing the full project lifecycle defined in the spec (`openspec/specs/projects.md`). This change brings the capability from spec-only to fully implemented (list, create, read, update, archive, delete, member management).

No new capabilities are introduced. The `projects` capability was declared in the spec and partially prepared by `register-schemas`; this change completes it.

---

## Impact

### Files changed

| File | Change |
|------|--------|
| `src/views/ProjectList.vue` | New — project list view using `CnListViewLayout` |
| `src/views/ProjectBoard.vue` | New — project board shell (placeholder until `kanban-board`) |
| `src/views/ProjectBacklog.vue` | New — project backlog view shell |
| `src/store/projects.js` | New — Pinia store for project CRUD and member management |
| `src/components/dialogs/ProjectCreationDialog.vue` | New — project creation modal |
| `src/components/ProjectSettingsSidebar.vue` | New — sidebar for project settings and member management |
| `src/router/index.js` | Modified — add `/projects`, `/projects/:id`, `/projects/:id/backlog` routes |
| `src/navigation/MainMenu.vue` | Modified — add Projects navigation entry |
| `appinfo/routes.php` | Modified — add SPA catch-all route for `/projects*` |
| `l10n/en.json` | Modified — add all project-related translation strings |
| `l10n/nl.json` | Modified — add Dutch translations for all project strings |

### Risk

Low. This change is additive: new Vue components, a new Pinia store, and new routes. No existing functionality is modified except `MainMenu.vue`, `router/index.js`, and `appinfo/routes.php`, which receive purely additive entries.

### Dependencies

- `register-schemas` change must be applied first (Project schema must exist in OpenRegister).
- `@conduction/nextcloud-vue` must export `CnListViewLayout`, `CnDetailPage`, `CnObjectSidebar`, `useObjectStore`, `useListView`, `useDetailView` (already declared in `package.json`).
- OpenRegister `^v0.2.10` (already declared).
