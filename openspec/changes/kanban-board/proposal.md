# Change Proposal: kanban-board

**Change ID:** kanban-board
**Status:** proposed
**Created:** 2026-04-02
**Author:** Conduction Development Team

---

## Why

The `projects` change gives users a project container, and the `tasks` change delivers full task CRUD with a `TaskCard` component and a flat backlog list. However, there is currently no way to visualise the flow of work across stages. A kanban board is the defining UX pattern for workflow management: every comparable tool (Jira, Linear, Trello, Taiga, Wekan) leads with it.

Without a board view, Planix is a task list — useful but not compelling. Users cannot see at a glance where work is stalled, which stages are overloaded, or how close the team is to shipping. The kanban board transforms Planix from a task tracker into a workflow management tool.

This change builds the complete board interaction layer: draggable columns, drag-and-drop card movement with optimistic UI, WIP limit warnings, a board filter bar, a view toggle (kanban ↔ list), column management (create, rename, reorder, delete with task migration), and backlog panel integration. It is the most UX-complex feature in the Planix MVP.

---

## What Changes

Build the Vue frontend and npm dependency needed to:

1. **KanbanBoard.vue** — board container view at route `/projects/:id`. Renders columns horizontally with CSS Grid and overflow-x scroll. Hosts the filter bar, view toggle, and column management controls. Handles optimistic drag-and-drop across columns.
2. **KanbanColumn.vue** — individual column component with a colour-strip header (title, task count, WIP indicator), a drag-drop target zone using `vuedraggable`, a column actions menu (rename, set WIP limit, delete), and an empty-column CnEmptyState with "+ Add task".
3. **KanbanFilterBar.vue** — filter chip row for Assignee, Label, and Priority. Syncs active filters to the URL query string for shareable views. Preserves filters when switching between kanban and list views.
4. **ViewToggle.vue** — toolbar toggle button (kanban ↔ list). Persists selected view in the URL hash. When in list view, renders `ProjectTaskList.vue` (sortable table: title, assignee, due date, status, priority, labels). Switching views preserves filters.
5. **ColumnCreateDialog.vue** — `NcDialog` for creating a column (title + optional WIP limit + optional colour).
6. **ColumnRenameDialog.vue** — `NcDialog` for renaming a column and adjusting its WIP limit.
7. **ColumnDeleteDialog.vue** — `NcDialog` for deleting a column that has tasks. Prompts: "Move to backlog" / "Move to column: [select]" / "Cancel". Blocks deletion until a destination is confirmed.
8. **ProjectTaskList.vue** — list view rendered inside the board route. Uses `CnDataTable` with columns: Title, Assignee, Due Date, Status, Priority, Labels. Sortable by each column. Row click navigates to `/tasks/:id`; back returns to list view.
9. **Column store** — `useColumnsStore` wraps `useObjectStore('planix', 'column')`. Exposes CRUD, reorder, and project-scoped column fetch.
10. **BacklogPanel integration** — collapsible left sidebar showing tasks where `column === null` for the current project. Tasks can be dragged from the backlog panel into any board column.
11. **npm dependency** — add `vuedraggable` (Vue 2 SortableJS wrapper) to `package.json`.
12. **i18n strings** — all user-visible strings added to `l10n/en.json` and `l10n/nl.json`.

---

## Capabilities

### Modified Capabilities

- **`kanban-board`** — implementing the full kanban board spec defined in `openspec/specs/kanban-board.md`. This change brings the capability from spec-only to fully implemented: board rendering, drag-and-drop with optimistic UI, WIP limit warnings, board filters (URL-synced), view toggle, column management, backlog panel integration, and keyboard accessibility (WCAG AA).

No new capabilities are introduced. The `kanban-board` capability was declared in the spec; this change delivers the complete interaction layer.

---

## Impact

### Files Changed

| File | Change |
|------|--------|
| `src/views/ProjectBoard.vue` | Modified — implement board container (was placeholder from `projects` change) |
| `src/components/kanban/KanbanBoard.vue` | New — board layout component (CSS Grid, column list, drag state) |
| `src/components/kanban/KanbanColumn.vue` | New — column with header, drag zone, WIP indicator, actions |
| `src/components/kanban/KanbanFilterBar.vue` | New — filter chip bar with URL sync |
| `src/components/kanban/ViewToggle.vue` | New — kanban ↔ list toggle in project toolbar |
| `src/components/kanban/BacklogPanel.vue` | New — collapsible left sidebar showing backlog tasks |
| `src/views/ProjectTaskList.vue` | New — list view rendered inside the board route |
| `src/components/dialogs/ColumnCreateDialog.vue` | New — create column dialog |
| `src/components/dialogs/ColumnRenameDialog.vue` | New — rename/WIP-limit dialog |
| `src/components/dialogs/ColumnDeleteDialog.vue` | New — delete column with task migration dialog |
| `src/store/columns.js` | New — Pinia column store (`useColumnsStore`) |
| `src/router/index.js` | Modified — confirm `/projects/:id` renders `ProjectBoard.vue` with list-view sub-route |
| `package.json` | Modified — add `vuedraggable` dependency |
| `l10n/en.json` | Modified — add kanban board translation strings |
| `l10n/nl.json` | Modified — add Dutch translations for all kanban strings |

### Risk

Medium. The drag-and-drop logic and optimistic UI with rollback are the highest-risk areas. SortableJS/vuedraggable is mature and well-tested, but integrating it with Pinia optimistic state requires careful sequencing (clone pre-move state → apply → API call → revert on failure). The column delete dialog involves a multi-step data migration (reassign tasks) before the column is deleted — incorrect sequencing could leave orphaned tasks.

The `ProjectBoard.vue` view modifies a placeholder installed by the `projects` change; this is a controlled replacement with no shared state.

### Dependencies

- `register-schemas` must be applied first (Column schema must exist in OpenRegister).
- `projects` must be applied first (`ProjectBoard.vue` placeholder and project store must be in place; project route `/projects/:id` must be registered).
- `tasks` must be applied first (`TaskCard` component must exist with its stable `{ task, compact }` prop interface; task store `useTasksStore` must be available).
- `vuedraggable` npm package must be installed (`npm install vuedraggable`).
- `@conduction/nextcloud-vue` must export `CnEmptyState`, `CnDataTable`, `useObjectStore` (already declared in `package.json`).
- OpenRegister `^v0.2.10` (already declared).
