---
kind: code
---

# Start and stop a timer on a task, and record what kind of work the time was

## Why

A person who wants to log time has to know the duration afterwards and type it. The only way to log time is `TimeEntryDialog`, which takes a typed duration such as "2h 30m" (`src/dialogs/TimeEntryDialog.vue:7-13`), opened from "Log time" on the task page (`src/views/TaskDetail.vue:90-95`). There is no running timer anywhere in `src/`. `docs/FEATURES.md` lists "Timer (start/stop, auto-log)" as V1, the flat spec `openspec/specs/time-tracking.md:152` defers it to V1, and ADR-001 rule 6 names the timer as part of Mijn werk.

Logged time also cannot say what kind of work it was. The write path is `TaskDetail` to `TimeEntryDialog` to `timeEntries.create`, with the payload task, duration, date and description (`src/store/timeEntries.js:149-166`); the `plannedTimeEntry` schema has no field for it (`lib/Settings/planninq_register.json:904-1058`). A team cannot tell meetings from development in its hours.

OpenProject and Kanboard have a timer on a work item; OpenProject records an activity type on every time entry. The work type row was mined from a Jira feature request (JRASERVER-1780).

Parity rows: `tim-timer`, `tim-work-type` in planninq's `openspec/parity/capabilities.json`.
Decision: build, because two competitors have a task timer, one records a work type and a Jira feature request asks for it, and planninq's own feature list and ADR-001 already plan the timer.

This change extends the flat spec `openspec/specs/time-tracking.md` through two new capabilities, `time-timer` and `time-work-type`.

## What changes

- A person can start a timer on a task, see it running on their Mijn werk pages and on the task, and stop it. Stopping opens the usual time entry form with the measured duration filled in, so they confirm before anything is booked.
- One timer runs per person, and it survives a page reload and another device.
- An admin keeps a list of work types in Beheer. When the list is not empty, every time entry asks for one, and the time report can be split by it.
- Both fit the move of hours to humaniq: the timer books a clocked interval, and the work type stays on planninq's side of the booking.

## Evidence from the parity matrix

Matrix: `openspec/parity/capabilities.json` in ConductionNL/planninq (compared on 2026-09-26). Rows in this change: `tim-timer`, `tim-work-type`.

### `tim-timer`: Start and stop a timer on a task.

- Area `time`. Planninq is rated `no`, built.state `none`, owner `ConductionNL/planninq`.
- Built evidence: "src/dialogs/TimeEntryDialog.vue only accepts a typed duration (durationInput); no start/stop/running-timer state anywhere in src/"
- Demand: none recorded on the row.
- Competitors rated yes (2):
  - OpenProject 16 Community (no URL in the cell; source or corpus citation only): "docs: opf/openproject HEAD 27a58131 docs/user-guide/time-and-costs/time-tracking/README.md:54 'Starting with OpenProject 13.0, you can also track time in real time using the start/stop time tracking button'; source: opf/openproject HEAD 27a58131 (18.0.0-dev): modules/costs/app/controllers/my/timer_controller.rb (cited source paths checked present at the released v17.8.0 tag.) ; source read at v17.8.0: frontend/src/app/features/work-packages/components/wp-timer-button/wp-timer-button.component.ts:82 'Stop' and :99-100 stop via TimeEntryTimerService; frontend/src/app/shared/components/time_entries/timer/timer-account-menu.component.ts running timer in the account menu; ... (shortened; full text in the matrix row)"
  - Kanboard 1.2 (no URL in the cell; source or corpus citation only): "corpus: _round4/compare/promoted-rows-batch7.md 10.11 (Kanboard) 'tasks.time_estimated and time_spent, subtask time tracking with a start/stop timer ... a per-user timesheet (getUserTimesheet). Verified live' ; menu-tree.md task sidebar 'Time tracking' ; source: app/Model/SubtaskTimeTrackingModel.php:143 toggleTimer ; source read at v1.2.54: app/Template/subtask/timer.php:12-13 timer shown to the subtask's assignee; app/Helper/SubtaskHelper.php:80 renderTimer; app/Controller/SubtaskStatusController.php:44 timer, logStartTime and logEndTime"

### `tim-work-type`: Record what kind of work logged time was, such as a meeting or development.

- Area `time`. Planninq is rated `no`, built.state `none`, owner `ConductionNL/planninq`.
- Built evidence: "Write path: src/views/TaskDetail.vue:91 -> src/dialogs/TimeEntryDialog.vue:7-32 -> src/store/timeEntries.js:149 create, payload task, duration, date, description. No work type field in dialog or schema."
- Note: "Demand row mined from jira (featureRequest) on 2026-09-26."
- Demand (featureRequest, via origin): https://jira.atlassian.com/browse/JRASERVER-1780
- Competitors rated yes (1):
  - OpenProject 16 Community (no URL in the cell; source or corpus citation only): "source read at v17.8.0: modules/costs/app/components/time_entries/activity_form.rb:35-49 activity picker on the log time form; modules/costs/app/models/time_entry.rb:39 activity; modules/costs/app/models/time_entry_activity.rb:29; config/locales/en.yml:2617 'Time tracking activities'"


## Scope

### In scope

- Start, show, stop and discard a timer; one per person; stored as a personal preference until it is stopped.
- The stop step prefilled into `TimeEntryDialog`.
- An admin list of work types, a work type field on every time entry, and a "By work type" chart in the time report.
- How both behave before and after `plannedtimeentry-reads-humaniqs-hours`.

### Out of scope

- Several timers at once, pausing, and idle detection.
- A timer on something other than a task. Time is booked per task only (`docs/ARCHITECTURE.md` section 5 question 4); a meeting is booked on a meeting task, with the work type "Meeting".
- Rates per work type. Rates stay on the project and the entry.

## Impact

- Settings: new user key `running_timer` and new admin key `work_types` in `lib/Service/SettingsService.php`; a work types section in `src/views/settings/Settings.vue`.
- Schema: `plannedTimeEntry` gains `workType` (string).
- Store: `src/store/timeEntries.js` gains `startTimer`, `stopTimer`, `discardTimer` and passes `workType`.
- Components and views: a new `src/components/RunningTimer.vue` shown on the My tasks page, the Timesheet and the task page; "Start timer" on `TaskDetail`; a work type select in `src/dialogs/TimeEntryDialog.vue`; a chart widget in the `TimeSpentReport` page of `src/manifest.json`.
- Depends on: `portfolio-my-work-dashboard` (the My tasks page the running timer shows on). Coordinates with the open change `plannedtimeentry-reads-humaniqs-hours`, which moves the hours to humaniq's `TimeEntry`.

## Risks

### Risk 1: a forgotten timer books a whole night
**Severity**: Medium
**Mitigation**: stopping never books directly; it opens the form with the measured time filled in. A timer that ran longer than 12 hours opens the form with a warning "This timer ran for 14 hours. Check the duration before you save." A person can also discard a timer.

### Risk 2: the humaniq move changes where hours are written
**Severity**: Medium
**Mitigation**: the timer writes through the same store action as the manual form, so whatever that action writes (a `plannedTimeEntry` today, a humaniq `TimeEntry` plus its `plannedTimeEntry` after the move) the timer writes too. The work type is on `plannedTimeEntry` in both states, because humaniq's `TimeEntry` has no field for it.

### Risk 3: renaming a work type splits the report
**Severity**: Low
**Mitigation**: an entry stores the work type's name at booking time. Renaming asks "Also rename it on existing entries?" and, when confirmed, updates them in the background; retiring a type only removes it from the picker.
