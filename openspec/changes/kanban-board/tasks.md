# Tasks: kanban-board

**Change ID:** kanban-board
**Status:** draft
**Created:** 2026-04-02

---

## Implementation Tasks

### Task 1: Setup and Dependencies
- **spec_ref**: `openspec/changes/kanban-board/proposal.md`
- **files**: `package.json`, `src/store/columns.js`, `src/components/kanban/`
- **acceptance_criteria**:
  - GIVEN the developer runs `npm install` THEN `vuedraggable` is installed and importable
  - GIVEN the developer inspects `@conduction/nextcloud-vue` WHEN checking exports THEN `CnEmptyState`, `CnDataTable`, `useObjectStore` are available
  - GIVEN the developer runs `ls src/components/kanban/` THEN the directory exists
  - GIVEN the `tasks` change is applied WHEN the developer imports `TaskCard` THEN the component is available with `{ task: Object, compact: Boolean }` props
- [ ] Verify `register-schemas`, `projects`, and `tasks` changes are applied
- [ ] Run `npm install vuedraggable` and add to `package.json` dependencies
- [ ] Create directory structure: `src/components/kanban/`
- [ ] Confirm `TaskCard.vue` props interface: `{ task, compact }` (stable contract from `tasks` change)

---

### Task 2: Column Store — Base CRUD
- **spec_ref**: `openspec/changes/kanban-board/design.md#pinia-store-usecolumnsstore`
- **files**: `src/store/columns.js`
- **acceptance_criteria**:
  - GIVEN `useColumnsStore()` is called WHEN the store initialises THEN reactive state `columns`, `orderedColumns`, `loading`, `error` are available
  - GIVEN a project ID WHEN `fetchColumns(projectId)` is called THEN OpenRegister returns only columns belonging to that project, sorted by `order`
  - GIVEN a column data object WHEN `createColumn(data)` is called THEN a POST is made to OpenRegister and the new column is appended to `columns`
  - GIVEN an existing column WHEN `updateColumn(id, data)` is called THEN a PATCH is made and `columns` is updated in place
  - GIVEN an existing column WHEN `deleteColumn(id)` is called THEN a DELETE is made and the column is removed from `columns`
- [ ] Create `src/store/columns.js` with Pinia store `useColumnsStore`
- [ ] Implement `fetchColumns(projectId)` — calls `objectStore.getObjects({ project: projectId })`, sorts by `order`
- [ ] Implement `createColumn(data)` — merges defaults (`order: max + 1`, `type: 'active'`)
- [ ] Implement `updateColumn(id, data)` — optimistic update with rollback on failure
- [ ] Implement `deleteColumn(id)` — DELETE only (task migration handled by `moveTasksToBacklog`/`moveTasksToColumn`)
- [ ] Implement `orderedColumns` computed — sort `columns` by `order` ascending
- [ ] Test

---

### Task 3: Column Store — Reorder and Task Migration
- **spec_ref**: `openspec/changes/kanban-board/specs/kanban-board/spec.md#added-requirement--column-management`, `openspec/changes/kanban-board/design.md`
- **files**: `src/store/columns.js`
- **acceptance_criteria**:
  - GIVEN an array of column IDs in a new order WHEN `reorderColumns(orderedIds)` is called THEN each column is PATCHed with `{ order: index }` where index matches its position in `orderedIds`
  - GIVEN `reorderColumns` fails for one column THEN all previously PATCHed columns MUST be reverted to their original `order` values
  - GIVEN a column ID WHEN `moveTasksToBacklog(columnId)` is called THEN all tasks with `column === columnId` are PATCHed with `{ column: null }` and `useTasksStore` reflects the change
  - GIVEN two column IDs WHEN `moveTasksToColumn(fromId, toId)` is called THEN all tasks with `column === fromId` are PATCHed with `{ column: toId }` and `useTasksStore` reflects the change
