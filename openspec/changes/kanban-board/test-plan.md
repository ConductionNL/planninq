# Test Plan: kanban-board

## Test Cases

### TC-1: Board renders columns in horizontal scroll layout
- **spec_ref**: `openspec/changes/kanban-board/specs/kanban-board/spec.md#scenario-board-renders-in-css-grid-layout-with-horizontal-scroll`
- **type**: functional
- **persona**: project member
- **preconditions**: A project with 3+ columns and tasks in each column; user navigates to `/projects/:id`
- **steps**: Navigate to the project board; count rendered columns; scroll horizontally if columns exceed viewport
- **expected result**: Columns rendered in CSS Grid row (280 px wide each); board container is `overflow-x: auto`; columns do not wrap to a second row
- **test command**: /test-functional

### TC-2: Board loading state shows skeleton
- **spec_ref**: `openspec/changes/kanban-board/specs/kanban-board/spec.md#scenario-board-loading-state`
- **type**: functional
- **persona**: project member
- **preconditions**: API response is delayed
- **steps**: Navigate to the project board with a simulated slow API
- **expected result**: Skeleton loading state (skeleton columns + skeleton cards) is shown; drag-and-drop is disabled during loading
- **test command**: /test-functional

### TC-3: Column header shows task count and WIP indicator
- **spec_ref**: `openspec/changes/kanban-board/specs/kanban-board/spec.md#scenario-column-header-displays-title-task-count-and-wip-indicator`
- **type**: functional
- **persona**: project member
- **preconditions**: A column with `wipLimit: 3` and 2 tasks exists
- **steps**: View the column header on the board
- **expected result**: Header shows column title, "2 / 3" indicator, and menu button; no warning styling (under limit)
- **test command**: /test-functional

### TC-4: WIP warning at limit and exceeded
- **spec_ref**: `openspec/changes/kanban-board/specs/kanban-board/spec.md#scenario-wip-warning-at-limit`
- **type**: functional
- **persona**: project member
- **preconditions**: A column with `wipLimit: 3`; load exactly 3 tasks, then add a 4th
- **steps**: View the column header with 3 tasks; then add a 4th task to the same column
- **expected result**: At 3 tasks: header background uses `--color-warning`; tooltip "At WIP limit — 3 tasks in column"; at 4 tasks: background uses `--color-error`; tooltip reads "WIP limit (3) exceeded — 4 tasks in column"
- **test command**: /test-functional

### TC-5: Column color strip renders from column.color
- **spec_ref**: `openspec/changes/kanban-board/specs/kanban-board/spec.md#scenario-column-header-colour-strip`
- **type**: functional
- **persona**: project member
- **preconditions**: A column with `color: "#4CAF50"` and one with no color set
- **steps**: View both columns on the board
- **expected result**: Colored column shows a 4 px top border in `#4CAF50`; uncolored column shows a neutral `var(--color-border)` strip
- **test command**: /test-functional

### TC-6: Drag card between columns applies optimistically and reverts on failure
- **spec_ref**: `openspec/changes/kanban-board/specs/kanban-board/spec.md#scenario-card-drop-applies-immediately-with-rollback-on-failure`
- **type**: functional
- **persona**: project member
- **preconditions**: Board has columns A and B; a task card is in column A
- **steps**: Drag the card from column A to column B; observe the card position; simulate API failure on the PATCH
- **expected result**: Card appears in column B immediately (before API response); PATCH sent with `{ column: columnBId, columnOrder: newIndex }`; on failure, card reverts to column A; toast "Failed to move task — try again" shown
- **test command**: /test-functional

### TC-7: Drag to done-type column sets status to done
- **spec_ref**: `openspec/changes/kanban-board/specs/kanban-board/spec.md#scenario-drag-to-done-type-column-sets-task-status-to-done`
- **type**: functional
- **persona**: project member
- **preconditions**: Board has a column with `type: 'done'`; a task with `status: 'open'` exists
- **steps**: Drag the open task into the done-type column
- **expected result**: PATCH includes `{ column: columnId, columnOrder: newIndex, status: 'done', completedAt: <ISO> }`; `TaskStatusBadge` immediately reflects `status: 'done'`
- **test command**: /test-functional

