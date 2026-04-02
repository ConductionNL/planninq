# Change Proposal: time-tracking

**Change ID:** time-tracking
**Status:** proposed
**Created:** 2026-04-02
**Author:** Conduction Development Team

---

## Why

Time tracking is the single most-cited differentiator that sets Planix apart from its direct competitors (Nextcloud Deck, Plane, Linear, and GitHub Projects). None of those tools offer in-context time logging per task — users must maintain separate spreadsheets or third-party apps. Planix closes this gap entirely.

The `register-schemas` change already defined and registered the `TimeEntry` schema in OpenRegister. The `tasks` change built the Task detail view, backlog, and store. Both are prerequisites. What is missing is the entire interaction layer: the utility functions to parse and format durations, the UI to log time against a task, the estimate field on the task form, the progress indicator, and the personal timesheet view. This change delivers all of it.

---

## What Changes

Build the Vue frontend utilities, components, store logic, and views needed to:

1. **Time duration parser** — `src/utils/timeDuration.js` exports `parseMinutes(input)` (flexible input → integer minutes) and `formatMinutes(minutes)` (integer → "Xh Ym" display string). Both are pure functions, trivially testable, used everywhere time is displayed or accepted.
2. **Time estimate on task form** — Estimate field added to `TaskCreateDialog.vue` and `TaskMetaSidebar.vue` using the parser. Displays live preview of parsed minutes as "Xh Ym" while the user types.
3. **Progress indicator component** — `src/components/time/TimeProgress.vue` shows "logged / estimated" text (e.g. "1h 30m / 3h") and a progress bar. Turns red when logged exceeds estimated. Handles no-estimate state gracefully (shows logged total only).
4. **Time entry form component** — `src/components/time/TimeEntryForm.vue` — modal/inline form for logging a time entry. Fields: duration (parsed input), date (defaults to today), description (optional). Validates duration on blur.
5. **Time entries list on task detail** — `src/components/time/TimeEntriesList.vue` — shows all entries for a task ordered by date descending. Each row: date, duration, description, user avatar. Own entries show Edit/Delete buttons.
6. **Task detail integration** — The task detail view (`src/views/TaskDetail.vue`) gains a "Time" tab (or dedicated section) in `CnObjectSidebar` that renders `TimeProgress.vue`, a "Log Time" button opening `TimeEntryForm.vue`, and `TimeEntriesList.vue` below.
7. **Time entry Pinia store** — `src/store/timeEntries.js` wraps `useObjectStore('planix', 'timeEntry')`. Exposes `fetchEntries(taskId)`, `logTime(data)`, `updateEntry(id, data)`, `deleteEntry(id)`, and the computed `loggedDuration(taskId)` (sum of durations for a task).
8. **Personal timesheet view** — `src/views/TimesheetView.vue` — lists the current user's time entries grouped by date, newest first. Each group shows a daily total. A date range filter (this week / last week / custom) narrows the list. Each task title is a link to `/tasks/:id`.
9. **Timesheet route and navigation** — route `/timesheet` added to `src/router/index.js`; "My Timesheet" entry added to `src/navigation/MainMenu.vue`.
10. **i18n strings** — all user-visible strings added to `l10n/en.json` and `l10n/nl.json`.

---

## Capabilities

### Modified Capabilities

- **`time-tracking`** — implementing the full time-tracking capability defined in `openspec/specs/time-tracking.md`. This change brings the capability from spec-only (schema defined by `register-schemas`) to fully implemented: duration parser/formatter utilities, time entry CRUD, task estimate field, progress indicator, personal timesheet with date filtering.

No new capabilities are introduced. The `time-tracking` capability was declared in the spec and the `TimeEntry` schema was prepared by `register-schemas`; this change completes the interaction layer.

---

## Impact

### Files Changed

| File | Change |
|------|--------|
| `src/utils/timeDuration.js` | New — `parseMinutes()` and `formatMinutes()` utility functions |
| `src/store/timeEntries.js` | New — Pinia store for time entry CRUD and `loggedDuration` computation |
| `src/components/time/TimeProgress.vue` | New — logged/estimated progress indicator with over-estimate red state |
| `src/components/time/TimeEntryForm.vue` | New — time entry creation/edit form with duration parser |
| `src/components/time/TimeEntriesList.vue` | New — ordered list of time entries for a task with own-entry controls |
| `src/views/TimesheetView.vue` | New — personal timesheet grouped by date with date range filter |
| `src/views/TaskDetail.vue` | Modified — add "Time" sidebar tab wiring `TimeProgress`, `TimeEntryForm`, `TimeEntriesList` |
| `src/components/dialogs/TaskCreateDialog.vue` | Modified — add estimate field |
| `src/components/TaskMetaSidebar.vue` | Modified — add estimate field with live preview |
| `src/router/index.js` | Modified — add `/timesheet` route |
| `src/navigation/MainMenu.vue` | Modified — add "My Timesheet" nav entry |
| `l10n/en.json` | Modified — add all time-tracking strings |
| `l10n/nl.json` | Modified — add all time-tracking strings (Dutch) |

### Dependencies

| Change | Role |
|--------|------|
| `register-schemas` | Must be applied — provides the `TimeEntry` schema in OpenRegister |
| `tasks` | Must be applied — provides `TaskDetail.vue`, `TaskCreateDialog.vue`, `TaskMetaSidebar.vue` to modify |
