# Tasks: time-tracking

**Change ID:** time-tracking
**Status:** draft
**Created:** 2026-04-02

---

## Implementation Tasks

### Task 1: Setup and Prerequisites
- **spec_ref**: `openspec/specs/time-tracking.md`
- **files**: `src/utils/`, `src/components/time/`, `src/store/timeEntries.js`
- **acceptance_criteria**:
  - GIVEN a fresh Planix install WHEN the developer checks the OpenRegister admin UI THEN the `timeEntry` schema exists in the `planix` register (prerequisite from `register-schemas`)
  - GIVEN the developer inspects `@conduction/nextcloud-vue` WHEN checking exports THEN `useObjectStore`, `CnDataTable`, `CnDetailPage`, `CnObjectSidebar` are all available
  - GIVEN the developer checks `src/views/TaskDetail.vue` THEN it exists and has a `CnObjectSidebar` (prerequisite from `tasks` change)
- [ ] Verify `register-schemas` and `tasks` changes are applied
- [ ] Confirm `@conduction/nextcloud-vue` exports: `useObjectStore`, `CnDataTable`, `CnObjectSidebar`
- [ ] Create directory `src/components/time/` (if not already present)
- [ ] Confirm `src/store/` directory exists

---

### Task 2: Time Duration Utilities — `parseMinutes` and `formatMinutes`
- **spec_ref**: `openspec/changes/time-tracking/specs/time-tracking/spec.md#requirement-time-duration-parser`, `openspec/changes/time-tracking/specs/time-tracking/spec.md#requirement-time-duration-formatter`
- **files**: `src/utils/timeDuration.js`
- **acceptance_criteria**:
  - GIVEN input `"2h 30m"` WHEN `parseMinutes` is called THEN it returns `150`
  - GIVEN input `"1.5h"` WHEN `parseMinutes` is called THEN it returns `90`
  - GIVEN input `"90"` (bare integer) WHEN `parseMinutes` is called THEN it returns `90`
  - GIVEN input `"lots"` or `"-5"` or `"0"` or `""` WHEN `parseMinutes` is called THEN it returns `null`
  - GIVEN input `"  2h 30m  "` (whitespace) WHEN `parseMinutes` is called THEN it returns `150`
  - GIVEN `minutes = 150` WHEN `formatMinutes` is called THEN it returns `"2h 30m"`
  - GIVEN `minutes = 45` WHEN `formatMinutes` is called THEN it returns `"45m"`
  - GIVEN `minutes = null` WHEN `formatMinutes` is called THEN it returns `""`
- [ ] Create `src/utils/timeDuration.js`
- [ ] Implement `parseMinutes(input)` — regex-based, handles all accepted formats, returns `null` for invalid/zero/negative
- [ ] Implement `formatMinutes(minutes)` — `"Xh Ym"` for >=60, `"Ym"` for <60, `""` for null
- [ ] Export both functions as named exports
- [ ] Test: unit tests covering all accepted formats and all invalid cases

---

### Task 3: Time Entry Pinia Store
- **spec_ref**: `openspec/specs/time-tracking.md#requirement-log-time`
- **files**: `src/store/timeEntries.js`
- **acceptance_criteria**:
  - GIVEN the store is imported WHEN `useTimeEntriesStore()` is called THEN it returns reactive state: `entries`, `loading`, `error`
  - GIVEN a task ID WHEN `fetchEntries(taskId)` is called THEN OpenRegister is queried with `type=timeEntry&task=taskId` and results populate `entries`
  - GIVEN filters including `user` and date range WHEN `fetchMyEntries(filters)` is called THEN only entries matching all filters are returned
  - GIVEN a valid time entry payload WHEN `logTime(data)` is called THEN a new `timeEntry` object is created in OpenRegister and added to `entries`
  - GIVEN an existing entry ID WHEN `updateEntry(id, data)` is called THEN the entry is PATCHed in OpenRegister and updated in `entries` in place
  - GIVEN an existing entry ID WHEN `deleteEntry(id)` is called THEN the entry is deleted in OpenRegister and removed from `entries`
  - GIVEN `entries` contains entries for a task WHEN `loggedDuration(taskId)` is accessed THEN it returns the sum of all `duration` values for that task
