# Test Plan: time-tracking

## Test Cases

### TC-1: Duration parser — valid inputs parsed correctly
- **spec_ref**: `openspec/changes/time-tracking/specs/time-tracking/spec.md#scenario-parse-hours-and-minutes-combined`
- **type**: functional
- **persona**: any authenticated Nextcloud user
- **preconditions**: Time entry form is open (or unit test context for `parseMinutes`)
- **steps**: Enter the following values into the duration field and verify the live preview after each: "2h 30m" → 150m, "1.5h" → 90m, "30m" → 30m, "90" → 90m, "2h 30" → 150m
- **expected result**: Each value produces the correct preview label (e.g. "= 2h 30m"); `parseMinutes` returns the expected integer
- **test command**: /test-functional

### TC-2: Duration parser — invalid inputs return null
- **spec_ref**: `openspec/changes/time-tracking/specs/time-tracking/spec.md#scenario-reject-invalid-input-non-numeric`
- **type**: functional
- **persona**: any authenticated Nextcloud user
- **preconditions**: Time entry form duration field is visible
- **steps**: Enter each invalid value and blur the field: "lots", "-5", "0", "0m", "99999h" (> 999 hours), "" (empty)
- **expected result**: For each invalid input: the live preview is hidden; on blur an inline error appears: "Enter a valid duration (e.g. 1h 30m, 90m, 1.5h)"; for empty field on submit attempt: "Duration is required"; submit button remains disabled
- **test command**: /test-functional

### TC-3: Duration formatter — output format rules
- **spec_ref**: `openspec/changes/time-tracking/specs/time-tracking/spec.md#scenario-format-hours-and-minutes`
- **type**: functional
- **persona**: any authenticated Nextcloud user (or unit test)
- **preconditions**: `formatMinutes` is called with various inputs
- **steps**: Verify output: 150 → "2h 30m", 120 → "2h 0m", 45 → "45m", 0 → "0m", null → ""
- **expected result**: Each input produces exactly the expected display string
- **test command**: /test-functional

### TC-4: Time entry form — default values on create
- **spec_ref**: `openspec/changes/time-tracking/specs/time-tracking/spec.md#scenario-default-field-values-on-create`
- **type**: functional
- **persona**: any authenticated Nextcloud user
- **preconditions**: Task detail view is open; user clicks "Log Time"
- **steps**: Open the time entry form in create mode; observe default field values
- **expected result**: Date field defaults to today's ISO date; duration field is empty with placeholder "e.g. 1h 30m"; description field is empty; current user is pre-filled (hidden from UI)
- **test command**: /test-functional

### TC-5: Time entry form — live duration preview updates as user types
- **spec_ref**: `openspec/changes/time-tracking/specs/time-tracking/spec.md#scenario-live-duration-preview`
- **type**: functional
- **persona**: any authenticated Nextcloud user
- **preconditions**: Time entry form is open
- **steps**: Type "1h 30m" into the duration field; observe the preview label
- **expected result**: Preview label shows "= 1h 30m" while the input is valid; when the input is invalid, the preview label is hidden (not shown as empty label)
- **test command**: /test-functional

### TC-6: Time entry form — loading state during save and success
- **spec_ref**: `openspec/changes/time-tracking/specs/time-tracking/spec.md#scenario-loading-state-during-save`
- **type**: functional
- **persona**: any authenticated Nextcloud user
- **preconditions**: Time entry form has valid duration and date
- **steps**: Click the submit button; observe the form state during save; wait for success
- **expected result**: Submit button shows spinner and is disabled; cancel/close button is disabled; fields are read-only; on success: form closes; toast "Time logged" shown; `TimeEntriesList` updates immediately
- **test command**: /test-functional

### TC-7: Time entry form — error preserves values
- **spec_ref**: `openspec/changes/time-tracking/specs/time-tracking/spec.md#scenario-error-preservation-on-failure`
- **type**: functional
- **persona**: any authenticated Nextcloud user
- **preconditions**: Time entry form has valid data; store operation is set to fail
- **steps**: Submit the form when the API returns an error
- **expected result**: Form remains open; all user-entered values are preserved; inline error banner shows the failure reason
- **test command**: /test-functional

