---
kind: code
depends_on: []
---

## Why

Planix project members have no visual board to track task progress by stage. They must open each task individually to understand what the team is working on — there is no at-a-glance view of work state across stages. WIP overloads go unnoticed because there is no flow discipline layer forcing teams to limit work-in-progress.

## What Changes

- A new **Column** OpenRegister schema (scoped to a Project) defines the stages of a kanban board in ordered, configurable columns with optional WIP limits and a colour indicator.
- A new **KanbanBoardView** Vue page renders Task cards in Column lanes with drag-and-drop via vue-draggable (SortableJS); cards display title, assignee avatar, due date, priority indicator, and label chips.
- WIP limit violations surface visually (red column header + tooltip) as a soft limit — the task is accepted into the column but the violation is flagged.
- Board filtering by assignee, label, and priority updates the visible cards in-place; the active filter is reflected in the URL hash for shareable links.
- Column management (create, rename, reorder, delete) is available via project settings. Deleting a column that contains tasks prompts the user to move tasks to the backlog or another column before the column is deleted.
- A **view toggle** in the project toolbar switches between the kanban card view and a sortable flat list (table) view; the active view and any active filter persist in the URL hash.
- Empty states guide new users: a board with no columns shows an "Add column" call-to-action (creator/admin only); empty columns show a "+ Add task" shortcut that pre-selects that column in the task creation form.

## Capabilities

### New Capabilities

- `column-schema`: Column entity stored in OpenRegister — project-scoped, with `title`, `order`, `wipLimit`, `color`, and `type` (active | done). Aligned to schema.org `DefinedTerm`.
- `kanban-board-view`: Vue view showing Tasks as draggable cards grouped in Column lanes; optimistic drag-drop with automatic rollback on backend failure.
- `board-filters`: Filter board cards by assignee, label, and priority without full page reload; filter state encoded in URL hash.
- `column-management`: CRUD for columns from project settings; safe deletion dialog when tasks exist in the column.
- `view-toggle`: Switch between kanban and list views per project; active view and filters persist in URL hash.
- `list-view`: Sortable flat table of tasks (title, assignee, due date, status, priority, labels) as an alternative to the board; row click navigates to task detail, browser back returns to list view.

### Modified Capabilities

- **Task schema**: extends with `column` (OpenRegister reference → Column) and `columnOrder` (integer) to record board position.

## Impact

- `src/views/KanbanBoardView.vue` — new kanban board page with column lanes and draggable cards
- `src/views/ListViewPage.vue` — new flat sortable table view of project tasks
- `src/components/kanban/KanbanColumn.vue` — column lane: WIP indicator, drop zone, empty state
- `src/components/kanban/KanbanCard.vue` — task card: avatar, due date, priority dot, label chips
- `src/components/kanban/BoardToolbar.vue` — view toggle + filter controls in project toolbar
- `src/dialogs/DeleteColumnDialog.vue` — confirmation dialog for deleting columns that contain tasks
- `src/store/modules/columns.js` — Pinia store via `createObjectStore` for Column entities
- `lib/Settings/planix_register.json` — updated: Column schema definition, Task schema patch (`column`, `columnOrder`), seed data (3–5 Column objects)
