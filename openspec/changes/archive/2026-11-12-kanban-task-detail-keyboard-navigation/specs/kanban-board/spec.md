# Kanban Board — Card Navigation and Keyboard Operability

**Spec refs**: `kanban-board` (openspec/specs/kanban-board/spec.md, AC "Board is keyboard-navigable (WCAG AA)"), ADR-010 (nl-design, hydra/openspec/architecture)
**Standards**: WCAG 2.1 AA (2.1.1 Keyboard, 4.1.2 Name/Role/Value)

## MODIFIED Requirements

### Requirement: Kanban Board View [MVP]

The system MUST render a kanban board with columns and task cards for each
project, and every card MUST be reachable and operable without a pointing
device. Each card MUST be a focusable control (`tabindex="0"`, `role="button"`,
an accessible name equal to the task title) that opens the task's detail view
on both `click` and keyboard activation (`Enter`/`Space`). Moving a task
between columns MUST be possible both via pointer drag-and-drop (existing
behavior, unchanged) and via a keyboard-operable "Move to…" control on the
card that invokes the same status-update path
(`updateTaskStatus`) drag-and-drop already uses, so the two paths produce
identical persisted state, optimistic-update, and rollback-on-failure
behavior.

**Feature tier**: MVP

#### Scenario: Display board

- GIVEN a project has columns and tasks
- WHEN a project member opens the board view
- THEN the system MUST display columns in their configured order
- AND each task card MUST show: title, assignee avatar, due date, priority
  indicator, label chips

#### Scenario: Keyboard user opens a task's detail view

- GIVEN a project member is navigating the board with the keyboard only
- WHEN the member tabs to a task card and presses Enter (or Space)
- THEN the system MUST navigate to that task's `TaskDetail` view
  (`/projects/:id/tasks/:taskId`)
- AND this MUST be the same destination a mouse click on the card reaches

#### Scenario: Mouse user opens a task's detail view

- GIVEN a project member is using a mouse
- WHEN the member clicks a task card (not initiating a drag)
- THEN the system MUST navigate to that task's `TaskDetail` view

#### Scenario: Keyboard user moves a task between columns

- GIVEN a project member is navigating the board with the keyboard only
- WHEN the member activates a card's "Move to…" control and selects a
  different column
- THEN the system MUST update the task's status via the same
  `updateTaskStatus` action the drag-and-drop path uses
- AND the change MUST be optimistic with rollback on failure, identical to
  a drag-and-drop move

#### Scenario: Drag-and-drop still works unchanged

- GIVEN a project member using a mouse
- WHEN the member drags a task card from one column and drops it on another
- THEN the task's status MUST update exactly as before this change
- AND the new click/keyboard affordances MUST NOT interfere with the drag
  gesture

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