### TC-8: Time entries list — sort order and own-entry controls
- **spec_ref**: `openspec/changes/time-tracking/specs/time-tracking/spec.md#scenario-sort-order`
- **type**: functional
- **persona**: any authenticated Nextcloud user (logged time on a task shared with another user)
- **preconditions**: A task has 3 time entries: 2 by the current user (on different dates) and 1 by another user
- **steps**: Navigate to the task's Time sidebar tab; inspect the `TimeEntriesList`
- **expected result**: Entries ordered by date descending (most recent first); current user's entries show Edit and Delete buttons; other user's entry shows no edit/delete controls; other user's entry shows an `NcAvatar`
- **test command**: /test-functional

### TC-9: Time entries list — edit entry
- **spec_ref**: `openspec/changes/time-tracking/specs/time-tracking/spec.md#scenario-edit-entry-inline`
- **type**: functional
- **persona**: any authenticated Nextcloud user
- **preconditions**: `TimeEntriesList` shows at least one own entry
- **steps**: Click Edit on an own entry
- **expected result**: `TimeEntryForm` opens in edit mode pre-filled with the entry's current values (duration, date, description)
- **test command**: /test-functional

### TC-10: Time entries list — delete entry with confirmation
- **spec_ref**: `openspec/changes/time-tracking/specs/time-tracking/spec.md#scenario-delete-entry-with-confirmation`
- **type**: functional
- **persona**: any authenticated Nextcloud user
- **preconditions**: `TimeEntriesList` shows at least one own entry
- **steps**: Click Delete on an own entry; observe confirmation dialog; confirm
- **expected result**: Confirmation dialog appears with "Delete this time entry? This cannot be undone."; confirming calls `useTimeEntriesStore.deleteEntry(id)`; entry removed from list immediately (optimistic update)
- **test command**: /test-functional

### TC-11: Progress indicator — all states
- **spec_ref**: `openspec/changes/time-tracking/specs/time-tracking/spec.md#scenario-no-estimate-set`
- **type**: functional
- **persona**: any authenticated Nextcloud user
- **preconditions**: Three tasks exist: (a) no estimate, (b) estimate set and logged < estimate, (c) estimate set and logged > estimate
- **steps**: View the Time tab of each task
- **expected result**: (a) Shows only "Logged: Xh Ym"; no progress bar; (b) Shows "Xh Ym / Yh Zm"; green progress bar at correct %; (c) Logged time shown in red; bar at 100% red; "+Xh Ym over" overflow label
- **test command**: /test-functional

### TC-12: Estimate field on task creation form
- **spec_ref**: `openspec/changes/time-tracking/specs/time-tracking/spec.md#scenario-live-duration-preview`
- **type**: functional
- **persona**: any authenticated Nextcloud user
- **preconditions**: Task creation dialog is open (after `tasks` change is applied)
- **steps**: Type "3h" into the Estimate field in `TaskCreateDialog`
- **expected result**: Live preview shows "= 3h 0m" below the input; on task creation, `estimatedDuration: 180` is saved to the task
- **test command**: /test-functional

### TC-13: Timesheet — entries grouped by date with daily totals
- **spec_ref**: `openspec/changes/time-tracking/specs/time-tracking/spec.md#scenario-entries-grouped-by-date`
- **type**: functional
- **persona**: any authenticated Nextcloud user
- **preconditions**: User has time entries on 3 different dates within the current week; user navigates to `/timesheet`
- **steps**: View `TimesheetView`
- **expected result**: Entries grouped by date (newest date first); each date group shows a header with the date and the group's total duration; a range total shows the sum for the entire selected period
- **test command**: /test-functional

