# Tasks: time-timer-and-work-type

## 1. Timer

- [ ] 1.1 `running_timer` user key (`{ task, startedAt }`, validated) in `SettingsService`, returned by `GET /api/settings`. Verify: PHPUnit `SettingsServiceTest::testRunningTimerRoundTrips` and `testRunningTimerRejectsMalformedValue`.
- [ ] 1.2 `startTimer`, `stopTimer` and `discardTimer` in `src/store/timeEntries.js`, with the pure helper `src/utils/timer.js` (elapsed minutes rounded up, at least one, the 12-hour warning). Verify: vitest `tests/vitest/timer.spec.js` "rounds up to whole minutes", "never books zero minutes" and "warns after twelve hours".
- [ ] 1.3 `src/components/RunningTimer.vue` on My tasks, the Timesheet and the task page; "Start timer" on `TaskDetail` and on the Timesheet with a task picker; the second-timer question. Verify: Playwright `tests/e2e/timer.spec.ts` "person starts a timer on a task and sees it on My tasks", "timer survives a reload", "starting a second timer asks first" and "discard books nothing".
- [ ] 1.4 Stop opens `TimeEntryDialog` prefilled; cancel books nothing; save books through `timeEntries.create`. Verify: Playwright `tests/e2e/timer.spec.ts` "stopping opens the form with the measured time" and "cancelling the form books nothing".
- [ ] 1.5 The running timer on a phone (moved here from platform-mobile-web, which was built before the timer existed): start and stop by tapping at 360 pixels, 44 pixel targets. Verify: Playwright `tests/e2e/mobile.spec.ts` "start and stop a timer on a phone" (phone-android and phone-ios projects).

## 2. Work type

- [ ] 2.1 `work_types` admin key with validation and the admin section (add, rename with optional update of existing entries, retire). Verify: PHPUnit `SettingsServiceTest::testWorkTypesRejectsDuplicates` and `WorkTypeRenameJobTest::testRenameUpdatesExistingEntries`; Playwright `tests/e2e/work-type.spec.ts` "admin adds work types".
- [ ] 2.2 `plannedTimeEntry.workType` (string) and a schema version bump. Verify: PHPUnit `PlanninqRegisterSchemaTest::testTimeEntryCarriesWorkType`.
- [ ] 2.3 Required "Work type" select in `TimeEntryDialog` when the list is not empty, hidden when it is. Verify: Playwright `tests/e2e/work-type.spec.ts` "entry records its work type" and "no work types means no field".
- [ ] 2.4 "By work type" chart in `TimeSpentReport`. Verify: Playwright `tests/e2e/work-type.spec.ts` "time report splits minutes by work type".

## 3. Verification

- [ ] 3.1 `openspec validate time-timer-and-work-type --type change --strict` passes.
- [ ] 3.2 Every scenario in specs/ is covered by a test named in the task above it, or carries an `@e2e exclude <reason>` note.