- [ ] Create `src/store/timeEntries.js` with Pinia store `useTimeEntriesStore`
- [ ] Implement `fetchEntries(taskId)` — sets `loading`, calls `objectStore.getObjects({ task: taskId })`, sets `error` on failure
- [ ] Implement `fetchMyEntries({ user, dateGte, dateLte })` — builds filter params, calls `objectStore.getObjects`
- [ ] Implement `logTime(data)` — merges defaults: `user: currentUserUid`, `date: today`; calls `objectStore.createObject`
- [ ] Implement `updateEntry(id, data)` — optimistic update with rollback on failure
- [ ] Implement `deleteEntry(id)` — optimistic removal with rollback on failure
- [ ] Implement `loggedDuration(taskId)` as a computed getter — sums `duration` for all entries where `entry.task === taskId`
- [ ] Test

---

### Task 4: Time Entry Form Component
- **spec_ref**: `openspec/changes/time-tracking/specs/time-tracking/spec.md#requirement-time-entry-form-ui`
- **files**: `src/components/time/TimeEntryForm.vue`
- **acceptance_criteria**:
  - GIVEN the form opens in create mode WHEN mounted THEN date defaults to today, duration is empty with placeholder `"e.g. 1h 30m"`, description is empty
  - GIVEN the user types `"1h 30m"` WHEN parsing succeeds THEN a preview label `"= 1h 30m"` appears below the duration input
  - GIVEN the user blurs the duration field with `"lots"` WHEN validation runs THEN inline error `"Enter a valid duration (e.g. 1h 30m, 90m, 1.5h)"` appears and submit is disabled
  - GIVEN the user submits with empty duration THEN error `"Duration is required"` appears and submission is prevented
  - GIVEN submission is in progress THEN submit and cancel buttons are disabled, fields are read-only
  - GIVEN submission succeeds (create) THEN form closes and toast `"Time logged"` is shown
  - GIVEN submission succeeds (edit) THEN form closes and toast `"Time entry updated"` is shown
  - GIVEN submission fails THEN form remains open with all values preserved and error banner shown
- [ ] Create `src/components/time/TimeEntryForm.vue` using `NcDialog` or inline modal
- [ ] Add props: `entry` (Object, optional — null = create mode), `taskId` (String, required)
- [ ] Add fields: duration (text input + live preview), date (date picker, defaults today), description (optional textarea)
- [ ] Implement live duration preview using `parseMinutes` / `formatMinutes`
- [ ] Implement blur + submit validation for duration
- [ ] Implement loading state (disabled submit/cancel, read-only fields)
- [ ] Emit `saved` event on success (parent closes or refreshes)
- [ ] Test

---

### Task 5: Time Entries List Component
- **spec_ref**: `openspec/changes/time-tracking/specs/time-tracking/spec.md#requirement-time-entries-list-on-task-detail`
- **files**: `src/components/time/TimeEntriesList.vue`
- **acceptance_criteria**:
  - GIVEN a task with multiple entries WHEN `TimeEntriesList` renders THEN entries are ordered by date descending
  - GIVEN the current user's entry WHEN rendered THEN Edit and Delete buttons are visible
  - GIVEN another user's entry WHEN rendered THEN no Edit or Delete buttons are visible
  - GIVEN the user clicks Edit THEN `TimeEntryForm` opens in edit mode pre-filled with the entry
  - GIVEN the user clicks Delete THEN a confirmation dialog appears; confirming removes the entry
  - GIVEN a task with no entries WHEN rendered THEN empty state message is shown
  - GIVEN an entry from another user THEN an `NcAvatar` (20 px) is shown for that user
- [ ] Create `src/components/time/TimeEntriesList.vue`
- [ ] Accept prop `taskId` (String, required)
- [ ] Fetch entries via `useTimeEntriesStore.fetchEntries(taskId)` on mount
- [ ] Sort entries by date descending (client-side after fetch)
- [ ] Render each entry row: date, duration (via `formatMinutes`), description, user avatar (`NcAvatar`)
- [ ] Show Edit/Delete buttons only when `entry.user === currentUserUid`
- [ ] Wire Edit button to open `TimeEntryForm` in edit mode
- [ ] Wire Delete button to confirmation dialog → `useTimeEntriesStore.deleteEntry(id)` with optimistic removal
- [ ] Render empty state when entries array is empty
- [ ] Test

---

