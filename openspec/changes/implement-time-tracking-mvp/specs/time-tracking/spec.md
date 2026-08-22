---
status: proposed
---

# Time Tracking — MVP Implementation

## Purpose

Closes the gap between `openspec/specs/time-tracking.md` (a fully-specified
MVP with 5 requirements, written and never implemented) and the shipped
product. The data model (`timeEntry` schema, `estimatedDuration` on `task`)
already exists in `lib/Settings/planninq_register.json`; this change adds the
missing frontend. Requirement names below mirror the main spec exactly so
`opsx-sync` folds this delta back cleanly.

---

## MODIFIED Requirements

### Requirement: Time Estimate

The system MUST allow users to set a time estimate on a task, accepting
`"2h 30m"`, `"150m"`, `"1.5h"`, `"90"`, `"2h"` formats, storing the value as
integer minutes, and rejecting unparseable/zero/negative input with an inline
validation error without saving.

#### Scenario: Set estimate on task

- **GIVEN** a user is editing a task detail
- **WHEN** the user enters an estimate (e.g. "2h 30m")
- **THEN** the system MUST store `estimatedDuration` in minutes on the task
- **AND** the task card on the kanban board MUST display the estimate
- @e2e planninq/tests/e2e/time-tracking.spec.ts

#### Scenario: Estimate input rejects invalid values

- **GIVEN** a user is entering a time estimate
- **WHEN** the user types an unparseable value (e.g. "lots", "-5", "0")
- **THEN** the system MUST show an inline validation error and MUST NOT save
- @e2e planninq/tests/e2e/time-tracking.spec.ts

#### Scenario: Logged vs estimated progress

- **GIVEN** a task has `estimatedDuration` = 180 minutes and total logged
  time = 90 minutes
- **WHEN** the user views the task detail
- **THEN** the system MUST display a progress indicator showing "1h 30m / 3h"
- **AND** if logged time exceeds the estimate, the indicator MUST turn red
  and show the overage
- @e2e planninq/tests/e2e/time-tracking.spec.ts

### Requirement: Log Time

The system MUST allow users to log time spent on a task, support multiple
entries per task, allow the owning user to edit or delete their own entries,
and reject (403, hidden UI controls) any attempt by a non-owner, non-admin
user to edit or delete another user's entry.

#### Scenario: Log a time entry

- **GIVEN** a user is viewing a task detail
- **WHEN** the user clicks "Log time" and enters duration, date, and an
  optional description
- **THEN** the system MUST create a TimeEntry linked to the task
- **AND** the task MUST display the total logged time
- **AND** the entry MUST appear in the user's timesheet
- @e2e planninq/tests/e2e/time-tracking.spec.ts

#### Scenario: User cannot edit or delete another user's time entry

- **GIVEN** UserA has a time entry on a task
- **WHEN** UserB (not an admin) views the same task detail
- **THEN** UserB MUST NOT see edit or delete controls on UserA's entry
- **AND** a direct API call from UserB to edit UserA's entry MUST return 403
  (enforced by the existing `timeEntry` schema RBAC rule, not a new service)
- @e2e planninq/tests/e2e/time-tracking.spec.ts

### Requirement: Personal Timesheet

The system MUST provide a timesheet view showing the current user's time
entries grouped by date, with daily and weekly totals, a date-range filter,
and click-through navigation to the source task that preserves scroll
position and filter state on return.

#### Scenario: View my timesheet

- **GIVEN** a user has logged time on multiple tasks
- **WHEN** the user opens the Timesheet view
- **THEN** the system MUST show all entries grouped by date (newest first)
  with task title, project, duration, description per row
- **AND** a daily total per date group and a weekly total for the current
  view MUST be shown
- @e2e planninq/tests/e2e/timesheet.spec.ts

#### Scenario: Filter timesheet by date range

- **GIVEN** the timesheet is open
- **WHEN** the user selects a date range (e.g. "This week" or a custom range)
- **THEN** the system MUST filter entries to the selected range and display
  the range total
- @e2e planninq/tests/e2e/timesheet.spec.ts

#### Scenario: Navigate to task from timesheet

- **GIVEN** the timesheet shows a time entry row
- **WHEN** the user clicks the task title
- **THEN** the system MUST navigate to the task detail view
- **AND** the browser back button MUST return to the timesheet at the same
  scroll position and date filter
- @e2e planninq/tests/e2e/timesheet.spec.ts
