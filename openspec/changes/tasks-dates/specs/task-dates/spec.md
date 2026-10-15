# task-dates delta for tasks-dates

Extends the flat main spec `openspec/specs/tasks.md` (task fields).

## ADDED Requirements

### Requirement: A project member can set a due date without a time

The system MUST let a project member set, change and clear a task's due date as a calendar
date without a time of day, from the task page and from the task dialog. Tier: MVP
(docs/FEATURES.md).

#### Scenario: Set a due date on the task page

- **GIVEN** a project member is on the task page of a task with no due date
- **WHEN** they pick 30 September 2026 in the due date control
- **THEN** the task stores `dueDate` as `2026-09-30`
- **AND** no time of day is asked for or stored

#### Scenario: The board badge follows the new date

- **GIVEN** a project member set yesterday's date as the due date of an open task
- **WHEN** they open the project board
- **THEN** that task's card shows the overdue badge

#### Scenario: Clear a due date

- **GIVEN** a task with a due date
- **WHEN** a project member clears the due date on the task page
- **THEN** the task stores no due date and its card shows no due badge

### Requirement: A project member can set a start date

The system MUST let a project member set, change and clear a task's start date from the task
page and the task dialog, and MUST refuse a start date later than the due date. Tier: MVP.

#### Scenario: The start date places the timeline bar

- **GIVEN** a project member set start date 1 October and due date 10 October on a task
- **WHEN** they open the project timeline at /projects/:id/timeline
- **THEN** the task's bar runs from 1 to 10 October

#### Scenario: A start after the due date is refused

- **GIVEN** a task with due date 10 October
- **WHEN** a project member picks 12 October as the start date
- **THEN** they see "The start date is after the due date." and nothing is saved

### Requirement: A date-only due date keeps its day in every time zone

The system MUST treat a stored `YYYY-MM-DD` due or start date as that calendar day in the
viewer's local time. Tier: MVP.

#### Scenario: A viewer west of UTC sees the same day

- **GIVEN** a task due on 2026-09-30 and a viewer whose browser runs in America/New_York
- **WHEN** they open the board on 30 September
- **THEN** the card shows the due-soon badge for today, not an overdue badge
