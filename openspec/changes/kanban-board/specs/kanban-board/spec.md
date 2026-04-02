# Delta Spec: kanban-board

**Change ID:** kanban-board
**Parent spec:** `openspec/specs/kanban-board.md`
**Status:** draft
**Created:** 2026-04-02

---

## Scope

This delta spec adds implementation-level requirements for the `kanban-board` change. All requirements in `openspec/specs/kanban-board.md` remain in effect. This document specifies **ADDED** requirements for concrete component behaviour, interaction details, and edge cases not covered in the parent spec.

---

## ADDED: Requirement — KanbanBoard Component Layout

The `KanbanBoard.vue` component MUST render the project board with the following structural properties.

### Scenario: Board renders in CSS Grid layout with horizontal scroll
- GIVEN a project with one or more columns
- WHEN `KanbanBoard` mounts and `fetchColumns(projectId)` resolves
- THEN columns MUST be rendered in a CSS Grid row (`grid-auto-flow: column`) with each column 280 px wide
- AND the board container MUST be `overflow-x: auto` so that columns exceeding viewport width are reachable by scrolling
- AND columns MUST NOT wrap to a second row

### Scenario: Board loading state
- GIVEN `fetchColumns` or `fetchTasks` is in progress
- WHEN the component is mounted
- THEN a skeleton loading state MUST be shown (skeleton columns + skeleton cards)
- AND drag-and-drop MUST be disabled during loading

### Scenario: Board fetches all tasks for the project on mount
- GIVEN the user navigates to `/projects/:id`
- WHEN `KanbanBoard` mounts
- THEN `fetchColumns(projectId)` and `fetchTasks({ project: projectId })` MUST be called
- AND all tasks (regardless of column) MUST be loaded into `useTasksStore`
- AND each column MUST render only its own tasks (filtered client-side by `task.column === column.id`)

---

## ADDED: Requirement — KanbanColumn Component

The `KanbanColumn.vue` component MUST implement the following behaviour.

### Scenario: Column header displays title, task count, and WIP indicator
- GIVEN a column with `wipLimit: 3` and 2 tasks
- WHEN the column renders
- THEN the header MUST show the column title, "2 / 3" indicator, and the column menu button
- AND the header MUST have no warning styling (count is below limit)

### Scenario: Column header colour strip
- GIVEN a column with `color: "#4CAF50"`
- WHEN the column renders
- THEN a 4 px top border strip in the column's colour MUST be visible on the column header
- AND if `color` is not set, the strip MUST use `var(--color-border)` as a neutral default

### Scenario: WIP warning at limit
- GIVEN a column with `wipLimit: 3` and exactly 3 tasks
- WHEN the column renders
- THEN the column header background MUST use `var(--color-warning)` (amber)
- AND a tooltip MUST be visible on the WIP indicator: "At WIP limit — 3 tasks in column"

### Scenario: WIP exceeded
- GIVEN a column with `wipLimit: 3` and 4 or more tasks
- WHEN the column renders
- THEN the column header background MUST use `var(--color-error)` (red)
- AND the tooltip on the WIP indicator MUST read "WIP limit (3) exceeded — {count} tasks in column"

### Scenario: Column menu actions
- GIVEN the user clicks the column menu (⋮) button
- WHEN the menu opens
- THEN the menu MUST contain: "Rename column", "Set WIP limit", "Delete column"
- AND "Rename column" MUST open `ColumnRenameDialog`
- AND "Delete column" MUST open `ColumnDeleteDialog`

### Scenario: Empty column
- GIVEN a column with zero tasks and no active filter
- WHEN the column renders
- THEN a `CnEmptyState` MUST be shown inside the column with the message "No tasks yet"
- AND a "+ Add task" button MUST be visible below the empty state
- AND clicking "+ Add task" MUST open `TaskCreateDialog` with `column` pre-populated to this column's ID

