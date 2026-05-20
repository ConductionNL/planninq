# Spec: Kanban Board View

## ADDED Requirements

---

### Requirement: REQ-KBD-001 — Board Rendering

The system MUST render a kanban board with columns and task cards for each project, displayed in the configured column order.

#### Scenario: Display board with tasks

- GIVEN a project has one or more columns and one or more tasks assigned to those columns
- WHEN a project member opens the board view
- THEN the system MUST display columns in ascending `order` sequence, laid out horizontally
- AND each column header MUST show the column title, current task count, and WIP limit (if set)
- AND each task card MUST show: title, assignee avatar, due date (red if overdue), priority indicator, and label chips
- AND columns MUST be horizontally scrollable when there are more columns than fit the viewport

#### Scenario: Empty board — no columns

- GIVEN a project has no columns (e.g. default columns were cleared before project creation)
- WHEN a project member opens the board view
- THEN the system MUST show a `CnEmptyState` with the message "Nog geen kolommen"
- AND a project creator or admin MUST see an "Kolom toevoegen" button to create the first column
- AND non-admin members MUST NOT see the "Kolom toevoegen" button

#### Scenario: Empty column — no tasks

- GIVEN a project has columns but a column contains no tasks
- WHEN a member opens the board view
- THEN each empty column MUST show a `CnEmptyState` with a "+ Taak toevoegen" button
- AND clicking "+ Taak toevoegen" in a column MUST open the task creation form (`CnFormDialog`) with that column pre-selected

---

### Requirement: REQ-KBD-002 — Drag-and-Drop Task Movement

The system MUST allow users to drag task cards between columns to update task status.

#### Scenario: Drag task to a new column (success)

- GIVEN a task card is visible on the board
- WHEN a user drags the task card from one column to another
- THEN the card MUST appear in the target column immediately (optimistic UI — before the backend confirms)
- AND the system MUST call `saveObject` to update the task's `column` reference and `columnOrder` in OpenRegister
- AND on backend success the card MUST remain in the new position

#### Scenario: Drag task to a new column (backend failure)

- GIVEN a task card has been dragged to a new column via optimistic UI
- WHEN the backend `saveObject` call returns an error
- THEN the system MUST revert the card to its original column and `columnOrder`
- AND the system MUST display an error toast (Nextcloud `showError`) informing the user the move failed

#### Scenario: Drag task from backlog to board column

- GIVEN the backlog panel is open alongside the board
- WHEN a user drags a task from the backlog panel into a board column
- THEN the system MUST assign the task's `column` reference to that column
- AND the task MUST be removed from the backlog panel immediately

---

### Requirement: REQ-KBD-003 — WIP Limit Enforcement (Soft)

The system MUST visually warn users when a column's WIP limit is exceeded, without blocking the card placement.

#### Scenario: WIP limit exceeded

- GIVEN a column has `wipLimit` = N and currently contains N tasks
- WHEN a user drags an additional task into that column
- THEN the column header MUST change to the error colour (`var(--color-error)`)
- AND the column header MUST display a tooltip with the message "WIP-limiet (N) overschreden"
- AND the task MUST be accepted into the column (soft limit — not blocked)

#### Scenario: WIP limit not exceeded

- GIVEN a column has `wipLimit` = N and contains fewer than N tasks
- WHEN a user views the board
- THEN the column header MUST display in its configured `color` (or the default neutral colour if no colour is set)
- AND no warning indicator MUST be shown

#### Scenario: Column without WIP limit

- GIVEN a column has `wipLimit` = null
- WHEN any number of tasks are in the column
- THEN no WIP limit indicator MUST be shown regardless of task count

---

### Requirement: REQ-KBD-004 — Board Filters

The system MUST allow users to filter the visible board cards by assignee, label, and priority without a full page reload.

#### Scenario: Filter by assignee

- GIVEN the board shows tasks from multiple assignees
- WHEN the user selects "Toegewezen aan: ik" from the filter controls
- THEN the system MUST visually dim or hide tasks not assigned to the current user
- AND the URL hash MUST be updated to reflect the active filter (e.g. `#filter=assignee:me`)

#### Scenario: Filter by label

- GIVEN the board shows tasks with various labels
- WHEN the user selects a label from the filter controls
- THEN only tasks with that label MUST be visually prominent; others MUST be dimmed or hidden
- AND the URL hash MUST reflect the active label filter

#### Scenario: Filter by priority

- GIVEN the board shows tasks with different priority levels
- WHEN the user selects a priority filter
- THEN only tasks matching that priority MUST be visually prominent
- AND the URL hash MUST reflect the active priority filter

#### Scenario: Shareable filtered view

- GIVEN a user has applied one or more filters (URL hash is populated)
- WHEN another user opens the same URL
- THEN the board MUST load with those filters pre-applied

---

### Requirement: REQ-KBD-005 — Column Management

The system MUST allow project creators and admins to create, rename, reorder, and delete columns via project settings.

#### Scenario: Add a new column

- GIVEN a project creator or admin is in the project settings
- WHEN the user submits the "Kolom toevoegen" form with a title (and optional WIP limit and colour)
- THEN the system MUST create the column object in OpenRegister and append it to the board at the highest `order` value
- AND the new column MUST appear on the board immediately

#### Scenario: Reorder columns

- GIVEN a project creator or admin is in the project settings
- WHEN the user drags a column to a different position
- THEN the system MUST update the `order` values of all affected columns in OpenRegister
- AND the board MUST reflect the new column order immediately

#### Scenario: Delete column without tasks

- GIVEN a column contains zero tasks
- WHEN a project creator or admin deletes the column from project settings
- THEN the system MUST delete the column object from OpenRegister without prompting
- AND the column MUST be removed from the board immediately

#### Scenario: Delete column with tasks

- GIVEN a column contains one or more tasks
- WHEN a project creator or admin clicks "Verwijderen" on the column in project settings
- THEN the system MUST show the `DeleteColumnDialog` with the message "Deze kolom bevat {N} taken"
- AND the dialog MUST offer two destinations: "Naar backlog verplaatsen" and "Naar andere kolom verplaatsen"
- AND the system MUST NOT delete the column until the user confirms a destination
- AND if "Naar backlog verplaatsen" is chosen, all tasks in the column MUST have their `column` reference cleared
- AND if "Naar andere kolom verplaatsen" is chosen, all tasks MUST be reassigned to the selected column

---

### Requirement: REQ-KBD-006 — Keyboard Navigation and Accessibility

The kanban board MUST be keyboard-navigable and meet WCAG AA accessibility standards.

#### Scenario: Keyboard navigation of task cards

- GIVEN a user navigates the board using the keyboard
- WHEN the user tabs to a task card
- THEN the card MUST receive visible focus
- AND the user MUST be able to open the task detail via Enter/Space key
- AND the board MUST not trap focus (focus escape must be possible)

#### Scenario: Colour not sole indicator

- GIVEN a column shows a WIP limit violation
- WHEN the user views the column header
- THEN the warning MUST be conveyed by both colour (error colour) AND a textual or icon indicator
- AND the tooltip MUST be accessible to screen readers (ARIA attributes)
