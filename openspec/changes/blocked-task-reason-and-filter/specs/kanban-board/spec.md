# Kanban Board — Blocked Reason and Blocked Filter

**Spec refs**: `kanban-board` (openspec/specs/kanban-board.md, requirement "Blocked task indicator on cards [V1]")
**Change**: blocked-task-reason-and-filter (issue #640)
**Standards**: WCAG 2.1 AA (1.4.1 Use of Color, 4.1.2 Name/Role/Value)

## ADDED Requirements

### Requirement: A blocked task carries a short reason

The system SHALL store an optional free-text reason on a task, readable back through the OpenRegister API as `blockedReason` on the `task` object, and SHALL treat the reason as meaningful only while the task's `status` is `blocked`.

#### Scenario: Reason is stored and read back

- **Given** a task exists on a board
- **When** a project member sets the task's status to `blocked` and enters the reason "waiting on design"
- **Then** reading the task through the OpenRegister API returns `status: "blocked"` and `blockedReason: "waiting on design"`

#### Scenario: Reason is optional

- **Given** a task exists on a board
- **When** a project member sets the task's status to `blocked` and enters no reason
- **Then** the task is stored with `status: "blocked"` and no `blockedReason` value
- **And** the board shows the task as blocked without a reason line

#### Scenario: A task that is not blocked has no reason shown

- **Given** a task has `status: "open"` and a leftover `blockedReason` value
- **When** a project member opens the board
- **Then** the task's card shows no blocked mark and no reason

### Requirement: The blocked reason is readable on the task card

The board SHALL show the blocked reason on the task's card without the task being opened, and SHALL show the full reason when the card's reason line is truncated.

#### Scenario: Reason is shown on the card

- **Given** a task on the board has `status: "blocked"` and `blockedReason: "waiting on design"`
- **When** a project member opens the board
- **Then** the task's card shows a blocked mark and the text "waiting on design"
- **And** the reason is readable without opening the task

#### Scenario: A long reason is truncated but fully readable

- **Given** a task on the board has a blocked reason longer than the card's reason line
- **When** a project member opens the board
- **Then** the card shows a shortened reason line
- **And** the full reason is available from the card, for example as a tooltip

#### Scenario: A task that is not blocked shows no reason

- **Given** a board holds one blocked task and one task that is not blocked
- **When** a project member opens the board
- **Then** only the blocked task's card shows a blocked mark and a reason

### Requirement: The board filters down to blocked tasks

The board SHALL offer a blocked filter that, when selected, shows only tasks whose `status` is `blocked` and hides every other task, and SHALL offer a way to clear it.

#### Scenario: Selecting the blocked filter hides unblocked tasks

- **Given** a board holds one blocked task and one task that is not blocked
- **When** a project member selects the blocked filter
- **Then** only the blocked task remains visible on the board

#### Scenario: Clearing the blocked filter restores the board

- **Given** the blocked filter is selected and only the blocked task is visible
- **When** a project member clears the blocked filter
- **Then** both tasks are visible again

#### Scenario: The blocked filter composes with the label filter

- **Given** a board holds a blocked task with label "Bug" and a blocked task with label "Feature"
- **When** a project member selects the blocked filter and the "Bug" label filter
- **Then** only the blocked task with label "Bug" remains visible

#### Scenario: The blocked filter is available on a board with no labels

- **Given** a board holds a blocked task and the instance has no labels defined
- **When** a project member opens the board
- **Then** the blocked filter is offered and selecting it shows the blocked task

#### Scenario: The blocked filter shows an empty board when nothing is blocked

- **Given** no task on the board has `status: "blocked"`
- **When** a project member selects the blocked filter
- **Then** the board shows no task cards
- **And** the board shows an empty state rather than an error

### Requirement: Blocking and unblocking a task is done on the task detail surface

The task detail surface SHALL let a project member set a task's status to `blocked` with a reason and clear the blocked state again, and SHALL persist the change through the shared object store.

#### Scenario: A member blocks a task from the detail surface

- **Given** a project member has a task's detail surface open
- **When** the member marks the task blocked and enters the reason "waiting on design" and saves
- **Then** the task is stored with `status: "blocked"` and `blockedReason: "waiting on design"`
- **And** the board shows the mark and the reason on that task's card after a reload

#### Scenario: A member unblocks a task

- **Given** a task is blocked with the reason "waiting on design"
- **When** a project member clears the blocked state on the task's detail surface and saves
- **Then** the task is stored with a status other than `blocked`
- **And** the board shows no blocked mark and no reason on that task's card
- **And** the blocked filter no longer includes the task

#### Scenario: A member without write access cannot change the blocked state

- **Given** a project member has read-only access to a task
- **When** the member opens the task's detail surface
- **Then** the blocked state and the reason are shown as read-only
- **And** no save control for the blocked state is offered

#### Scenario: A failed save leaves the stored state unchanged

- **Given** a project member marks a task blocked and saves
- **When** the write to the object store fails
- **Then** the task's stored status and reason are unchanged
- **And** the member is told the change was not saved

## MODIFIED Requirements

### Requirement: Blocked task indicator on cards [V1]

Task cards on the kanban board MUST show a compact "Blocked" indicator when the task's `status` is `blocked`. The indicator MUST be visually consistent with the card's other status chips (priority, due-date badge), MUST not require opening the task to be understood, and MUST never prevent dragging the card (soft signal, same philosophy as the WIP limit). When the task carries a `blockedReason`, the card MUST also show that reason as a short line, truncated when it exceeds the line, with the full reason available from the card. Board filters and the list-view toggle MUST render the indicator identically. The dependency-derived blocked state (`isBlocked` / `deriveBlockedTaskIds`) is a separate signal and MUST NOT be conflated with this indicator.

#### Scenario: Blocked badge shown on the card

- **Given** a task on the board has `status: "blocked"`
- **When** the board renders
- **THEN** the task's card MUST show a "Blocked" badge alongside its existing chips

#### Scenario: Badge visible in list view too

- **Given** the same blocked task
- **When** the user toggles the board to list view
- **Then** the row MUST show the same blocked indication and the same reason

#### Scenario: Dragging a blocked card is not prevented

- **Given** a card shows the Blocked badge
- **When** the user drags it to another column
- **Then** the drop MUST succeed and the badge MUST remain while the task's status stays `blocked`

#### Scenario: Unblocking removes the badge and the reason

- **Given** a card shows the Blocked badge and the reason "waiting on design"
- **When** the task's status is changed away from `blocked`
- **Then** the card MUST show no Blocked badge and no reason line
