# Design: kanban-board

**Change ID:** kanban-board
**Status:** pr-created
**Created:** 2026-04-02
**PR:** https://github.com/ConductionNL/planix/pull/83

> **Implementation note:** The MVP shipped in PR #83 implements the kanban board as a
> PHP-controller-backed feature (ColumnController + ColumnService + ProjectBoard.vue)
> rather than the pure-Vue/Pinia approach described below. The full spec remains here
> as the target architecture for future iterations.

---

## Context

The `tasks` change delivered the `TaskCard` component, the task store, and the flat backlog list. The `projects` change installed placeholder views for `ProjectBoard.vue` and `ProjectBacklog.vue`. This change implements the kanban board on top of those foundations.

Planix is a thin client: all data operations go through the OpenRegister REST API via `useObjectStore`. There are no new PHP controllers in this change — all board state (columns, task positions) is read and written through the existing OpenRegister object API from the Vue frontend. PHP routing already handles the `/projects/:id` SPA catch-all (installed by the `projects` change).

---

## Goals

- Render a kanban board with columns (ordered, horizontally scrollable) and `TaskCard` instances per column.
- Drag-and-drop task cards between columns: optimistic update → PATCH task in OpenRegister → revert on failure.
- WIP limit visual warning (orange/red column header + tooltip) — soft limit, not blocking.
- Board filter bar (Assignee, Label, Priority) with URL query string sync for shareable views.
- View toggle (kanban ↔ list) persisted in URL hash; filters preserved across switch.
- Column management: create, rename (+ WIP limit), reorder (drag column headers), delete with task migration dialog.
- Backlog panel: collapsible left sidebar showing `column: null` tasks; drag-to-column moves tasks onto the board.
- Keyboard navigation: Tab through columns, arrow keys within column, Enter to open task detail (WCAG AA).
- Full i18n coverage (en + nl).

## Non-Goals

- TaskCard component definition (delivered by `tasks` change — reused here).
- Task CRUD / task detail view (delivered by `tasks` change).
- Project creation / settings (delivered by `projects` change).
- Swimlanes, collapsed columns, card quick-edit, blocked indicators (all V1 — declared in spec notes).
- Sub-task tree on the board (V1).
- PHP notification triggers from board drag events (V1 — drag to `done`-type column triggers status change but no separate notification).

---

## Decisions

### Decision 1: Drag-drop library — `vuedraggable` (Vue 2 wrapper for SortableJS)

**Options considered:**
1. Vanilla SortableJS with manual Vue integration.
2. `vuedraggable` (official Vue 2 wrapper for SortableJS) — chosen.
3. `@dnd-kit/core` (React-first, Vue port immature).
4. `vue-smooth-dnd` (unmaintained since 2021).

**Rationale:** `vuedraggable` is the standard Vue 2 drag-and-drop solution (>5M weekly npm downloads). It wraps SortableJS and exposes Vue-idiomatic `v-model` binding plus `@change` events, making optimistic state management straightforward. It handles touch events, cross-list dragging, and drag handles natively. The package is actively maintained and used across dozens of Nextcloud apps.

Package added: `vuedraggable` (`vuedraggable@^2.x` for Vue 2 compatibility).

### Decision 2: Optimistic UI pattern — clone → apply → PATCH → revert

**Options considered:**
1. Wait for API confirmation before moving the card (no optimism).
2. Apply immediately, revert on failure, show toast — chosen.

**Rationale:** Waiting for the API makes the board feel sluggish. The standard UX expectation for kanban boards is that card drops are instant. The implementation pattern is:

```
1. User drops card into target column
2. Clone pre-move state (source column tasks, target column tasks)
3. Apply new positions to local Pinia state immediately
4. Call PATCH task: { column: targetColumnId, columnOrder: newIndex }
5a. On success: discard clone, update task in store
5b. On failure: restore cloned state, show toast "Failed to move task — try again"
```

