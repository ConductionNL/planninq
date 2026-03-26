# Kanban Board Specification

**Status**: planned

**Standards**: Schema.org ItemList (board), DefinedTerm (column), Kanban Guide (kanban.university)
**Feature tier**: MVP

**OpenSpec changes:** _(links to openspec/changes/ directories when in-progress or done)_

## Purpose

The kanban board is the primary visual interface for a project in Planix. It shows tasks as cards organized into configurable columns (stages). Users drag and drop cards between columns to update task status. WIP limits on columns enforce flow discipline. Boards are filtered by assignee, label, or priority to focus attention. Each project has exactly one kanban board; columns are managed as part of the project.

## Data Model

See [ARCHITECTURE.md](../../docs/ARCHITECTURE.md) for full Column entity definition.

**Column summary**:

| Property | Type | Required | Default |
|----------|------|----------|---------|
| `title` | string | Yes | — |
| `project` | reference (Project) | Yes | — |
| `order` | integer | Yes | 0 |
| `wipLimit` | integer \| null | No | null |
| `color` | string (hex) | No | — |
| `type` | enum: active, done | No | `active` |

## Requirements

### Requirement: Kanban Board View [MVP]
The system MUST render a kanban board with columns and task cards for each project.

#### Scenario: Display board
- GIVEN a project has columns and tasks
- WHEN a project member opens the board view
- THEN the system MUST display columns in their configured order
- AND each task card MUST show: title, assignee avatar, due date, priority indicator, label chips
- AND the system MUST show the task count and WIP limit indicator per column

#### Scenario: Drag-and-drop task to column
- GIVEN a task card is visible on the board
- WHEN a user drags a task from one column to another
- THEN the system MUST update the task's `column` reference and `columnOrder` in OpenRegister
- AND the card MUST appear in the new column position immediately (optimistic UI)
- AND if the backend update fails the system MUST revert the card to its original position

#### Scenario: WIP limit visual warning
- GIVEN column "In Progress" has wipLimit = 3 and contains 3 tasks
- WHEN a fourth task is dragged into the column
- THEN the column header MUST turn orange/red indicating a limit violation
- AND a tooltip MUST explain "WIP limit (3) exceeded"
- AND the task MUST be placed in the column despite the violation (soft limit)

#### Scenario: Column management
- GIVEN a project member is in the project settings
- WHEN the user adds a new column with title and optional WIP limit
- THEN the system MUST create the column and append it to the board
- AND the user MUST be able to reorder columns via drag-and-drop

#### Scenario: Board filter
- GIVEN the board shows multiple tasks from multiple assignees
- WHEN the user selects a filter (e.g., "Assignee: me")
- THEN the system MUST visually dim or hide tasks that do not match the filter
- AND the URL MUST reflect the active filter (for shareable filtered views)

#### Scenario: Drag task from backlog to board
- GIVEN the backlog panel is open alongside the board
- WHEN a user drags a task from the backlog into a board column
- THEN the system MUST assign the task to that column
- AND the task MUST disappear from the backlog panel

#### Scenario: Delete column with tasks
- GIVEN a column contains one or more tasks
- WHEN a user deletes the column from project settings
- THEN the system MUST show a dialog: "This column contains {N} tasks. Move them to the backlog, or move to another column?"
- AND the system MUST NOT delete the column until the user confirms a destination for the tasks
- AND if "Move to backlog" is chosen, all tasks MUST have their `column` cleared
- AND if "Move to column" is chosen, all tasks MUST be reassigned to the selected column

#### Scenario: Empty board — no columns yet
- GIVEN a project has no columns (e.g., default columns were cleared by admin before project was created)
- WHEN a member opens the board view
- THEN the system MUST show a CnEmptyState with message "No columns yet"
- AND a project creator or admin MUST see an "Add column" button to create the first column

#### Scenario: Empty board — no tasks yet
- GIVEN a project has columns but no tasks
- WHEN a member opens the board view
- THEN each empty column MUST show a CnEmptyState with a "+ Add task" button
- AND clicking "+ Add task" in a column MUST open the task creation form with that column pre-selected

### Requirement: View Toggle — Kanban and List [MVP]
The system MUST allow users to switch between kanban (card) view and list (table) view for a project's tasks.

#### Scenario: Switch to list view
- GIVEN a user is on the kanban board view
- WHEN the user clicks the list view toggle button
- THEN the system MUST render tasks as a sortable flat list (title, assignee, due date, status, priority, labels)
- AND the selected view MUST persist in the URL hash so the page reloads in the same view

#### Scenario: Switch back to kanban view
- GIVEN a user is in the list view
- WHEN the user clicks the kanban view toggle button
- THEN the system MUST render the kanban board with columns and cards
- AND any active filter MUST remain applied in the new view

#### Scenario: Update task from list view
- GIVEN a user is in list view
- WHEN the user clicks a task row
- THEN the system MUST navigate to the task detail view (CnDetailPage)
- AND the browser back button MUST return to list view (not kanban view)

## User Stories

- As a developer, I want to see all project tasks as cards on a board so that I understand the current state of work at a glance
- As a team member, I want to drag a task card to "Done" so that I can mark it as complete without opening the detail view
- As a project lead, I want to set WIP limits on columns so that the team doesn't overload any single stage
- As a user, I want to filter the board by my name so that I can see only my tasks
- As an admin, I want to add and reorder columns so that the board matches our team's workflow
- As a user, I want to switch between kanban and list view so that I can choose the layout that suits my current focus
- As a project creator, I want guidance when my board has no columns yet so that I know how to get started
- As a user, I want a safe prompt when deleting a column that contains tasks so that I don't accidentally lose work

## Acceptance Criteria

- [ ] Board columns render in configured order; columns are horizontally scrollable if there are many
- [ ] Task cards show title, assignee avatar (NC user avatar), due date (red if overdue), priority dot, label chips
- [ ] Drag-and-drop updates task column and order; optimistic UI with rollback on failure
- [ ] WIP limit exceeded shows a red column header and tooltip
- [ ] Filter by assignee, label, and priority works without full page reload
- [ ] Filter state is reflected in the URL hash (shareable)
- [ ] Columns can be created, renamed, reordered, and deleted via project settings
- [ ] Deleting a column with tasks prompts the user to move tasks to backlog or another column before deleting
- [ ] Board is keyboard-navigable (WCAG AA)
- [ ] Empty columns show a CnEmptyState with a "+ Add task" button; clicking pre-selects that column in the task form
- [ ] A board with no columns shows a CnEmptyState with an "Add column" button (visible to creator/admin)
- [ ] View toggle (kanban ↔ list) is available in the project toolbar; selected view persists in the URL hash
- [ ] List view shows tasks as a sortable table: title, assignee, due date, status, priority, labels
- [ ] Switching views preserves active filters
- [ ] Clicking a task in list view navigates to task detail; back button returns to list view

## Notes

- The kanban board is a custom Planix Vue component (drag-and-drop via SortableJS/vue-draggable), not from the `@conduction/nextcloud-vue` library. It uses `useObjectStore` for data operations and `CnStatusBadge` for card status indicators.
- Swimlanes (V1): group cards horizontally by assignee or priority within each column
- Card quick-edit (V1): inline editing of title, due date, assignee on hover without opening detail
- Collapsed columns (V1): columns can be collapsed to save horizontal space
- Blocked task indicators (V1): tasks with unresolved `blocks` dependencies show a lock icon