### TC-8: Filter bar dims non-matching cards
- **spec_ref**: `openspec/changes/kanban-board/specs/kanban-board/spec.md#scenario-applying-a-filter-dims-non-matching-cards`
- **type**: functional
- **persona**: project member
- **preconditions**: Board has tasks assigned to multiple users and with multiple priorities
- **steps**: Select "Priority: High" in the filter bar
- **expected result**: Cards NOT matching the filter render with `opacity: 0.35` and `pointer-events: none`; matching cards render at full opacity; no tasks are removed from the DOM
- **test command**: /test-functional

### TC-9: Filter state syncs to URL and is restored on load
- **spec_ref**: `openspec/changes/kanban-board/specs/kanban-board/spec.md#scenario-filter-state-syncs-to-url-query-params`
- **type**: functional
- **persona**: project member
- **preconditions**: Board is loaded
- **steps**: Activate filters "Assignee: admin" and "Priority: high"; copy the URL; navigate away; return to the copied URL
- **expected result**: URL updates to `?assignee=admin&priority=high` without reload; returning to the URL restores the same active filters
- **test command**: /test-functional

### TC-10: View toggle switches between kanban and list views
- **spec_ref**: `openspec/changes/kanban-board/specs/kanban-board/spec.md#scenario-switch-to-list-view`
- **type**: functional
- **persona**: project member
- **preconditions**: Board is in kanban view (default)
- **steps**: Click the list view toggle button
- **expected result**: `ProjectTaskList.vue` replaces the kanban board content area; URL hash updates to `#view=list`; active filter params are preserved; list view shows a sortable table with columns Title, Assignee, Due Date, Status, Priority, Labels
- **test command**: /test-functional

### TC-11: Create column
- **spec_ref**: `openspec/changes/kanban-board/specs/kanban-board/spec.md#scenario-create-column`
- **type**: functional
- **persona**: project creator / admin
- **preconditions**: Board is visible; user has project admin rights
- **steps**: Click "+ Add column"; enter a title and optional WIP limit in `ColumnCreateDialog`; click submit
- **expected result**: Column is created via POST; appears at the right end of the board with correct order; success toast "Column created" shown
- **test command**: /test-functional

### TC-12: Delete column with tasks — move to backlog
- **spec_ref**: `openspec/changes/kanban-board/specs/kanban-board/spec.md#scenario-delete-column-with-tasks-move-to-backlog`
- **type**: functional
- **persona**: project creator / admin
- **preconditions**: A column with 5 tasks exists
- **steps**: Open column menu; click "Delete column"; choose "Move to backlog" in the confirmation dialog; confirm
- **expected result**: All 5 tasks are PATCHed with `{ column: null }` before column deletion; delete button was disabled during migration; column is deleted; success toast shown
- **test command**: /test-functional

### TC-13: Delete column with tasks — move to another column
- **spec_ref**: `openspec/changes/kanban-board/specs/kanban-board/spec.md#scenario-delete-column-with-tasks-move-to-another-column`
- **type**: functional
- **persona**: project creator / admin
- **preconditions**: Two columns exist; source column has 5 tasks
- **steps**: Delete source column; choose "Move to column: [target column]"; confirm
- **expected result**: All 5 tasks are PATCHed with `{ column: targetColumnId }`; target column immediately shows the migrated tasks
- **test command**: /test-functional

### TC-14: Backlog panel toggle expands and collapses
- **spec_ref**: `openspec/changes/kanban-board/specs/kanban-board/spec.md#scenario-backlog-panel-toggle`
- **type**: functional
- **persona**: project member
- **preconditions**: Board is rendered; at least one backlog task exists (`column: null`)
- **steps**: Click the backlog panel toggle button; then toggle again
- **expected result**: Panel expands to 240 px (animated); shows task cards in compact mode and count "{N} task(s) in backlog"; second toggle collapses panel to 40 px icon strip with count badge
- **test command**: /test-functional