The rollback restores both the task's column reference and its `columnOrder` within the column. A toast notification (using `NcToast` or equivalent) is shown on failure so the user knows the action did not persist.

### Decision 3: Column store — separate `useColumnsStore` wrapping `useObjectStore('planix', 'column')`

**Options considered:**
1. Fetch columns inside the project store.
2. Dedicated `useColumnsStore` in `src/store/columns.js` — chosen.

**Rationale:** Columns are a first-class entity with their own CRUD and reorder logic. Keeping them in a dedicated store mirrors the `useTasksStore` pattern from the `tasks` change and keeps each store focused. The column store is project-scoped: `fetchColumns(projectId)` filters by `project === projectId`. Reorder is handled by PATCHing the `order` field on each column in sequence.

The store exposes:
```js
// src/store/columns.js
{
  columns,             // ref([]) — ordered list for current project
  loading,
  error,
  fetchColumns(projectId),
  createColumn(data),
  updateColumn(id, data),
  deleteColumn(id),
  reorderColumns(orderedIds),  // PATCH order on all columns
  moveTasksToBacklog(columnId),
  moveTasksToColumn(fromId, toId),
}
```

### Decision 4: Board layout — CSS Grid columns with overflow-x scroll

**Options considered:**
1. Flexbox row with fixed column width.
2. CSS Grid with `grid-auto-flow: column` and `overflow-x: auto` — chosen.

**Rationale:** CSS Grid with `grid-template-columns: repeat(auto-fill, 280px)` (or explicit per-column) gives precise column widths while allowing horizontal scroll when columns exceed viewport. This is the standard pattern used by Trello and Linear. Column width is 280 px (a widely-accepted kanban column width). Columns do not wrap — horizontal scroll is always `overflow-x: auto` on the board container.

The backlog panel, when open, sits as a fixed-width left sidebar (240 px) inside the board container, reducing the available width for columns.

### Decision 5: WIP limit UI — CSS class on column header

**Options considered:**
1. Computed property returning an inline style object.
2. CSS classes `wip-warning` (orange) and `wip-exceeded` (red) applied to the column header — chosen.

**Rationale:** CSS classes are more maintainable and allow overriding via NL Design tokens. The thresholds:
- `wip-warning`: task count equals `wipLimit` (at the limit, amber)
- `wip-exceeded`: task count exceeds `wipLimit` (over the limit, red)

The column header renders a `<span>` tooltip (`NcTooltip`) with text "WIP limit ({limit}) exceeded — {count} tasks in column". The tooltip is always present when a limit is set but only becomes visually prominent at `wip-warning` or `wip-exceeded`.

Colours use CSS variables only: `wip-warning` → `var(--color-warning)`, `wip-exceeded` → `var(--color-error)`.

### Decision 6: Board filter — URL query params, client-side application

**Options considered:**
1. Server-side filter: re-fetch from OpenRegister on each filter change.
2. Client-side filter after initial full board fetch — chosen.

**Rationale:** The board already fetches all tasks for the project on mount. Applying filters client-side is instantaneous (no spinner) and allows the "dim non-matching cards" UX pattern (cards remain visible but faded, so the user can still see the full board context). The filter state is synced to URL query params (`?assignee=uid&priority=high&label=bug`) so filtered views are shareable.

Filter application logic in `KanbanBoard.vue`:
```js
const filteredTaskIds = computed(() => {
  // Returns a Set of task IDs matching all active filters
  // KanbanColumn checks: isVisible(task) = filteredTaskIds.has(task.id) || !hasActiveFilters
})
```

Non-matching cards receive class `task-card--dimmed` (opacity 0.35) rather than being removed from the DOM, keeping drag-and-drop targets intact.

### Decision 7: View toggle — URL hash (`#view=kanban` / `#view=list`)

**Options considered:**
1. Separate route paths (`/projects/:id` and `/projects/:id/list`).
2. URL hash on the same route — chosen.