### Task 6: Progress Indicator Component
- **spec_ref**: `openspec/changes/time-tracking/specs/time-tracking/spec.md#requirement-progress-indicator-component`
- **files**: `src/components/time/TimeProgress.vue`
- **acceptance_criteria**:
  - GIVEN `task.estimatedDuration` is null WHEN rendered THEN only `"Logged: Xh Ym"` is shown with no bar
  - GIVEN logged < estimated WHEN rendered THEN green bar at correct percentage, text `"Xh Ym / Yh Zm"`
  - GIVEN logged === estimated WHEN rendered THEN green bar at 100%, not red
  - GIVEN logged > estimated WHEN rendered THEN logged text is red, bar is red at 100%, overflow label `"+Xh Ym over"` is shown
  - GIVEN logged === 0 and estimate is set WHEN rendered THEN bar at 0%, text `"0m / Xh Ym"`
- [ ] Create `src/components/time/TimeProgress.vue`
- [ ] Accept props: `loggedMinutes` (Number), `estimatedMinutes` (Number or null)
- [ ] Implement no-estimate state (logged only, no bar)
- [ ] Implement under/at-estimate state (green bar, width = min(100%, logged/estimated * 100%))
- [ ] Implement over-estimate state (red text for logged, bar capped at 100% in red, overflow label)
- [ ] Use CSS variables: `--color-success` (green), `--color-error` (red) with `cn-` prefix
- [ ] Test

---

### Task 7: Estimate Field on Task Create Dialog and Metadata Sidebar
- **spec_ref**: `openspec/specs/time-tracking.md#requirement-time-estimate`
- **files**: `src/components/dialogs/TaskCreateDialog.vue`, `src/components/TaskMetaSidebar.vue`
- **acceptance_criteria**:
  - GIVEN the task create dialog is open WHEN the user types `"2h 30m"` in the estimate field THEN a preview `"= 2h 30m"` appears below the input
  - GIVEN the user enters `"lots"` THEN inline error `"Enter a valid duration (e.g. 1h 30m, 90m, 1.5h)"` appears
  - GIVEN a valid estimate and form submit WHEN the task is created THEN `estimatedDuration` is stored as `150` (integer minutes) on the task object
  - GIVEN `task.estimatedDuration = 150` WHEN `TaskMetaSidebar` renders THEN the estimate field shows `"2h 30m"`
  - GIVEN the user edits the estimate to `"3h"` in the sidebar WHEN blur fires THEN `updateTask(id, { estimatedDuration: 180 })` is called
  - GIVEN the user clears the estimate field WHEN blur fires THEN `updateTask(id, { estimatedDuration: null })` is called
- [ ] Add estimate text input to `src/components/dialogs/TaskCreateDialog.vue` (optional field, after title)
- [ ] Implement live preview and validation using `parseMinutes` / `formatMinutes`
- [ ] Store `estimatedDuration` as integer minutes on task create
- [ ] Add estimate field to `src/components/TaskMetaSidebar.vue` (inline editable)
- [ ] On sidebar blur: call `useTasksStore.updateTask(id, { estimatedDuration })` or `null` if cleared
- [ ] Test

---

### Task 8: Task Detail "Time" Sidebar Tab
- **spec_ref**: `openspec/specs/time-tracking.md#requirement-log-time`
- **files**: `src/views/TaskDetail.vue`
- **acceptance_criteria**:
  - GIVEN the user opens a task detail WHEN the `CnObjectSidebar` renders THEN a "Time" tab is present alongside Files, Notes, Tags, Audit Trail
  - GIVEN the user clicks the "Time" tab WHEN the tab activates THEN `TimeProgress`, a "Log Time" button, and `TimeEntriesList` are rendered in the tab panel
  - GIVEN the user clicks "Log Time" WHEN the button is clicked THEN `TimeEntryForm` opens in create mode
  - GIVEN the user submits a new entry WHEN `TimeEntryForm` emits `saved` THEN `TimeEntriesList` refreshes and `TimeProgress` updates
- [ ] Add "Time" tab to `CnObjectSidebar` in `src/views/TaskDetail.vue`
- [ ] Render `TimeProgress` with `loggedMinutes` from `useTimeEntriesStore.loggedDuration(taskId)` and `estimatedMinutes` from `task.estimatedDuration`
- [ ] Render "Log Time" `NcButton` that opens `TimeEntryForm` in create mode
- [ ] Render `TimeEntriesList` with `taskId`
- [ ] Wire `TimeEntryForm` `saved` event to refresh entries and recompute progress
- [ ] Test