### Scenario: Empty column under active filter
- GIVEN a column that has tasks but all are filtered out (dimmed)
- WHEN a filter is active
- THEN the column MUST NOT show the "No tasks yet" empty state (tasks exist, they are just filtered)
- AND the column MUST show a faint "All tasks filtered" message instead

---

## ADDED: Requirement — Optimistic Drag-and-Drop

### Scenario: Card drop applies immediately with rollback on failure
- GIVEN a user drags a task card from column A to column B
- WHEN the card is dropped
- THEN the card MUST appear in column B at the drop position immediately (before API response)
- AND a PATCH request MUST be sent: `{ column: columnBId, columnOrder: newIndex }`
- AND if the PATCH fails, the card MUST be restored to its original position in column A
- AND a toast "Failed to move task — try again" MUST be shown on failure

### Scenario: Drag is disabled during pending API call
- GIVEN a card was just dropped and a PATCH is in-flight
- WHEN another user attempts to drag the same card
- THEN drag MUST be disabled on that card until the PATCH resolves
- AND a loading spinner MUST be shown on the card during the pending state

### Scenario: Drag to `done`-type column sets task status to `done`
- GIVEN a task with `status: 'open'` is dragged into a column with `type: 'done'`
- WHEN the card is dropped
- THEN the PATCH MUST include `{ column: columnId, columnOrder: newIndex, status: 'done', completedAt: <ISO> }`
- AND the `TaskCard` on the board MUST immediately reflect `status: 'done'` (via `TaskStatusBadge`)

### Scenario: Reorder within same column
- GIVEN a user drags a task card within the same column to a new position
- WHEN the card is dropped
- THEN all affected tasks MUST have their `columnOrder` PATCHed to reflect the new sequence
- AND the board MUST reflect the new order immediately

### Scenario: Drag from backlog panel to column
- GIVEN the backlog panel is open and shows task T (column: null)
- WHEN the user drags T from the panel and drops it into column B
- THEN the PATCH MUST set `{ column: columnBId, columnOrder: 0 }` (prepend to column by default)
- AND task T MUST disappear from the backlog panel
- AND task T MUST appear at the top of column B
- AND on failure, T MUST reappear in the backlog panel and a toast MUST be shown

---

## ADDED: Requirement — KanbanFilterBar Component

### Scenario: Filter chips render for available dimensions
- GIVEN the board has tasks assigned to multiple users and with multiple priorities
- WHEN `KanbanFilterBar` renders
- THEN three filter chip groups MUST be visible: "Assignee", "Priority", "Label"
- AND each chip group MUST show available values as selectable chips (multi-select within group)

### Scenario: Applying a filter dims non-matching cards
- GIVEN the user selects "Priority: High" in the filter bar
- WHEN the filter is applied
- THEN task cards NOT matching the filter MUST be rendered with `opacity: 0.35` and `pointer-events: none`
- AND task cards matching the filter MUST render at full opacity
- AND no tasks are removed from the DOM (columns and drag targets remain intact)

### Scenario: Active filter count badge
- GIVEN one or more filters are active
- WHEN the filter bar renders
- THEN a badge MUST show the number of active filter criteria
- AND a "Clear filters" button MUST be visible that removes all active filters

### Scenario: Filter state syncs to URL query params
- GIVEN the user activates filters "Assignee: admin" and "Priority: high"
- WHEN the filters are applied
- THEN the URL MUST update to `?assignee=admin&priority=high` without a page reload (Vue Router `replace`)
- AND navigating to the URL with those params MUST restore the same active filters on mount

### Scenario: Filters preserved on view switch
- GIVEN filters "Priority: high" are active in kanban view
- WHEN the user switches to list view
- THEN the list view MUST apply the same Priority: high filter
- AND the URL MUST retain the filter params: `?priority=high#view=list`

---

## ADDED: Requirement — ViewToggle Component

### Scenario: Default view is kanban
- GIVEN the user navigates to `/projects/:id` with no hash
- WHEN the board component mounts
- THEN the kanban view MUST be rendered by default
- AND the kanban toggle button MUST be in the active/selected state