**Rationale:** The view toggle does not change the logical resource (the project board). A hash avoids adding new Vue Router routes while persisting the selected view across page reloads. Query params are reserved for filters. The `ViewToggle` component reads `window.location.hash` on mount and sets it on toggle. If no hash is present, the kanban view is the default.

### Decision 8: Backlog panel — collapsible left sidebar

**Options considered:**
1. Separate route `/projects/:id/backlog` only (no panel).
2. Collapsible panel inside the board view — chosen.

**Rationale:** The spec requires drag-from-backlog-to-board. This is only possible if both surfaces are visible simultaneously. The backlog panel is a collapsible `<aside>` (240 px wide, `transition: width 0.2s`) rendered inside the board layout. When closed, it collapses to a 40 px icon strip (showing task count badge). The panel fetches `column: null` tasks for the current project using `useTasksStore.fetchTasks({ project: id, column: null })`. Dragging a task from the panel sets `column: targetColumnId` via the same optimistic pattern as Decision 2.

The separate `/projects/:id/backlog` route (from the `tasks` change) remains as a full-page backlog view — the panel is an additional shortcut within the board view.

### Decision 9: Column reorder — drag column headers via SortableJS

**Options considered:**
1. Up/down arrows in the column menu.
2. Drag column headers — chosen.

**Rationale:** Dragging column headers is the standard kanban UX. The board header strip (containing column headers) is wrapped in its own `<draggable>` component with a different drag handle (the header area, not the card area). On drag end, `reorderColumns(newOrder)` PATCHes the `order` field on all affected columns. This is an optimistic operation — the UI reorders immediately and reverts on API failure.

### Decision 10: Column delete with tasks — multi-step migration dialog

**Options considered:**
1. Show a simple "Are you sure?" dialog and delete the column + tasks.
2. Require the user to specify a destination for tasks before deleting — chosen.

**Rationale:** The spec is explicit: tasks must not be silently deleted when a column is deleted. The `ColumnDeleteDialog.vue` flow:

```
1. Open dialog — fetch task count for the column
2. If count > 0: show "This column has {N} tasks"
   → Radio/select: "Move to backlog" | "Move to column: [dropdown]"
   → "Delete" button enabled only when destination is chosen
3. On confirm:
   a. If "Move to backlog": PATCH all tasks with { column: null }
   b. If "Move to column": PATCH all tasks with { column: targetId }
   c. DELETE the column
4. Show success toast; close dialog; remove column from board
```

If `count === 0`, the dialog shows a simple confirmation without the migration step.

### Decision 11: Keyboard accessibility — WCAG AA

The board must be navigable without a mouse:

- `Tab` moves focus through interactive elements in document order: filter chips → view toggle → backlog panel toggle → column headers (left to right) → column action buttons → task cards within each column.
- Within a column, `↑` / `↓` arrow keys move focus between task cards.
- `Enter` on a task card opens the task detail view.
- `Space` on a focused task card initiates keyboard-based drag mode (SortableJS `forceFallback` option enables this).
- Column headers are `role="columnheader"` with `aria-label="{title} — {count} tasks"`. WIP exceeded adds `aria-describedby` pointing to the tooltip.
- The board container is `role="group"` with `aria-label="{project name} board"`.
- All dialogs use `NcDialog` which handles focus trapping and ESC close.

### Decision 12: Auto-status transition on drag to `done`-type column

When a task is dragged into a column with `type: 'done'`, the task's `status` is automatically set to `done` and `completedAt` is set to `now()`. This is done in the same PATCH call that updates `column`. This follows the spec requirement that `done`-type columns reflect task completion.

The reverse is not applied: dragging a `done` task back to an `active` column does not automatically reset status (the user may have a different intent). The task retains `status: 'done'` but its `column` is updated — the user can reset status manually from the task detail.

---

## Component Architecture

