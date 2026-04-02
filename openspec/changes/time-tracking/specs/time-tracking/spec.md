# Delta Spec: time-tracking

**Capability:** time-tracking
**Change ID:** time-tracking
**Delta type:** implementation
**Base spec:** [openspec/specs/time-tracking.md](../../../../specs/time-tracking.md)
**Status:** draft
**Created:** 2026-04-02

---

## Summary

This delta captures implementation-specific requirements added when building the time-tracking UI layer. The base spec (`openspec/specs/time-tracking.md`) defines all business requirements, scenarios, user stories, and acceptance criteria. The delta below documents:

1. Duration parser utility — accepted formats, edge cases, return contract.
2. Duration formatter utility — display rules.
3. Time entry form UI — field defaults, validation, loading state.
4. Time entries list on task detail — sort order, own-entry controls, empty state.
5. Progress indicator component — all states (no estimate, under, over).
6. Personal timesheet layout — CnDataTable grouping, daily totals, range total.
7. Date range picker — preset behaviour and ISO week definition.
8. i18n requirements.
9. Constraints introduced by the thin-client + `useObjectStore` architecture.

All base spec requirements are implemented as-is. No base spec requirement is modified or removed.

---

## ADDED Requirements

### Requirement: Time Duration Parser [MVP]

`parseMinutes(input: string): number | null` — a pure utility function in `src/utils/timeDuration.js`.

#### Scenario: Parse hours and minutes combined
- GIVEN input `"2h 30m"` or `"2h30m"`
- WHEN `parseMinutes` is called
- THEN it returns `150`

#### Scenario: Parse hours only
- GIVEN input `"2h"` or `"1.5h"`
- WHEN `parseMinutes` is called
- THEN it returns `120` or `90` respectively

#### Scenario: Parse minutes only
- GIVEN input `"30m"` or `"150m"`
- WHEN `parseMinutes` is called
- THEN it returns `30` or `150` respectively

#### Scenario: Parse bare integer
- GIVEN input `"90"` (no unit suffix)
- WHEN `parseMinutes` is called
- THEN it returns `90` (bare integer is treated as minutes)

#### Scenario: Parse hours with trailing bare integer
- GIVEN input `"2h 30"` (hours + bare integer minutes)
- WHEN `parseMinutes` is called
- THEN it returns `150`

#### Scenario: Reject invalid input — non-numeric
- GIVEN input `"lots"`, `"abc"`, `"two hours"`, or `""`
- WHEN `parseMinutes` is called
- THEN it returns `null`

#### Scenario: Reject invalid input — negative value
- GIVEN input `"-5"`, `"-1h"`, or `"-30m"`
- WHEN `parseMinutes` is called
- THEN it returns `null`

#### Scenario: Reject invalid input — zero
- GIVEN input `"0"`, `"0m"`, or `"0h"`
- WHEN `parseMinutes` is called
- THEN it returns `null` (zero-minute entries are not permitted)

#### Scenario: Reject invalid input — sanity ceiling
- GIVEN input that parses to more than 59,940 minutes (999 hours)
- WHEN `parseMinutes` is called
- THEN it returns `null`

#### Scenario: Whitespace tolerance
- GIVEN input with leading/trailing whitespace (e.g. `"  2h 30m  "`)
- WHEN `parseMinutes` is called
- THEN it returns `150` (whitespace is trimmed before parsing)

---

### Requirement: Time Duration Formatter [MVP]

`formatMinutes(minutes: number | null): string` — a pure utility function in `src/utils/timeDuration.js`.

#### Scenario: Format hours and minutes
- GIVEN `minutes = 150`
- WHEN `formatMinutes` is called
- THEN it returns `"2h 30m"`

#### Scenario: Format whole hours
- GIVEN `minutes = 120`
- WHEN `formatMinutes` is called
- THEN it returns `"2h 0m"` (always show minutes component when hours >= 1)

#### Scenario: Format minutes only
- GIVEN `minutes = 45` (less than 60)
- WHEN `formatMinutes` is called
- THEN it returns `"45m"` (no hours component)

#### Scenario: Format zero
- GIVEN `minutes = 0`
- WHEN `formatMinutes` is called
- THEN it returns `"0m"`

#### Scenario: Format null
- GIVEN `minutes = null`
- WHEN `formatMinutes` is called
- THEN it returns `""` (empty string — caller renders nothing)

---

### Requirement: Time Entry Form UI [MVP]

`src/components/time/TimeEntryForm.vue` — used for both creating new entries and editing existing ones.

