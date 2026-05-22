# Time Tracking Specification

**Status**: in-progress

**Standards**: Schema.org QuantitativeValue, iCalendar ESTIMATED-DURATION (RFC 7986), OpenProject spentTime model
**Feature tier**: MVP

**OpenSpec changes:**
- [time-tracking](../changes/time-tracking/) — implements manual time logging, timesheet view, estimate input, progress indicator

## Placement & Information Architecture

**Placement type:** `SUB_PAGE` — Sub-page beneath a top-level menu entry. Renders as a page inside the parent surface (usually reachable via a router child route or a tab on the parent index page).

**Lives at:** Mijn werk > Tijdregistratie

**Rationale:** time is personal-first  
_Source: /tmp/ia-small5.md_

> **Implementation note for builders:** Respect the placement above. Do not promote this spec to a top-level menu item, sub-page, or new route unless the placement type explicitly says so. If the placement is `DETAIL_TAB`, `WIDGET`, `ACTION`, `SETTING`, or `INFRA`, the feature must NOT introduce a new entry in the app sidebar. When in doubt, ask before creating a new top-level surface.

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

#### Scenario: Estimate input — accepted formats
- GIVEN a user is entering a time estimate
- WHEN the user types any of: "2h 30m", "150m", "1.5h", "90", "2h"
- THEN the system MUST parse the value and store it as an integer number of minutes
- AND the stored value MUST be displayed back in human-readable format (e.g., 90 → "1h 30m")

#### Scenario: Estimate input — invalid format
- GIVEN a user is entering a time estimate
- WHEN the user types an unparseable value (e.g., "lots", "-5", "0")
- THEN the system MUST show an inline validation error
- AND the system MUST NOT save the estimate until a valid value is entered

#### Scenario: Logged vs estimated progress
- GIVEN a task has `estimatedDuration` = 180 minutes and total logged time = 90 minutes
- WHEN the user views the task detail
- THEN the system MUST display a progress indicator showing "1h 30m / 3h"
- AND if logged time exceeds the estimate, the indicator MUST turn red and show the overage (e.g., "3h 30m / 3h")

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

#### Scenario: User cannot edit or delete another user's time entry
- GIVEN UserA has a time entry on a task
- WHEN UserB (not an admin) views the same task detail
- THEN UserB MUST NOT see edit or delete controls on UserA's time entries
- AND a direct API call from UserB to edit UserA's entry MUST return 403 Forbidden

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

#### Scenario: Navigate to task from timesheet
- GIVEN the timesheet shows a time entry row
- WHEN the user clicks the task title in the timesheet row
- THEN the system MUST navigate to the task detail view
- AND the browser back button MUST return to the timesheet at the same scroll position and date filter

## User Stories

- As a developer, I want to log the time I spent on a task so that the team has accurate capacity data
- As a team member, I want to see my logged time in a weekly timesheet so that I can review my work patterns
- As a project lead, I want to see estimated vs actual time per task so that I can improve future estimates
- As a user, I want to add multiple time logs per task so that I can track time across multiple work sessions
- As a team member, I want to see my total hours for the week so that I can track my workload
- As a user, I want the time estimate input to accept natural formats like "2h 30m" so that I don't have to do mental arithmetic
- As a user, I want to see a progress bar when my logged time approaches or exceeds the estimate so that I can flag scope creep

## Acceptance Criteria

- [ ] Time estimate can be set on any task; input accepts "2h 30m", "150m", "1.5h", "90", "2h" formats
- [ ] Invalid or zero estimate input shows an inline validation error and does not save
- [ ] Estimated duration is stored in minutes and displayed in human-readable format on task card and detail
- [ ] "Log time" button is accessible from the task detail view
- [ ] A time entry requires at minimum a duration and a date
- [ ] Multiple time entries can be added to the same task
- [ ] Total logged time is computed from all TimeEntries and displayed on the task detail
- [ ] Logged vs estimated progress indicator shows "Xh Ym / Zh" and turns red when logged exceeds estimate
- [ ] Users can edit and delete only their own time entries; other users' entries show no edit/delete controls
- [ ] A direct API call to edit another user's entry returns 403 Forbidden
- [ ] Timesheet view shows all entries by the current user grouped by date (newest first)
- [ ] Timesheet shows daily totals and a total for the selected date range
- [ ] Timesheet can be filtered by date range (presets: this week, last week, custom)
- [ ] Clicking a task title in the timesheet navigates to the task detail; back button returns to timesheet at same scroll and filter state

## Notes

- Time granularity is minutes (integer). Avoids floating-point precision issues.
- Timer (start/stop, auto-log) is a V1 feature. MVP is manual entry only.
- Time export (CSV) is V1. Includes: task title, project, user, date, duration, description.
- Project-level time report (V1): total estimated vs logged per task, aggregated per project.
- Team timesheet (V1): admin view of all users' entries, exportable.
- The `loggedDuration` field on Task is computed at read time (sum of linked TimeEntry objects), not stored.
