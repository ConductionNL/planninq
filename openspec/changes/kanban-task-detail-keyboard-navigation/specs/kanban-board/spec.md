# Kanban Board — Card Navigation and Keyboard Operability

**Spec refs**: `kanban-board` (openspec/specs/kanban-board.md, AC "Board is keyboard-navigable (WCAG AA)"), ADR-010 (nl-design, hydra/openspec/architecture)
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
