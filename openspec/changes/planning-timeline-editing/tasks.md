# Tasks: planning-timeline-editing

## 1. Working calendar

- [ ] 1.1 Add `working_weekdays` and `non_working_days` to `SettingsService::ADMIN_CONFIG_DEFAULTS` with validation in `setAdminSettings`. Verify: PHPUnit `SettingsServiceTest::testNonWorkingDaysRejectsMalformedValue` and `testWorkingCalendarReadableByMember`.
- [ ] 1.2 `src/utils/workingCalendar.js` (`isWorkingDay`, `nextWorkingDay`, `addWorkingDays`, `workingDaysBetween`, `dutchHolidays(year)`). Verify: vitest `tests/vitest/workingCalendar.spec.js` "skips weekends and listed holidays", "counts working days between two dates" and "Dutch holidays 2027 include Easter Monday 29 March".
- [ ] 1.3 "Working days and holidays" section in `src/views/settings/Settings.vue`. Verify: Playwright `tests/e2e/working-calendar.spec.ts` "admin adds a holiday" and "admin fills in the Dutch holidays for a year".
- [ ] 1.4 Timeline axis shades every non-working day and labels holidays. Verify: Playwright `tests/e2e/working-calendar.spec.ts` "holiday is shaded and named on the timeline".

## 2. Editing on the timeline

- [ ] 2.1 `TimelineController::fetchProjectDependencies` returns each edge's `type`. Verify: PHPUnit `TimelineControllerTest::testEdgesCarryType` and `testTimelineEndpointWritesNothing`.
- [ ] 2.2 Bars become focusable buttons with move and resize by pointer, arrow keys and `src/dialogs/TaskDatesDialog.vue`; writes through `updateTask`, revert on failure, live-region announcement. Verify: Playwright `tests/e2e/project-timeline.spec.ts` "member drags a bar to move a task", "member resizes the due date", "member moves a task with the keyboard", "member sets dates in the dialog", "failed write puts the bar back" and "a dropped start on a holiday moves to the next working day".
- [ ] 2.3 Non-blocking edge types drawn as dotted lines. Verify: vitest `tests/vitest/timelineHelpers.spec.js` "relates edge is styled as non-blocking"; Playwright `tests/e2e/project-timeline.spec.ts` "relates link moves nothing".

## 3. Auto-scheduling

- [ ] 3.1 `project.autoSchedule` (boolean, default false) in the register and a switch in `ProjectSettingsSidebar` for the owner. Verify: PHPUnit `PlanninqRegisterSchemaTest::testProjectAutoScheduleDefaultsOff`; Playwright `tests/e2e/project-timeline.spec.ts` "without auto-scheduling only the dragged task moves".
- [ ] 3.2 `cascade()` in `src/utils/scheduling.js` following `blocks` edges only, forward only, in working days. Verify: vitest `tests/vitest/scheduling.spec.js` "slip pushes a chain", "relates edge moves nothing", "earlier move pulls nothing" and "successor that already starts later stays".
- [ ] 3.3 `src/dialogs/RescheduleDialog.vue` preview with "Move all", "Only this task" and "Cancel", sequential writes, stop and report on failure. Verify: Playwright `tests/e2e/project-timeline.spec.ts` "slip shows the preview and moves the chain" and "member moves only the task they dragged"; vitest `tests/vitest/scheduling.spec.js` "write run stops at the first failure and reports it".

## 4. Specs and docs

- [ ] 4.1 Update the doc comments of `src/views/ProjectTimeline.vue` and `src/api/timeline.js` that call the timeline read-only, to say the endpoint is read-only and the view edits dates. Verify: reviewer reads the diff.

## 5. Verification

- [ ] 5.1 `openspec validate planning-timeline-editing --type change --strict` passes.
- [ ] 5.2 Every scenario in specs/ is covered by a test named in the task above it, or carries an `@e2e exclude <reason>` note.