- [ ] Implement `reorderColumns(orderedIds)` — clone current orders, PATCH each column, revert all on any failure
- [ ] Implement `moveTasksToBacklog(columnId)` — fetch tasks for column, PATCH each to `{ column: null }`
- [ ] Implement `moveTasksToColumn(fromId, toId)` — fetch tasks for column, PATCH each to `{ column: toId }`
- [ ] Expose `reorderColumns`, `moveTasksToBacklog`, `moveTasksToColumn` from store
- [ ] Test

---

### Task 4: KanbanBoard Component — Layout and Mount
- **spec_ref**: `openspec/changes/kanban-board/specs/kanban-board/spec.md#added-requirement--kanbanboard-component-layout`
- **files**: `src/components/kanban/KanbanBoard.vue`, `src/views/ProjectBoard.vue`
- **acceptance_criteria**:
  - GIVEN `KanbanBoard` mounts WHEN `fetchColumns` and `fetchTasks` are in-flight THEN skeleton columns (3 placeholder columns) and skeleton cards MUST be visible and drag MUST be disabled
  - GIVEN fetch resolves WHEN the board renders THEN columns appear in `orderedColumns` order in a CSS Grid row, each 280 px wide, with `overflow-x: auto` on the container
  - GIVEN 8 columns on a 1440 px viewport THEN horizontal scroll IS available and no column wraps
  - GIVEN the board renders WHEN inspected THEN `role="group"` and `aria-label="{projectName} board"` are present on the board container
- [ ] Create `src/components/kanban/KanbanBoard.vue`
- [ ] On mount: call `columnsStore.fetchColumns(projectId)` and `tasksStore.fetchTasks({ project: projectId })`
- [ ] Implement skeleton loading state: 3 placeholder column skeletons, each with 2–3 card-shaped skeletons
- [ ] Implement CSS Grid layout: `grid-auto-flow: column`, `grid-auto-columns: 280px`, `overflow-x: auto`
- [ ] Render `KanbanColumn` for each column in `orderedColumns`; pass `tasks` filtered by `column.id`
- [ ] Add `role="group"` and `aria-label` to board container
- [ ] Modify `src/views/ProjectBoard.vue` — replace placeholder shell; import and render `KanbanBoard`, `KanbanFilterBar`, `ViewToggle`; conditionally render `ProjectTaskList` when `#view=list`
- [ ] Test

---

### Task 5: KanbanColumn Component — Header and WIP
- **spec_ref**: `openspec/changes/kanban-board/specs/kanban-board/spec.md#added-requirement--kanbancolumn-component`
- **files**: `src/components/kanban/KanbanColumn.vue`
- **acceptance_criteria**:
  - GIVEN a column with `color: "#4CAF50"` WHEN the column renders THEN a 4 px top border in that colour is visible
  - GIVEN `wipLimit: 3` and 2 tasks THEN no warning styling is applied
  - GIVEN `wipLimit: 3` and exactly 3 tasks THEN header background uses `var(--color-warning)` and tooltip reads "At WIP limit — 3 tasks in column"
  - GIVEN `wipLimit: 3` and 4 tasks THEN header background uses `var(--color-error)` and tooltip reads "WIP limit (3) exceeded — 4 tasks in column"
  - GIVEN zero tasks and no active filter THEN `CnEmptyState` "No tasks yet" and "+ Add task" button are shown
  - GIVEN a column header renders THEN `role="columnheader"` and `aria-label="{title} — {count} task(s)"` are present
- [ ] Create `src/components/kanban/KanbanColumn.vue`
- [ ] Props: `{ column: Object, tasks: Array, isFiltered: Boolean, filteredTaskIds: Set }`
- [ ] Implement colour strip: `border-top: 4px solid` using `column.color` or `var(--color-border)`
- [ ] Implement WIP indicator: `computed wipState` returning `null | 'warning' | 'exceeded'`
- [ ] Apply CSS classes `wip-warning` / `wip-exceeded` to header based on `wipState`; use CSS variables only
- [ ] Implement WIP tooltip with `NcTooltip`; conditional text based on `wipState`
- [ ] Implement column action menu (⋮): Rename, Set WIP limit, Delete — emit events to parent
- [ ] Implement empty state: `CnEmptyState` when `tasks.length === 0 && !isFiltered`
- [ ] Implement "all filtered" message: show when `tasks.length > 0 && filteredTaskIds intersection tasks === empty`
- [ ] Implement "+ Add task" button: emits `add-task` event with `columnId`
- [ ] Add `role="columnheader"` and `aria-label` to header element
- [ ] Test