### Scenario: Switch to list view
- GIVEN the user is in kanban view
- WHEN the user clicks the list view toggle
- THEN `ProjectTaskList.vue` MUST replace the kanban board content area
- AND the URL hash MUST update to `#view=list` (no page reload)
- AND the list view MUST immediately apply any active filter params from the query string

### Scenario: List view — sortable table
- GIVEN the user is in list view
- WHEN the component renders
- THEN a sortable table MUST display all project tasks (filtered by active filters)
- AND columns MUST be: Title, Assignee, Due Date, Status, Priority, Labels
- AND clicking any column header MUST sort the table by that column (toggle asc/desc)
- AND clicking a task row MUST navigate to `/tasks/:id`
- AND the browser back button MUST return to the list view (hash preserved)

### Scenario: No tasks in list view
- GIVEN the project has no tasks matching active filters
- WHEN the list view renders
- THEN `NcEmptyContent` MUST be shown with "No tasks" message
- AND if no filters are active, an "Add your first task" button MUST be shown

---

## ADDED: Requirement — Column Management

### Scenario: Create column
- GIVEN the user clicks the "+ Add column" button (visible to project creator/admin)
- WHEN `ColumnCreateDialog` opens and the user enters a title and optional WIP limit
- THEN the column MUST be created via POST to OpenRegister
- AND the new column MUST appear at the right end of the board with `order: max(existing orders) + 1`
- AND a success toast "Column created" MUST be shown

### Scenario: Create column — validation
- GIVEN `ColumnCreateDialog` is open
- WHEN the user submits without entering a title
- THEN an inline error "Column title is required" MUST be shown
- AND the submit button MUST remain disabled

### Scenario: Rename column
- GIVEN the user selects "Rename column" from the column menu
- WHEN `ColumnRenameDialog` opens with the current title pre-filled
- THEN the user MAY update the title and/or WIP limit
- AND saving MUST PATCH the column with the new values
- AND the board MUST reflect the new title immediately (optimistic update)

### Scenario: Reorder columns via drag
- GIVEN the user drags a column header to a new position
- WHEN the column is dropped
- THEN all columns MUST have their `order` PATCHed to reflect the new sequence
- AND the board MUST reflect the new column order immediately
- AND on failure, columns MUST revert to their previous order and a toast MUST be shown

### Scenario: Delete column — no tasks
- GIVEN a column with zero tasks
- WHEN the user selects "Delete column"
- THEN `ColumnDeleteDialog` MUST show: "Delete column '{title}'? This will permanently delete the column."
- AND clicking "Delete" MUST delete the column and remove it from the board
- AND a success toast "Column deleted" MUST be shown

### Scenario: Delete column — with tasks (move to backlog)
- GIVEN a column with 5 tasks
- WHEN the user selects "Delete column" and chooses "Move to backlog"
- THEN all 5 tasks MUST be PATCHed with `{ column: null }` before the column is deleted
- AND the "Delete column" button MUST remain disabled until all task PATCHes complete
- AND the column MUST be deleted after all tasks are migrated
- AND a success toast MUST be shown

### Scenario: Delete column — with tasks (move to another column)
- GIVEN a column with 5 tasks and at least one other column exists
- WHEN the user selects "Delete column" and chooses "Move to column: [target]"
- THEN all 5 tasks MUST be PATCHed with `{ column: targetColumnId }` before the column is deleted
- AND the target column on the board MUST immediately reflect the migrated tasks

---

## ADDED: Requirement — Backlog Panel

### Scenario: Backlog panel toggle
- GIVEN the board view is open
- WHEN the user clicks the backlog panel toggle button
- THEN the backlog panel MUST expand to 240 px width (animated, 0.2 s)
- AND when toggled again, it MUST collapse to a 40 px icon strip showing the backlog task count badge