---

### Task 9: Personal Timesheet View
- **spec_ref**: `openspec/specs/time-tracking.md#requirement-personal-timesheet`, `openspec/changes/time-tracking/specs/time-tracking/spec.md#requirement-personal-timesheet-layout`
- **files**: `src/views/TimesheetView.vue`
- **acceptance_criteria**:
  - GIVEN the user navigates to `/timesheet` WHEN the view mounts THEN it defaults to "This week" and loads entries for the current user
  - GIVEN entries for multiple dates WHEN rendered THEN date group headers appear in descending order, each showing date + daily total
  - GIVEN an entry row WHEN rendered THEN it shows task title (link to `/tasks/:id`), project name, formatted duration, truncated description
  - GIVEN no entries in the range WHEN rendered THEN empty state `"No time logged for this period."` is shown
  - GIVEN the user clicks a task title WHEN navigated to the task THEN pressing back returns to `/timesheet` with same range
  - GIVEN a range total summary THEN total duration across all visible entries is shown
- [ ] Create `src/views/TimesheetView.vue`
- [ ] Implement `fetchMyEntries` call with `user=currentUserUid`, `dateGte`, `dateLte` from selected range
- [ ] Group entries by date descending (client-side reduce)
- [ ] Render date group header rows with daily totals
- [ ] Render entry rows: task title (`RouterLink` to `/tasks/:id`), project, `formatMinutes(duration)`, description (80-char truncate + title tooltip)
- [ ] Render range total summary
- [ ] Render empty state
- [ ] Test

---

### Task 10: Date Range Filter
- **spec_ref**: `openspec/changes/time-tracking/specs/time-tracking/spec.md#requirement-date-range-filter`
- **files**: `src/views/TimesheetView.vue`
- **acceptance_criteria**:
  - GIVEN the view mounts WHEN no range is specified THEN "This week" is the active preset (Monday of current ISO week through today)
  - GIVEN "Last week" is selected THEN `dateGte` = Monday of previous ISO week, `dateLte` = Sunday of previous ISO week
  - GIVEN "Custom" is selected THEN two `NcDateTimePicker` inputs appear; filter applies when both dates are set
  - GIVEN custom end date is before start date THEN inline error `"End date must be after start date"` is shown
- [ ] Implement date range filter UI in `TimesheetView.vue` — toggle buttons for This week / Last week / Custom
- [ ] Implement ISO week boundary computation (Monday = day 1) for presets
- [ ] Implement custom range UI using `NcDateTimePicker`
- [ ] Implement end-before-start validation
- [ ] On range change: re-call `fetchMyEntries` with new date bounds
- [ ] Test

---

### Task 11: Timesheet Route and Navigation
- **spec_ref**: `openspec/specs/time-tracking.md#requirement-personal-timesheet`
- **files**: `src/router/index.js`, `src/navigation/MainMenu.vue`
- **acceptance_criteria**:
  - GIVEN the app is running WHEN the user navigates to `/timesheet` THEN `TimesheetView.vue` is rendered
  - GIVEN `MainMenu.vue` renders WHEN the user is logged in THEN a "My Timesheet" entry is visible in the navigation
  - GIVEN the user clicks "My Timesheet" THEN the route `/timesheet` is activated
- [ ] Add route `{ path: '/timesheet', component: TimesheetView, name: 'timesheet' }` to `src/router/index.js`
- [ ] Add "My Timesheet" nav item to `src/navigation/MainMenu.vue` using `t('planix', 'nav_timesheet')`
- [ ] Test route navigation

---

### Task 12: i18n — English and Dutch Strings
- **spec_ref**: `openspec/changes/time-tracking/specs/time-tracking/spec.md#requirement-i18n-coverage`
- **files**: `l10n/en.json`, `l10n/nl.json`
- **acceptance_criteria**:
  - GIVEN all time-tracking components WHEN the user is on the `nl` locale THEN all strings are displayed in Dutch
  - GIVEN all string keys listed in the delta spec THEN each key exists in both `en.json` and `nl.json`
  - GIVEN any new time-tracking component string WHEN reviewed THEN it uses `t('planix', ...)` — no hardcoded English strings