---

### Task 6: KanbanColumn Component — Task Card Rendering
- **spec_ref**: `openspec/changes/kanban-board/specs/kanban-board/spec.md`
- **files**: `src/components/kanban/KanbanColumn.vue`
- **acceptance_criteria**:
  - GIVEN a column with 3 tasks WHEN the column renders THEN 3 `TaskCard` components are shown, each with `compact: true`
  - GIVEN a filter is active WHEN a task does not match THEN its `TaskCard` has class `task-card--dimmed` (opacity 0.35, pointer-events none)
  - GIVEN keyboard focus is on a task card WHEN the user presses ↓ THEN focus moves to the next card; pressing ↑ moves to the previous card
  - GIVEN focus is on the last card WHEN ↓ is pressed THEN focus moves to the "+ Add task" button
- [ ] Render `TaskCard` components inside the `<draggable>` zone with `compact: true`
- [ ] Pass `dimmed` class or prop to `TaskCard` when task is not in `filteredTaskIds` (and filter is active)
- [ ] Implement arrow-key navigation: `@keydown.down`, `@keydown.up` on card container; move focus using `$refs`
- [ ] Implement Enter key on card: emit `open-task` event (parent routes to `/tasks/:id`)
- [ ] Test

---

### Task 7: Drag-and-Drop Logic — Cross-Column
- **spec_ref**: `openspec/changes/kanban-board/specs/kanban-board/spec.md#added-requirement--optimistic-drag-and-drop`
- **files**: `src/components/kanban/KanbanBoard.vue`, `src/components/kanban/KanbanColumn.vue`
- **acceptance_criteria**:
  - GIVEN a card is dropped from column A to column B WHEN the PATCH is sent THEN the card appears in column B immediately (optimistic)
  - GIVEN the PATCH fails THEN the card returns to column A at its original position AND a toast "Failed to move task — try again" is shown
  - GIVEN a PATCH is in-flight THEN the dragged card shows a loading spinner AND drag is disabled on it
  - GIVEN drag to a `done`-type column THEN the PATCH includes `{ status: 'done', completedAt: <ISO> }`
- [ ] Wrap column task list in `<draggable>` from `vuedraggable`; set `group="tasks"` to allow cross-column drag
- [ ] Use `@change` event (not `v-model`) for cross-column move: capture `{ added, removed }` events per column
- [ ] On `added` event: clone pre-move state → apply optimistic update → call `tasksStore.updateTask(id, { column, columnOrder })`
- [ ] On PATCH failure: restore cloned state → show toast via `useToast` (or equivalent NC toast mechanism)
- [ ] For `done`-type target column: include `{ status: 'done', completedAt: new Date().toISOString() }` in PATCH
- [ ] Disable drag on card during pending PATCH: bind `:disabled` on `<draggable>` per-card or use CSS `pointer-events: none` while loading
- [ ] Test

---

### Task 8: Drag-and-Drop Logic — Within Column Reorder
- **spec_ref**: `openspec/changes/kanban-board/specs/kanban-board/spec.md#added-requirement--optimistic-drag-and-drop`
- **files**: `src/components/kanban/KanbanColumn.vue`
- **acceptance_criteria**:
  - GIVEN a user reorders cards within a column WHEN the drag ends THEN all affected tasks have their `columnOrder` PATCHed to reflect the new sequence
  - GIVEN the PATCH fails for any task THEN all affected tasks revert to their original `columnOrder`