```
src/
  views/
    ProjectBoard.vue              # /projects/:id — board container (modified from placeholder)
    ProjectTaskList.vue           # list view rendered inside ProjectBoard when view=list
  components/
    kanban/
      KanbanBoard.vue             # Board layout: CSS Grid columns + drag state
      KanbanColumn.vue            # Column: header (WIP), drag zone, empty state
      KanbanFilterBar.vue         # Filter chips row with URL sync
      ViewToggle.vue              # Kanban ↔ list toggle button
      BacklogPanel.vue            # Collapsible sidebar: column-less tasks
  components/
    dialogs/
      ColumnCreateDialog.vue      # NcDialog — create column
      ColumnRenameDialog.vue      # NcDialog — rename + WIP limit
      ColumnDeleteDialog.vue      # NcDialog — delete with task migration
  store/
    columns.js                    # Pinia store (useObjectStore wrapper)
```

### Reused from `tasks` change (no modifications):
- `src/components/TaskCard.vue` — rendered inside `KanbanColumn.vue` with `compact: true`
- `src/store/tasks.js` — `useTasksStore` for task fetching and updates

---

## Pinia Store: `useColumnsStore`

```js
// src/store/columns.js
import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { useObjectStore } from '@conduction/nextcloud-vue'

export const useColumnsStore = defineStore('columns', () => {
  const objectStore = useObjectStore('planix', 'column')

  const columns = ref([])
  const loading = ref(false)
  const error = ref(null)

  const orderedColumns = computed(() =>
    [...columns.value].sort((a, b) => a.order - b.order)
  )

  async function fetchColumns(projectId) { /* GET column list filtered by project */ }
  async function createColumn(data) { /* POST + append to columns */ }
  async function updateColumn(id, data) { /* PATCH + update in place */ }
  async function deleteColumn(id) { /* DELETE */ }
  async function reorderColumns(orderedIds) {
    // PATCH order on each column (orderedIds[i] → order: i)
    // Optimistic: apply new order immediately, revert on any failure
  }
  async function moveTasksToBacklog(columnId) {
    // PATCH all tasks where column === columnId → { column: null }
    // Uses useTasksStore internally
  }
  async function moveTasksToColumn(fromColumnId, toColumnId) {
    // PATCH all tasks where column === fromColumnId → { column: toColumnId }
  }

  return {
    columns, orderedColumns, loading, error,
    fetchColumns, createColumn, updateColumn, deleteColumn,
    reorderColumns, moveTasksToBacklog, moveTasksToColumn,
  }
})
```

---

## Drag-and-Drop State Machine

```
IDLE
  → user mousedown on card → DRAGGING
    → drag ends in same column → reorder (PATCH columnOrder only) → IDLE
    → drag ends in different column → MOVING
      → apply optimistic state
      → PATCH { column, columnOrder }
      → success → IDLE
      → failure → revert state + toast → IDLE
  → user mousedown on column header → COLUMN_DRAGGING
    → drag ends → reorder columns → IDLE
  → drag from backlog panel → BACKLOG_MOVING
    → drop on column → PATCH { column, columnOrder: 0 }
    → success → remove from backlog panel → IDLE
    → failure → revert + toast → IDLE
```

---

## KanbanColumn Component Anatomy

```
┌─────────────────────────────────────────────────────────────┐
│ [color strip — top border, column.color or default]         │
│ ┌─────────────────────────────────────────────────────────┐ │
│ │ Column Title               [count]/[wipLimit]  [⋮ menu] │ │  ← header: drag handle for column reorder
│ └─────────────────────────────────────────────────────────┘ │
│   wip-warning: amber header background when count=wipLimit   │
│   wip-exceeded: red header background when count>wipLimit    │
│                                                             │
│  ┌──────────────────────────────────────────────────────┐  │
│  │ TaskCard (compact=true)                              │  │
│  │ TaskCard (compact=true)                              │  │
│  │ TaskCard (compact=true)  ← drag-and-drop zone       │  │
│  └──────────────────────────────────────────────────────┘  │
│                                                             │
│  [+ Add task]  ← CnEmptyState or inline button             │
└─────────────────────────────────────────────────────────────┘
```