#### Scenario: Default field values on create
- GIVEN the form is opened in create mode
- WHEN the component mounts
- THEN the date field defaults to today's ISO date
- AND the user field is pre-filled with the current user's UID (hidden from UI)
- AND the duration field is empty with placeholder `"e.g. 1h 30m"`
- AND the description field is empty

#### Scenario: Live duration preview
- GIVEN the user types into the duration field
- WHEN `parseMinutes` returns a non-null value
- THEN a preview label renders below the input showing the formatted result (e.g. `"= 1h 30m"`)
- AND when `parseMinutes` returns `null` and the field is non-empty, the preview label is hidden

#### Scenario: Duration validation on blur
- GIVEN the user types an invalid duration and moves focus away
- WHEN the blur event fires
- THEN an inline error message appears: `"Enter a valid duration (e.g. 1h 30m, 90m, 1.5h)"`
- AND the submit button is disabled while the error is shown

#### Scenario: Duration validation on submit attempt
- GIVEN the user submits without entering a duration
- WHEN the submit button is clicked
- THEN an inline error message appears: `"Duration is required"`
- AND submission is prevented

#### Scenario: Loading state during save
- GIVEN the form has valid data and the user clicks submit
- WHEN the store operation is in progress
- THEN the submit button shows a spinner and is disabled
- AND the cancel/close button is disabled
- AND form fields are read-only

#### Scenario: Success on create
- GIVEN the form submission succeeds
- WHEN the operation completes
- THEN the form closes
- AND a success toast `"Time logged"` appears
- AND `TimeEntriesList` updates immediately (reactive store)

#### Scenario: Success on edit
- GIVEN the edit form submission succeeds
- WHEN the operation completes
- THEN the form closes
- AND a success toast `"Time entry updated"` appears

#### Scenario: Error preservation on failure
- GIVEN the store operation fails
- WHEN the error is returned
- THEN the form remains open
- AND all user-entered values are preserved
- AND an inline error banner shows the failure reason

---

### Requirement: Time Entries List on Task Detail [MVP]

`src/components/time/TimeEntriesList.vue` — rendered inside the "Time" sidebar tab of `TaskDetail.vue`.

#### Scenario: Sort order
- GIVEN a task with multiple time entries
- WHEN `TimeEntriesList` renders
- THEN entries are ordered by `date` descending (most recent first)
- AND entries with the same date are ordered by creation timestamp descending

#### Scenario: Own-entry controls
- GIVEN the current user's UID matches `timeEntry.user`
- WHEN `TimeEntriesList` renders that entry's row
- THEN an Edit button and a Delete button are visible for that row
- AND other users' entries show no edit/delete controls

#### Scenario: Edit entry inline
- GIVEN the user clicks Edit on an own entry
- WHEN the edit action triggers
- THEN `TimeEntryForm` opens in edit mode pre-filled with the entry's current values

#### Scenario: Delete entry with confirmation
- GIVEN the user clicks Delete on an own entry
- WHEN the action triggers
- THEN a confirmation dialog appears: `"Delete this time entry? This cannot be undone."`
- AND confirming calls `useTimeEntriesStore.deleteEntry(id)`
- AND the entry is removed from the list immediately (optimistic update)

#### Scenario: Empty state
- GIVEN a task has no time entries
- WHEN `TimeEntriesList` renders
- THEN it shows: `"No time logged yet. Click 'Log Time' to add your first entry."`

#### Scenario: User avatar display
- GIVEN an entry from another user
- WHEN `TimeEntriesList` renders
- THEN an `NcAvatar` (20 px) is shown for the entry's user

---

### Requirement: Progress Indicator Component [MVP]

`src/components/time/TimeProgress.vue` — rendered at the top of the "Time" sidebar tab.

#### Scenario: No estimate set
- GIVEN `task.estimatedDuration` is `null` or `0`
- WHEN `TimeProgress` renders
- THEN it shows only `"Logged: Xh Ym"` (formatted using `formatMinutes`)
- AND no progress bar is rendered
- AND no estimated duration text is shown

#### Scenario: Under estimate
- GIVEN `loggedDuration < task.estimatedDuration` and both are non-zero
- WHEN `TimeProgress` renders
- THEN it shows text `"Xh Ym / Yh Zm"` (logged / estimated)
- AND a green progress bar is rendered at `(logged / estimated * 100)%` width

#### Scenario: At estimate
- GIVEN `loggedDuration === task.estimatedDuration`
- WHEN `TimeProgress` renders
- THEN it shows text `"Xh Ym / Yh Zm"` with the bar at 100%
- AND the bar remains green (not red — exactly on budget is not over)