- [ ] Handle `@change` `moved` event within the same column
- [ ] Clone pre-reorder `columnOrder` values for all affected tasks
- [ ] Issue PATCH for each task whose `columnOrder` changed (optimistic)
- [ ] On any failure: revert all affected tasks and show toast
- [ ] Test

---

### Task 9: Drag-and-Drop Logic — Column Reorder
- **spec_ref**: `openspec/changes/kanban-board/specs/kanban-board/spec.md#added-requirement--column-management`
- **files**: `src/components/kanban/KanbanBoard.vue`
- **acceptance_criteria**:
  - GIVEN the user drags a column header to a new position WHEN dropped THEN `columnsStore.reorderColumns(newOrderedIds)` is called
  - GIVEN the column reorder API call fails THEN columns revert to their previous order AND a toast is shown
- [ ] Wrap column header strip in a separate `<draggable>` with `handle=".column-header"` and `group="columns"`
- [ ] On `@change` `moved` event: compute new ordered IDs array → call `columnsStore.reorderColumns(orderedIds)`
- [ ] Clone pre-reorder column order → on failure, restore and show toast "Failed to reorder columns — try again"
- [ ] Test

---

### Task 10: WIP Limit UI — Visual States
- **spec_ref**: `openspec/changes/kanban-board/specs/kanban-board/spec.md#added-requirement--kanbancolumn-component`, `openspec/specs/kanban-board.md#scenario-wip-limit-visual-warning`
- **files**: `src/components/kanban/KanbanColumn.vue`
- **acceptance_criteria**:
  - GIVEN `wipLimit` is null THEN no WIP indicator is shown in the column header
  - GIVEN count < wipLimit THEN no warning styling; WIP indicator shows `{count} / {wipLimit}` in neutral colour
  - GIVEN count === wipLimit THEN header uses `var(--color-warning)`, tooltip: "At WIP limit — {count} tasks"
  - GIVEN count > wipLimit THEN header uses `var(--color-error)`, tooltip: "WIP limit ({limit}) exceeded — {count} tasks"
  - GIVEN WIP is exceeded THEN the column header's `aria-describedby` references the tooltip element ID
- [ ] Implement `wipState` computed in `KanbanColumn.vue`: returns `null | 'at' | 'exceeded'`
- [ ] Apply CSS class `wip-at` / `wip-exceeded` to header; define classes using `var(--color-warning)` / `var(--color-error)` background
- [ ] Render `NcTooltip` on WIP count element with conditional message text
- [ ] Add `aria-describedby` attribute when `wipState !== null`
- [ ] Hide WIP count element entirely when `column.wipLimit === null`
- [ ] Test

---

### Task 11: KanbanFilterBar Component
- **spec_ref**: `openspec/changes/kanban-board/specs/kanban-board/spec.md#added-requirement--kanbanfilterbar-component`
- **files**: `src/components/kanban/KanbanFilterBar.vue`
- **acceptance_criteria**:
  - GIVEN the board has tasks with various assignees, priorities, and labels WHEN `KanbanFilterBar` renders THEN three filter groups appear: Assignee, Priority, Label
  - GIVEN the user selects "Priority: high" THEN `filteredTaskIds` updates and non-matching cards are dimmed on the board
  - GIVEN two filters are active THEN the filter bar shows a badge "2 filters active" and a "Clear filters" button
  - GIVEN the user activates a filter THEN the URL updates to `?priority=high` (Vue Router `replace`)
  - GIVEN the page loads with `?priority=high` in URL THEN the Priority: high chip is pre-selected
- [ ] Create `src/components/kanban/KanbanFilterBar.vue`
- [ ] Props: `{ tasks: Array }` (all project tasks, for deriving available filter values)
- [ ] Emit: `filter-change` event with `{ activeFilters }` object
- [ ] Derive available assignees, priorities, labels from `tasks` prop (computed)
- [ ] Render chip groups for each dimension; chips are toggle-able (multi-select per group)
- [ ] Implement `activeFilters` reactive state; compute `filteredTaskIds` Set from active filters
- [ ] On filter change: emit `filter-change`; call `$router.replace({ query: filterParams })` to sync URL
- [ ] On mount: read `$route.query` to initialise active filters from URL
- [ ] Show active filter count badge and "Clear filters" button when any filter is active
- [ ] Test