Column header WIP indicator: `{taskCount} / {wipLimit}` (hidden if no wipLimit set).

---

## URL State Schema

| Parameter | Type | Location | Example |
|-----------|------|----------|---------|
| `view` | `kanban` \| `list` | URL hash | `#view=list` |
| `assignee` | user UID string | query param | `?assignee=admin` |
| `priority` | `low\|normal\|high\|urgent` | query param | `?priority=high` |
| `label` | label ID string | query param | `?label=bug` |

Multiple filters: `?assignee=admin&priority=high#view=kanban`

The `ViewToggle` and `KanbanFilterBar` components read and write these params on mount and on user interaction using `window.location` + Vue Router's `$route.query`. No full page reload occurs when params change — Vue Router `replace` is used.

---

## i18n String Inventory

| Key context | Example string |
|-------------|---------------|
| Board header | `Board`, `{project} board` |
| View toggle | `Kanban view`, `List view` |
| Column header | `{title}`, `{count} task`, `{count} tasks` |
| WIP indicator | `WIP limit ({limit}) exceeded — {count} tasks in column`, `At WIP limit — {count} tasks in column` |
| Column menu | `Rename column`, `Set WIP limit`, `Delete column` |
| Empty board (no columns) | `No columns yet`, `Add column` |
| Empty column | `No tasks yet`, `+ Add task` |
| Filter bar | `Filter by`, `Assignee`, `Priority`, `Label`, `Clear filters` |
| Filter active | `Filtered by {count} criteria` |
| Create column dialog | `Create column`, `Column title`, `WIP limit (optional)`, `Column colour (optional)`, `Create`, `Cancel` |
| Rename column dialog | `Rename column`, `Title`, `WIP limit`, `Save`, `Cancel` |
| Delete column dialog (no tasks) | `Delete column "{title}"?`, `This will permanently delete the column.`, `Delete`, `Cancel` |
| Delete column dialog (with tasks) | `Delete column "{title}"?`, `This column contains {count} task(s). Where should they go?`, `Move to backlog`, `Move to column:`, `Delete column`, `Cancel` |
| Drag-drop failure toast | `Failed to move task — try again` |
| Column reorder failure toast | `Failed to reorder columns — try again` |
| Column delete success toast | `Column deleted` |
| Column create success toast | `Column created` |
| Backlog panel | `Backlog`, `{count} task`, `{count} tasks` |
| List view columns | `Title`, `Assignee`, `Due date`, `Status`, `Priority`, `Labels` |
| List view empty | `No tasks`, `Add your first task` |
| Keyboard nav | `Open task`, `Move to next column`, `Move to previous column` |

---

## Risks and Trade-offs

| Risk | Likelihood | Mitigation |
|------|-----------|-----------|
| Optimistic drag rollback races with a second drag | Low | Disable drag during pending API calls (vuedraggable `:disabled` binding while PATCH is in-flight) |
| Column reorder PATCH-per-column is slow for many columns | Low | MVP boards have ≤10 columns. Future: OpenRegister batch PATCH. Show loading indicator on header strip during reorder. |
| `moveTasksToBacklog`/`moveTasksToColumn` fails mid-migration (partial state) | Low-Medium | Run in sequence; on failure, revert already-PATCHed tasks by re-PATCHing back to original column; show error toast "Some tasks could not be moved — check the backlog" |
| Filter dimming confuses users who expect hidden cards | Low | Provide clear visual indicator: "Filtered: {N} tasks hidden" badge in filter bar; show un-dim affordance |
| `vue-draggable` + Vue 2 reactivity edge cases with cross-list drag | Medium | Use `vuedraggable` `@change` event (not `v-model`) for cross-list moves to maintain explicit control over state |
| Column delete dialog blocks on large task migrations (>100 tasks) | Low | Show progress indicator in dialog; MVP task sets are small |