### Scenario: Backlog panel content
- GIVEN the backlog panel is expanded
- WHEN `fetchTasks({ project: projectId, column: null })` resolves
- THEN the panel MUST render task cards using `TaskCard` (compact mode)
- AND the panel MUST show the count: "{N} task(s) in backlog" in the header

### Scenario: Drag from backlog to board (covered in drag-and-drop requirement above)

### Scenario: Empty backlog panel
- GIVEN the project has no backlog tasks (`column: null` tasks)
- WHEN the backlog panel is expanded
- THEN `CnEmptyState` MUST be shown with "Backlog is empty"

---

## ADDED: Requirement — Keyboard Navigation (WCAG AA)

### Scenario: Tab navigation reaches all interactive elements
- GIVEN the board view is rendered
- WHEN the user navigates with Tab
- THEN focus MUST move through: filter chips → view toggle → backlog panel toggle → "+ Add column" → each column header → each column action button → each task card (left-to-right, top-to-bottom)

### Scenario: Arrow key navigation within column
- GIVEN focus is on a task card in a column
- WHEN the user presses ↓
- THEN focus MUST move to the next task card in the same column
- AND when the user presses ↑, focus MUST move to the previous task card
- AND at the first/last card, focus MUST wrap to the column header / "+ Add task" button respectively

### Scenario: Enter key opens task detail
- GIVEN focus is on a task card
- WHEN the user presses Enter
- THEN the router MUST navigate to `/tasks/:id` for that task

### Scenario: Accessible column header
- GIVEN a column with `wipLimit: 3` exceeded
- WHEN the column header renders
- THEN `role="columnheader"` MUST be set
- AND `aria-label` MUST include the column title and task count
- AND `aria-describedby` MUST reference the WIP exceeded tooltip text

### Scenario: Board container ARIA role
- GIVEN the kanban board renders
- WHEN inspected with an accessibility tool
- THEN the board container MUST have `role="group"` and `aria-label="{projectName} board"`

---

## ADDED: Requirement — Empty Board States

### Scenario: No columns — admin sees "Add column"
- GIVEN a project has zero columns
- WHEN a project creator or admin opens the board
- THEN `CnEmptyState` MUST be shown with "No columns yet"
- AND an "Add column" button MUST be shown and MUST open `ColumnCreateDialog`

### Scenario: No columns — regular member sees read-only message
- GIVEN a project has zero columns
- WHEN a project member (non-creator, non-admin) opens the board
- THEN `CnEmptyState` MUST be shown with "No columns yet — ask a project admin to set up the board"
- AND NO "Add column" button is shown

---

## Acceptance Criteria (delta)

All acceptance criteria from `openspec/specs/kanban-board.md` apply. The following are ADDED by this delta spec:

- [ ] Board renders skeleton loading state while columns/tasks are fetched
- [ ] Drag is disabled during pending PATCH calls on the affected card
- [ ] Drag to `done`-type column automatically sets `status: 'done'` in the same PATCH
- [ ] Filter chips show available values from current board tasks; multi-select supported
- [ ] Non-matching cards are dimmed (opacity 0.35) not hidden; DOM columns remain intact for drop targets
- [ ] Column header renders a colour strip (4 px top border) using `column.color` or neutral default
- [ ] Column reorder PATCH is optimistic with revert-on-failure
- [ ] Column delete dialog blocks the delete button until migration destination is confirmed
- [ ] Backlog panel collapses to a 40 px strip with task count badge
- [ ] Drag from backlog panel to column: on failure, task reappears in backlog panel
- [ ] Empty backlog panel shows CnEmptyState
- [ ] Board container has `role="group"` and `aria-label`
- [ ] Column headers have `role="columnheader"` and descriptive `aria-label`
- [ ] WIP exceeded headers have `aria-describedby` pointing to the tooltip text
- [ ] Tab order covers all interactive elements left-to-right, top-to-bottom
- [ ] Arrow key navigation within a column works between task cards
- [ ] Non-admin members see a read-only message on an empty board (no "Add column" button)