---

### Task 12: ViewToggle Component
- **spec_ref**: `openspec/changes/kanban-board/specs/kanban-board/spec.md#added-requirement--viewtoggle-component`
- **files**: `src/components/kanban/ViewToggle.vue`, `src/views/ProjectTaskList.vue`
- **acceptance_criteria**:
  - GIVEN no URL hash WHEN the board mounts THEN kanban view is shown; toggle button shows kanban as active
  - GIVEN the user clicks list view toggle THEN `#view=list` is set in the hash; `ProjectTaskList` renders
  - GIVEN the user clicks kanban view toggle THEN `#view=kanban` is set; `KanbanBoard` renders
  - GIVEN active filters exist WHEN switching views THEN filters remain applied in the new view
  - GIVEN the user is in list view WHEN clicking a task row THEN router navigates to `/tasks/:id`; back returns to list view
- [ ] Create `src/components/kanban/ViewToggle.vue`
  - Renders two `NcButton` (or toggle button group): Kanban, List
  - Reads `window.location.hash` on mount to set active view
  - On click: update hash via `window.location.hash = '#view=kanban'` or `'#view=list'`
  - Emits `view-change` with `'kanban' | 'list'`
- [ ] Create `src/views/ProjectTaskList.vue`
  - Receives `filters` prop from `ProjectBoard`
  - Uses `CnDataTable` with columns: Title, Assignee, Due Date, Status, Priority, Labels
  - Sortable headers — sort applied client-side using `useTasksStore.filteredTasks`
  - Row click navigates to `/tasks/:id`
  - Empty state: `NcEmptyContent` "No tasks" + "Add your first task" button (if no filters active)
- [ ] Wire view toggle in `ProjectBoard.vue`: conditionally render `KanbanBoard` or `ProjectTaskList` based on current view; pass `filteredTaskIds` / `activeFilters` to both
- [ ] Test

---

### Task 13: BacklogPanel Component
- **spec_ref**: `openspec/changes/kanban-board/specs/kanban-board/spec.md#added-requirement--backlog-panel`
- **files**: `src/components/kanban/BacklogPanel.vue`
- **acceptance_criteria**:
  - GIVEN the backlog panel is collapsed WHEN the user clicks the toggle THEN it expands to 240 px (animated)
  - GIVEN the panel is expanded WHEN collapsed THEN it shrinks to a 40 px icon strip showing the backlog task count badge
  - GIVEN the panel is expanded WHEN tasks load THEN `TaskCard` components (compact) are rendered for each backlog task
  - GIVEN zero backlog tasks THEN `CnEmptyState` "Backlog is empty" is shown inside the panel
  - GIVEN the user drags a task from the panel to a board column THEN the task is removed from the panel and appears in the column
- [ ] Create `src/components/kanban/BacklogPanel.vue`
- [ ] Props: `{ projectId: String }`; maintains `isExpanded` local state
- [ ] On mount (or when expanded): call `tasksStore.fetchTasks({ project: projectId, column: null })`
- [ ] Render `TaskCard` (compact) for each backlog task inside a `<draggable>` zone with `group="tasks"`
- [ ] Implement expand/collapse: CSS transition on `width` (240 px ↔ 40 px); collapsed state shows icon + task count badge
- [ ] When collapsed, show task count badge on the icon strip
- [ ] Cross-panel drag: the `<draggable>` group `"tasks"` is shared with column `<draggable>` zones — vuedraggable handles cross-list drag natively
- [ ] On drag out of backlog (`removed` event): this is handled by Task 7 — the card's optimistic PATCH sets `{ column: targetId }`
- [ ] Empty state with `CnEmptyState` when no backlog tasks
- [ ] Test

---

