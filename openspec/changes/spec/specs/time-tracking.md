# Time Tracking Specification

**Status**: implemented

**Standards**: Schema.org QuantitativeValue, iCalendar ESTIMATED-DURATION (RFC 7986), OpenProject spentTime model
**Feature tier**: MVP

**OpenSpec changes:** _(links to openspec/changes/ directories when in-progress or done)_

## Purpose

Time tracking in Planix allows team members to estimate task effort and log actual time spent. This enables project capacity planning, billing, and retrospective analysis. Each task carries an estimate (minutes); actual time is logged as separate TimeEntry objects — multiple per task, one per work session. Users view their logged time in a personal timesheet. Project leads view aggregated time reports (V1). Time tracking is intentionally simple in MVP: manual entry only (no live timer).

## Data Model

See [ARCHITECTURE.md](../../docs/ARCHITECTURE.md) for full TimeEntry entity definition.

**TimeEntry summary**:

| Property | Type | Required | Default |
|----------|------|----------|---------|
| `task` | reference (Task) | Yes | — |
| `user` | string (user UID) | Yes | current user |
| `duration` | integer (minutes) | Yes | — |
| `date` | date | Yes | today |
| `description` | string | No | — |

**Task time fields** (on the Task entity):

| Property | Type | Description |
|----------|------|-------------|
| `estimatedDuration` | integer (minutes) | Planned effort |
| `(computed) loggedDuration` | integer (minutes) | Sum of all TimeEntry.duration for this task |

## Requirements

### Requirement: Time Estimate [MVP]
The system MUST allow users to set a time estimate on a task.

#### Scenario: Set estimate on task
- GIVEN a user is editing a task detail
- WHEN the user enters an estimate (e.g., "2h 30m" or "150 minutes")
- THEN the system MUST store `estimatedDuration` in minutes on the task
- AND the task card on the kanban board MUST display the estimate (e.g., "2h 30m")

### Requirement: Log Time [MVP]
The system MUST allow users to log time spent on a task.

#### Scenario: Log a time entry
- GIVEN a user is viewing a task detail
- WHEN the user clicks "Log time" and enters duration, date, and optional description
- THEN the system MUST create a TimeEntry object linked to the task
- AND the task MUST display the total logged time (sum of all entries)
- AND the logged time MUST appear in the user's timesheet

#### Scenario: Multiple time entries per task
- GIVEN a user has already logged 1h on a task on Monday
- WHEN the user logs another 45 minutes on Tuesday
- THEN the system MUST create a second TimeEntry for Tuesday
- AND the task MUST show total logged time of 1h 45m

#### Scenario: Edit a time entry
- GIVEN a user has a time entry on a task
- WHEN the user edits the entry's duration or description
- THEN the system MUST update the entry
- AND the task's total logged time MUST recalculate immediately

#### Scenario: Delete a time entry
- GIVEN a user has a time entry
- WHEN the user deletes it
- THEN the system MUST remove the entry
- AND the task's total logged time MUST recalculate

### Requirement: Personal Timesheet [MVP]
The system MUST provide a timesheet view showing the current user's time entries grouped by date.

#### Scenario: View my timesheet
- GIVEN a user has logged time on multiple tasks
- WHEN the user opens the Timesheet view
- THEN the system MUST show all entries grouped by date (newest first)
- AND each row MUST show: task title, project, duration, description
- AND a daily total MUST be shown for each date group
- AND a weekly total MUST be shown for the current view

#### Scenario: Filter timesheet by date range
- GIVEN the timesheet is open
- WHEN the user selects a date range (e.g., "This week" or a custom range)
- THEN the system MUST filter entries to the selected range
- AND the total for the range MUST be displayed

## User Stories

- As a developer, I want to log the time I spent on a task so that the team has accurate capacity data
- As a team member, I want to see my logged time in a weekly timesheet so that I can review my work patterns
- As a project lead, I want to see estimated vs actual time per task so that I can improve future estimates
- As a user, I want to add multiple time logs per task so that I can track time across multiple work sessions
- As a team member, I want to see my total hours for the week so that I can track my workload

## Acceptance Criteria

- [ ] Time estimate can be set on any task (input accepts "1h 30m", "90m", "1.5h" formats)
- [ ] Estimated duration is stored in minutes and displayed in human-readable format on task card and detail
- [x] "Log time" button is accessible from the task detail view
- [x] A time entry requires at minimum a duration and a date
- [x] Multiple time entries can be added to the same task
- [x] Total logged time is computed from all entries and displayed on the task
- [ ] Logged time vs estimated time shows a progress indicator (e.g., "1h 30m / 3h")
- [ ] Timesheet view shows all entries by the current user grouped by date
- [ ] Timesheet shows daily totals and weekly total
- [ ] Timesheet can be filtered by date range
- [x] Users can edit and delete their own time entries
- [ ] Admins (V1) can view and export all users' time entries per project

## Notes

- Time granularity is minutes (integer). Avoids floating-point precision issues.
- Timer (start/stop, auto-log) is a V1 feature. MVP is manual entry only.
- Time export (CSV) is V1. Includes: task title, project, user, date, duration, description.
- Project-level time report (V1): total estimated vs logged per task, aggregated per project.
- Team timesheet (V1): admin view of all users' entries, exportable.
- The `loggedDuration` field on Task is computed at read time (sum of linked TimeEntry objects), not stored.