### TC-14: Timesheet — task title link navigates to task detail
- **spec_ref**: `openspec/changes/time-tracking/specs/time-tracking/spec.md#scenario-navigate-to-task`
- **type**: functional
- **persona**: any authenticated Nextcloud user
- **preconditions**: Timesheet has at least one entry with a task link
- **steps**: Click a task title link in the timesheet; then press the browser back button
- **expected result**: Navigates to `/tasks/:id`; pressing back returns to `/timesheet` with the same date range selected
- **test command**: /test-functional

### TC-15: Timesheet — date range filter defaults and presets
- **spec_ref**: `openspec/changes/time-tracking/specs/time-tracking/spec.md#scenario-default-range-on-load`
- **type**: functional
- **persona**: any authenticated Nextcloud user
- **preconditions**: Timesheet is loaded
- **steps**: (a) Note the default range on load; (b) Select "Last week"; (c) Select "This week" again; (d) Select "Custom" and pick dates
- **expected result**: (a) Defaults to "This week" (Monday of current ISO week through today); (b) Shows entries for the previous ISO week (Mon–Sun); (c) Returns to current week; (d) Custom date inputs appear; filter applied when both dates selected
- **test command**: /test-functional

### TC-16: Timesheet — custom range validation
- **spec_ref**: `openspec/changes/time-tracking/specs/time-tracking/spec.md#scenario-range-validation`
- **type**: functional
- **persona**: any authenticated Nextcloud user
- **preconditions**: Timesheet is in "Custom" range mode
- **steps**: Set the end date to a date before the start date
- **expected result**: Inline error "End date must be after start date" appears; the filter is not applied until the dates are corrected
- **test command**: /test-functional

### TC-17: Timesheet — empty state for date range with no entries
- **spec_ref**: `openspec/changes/time-tracking/specs/time-tracking/spec.md#scenario-empty-state-for-date-range`
- **type**: functional
- **persona**: any authenticated Nextcloud user with no time entries in the selected range
- **preconditions**: User has no time entries in the current week
- **steps**: Navigate to `/timesheet`
- **expected result**: "No time logged for this period." is shown
- **test command**: /test-functional

### TC-18: Time entries list — empty state
- **spec_ref**: `openspec/changes/time-tracking/specs/time-tracking/spec.md#scenario-empty-state`
- **type**: functional
- **persona**: any authenticated Nextcloud user
- **preconditions**: A task exists with no logged time entries
- **steps**: Navigate to the task's Time sidebar tab
- **expected result**: Message shown: "No time logged yet. Click 'Log Time' to add your first entry."
- **test command**: /test-functional

## Coverage Summary

| Requirement | Scenarios Covered | Test Cases |
|-------------|-------------------|------------|
| Time Duration Parser [MVP] | Valid inputs, invalid inputs, zero, ceiling, whitespace | TC-1, TC-2 |
| Time Duration Formatter [MVP] | Hours+min, whole hours, min-only, zero, null | TC-3 |
| Time Entry Form UI [MVP] | Defaults, preview, validation, loading, success, error | TC-4, TC-5, TC-6, TC-7 |
| Time Entries List on Task Detail [MVP] | Sort, own-entry controls, edit, delete, empty, avatar | TC-8, TC-9, TC-10, TC-18 |
| Progress Indicator Component [MVP] | No estimate, under, at, over, zero logged | TC-11 |
| Personal Timesheet Layout [MVP] | Grouping, row content, range total, task link | TC-13, TC-14 |
| Date Range Filter [MVP] | Default, presets, custom, validation, empty | TC-15, TC-16, TC-17 |
| Estimate field on task form | Live preview, save | TC-12 |
| i18n Coverage [MVP] | Not covered in browser test (see Out of Scope) | — |

## Out of Scope

- i18n translation completeness — verified via build-time linting; the full i18n string table is in the spec and tested via `l10n/` file completeness check
- `parseMinutes` and `formatMinutes` unit tests — should be covered in Jest/Vitest unit tests, not browser tests (pure functions are fastest and most reliably tested at unit level)
- Time entries for other users visible to project admins — access control scoping is deferred to a dedicated security test pass
