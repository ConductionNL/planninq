# kanban-board Specification

## Purpose

Due-date warning badges on kanban board task cards. This is the badge slice of
the kanban board capability — the broader board (columns, drag-drop, WIP limits,
filters, list-view toggle, blocked indicator) is described in the roadmap spec
[`openspec/specs/kanban-board.md`](../kanban-board.md). This file is the
canonical, gate-traceable home for the `task-due-date-warning` requirements,
archived from the change of the same name.

## Requirements

### Requirement: Due Date Status Helper [MVP]
The system MUST provide a `dueDateStatus` helper function that computes the due date urgency of a task.

The function MUST accept a task object and return:
- `null` when the task has no `dueDate`, or the due date is more than 2 days in the future
- `"approaching"` when the due date is within 2 days (including exactly 2 days from now)
- `"overdue"` when the due date is in the past (before today)

Date comparison MUST use date-only values (ignore time component) to align with the `dueDate` field type (iCalendar `DUE`, type: `date`).

<!-- @e2e exclude Pure helper covered by the dueDateStatus vitest unit suite (tests/vitest/dueDateStatus.spec.js); no UI surface to drive in Playwright. -->

#### Scenario: Task with no due date
- GIVEN a task has no `dueDate` set (null or undefined)
- WHEN `dueDateStatus` is called with the task
- THEN it MUST return `null`

#### Scenario: Task due date far in the future
- GIVEN a task has `dueDate` set to 5 days from today
- WHEN `dueDateStatus` is called with the task
- THEN it MUST return `null`

#### Scenario: Task due date approaching
- GIVEN a task has `dueDate` set to tomorrow
- WHEN `dueDateStatus` is called with the task
- THEN it MUST return `"approaching"`

#### Scenario: Task due date is today
- GIVEN a task has `dueDate` set to today
- WHEN `dueDateStatus` is called with the task
- THEN it MUST return `"approaching"`

#### Scenario: Task due date exactly 2 days away
- GIVEN a task has `dueDate` set to exactly 2 days from today
- WHEN `dueDateStatus` is called with the task
- THEN it MUST return `"approaching"`

#### Scenario: Task is overdue
- GIVEN a task has `dueDate` set to yesterday
- WHEN `dueDateStatus` is called with the task
- THEN it MUST return `"overdue"`

### Requirement: Due Date Badge on Task Card [MVP]
The kanban board task card MUST display a colored badge indicating due date urgency.

- When `dueDateStatus` returns `"approaching"`: the card MUST show a yellow/warning `NcChip` with text "Due soon"
- When `dueDateStatus` returns `"overdue"`: the card MUST show a red/error `NcChip` with text "Overdue"
- When `dueDateStatus` returns `null`: the card MUST NOT show a due date badge

The badge MUST use Nextcloud theming CSS variables for colors to ensure consistency with the active theme.

#### Scenario: Approaching task shows yellow badge
- GIVEN a task has `dueDate` set to tomorrow
- WHEN the task card is rendered on the kanban board
- THEN the card MUST display a yellow badge with text "Due soon"

#### Scenario: Overdue task shows red badge
- GIVEN a task has `dueDate` set to 3 days ago
- WHEN the task card is rendered on the kanban board
- THEN the card MUST display a red badge with text "Overdue"

#### Scenario: Normal task shows no badge
- GIVEN a task has `dueDate` set to 10 days from now
- WHEN the task card is rendered on the kanban board
- THEN the card MUST NOT display a due date warning badge

#### Scenario: Task without due date shows no badge
- GIVEN a task has no `dueDate`
- WHEN the task card is rendered on the kanban board
- THEN the card MUST NOT display a due date warning badge
