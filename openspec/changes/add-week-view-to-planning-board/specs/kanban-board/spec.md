# Kanban Board — Week View, Current-User Highlight and Day Drag

**Spec refs**: `kanban-board` (openspec/specs/kanban-board.md)
**Standards**: WCAG 2.1 AA (1.4.1 Use of Color, 2.1.1 Keyboard)

## MODIFIED Requirements

### Requirement: View Toggle — Kanban, List and Week [MVP]

The system MUST allow users to switch between kanban (card) view, list (table) view and
week (Monday to Sunday) view for a project's tasks, and MUST persist the selected view
in the URL so that a reload returns to the same view.

#### Scenario: Switch to week view

- **Given** a user is on the kanban board view of a project
- **When** the user selects the week view in the view toggle
- **Then** the board renders seven day columns, Monday through Sunday, in place of the status columns
- **And** the URL contains the week view mode

#### Scenario: Reload returns to the week view

- **Given** a user is in the week view of a project
- **When** the user reloads the page
- **Then** the board renders the week view again, not the kanban view

#### Scenario: Switching views keeps the active filter

- **Given** a user has an assignee filter applied on the kanban board
- **When** the user switches to the week view
- **Then** the same filter is still applied to the tasks shown in the week columns

#### Scenario: Switch back to kanban view

- **Given** a user is in the week view
- **When** the user selects the kanban view in the view toggle
- **Then** the board renders the status columns and cards as before this change

## ADDED Requirements

### Requirement: Week view shows one week, Monday to Sunday

The week view SHALL render seven day columns, Monday through Sunday, for the week that
contains the current date, and SHALL place each task of the project in the column of the
day it is scheduled for.

#### Scenario: Seven day columns are shown

- **Given** a project with tasks
- **When** a user opens the week view
- **Then** the board shows seven columns labelled Monday, Tuesday, Wednesday, Thursday, Friday, Saturday and Sunday
- **And** all seven columns are visible in one screen without opening a day

#### Scenario: A task appears in the column of its day

- **Given** a task of the project has a due date on Wednesday of the current week
- **When** a user opens the week view
- **Then** the task's card is shown in the Wednesday column
- **And** the card is not shown in any other day column

#### Scenario: A task outside the current week is not shown in a day column

- **Given** a task of the project has a due date in the following week
- **When** a user opens the week view
- **Then** the task's card is not shown in any of the seven day columns

#### Scenario: A task without a day is shown as unscheduled

- **Given** a task of the project has no due date
- **When** a user opens the week view
- **Then** the task's card is shown in an "Unscheduled" area next to the seven day columns
- **And** the task is not silently omitted from the week view

#### Scenario: An empty day column is shown as empty

- **Given** no task of the project has a due date on Saturday of the current week
- **When** a user opens the week view
- **Then** the Saturday column is rendered with an empty state
- **And** the other six columns are unaffected

### Requirement: The current user's tasks are highlighted in the week view

The week view SHALL visually mark every task whose `assignedTo` equals the current
Nextcloud user id, and the mark SHALL be distinguishable without relying on colour alone.

#### Scenario: A task assigned to the current user is marked

- **Given** a task of the project is assigned to the current user and falls on Tuesday of the current week
- **When** the current user opens the week view
- **Then** that task's card carries a visible mark identifying it as the current user's task

#### Scenario: A task assigned to someone else is not marked

- **Given** a task of the project is assigned to another user and falls on Tuesday of the current week
- **When** the current user opens the week view
- **Then** that task's card does not carry the current-user mark

#### Scenario: An unassigned task is not marked

- **Given** a task of the project has no assignee
- **When** a user opens the week view
- **Then** that task's card does not carry the current-user mark

#### Scenario: The mark is not colour alone

- **Given** a task assigned to the current user is shown in the week view
- **When** the card is inspected without colour
- **Then** the current-user mark is still perceivable, for example through a text label or an icon

### Requirement: A task can be dragged from one day to another

The week view SHALL let a user drag a task card from one day column and drop it on
another day column, and SHALL persist the target day as the task's due date through the
existing task update path, showing the move immediately and reverting it if the write
fails.

#### Scenario: Dragging a task to another day moves it

- **Given** a task of the project is shown in the Monday column of the week view
- **When** a user drags the card and drops it on the Wednesday column
- **Then** the card is shown in the Wednesday column and no longer in the Monday column

#### Scenario: The new day survives a reload

- **Given** a user has dragged a task from Monday to Wednesday in the week view
- **When** the user reloads the page
- **Then** the task is shown in the Wednesday column again

#### Scenario: The day view shows the moved task on its new day

- **Given** a user has dragged a task from Monday to Wednesday in the week view
- **When** the user opens the day view for Wednesday
- **Then** the task is listed on Wednesday

#### Scenario: A failed write reverts the card

- **Given** a task is shown in the Monday column of the week view
- **When** a user drops the card on Wednesday and the task update is rejected
- **Then** the card returns to the Monday column
- **And** the user is told that the move did not succeed

#### Scenario: Dropping a task on its own day changes nothing

- **Given** a task is shown in the Monday column of the week view
- **When** a user drops the card back on the Monday column
- **Then** the task stays on Monday and no update is sent

#### Scenario: A task without a day can be scheduled by dragging

- **Given** a task of the project is shown in the "Unscheduled" area of the week view
- **When** a user drags the card and drops it on the Thursday column
- **Then** the task is shown in the Thursday column
- **And** the task is no longer shown in the "Unscheduled" area