### Task 14: ColumnCreateDialog Component
- **spec_ref**: `openspec/changes/kanban-board/specs/kanban-board/spec.md#added-requirement--column-management`
- **files**: `src/components/dialogs/ColumnCreateDialog.vue`
- **acceptance_criteria**:
  - GIVEN the dialog is open WHEN the user submits without a title THEN "Column title is required" is shown inline and submit is disabled
  - GIVEN the user submits with a title WHEN the store creates the column THEN a toast "Column created" is shown and the dialog closes
  - GIVEN creation fails WHEN the dialog remains open THEN all field values are preserved
  - GIVEN creation is in progress THEN the submit button is disabled and shows a spinner
- [ ] Create `src/components/dialogs/ColumnCreateDialog.vue` using `NcDialog`
- [ ] Fields: Title (required text), WIP limit (optional number, min 1), Colour (optional colour picker or hex input)
- [ ] Title validation: inline error on blur; submit button disabled until title is non-empty
- [ ] On submit: call `columnsStore.createColumn({ title, wipLimit, color, project: projectId })`
- [ ] Success: close dialog, emit `created` event, show toast
- [ ] Failure: show toast, keep dialog open with field values intact
- [ ] Test

---

### Task 15: ColumnRenameDialog Component
- **spec_ref**: `openspec/changes/kanban-board/specs/kanban-board/spec.md#added-requirement--column-management`
- **files**: `src/components/dialogs/ColumnRenameDialog.vue`
- **acceptance_criteria**:
  - GIVEN the dialog opens WHEN called with a column THEN the title field is pre-filled with the current column title and WIP limit is pre-filled
  - GIVEN the user saves WHEN the PATCH succeeds THEN the board reflects the new title immediately (optimistic) and a toast is shown
  - GIVEN the user saves with an empty title THEN an inline error is shown and save is disabled
- [ ] Create `src/components/dialogs/ColumnRenameDialog.vue` using `NcDialog`
- [ ] Props: `{ column: Object }`
- [ ] Pre-fill title and `wipLimit` fields from `column` prop on open
- [ ] On save: call `columnsStore.updateColumn(column.id, { title, wipLimit })`
- [ ] Title validation: inline error, disabled save
- [ ] Emit `renamed` event on success; show toast
- [ ] Test

---

### Task 16: ColumnDeleteDialog Component
- **spec_ref**: `openspec/changes/kanban-board/specs/kanban-board/spec.md#added-requirement--column-management`
- **files**: `src/components/dialogs/ColumnDeleteDialog.vue`
- **acceptance_criteria**:
  - GIVEN a column with 0 tasks WHEN the dialog opens THEN simple confirmation "Delete column '{title}'?" is shown; "Delete" button is enabled immediately
  - GIVEN a column with 5 tasks WHEN the dialog opens THEN "This column has 5 tasks. Where should they go?" is shown and "Delete" is disabled until destination is chosen
  - GIVEN the user chooses "Move to backlog" THEN `moveTasksToBacklog` is called before `deleteColumn`; "Delete" button shows a spinner during migration
  - GIVEN the user chooses "Move to column" THEN a dropdown of other columns is shown; selecting one enables "Delete"
  - GIVEN migration partially fails THEN the column is NOT deleted; an error toast is shown; the dialog stays open
- [ ] Create `src/components/dialogs/ColumnDeleteDialog.vue` using `NcDialog`
- [ ] Props: `{ column: Object, otherColumns: Array }`
- [ ] On open: compute task count from `tasksStore.tasks` (filtered by `column.id`)
- [ ] If count === 0: show simple confirmation; enable Delete immediately
- [ ] If count > 0: show migration UI (radio: "Move to backlog" | "Move to column: [select]"); disable Delete until option chosen
- [ ] On confirm: if backlog → `columnsStore.moveTasksToBacklog(column.id)` then `columnsStore.deleteColumn(column.id)`
- [ ] On confirm: if column → `columnsStore.moveTasksToColumn(column.id, targetId)` then `columnsStore.deleteColumn(column.id)`
- [ ] Show spinner on Delete button during migration
- [ ] On any failure: show toast, keep dialog open
- [ ] On success: emit `deleted`, show toast "Column deleted", close dialog
- [ ] Test

