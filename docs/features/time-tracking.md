# Time Tracking

Estimate task effort and log actual time spent — then review your work in a personal timesheet.

## Overview

Time tracking in Planix operates at the task level. Each task carries an optional time estimate (how long it should take). Actual time is logged as separate time entries — one per work session — linked to the task. A personal timesheet shows all your logged entries grouped by date, with daily and weekly totals.

## Key Capabilities

### Time Estimate

- Set an estimate on any task in the task detail view
- Accepted input formats: `2h 30m`, `150m`, `1.5h`, `90` (minutes), `2h`
- Stored as an integer number of minutes; displayed in human-readable format (e.g., 90 → "1h 30m")
- Task card on the kanban board shows the estimate
- Invalid input (e.g., zero, negative, unparseable) shows an inline validation error

### Log Time

- Click **Log time** in the task detail view to create a time entry
- A time entry requires: duration and date (description is optional)
- Multiple entries can be added to the same task across different sessions or days
- Task detail shows the total logged time (sum of all entries for the task)
- A progress indicator shows logged vs estimated: "1h 30m / 3h"; turns red when logged time exceeds the estimate

### Timesheet

- The Timesheet view shows all your time entries grouped by date (newest first)
- Each row shows: task title (clickable link to task detail), project, duration, description, and edit/delete controls
- Daily totals are shown for each date group
- Filter by date range: "This week", "Last week", or a custom range; the total for the range is shown
- Clicking a task title navigates to the task detail view; the browser back button returns to the timesheet at the same scroll position and date filter

### Access Control

- Users can only edit or delete their own time entries
- Attempting to edit another user's entry via the API returns 403 Forbidden

## Standards

- Schema.org QuantitativeValue — time entry (value with unit code MIN)
- iCalendar ESTIMATED-DURATION (RFC 7986) — estimated task duration field

## Spec

- [time-tracking spec](../../openspec/specs/time-tracking.md)