### TC-15: Drag from backlog panel to column reverts on failure
- **spec_ref**: `openspec/changes/kanban-board/specs/kanban-board/spec.md#scenario-drag-from-backlog-panel-to-column`
- **type**: functional
- **persona**: project member
- **preconditions**: Backlog panel is expanded; at least one backlog task exists; API PATCH is set to fail
- **steps**: Drag a task from the backlog panel into a board column; observe failure
- **expected result**: On failure: task reappears in the backlog panel; a toast is shown; task does not appear in the column
- **test command**: /test-functional

### TC-16: Tab key navigation reaches all interactive elements
- **spec_ref**: `openspec/changes/kanban-board/specs/kanban-board/spec.md#scenario-tab-navigation-reaches-all-interactive-elements`
- **type**: accessibility
- **persona**: keyboard-only user
- **preconditions**: Board is rendered with at least 2 columns each having at least 2 task cards; backlog panel toggle and "+ Add column" button are visible
- **steps**: Starting from before the filter bar, press Tab repeatedly and record focused elements in order
- **expected result**: Focus moves through: filter chips → view toggle → backlog panel toggle → "+ Add column" → each column header → each column action button → each task card (left-to-right, top-to-bottom)
- **test command**: /test-accessibility

### TC-17: Arrow key navigation within column
- **spec_ref**: `openspec/changes/kanban-board/specs/kanban-board/spec.md#scenario-arrow-key-navigation-within-column`
- **type**: accessibility
- **persona**: keyboard-only user
- **preconditions**: Board is rendered with a column containing at least 3 task cards
- **steps**: Tab to the first task card in a column; press ↓ twice; press ↑ once
- **expected result**: Focus moves to the next card on ↓; moves to the previous card on ↑; focus wraps correctly at boundaries
- **test command**: /test-accessibility

### TC-18: Board and column ARIA roles
- **spec_ref**: `openspec/changes/kanban-board/specs/kanban-board/spec.md#scenario-board-container-aria-role`
- **type**: accessibility
- **persona**: screen reader user
- **preconditions**: Board is rendered
- **steps**: Inspect the board container and column header elements with an accessibility tool
- **expected result**: Board container has `role="group"` and `aria-label="{projectName} board"`; column headers have `role="columnheader"` with descriptive `aria-label`; WIP exceeded headers have `aria-describedby` pointing to tooltip text
- **test command**: /test-accessibility

### TC-19: Empty board — admin sees Add column button; member sees read-only message
- **spec_ref**: `openspec/changes/kanban-board/specs/kanban-board/spec.md#scenario-no-columns-admin-sees-add-column`
- **type**: functional
- **persona**: project admin (TC-19a) and project member non-admin (TC-19b)
- **preconditions**: A project with zero columns exists
- **steps**: Open the board as a project admin (TC-19a); open the board as a regular project member (TC-19b)
- **expected result**: Admin sees `CnEmptyState` "No columns yet" with "Add column" button; member sees "No columns yet — ask a project admin to set up the board" with NO add button
- **test command**: /test-functional

## Coverage Summary

| Requirement | Scenarios Covered | Test Cases |
|-------------|-------------------|------------|
| KanbanBoard Component Layout | CSS Grid, loading, task fetch | TC-1, TC-2 |
| KanbanColumn Component | Header, WIP, color strip, menu, empty states | TC-3, TC-4, TC-5 |
| Optimistic Drag-and-Drop | Drop/rollback, drag lock, done-type, reorder, backlog | TC-6, TC-7, TC-15 |
| KanbanFilterBar Component | Dimming, URL sync, filter count badge | TC-8, TC-9 |
| ViewToggle Component | Switch to list, sortable table, no-task empty state | TC-10 |
| Column Management | Create, rename (covered by TC-11), delete (2 migration paths) | TC-11, TC-12, TC-13 |
| Backlog Panel | Toggle, content, drag to board, empty state | TC-14, TC-15 |
| Keyboard Navigation (WCAG AA) | Tab order, arrow keys, Enter | TC-16, TC-17 |
| ARIA Roles | Board, column, WIP | TC-18 |
| Empty Board States | Admin vs member | TC-19 |

## Out of Scope

- Reorder columns via drag — covered in integration tests after full kanban implementation (complex multi-column PATCH sequencing)
- `ProjectTaskList` sort persistence across navigation — deferred to regression tests
- WIP limit override by admin (if any) — not in current spec MVP