- [ ] Add all `time_*` and `timesheet_*` and `nav_timesheet` keys to `l10n/en.json`
- [ ] Add all keys to `l10n/nl.json` with correct Dutch translations
- [ ] Audit all new components for any un-translated strings
- [ ] Test: load app in `nl` locale and verify no English strings appear in time-tracking UI

---

### Task 13: Testing and Quality
- **spec_ref**: `openspec/changes/time-tracking/specs/time-tracking/spec.md`
- **files**: `src/utils/__tests__/timeDuration.test.js`, `src/store/__tests__/timeEntries.test.js`, `src/components/time/__tests__/`
- **acceptance_criteria**:
  - GIVEN the test suite runs WHEN `parseMinutes` tests execute THEN all accepted format cases and all invalid cases pass
  - GIVEN the test suite runs WHEN `useTimeEntriesStore` tests execute THEN CRUD operations and `loggedDuration` computation pass
  - GIVEN the test suite runs WHEN `TimeProgress` tests execute THEN all four states (no estimate, under, at, over) are covered
  - GIVEN the test suite runs WHEN `TimeEntryForm` tests execute THEN validation, create mode, and edit mode are covered
  - GIVEN the test suite runs WHEN `TimesheetView` tests execute THEN grouping, empty state, date range filter, and range total are covered
- [ ] Write unit tests for `parseMinutes` — all 9 accepted format variations + all invalid cases
- [ ] Write unit tests for `formatMinutes` — all 5 stated scenarios
- [ ] Write store tests for `useTimeEntriesStore` — CRUD methods + `loggedDuration` computed getter
- [ ] Write component tests for `TimeProgress` — all 5 rendering states
- [ ] Write component tests for `TimeEntryForm` — create mode validation, edit mode pre-fill, loading state
- [ ] Write component tests for `TimeEntriesList` — sort order, own vs. other-user controls, empty state
- [ ] Write component tests for `TimesheetView` — grouping by date, daily totals, range total, empty state, date range presets
- [ ] Run `composer check:strict` equivalent (lint + type check) on any PHP files touched; fix any pre-existing warnings encountered

---

## Verification

- [ ] Navigate to a task detail → "Time" tab is visible alongside other sidebar tabs
- [ ] Log time entry with `"1h 30m"` → entry appears in list, progress bar shows `"1h 30m / <estimate>"`
- [ ] Log a second entry that exceeds the estimate → progress bar turns red with `"+X over"` label
- [ ] Edit own time entry → form opens pre-filled, saving updates the list
- [ ] Attempt to edit another user's time entry → no edit/delete buttons visible
- [ ] Navigate to `/timesheet` → "My Timesheet" nav item is highlighted, entries from this week are shown grouped by date
- [ ] Switch to "Last week" preset → entries update to last week's range
- [ ] Set custom range → date pickers appear, entries filter to custom range
- [ ] Click a task title in timesheet → navigates to task detail; back button returns to timesheet
- [ ] Clear task estimate → TimeProgress shows "Logged: Xh Ym" with no bar
- [ ] Verify all strings appear in Dutch when locale is set to `nl`

---

## Tests (ADR-009)

- Unit tests for `parseMinutes` and `formatMinutes` utility functions (all format variants + all invalid cases)
- Unit tests for `useTimeEntriesStore` (CRUD + `loggedDuration` computed)
- Component tests for `TimeProgress` (5 states), `TimeEntryForm` (create/edit/validation), `TimeEntriesList` (own vs. others, sort, empty), `TimesheetView` (grouping, totals, date filter)
- Integration test: log time on a task → verify `loggedDuration` updates reactively in `TimeProgress`

---

## Documentation (ADR-010)

- Update `docs/features/time-tracking.md` (or create if absent) with: feature overview, how to log time, how to set an estimate, how to use the timesheet, date range filter explanation
- Document `parseMinutes` accepted input formats in a code comment in `src/utils/timeDuration.js`

---

## i18n (ADR-005)

- All new strings use `t('planix', key)` — no hardcoded UI text
- All keys added to both `l10n/en.json` and `l10n/nl.json`
- Dutch translations reviewed for natural phrasing (not machine-translated)
- Interpolated strings (e.g. `"= {formatted}"`, `"+{excess} over"`) use named placeholders per ADR-005 conventions
