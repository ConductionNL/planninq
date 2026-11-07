# Design: start and stop a timer on a task, and record what kind of work the time was

## Context

What exists at de35541:

- Time is logged per task through `TimeEntryDialog` (`src/dialogs/TimeEntryDialog.vue`): a duration text field parsed by the duration parser (`:7-13`), a date and an optional description. It is opened from "Log time" on the task page (`src/views/TaskDetail.vue:90-95`, dialog at `:141`). The store action `timeEntries.create` fills `user` (current uid) and `date` (today) and saves a `plannedTimeEntry` through the object store (`src/store/timeEntries.js:149-166`).
- `plannedTimeEntry` (`lib/Settings/planninq_register.json:904-1059`): `timeEntry` (the humaniq reference, already declared), `task` (required), `user`, `contractorRef`, `duration` in minutes, `date`, `description`, `project`, `billable`, `hourlyRate`. Its authorization lets only the entry's own user and admins create, update and delete it.
- The personal Timesheet (`src/views/Timesheet.vue`) lists the user's entries by week; `TimeSpentReport` in `src/manifest.json:193-213` is a declarative dashboard over `plannedTimeEntry` with a `chart` widget grouped by `user`.
- Per-user settings are Nextcloud user config values, validated in `SettingsService::updateUserSettings` (`lib/Service/SettingsService.php:376`) behind `POST /api/settings/user`, and returned by `GET /api/settings` (`getSettings`, `:340-361`). Admin lists such as `default_columns` are JSON strings in IAppConfig with per-key validation (`:60-64`, `:239-279`).
- ADR-001 rule 4: time is personal-first; entries are created and edited under Mijn werk > Tijdregistratie; project views are read-only roll-ups; a manager never edits another person's entry. Rule 6: Mijn werk carries the timer.
- `docs/ARCHITECTURE.md` section 5 question 4: every time entry belongs to a task, "forever"; overhead such as meetings is tracked as tasks.
- The open change `plannedtimeentry-reads-humaniqs-hours` moves `date`, `duration`, `user`, `description`, `billable`, `project` and `task` to humaniq's `TimeEntry`; `plannedTimeEntry` keeps only what humaniq has no column for (`contractorRef`, `hourlyRate`) plus the reference. Humaniq's `TimeEntry` (read at humaniq dbcac918, `lib/Settings/register.d/hr-timesheet.json`) takes a clocked booking with `startedAt` and `endedAt`, or a day booking with `date` and `hours`, and its `origin` enum already includes `timer`. It has no field for a kind of work.

## Goals / non-goals

Goals:
- A timer that is hard to lose and never books without the person confirming.
- A work type on every entry when the organisation defines types, reportable without code.
- Both correct before and after the humaniq move.

Non-goals:
- Several timers, pause, idle detection, timers without a task, rates per type.

## Decisions

### Decision 1: a running timer is a personal preference, not a time entry
Starting a timer stores `running_timer` = `{ task, startedAt }` for the current user through `POST /api/settings/user`. Nothing is written to `plannedTimeEntry` or to humaniq while it runs, so there is never a half-finished booking in anyone's hours, and a page reload or another device shows the same timer (it comes back in `GET /api/settings`). Starting a second timer asks "A timer is running on Export to CSV. Stop it and start one on Printer 2nd floor?"; stopping the first follows Decision 2. The alternative, an open humaniq `TimeEntry` with `startedAt` and no `endedAt`, puts an unfinished row into timesheets and payroll exports.

### Decision 2: stopping opens the form, it does not book
"Stop" clears `running_timer` and opens `TimeEntryDialog` for that task with the elapsed time rounded up to whole minutes in the duration field (at least one minute), today's date, and the start and stop times kept for the write. The person can change anything and saves, or cancels and the time is not booked. Over 12 hours the dialog warns first. "Discard" clears the timer without a dialog.

### Decision 3: where the timer shows and starts (ADR-001 rules 4 and 6)
`RunningTimer.vue` shows the task title, the elapsed time (updated each second, announced to assistive technology only on start and stop) and Stop and Discard buttons. It renders at the top of the Mijn werk pages, My tasks (`portfolio-my-work-dashboard`) and the Timesheet, and on the task page of the task it is running on. "Start timer" sits on the task page next to "Log time", and the Timesheet offers "Start timer" with a task picker. The booking itself is still made and edited in the person's own time entry form, never on a project screen, and nobody can start or stop another person's timer.

### Decision 4: the timer writes through the same store action as the manual form
`TimeEntryDialog` saves through `timeEntries.create` whether it was opened by "Log time" or by "Stop". Before `plannedtimeentry-reads-humaniqs-hours` lands, that is a `plannedTimeEntry` with `duration` and `date`. After it lands, the same action writes the humaniq `TimeEntry`; for a timer it sends the clocked shape (`startedAt`, `endedAt`, `origin: timer`) that humaniq's `TimeEntry` already declares, so the booking keeps its real start and end, and the `plannedTimeEntry` that references it. The timer adds no second write path.

### Decision 5: work types are an admin list, stored by name on the entry
Admin key `work_types`: a JSON list of names, empty by default. When it is empty, nothing changes anywhere. When it has names, `TimeEntryDialog` shows a required "Work type" select, and the entry stores the chosen name in a new `plannedTimeEntry.workType` string. After the humaniq move, `workType` stays on `plannedTimeEntry`, next to `contractorRef` and `hourlyRate`, because humaniq's `TimeEntry` has no column for it. The name is stored rather than an id so the declarative report can group on it directly: a new "By work type" `chart` widget in `TimeSpentReport` groups by `workType`, summing `duration` (and, after the move, the report repoints with the rest of the time widgets as that change describes). The admin section lists the names with add, rename and retire; a rename offers to update existing entries through a background job run as the system. The alternative, a `workType` schema, adds a register entry and makes the report show uuids.

## Risks / trade-offs

- [Forgotten timer] -> The stop form, the 12-hour warning, and Discard (Decision 2).
- [The humaniq move] -> One write path (Decision 4); `workType` lives where humaniq has no column (Decision 5).
- [A timer on a task that is deleted while it runs] -> Stop finds no task and says "This task no longer exists. The timer was discarded."