#### Scenario: Over estimate
- GIVEN `loggedDuration > task.estimatedDuration`
- WHEN `TimeProgress` renders
- THEN the logged duration text is rendered in red (`--color-error` variable)
- AND the bar is shown at 100% width in red
- AND an overflow label shows `"+Xh Ym over"` (the excess, formatted)

#### Scenario: No logged time yet
- GIVEN `loggedDuration === 0` and `task.estimatedDuration` is set
- WHEN `TimeProgress` renders
- THEN it shows `"0m / Xh Ym"` and an empty progress bar (0% width, green track)

---

### Requirement: Personal Timesheet Layout [MVP]

`src/views/TimesheetView.vue` — accessible at route `/timesheet`.

#### Scenario: Entries grouped by date
- GIVEN the user has time entries across multiple dates within the selected range
- WHEN `TimesheetView` renders
- THEN entries are grouped by date, newest date first
- AND each date group has a header row showing the date and the group's total duration

#### Scenario: Entry row content
- GIVEN a time entry in the timesheet
- WHEN `TimesheetView` renders that entry
- THEN the row shows: task title (as a link to `/tasks/:id`), project name, duration (formatted), description (truncated at 80 chars with tooltip for full text)

#### Scenario: Range total
- GIVEN the user has entries across multiple dates in the selected range
- WHEN `TimesheetView` renders
- THEN a summary row at the top (or bottom) shows the total logged duration for the entire range

#### Scenario: Navigate to task
- GIVEN the user clicks a task title link in the timesheet
- WHEN the navigation happens
- THEN the user is taken to `/tasks/:id` for that task
- AND pressing the browser back button returns to `/timesheet` with the same date range selected

#### Scenario: Empty state for date range
- GIVEN the user has no time entries in the selected date range
- WHEN `TimesheetView` renders
- THEN it shows: `"No time logged for this period."`

---

### Requirement: Date Range Filter [MVP]

Date range filter within `TimesheetView.vue`.

#### Scenario: Default range on load
- GIVEN the user navigates to `/timesheet`
- WHEN the component mounts
- THEN the date range defaults to "This week" (Monday of the current ISO week through today)

#### Scenario: This week preset
- GIVEN the user selects "This week"
- WHEN the preset is applied
- THEN `date_gte` is set to the Monday of the current ISO week (00:00 local time)
- AND `date_lte` is set to today (23:59 local time)

#### Scenario: Last week preset
- GIVEN the user selects "Last week"
- WHEN the preset is applied
- THEN `date_gte` is set to Monday of the previous ISO week
- AND `date_lte` is set to Sunday of the previous ISO week

#### Scenario: Custom range
- GIVEN the user selects "Custom"
- WHEN the picker opens
- THEN two `NcDateTimePicker` date inputs appear (start and end)
- AND the user can pick any calendar dates
- AND the filter is applied when both dates are selected

#### Scenario: Range validation
- GIVEN the user picks a custom end date before the start date
- WHEN the second date is selected
- THEN an inline error `"End date must be after start date"` is shown
- AND the filter is not applied until corrected

---

### Requirement: i18n Coverage [MVP]

All user-visible strings in time-tracking components MUST be wrapped in `t('planix', ...)` and added to `l10n/en.json` and `l10n/nl.json`.

Strings required (English):

| Key | Value |
|-----|-------|
| `time_duration_placeholder` | `"e.g. 1h 30m"` |
| `time_duration_invalid` | `"Enter a valid duration (e.g. 1h 30m, 90m, 1.5h)"` |
| `time_duration_required` | `"Duration is required"` |
| `time_log_button` | `"Log Time"` |
| `time_logged_success` | `"Time logged"` |
| `time_entry_updated` | `"Time entry updated"` |
| `time_entry_delete_confirm` | `"Delete this time entry? This cannot be undone."` |
| `time_no_entries` | `"No time logged yet. Click 'Log Time' to add your first entry."` |
| `time_estimate_label` | `"Estimate"` |
| `time_estimate_preview` | `"= {formatted}"` |
| `time_logged_label` | `"Logged: {formatted}"` |
| `time_progress_label` | `"{logged} / {estimated}"` |
| `time_over_estimate` | `"+{excess} over"` |
| `timesheet_title` | `"My Timesheet"` |
| `timesheet_daily_total` | `"Daily total: {total}"` |
| `timesheet_range_total` | `"Total: {total}"` |
| `timesheet_no_entries` | `"No time logged for this period."` |
| `timesheet_this_week` | `"This week"` |
| `timesheet_last_week` | `"Last week"` |
| `timesheet_custom` | `"Custom"` |
| `timesheet_range_end_before_start` | `"End date must be after start date"` |
| `timesheet_description_truncated` | `"{text}..."` |
| `nav_timesheet` | `"My Timesheet"` |
