# Tasks: planning-timeline-editing

## 1. Working calendar

- [x] 1.1 Add `working_weekdays` and `non_working_days` in `WorkingCalendarService` (amended: `SettingsService` is at phpmd's limits; the keys are saved and read through `SettingsController` like `TimetableGridService`'s), with validation on save. Verify: PHPUnit `WorkingCalendarServiceTest::testNonWorkingDaysRejectsMalformedValue`, `testWorkingCalendarReadableByMember` and `SettingsControllerTest::testAMemberReadsTheWorkingCalendar`.
- [x] 1.2 `src/utils/workingCalendar.js` (`isWorkingDay`, `nextWorkingDay`, `addWorkingDays`, `workingDaysBetween`, `dutchHolidays(year)`). Verify: vitest `tests/vitest/workingCalendar.spec.js` "skips weekends and listed holidays", "counts working days between two dates" and "Dutch holidays 2027 include Easter Monday 29 March".
- [x] 1.3 "Working days and holidays" section, `src/components/WorkingCalendarSettings.vue`, shown in `src/views/settings/Settings.vue`. Verify: Playwright `tests/e2e/working-calendar.spec.ts` "admin adds a holiday" and "admin fills in the Dutch holidays for a year".
- [x] 1.4 Timeline axis shades every non-working day and labels holidays. Verify: Playwright `tests/e2e/working-calendar.spec.ts` "holiday is shaded and named on the timeline".

## 2. Editing on the timeline

- [x] 2.1 `TimelineController::fetchProjectDependencies` returns each edge's `type` (already built by `integration-msproject-import`, #732; this change adds the named tests). Verify: PHPUnit `TimelineControllerTest::testEdgesCarryType` and `testTimelineEndpointWritesNothing`.
- [x] 2.2 Bars become focusable buttons with move and resize by pointer, arrow keys and `src/dialogs/TaskDatesDialog.vue`; writes through `updateTask`, revert on failure, live-region announcement; the date sums in `src/utils/timelineEditing.js` (vitest `tests/vitest/timelineEditing.spec.js`). Verify: Playwright `tests/e2e/timeline-editing.spec.ts` (amended: its own file) "member drags a bar to move a task", "member resizes the due date", "member moves a task with the keyboard", "member sets dates in the dialog", "failed write puts the bar back" and "a dropped start on a holiday moves to the next working day".
- [x] 2.3 Non-blocking edge types drawn as dotted lines. Verify: vitest `tests/vitest/timelineHelpers.spec.js` "relates edge is styled as non-blocking"; Playwright `tests/e2e/timeline-autoschedule.spec.ts` (amended: its own file) "relates link moves nothing".

## 3. Auto-scheduling

- [x] 3.1 `project.autoSchedule` (boolean, default false) in the register and a switch in `ProjectSettingsSidebar` for the owner. Verify: PHPUnit `PlanninqRegisterSchemaTest::testProjectAutoScheduleDefaultsOff`; Playwright `tests/e2e/timeline-autoschedule.spec.ts` "without auto-scheduling only the dragged task moves".
- [x] 3.2 `cascade()` in `src/utils/scheduling.js` following `blocks` edges only, forward only, in working days. Verify: vitest `tests/vitest/scheduling.spec.js` "slip pushes a chain", "relates edge moves nothing", "earlier move pulls nothing" and "successor that already starts later stays".
- [x] 3.3 `src/dialogs/RescheduleDialog.vue` preview with "Move all", "Only this task" and "Cancel", sequential writes, stop and report on failure. Verify: Playwright `tests/e2e/timeline-autoschedule.spec.ts` "slip shows the preview and moves the chain" and "member moves only the task they dragged"; vitest `tests/vitest/scheduling.spec.js` "write run stops at the first failure and reports it".

## 4. Specs and docs

- [x] 4.1 Update the doc comments of `src/views/ProjectTimeline.vue` and `src/api/timeline.js` that call the timeline read-only, to say the endpoint is read-only and the view edits dates. Verify: reviewer reads the diff.

## 5. Verification

- [x] 5.1 `openspec validate planning-timeline-editing --type change --strict` passes.
- [x] 5.2 Every scenario in specs/ is covered by a test named in the task above it, or carries an `@e2e exclude <reason>` note.