---

### Task 17: ProjectBoard.vue — Wire Up All Components
- **spec_ref**: `openspec/changes/kanban-board/specs/kanban-board/spec.md`
- **files**: `src/views/ProjectBoard.vue`
- **acceptance_criteria**:
  - GIVEN the user navigates to `/projects/:id` THEN `KanbanBoard`, `KanbanFilterBar`, `ViewToggle`, and `BacklogPanel` are all mounted
  - GIVEN `#view=list` in URL hash THEN `ProjectTaskList` renders instead of `KanbanBoard`
  - GIVEN filter changes from `KanbanFilterBar` THEN `filteredTaskIds` is propagated to `KanbanBoard` (or `ProjectTaskList`)
  - GIVEN "+ Add task" is triggered from a column THEN `TaskCreateDialog` opens with `column` pre-filled
  - GIVEN "+ Add column" is clicked (admin/creator only) THEN `ColumnCreateDialog` opens
  - GIVEN the board has no columns and the user is admin/creator THEN "Add column" CnEmptyState is shown
  - GIVEN the board has no columns and the user is a regular member THEN read-only empty state is shown
- [ ] Modify `src/views/ProjectBoard.vue` — replace placeholder; import all kanban components
- [ ] Read URL hash on mount; set `currentView` ref (`'kanban'` | `'list'`)
- [ ] Manage `activeFilters` and `filteredTaskIds` at board level; pass down to child components
- [ ] Handle `add-task` event from columns: open `TaskCreateDialog` with `columnId` prop
- [ ] Handle `open-task` event from task cards: `$router.push({ name: 'TaskDetail', params: { id } })`
- [ ] Handle `created`, `renamed`, `deleted` events from dialogs: re-fetch columns or update store
- [ ] Permission check: show "+ Add column" and "Add column" CnEmptyState only to project creator/admin
- [ ] Test

---

### Task 18: Keyboard Accessibility
- **spec_ref**: `openspec/changes/kanban-board/specs/kanban-board/spec.md#added-requirement--keyboard-navigation-wcag-aa`
- **files**: `src/components/kanban/KanbanBoard.vue`, `src/components/kanban/KanbanColumn.vue`
- **acceptance_criteria**:
  - GIVEN the board is rendered WHEN the user presses Tab THEN focus moves through filter chips → view toggle → backlog toggle → "+ Add column" → column headers (left to right) → column action buttons → task cards
  - GIVEN focus is on a task card WHEN ↓ is pressed THEN focus moves to the next card in the same column; at last card, moves to "+ Add task"
  - GIVEN focus is on a task card WHEN Enter is pressed THEN `open-task` event is emitted and the router navigates to `/tasks/:id`
  - GIVEN the board container WHEN inspected with axe THEN no accessibility violations are reported
- [ ] Confirm all interactive elements are reachable by Tab in correct order (verify with browser test)
- [ ] Implement `@keydown.down` / `@keydown.up` in `KanbanColumn.vue` card list: manage focus with `$el.querySelectorAll('.task-card')` and `element.focus()`
- [ ] Implement `@keydown.enter` on task card: emit `open-task` to parent
- [ ] Verify `role="group"` on board container, `role="columnheader"` on column headers, `aria-label` on both
- [ ] Verify `aria-describedby` on WIP exceeded columns pointing to tooltip ID
- [ ] Run axe accessibility check in browser test
- [ ] Test

---

### Task 19: i18n — English Strings
- **spec_ref**: `openspec/changes/kanban-board/specs/kanban-board/spec.md`
- **files**: `l10n/en.json`
- **acceptance_criteria**:
  - GIVEN `l10n/en.json` WHEN inspected THEN all strings in the i18n inventory in `design.md` are present as keys
  - GIVEN any Vue template in this change WHEN all user-visible strings are checked THEN each uses `t('planix', '...')` and the key exists in `en.json`
  - GIVEN plural strings (task count, filter count) THEN `n('planix', singular, plural, count)` is used
- [ ] Add all kanban board strings to `l10n/en.json` (see i18n inventory in `design.md`)
- [ ] Use `t('planix', '...')` for all static strings; `n()` for plurals
- [ ] Verify no hardcoded English strings remain in any new Vue component
- [ ] Test

---

### Task 20: i18n — Dutch Translations
- **spec_ref**: `openspec/changes/kanban-board/specs/kanban-board/spec.md`
- **files**: `l10n/nl.json`
- **acceptance_criteria**:
  - GIVEN `l10n/nl.json` WHEN compared to `l10n/en.json` THEN every key added by this change in `en.json` also exists in `nl.json`
  - GIVEN the Dutch translations WHEN reviewed THEN they are natural Dutch (not literal English)
- [ ] Add Dutch translations for all kanban strings to `l10n/nl.json`
- [ ] Key translations: `Board` → `Bord`, `Kanban view` → `Kanbanbord`, `List view` → `Lijstweergave`, `Add column` → `Kolom toevoegen`, `Rename column` → `Kolom hernoemen`, `Delete column` → `Kolom verwijderen`, `No columns yet` → `Nog geen kolommen`, `No tasks yet` → `Nog geen taken`, `Add task` → `Taak toevoegen`, `Backlog` → `Backlog`, `Filter by` → `Filteren op`, `Clear filters` → `Filters wissen`, `Failed to move task — try again` → `Verplaatsen mislukt — probeer opnieuw`, `Column created` → `Kolom aangemaakt`, `Column deleted` → `Kolom verwijderd`, `At WIP limit` → `WIP-limiet bereikt`, `WIP limit exceeded` → `WIP-limiet overschreden`
- [ ] Test

---

## Verification
- [ ] All tasks checked off
- [ ] Manual testing against acceptance criteria in `openspec/specs/kanban-board.md` and delta spec

## Tests (company-wide ADR-009)
- [ ] Browser tests (Playwright MCP): board renders with columns and task cards
- [ ] Browser tests (Playwright MCP): drag card from column A to column B — card moves, PATCH is sent
- [ ] Browser tests (Playwright MCP): drag failure simulation — card reverts to original column, toast shown
- [ ] Browser tests (Playwright MCP): WIP limit exceeded — column header turns red, tooltip visible
- [ ] Browser tests (Playwright MCP): filter by priority — non-matching cards are dimmed
- [ ] Browser tests (Playwright MCP): filter state reflected in URL; navigating to URL restores filter
- [ ] Browser tests (Playwright MCP): view toggle — switch to list view and back; filters preserved
- [ ] Browser tests (Playwright MCP): column create (happy path + title validation)
- [ ] Browser tests (Playwright MCP): column rename
- [ ] Browser tests (Playwright MCP): column delete — no tasks (simple confirm)
- [ ] Browser tests (Playwright MCP): column delete — with tasks, move to backlog
- [ ] Browser tests (Playwright MCP): column delete — with tasks, move to another column
- [ ] Browser tests (Playwright MCP): column reorder via drag
- [ ] Browser tests (Playwright MCP): backlog panel expand/collapse
- [ ] Browser tests (Playwright MCP): drag from backlog panel to column
- [ ] Browser tests (Playwright MCP): keyboard navigation — Tab through board, arrow keys within column, Enter opens task
- [ ] Browser tests (Playwright MCP): empty board (no columns) — admin sees "Add column"; member sees read-only message
- [ ] Browser tests (Playwright MCP): empty column — shows CnEmptyState and "+ Add task" button
- [ ] All tests pass

## Documentation (company-wide ADR-010)
- [ ] Feature documentation updated (kanban board section in `docs/`)
- [ ] Screenshots captured: board with columns and cards, WIP exceeded state, filter active, list view, column delete dialog

## i18n (company-wide ADR-005)
- [ ] Dutch and English translation strings added (Tasks 19 and 20)
