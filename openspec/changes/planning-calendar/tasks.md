# Tasks: planning-calendar

## 1. Calendar view

- [x] 1.1 `src/components/TaskCalendar.vue` with month, week and list views, plus the pure helper `src/utils/calendarHelpers.js` (`tasksByDay`, month and week ranges). Verify: vitest `tests/vitest/calendarHelpers.spec.js` "places tasks on their due date", "start-only task sits on its start date" and "week range starts on Monday".
- [x] 1.2 `ProjectCalendar` page at `/projects/:id/calendar` in `src/manifest.json` and `src/registry.js`, Calendar tab in `ProjectTabs` (`PROJECT_TABS`; design Decision 2 amended). Verify: Playwright `tests/e2e/task-calendar.spec.ts` "member sees project tasks on their due dates", "member switches to week view" and "list view shows the same tasks".
- [x] 1.3 `MyCalendar` page at `/my-calendar` over the My tasks query (`assignedTo` plus `sharedWith`), with links from the My tasks page and the dashboard quick actions. Verify: Playwright `tests/e2e/task-calendar.spec.ts` "my calendar shows only my tasks across projects".
- [x] 1.4 Keyboard and screen-reader structure (table with caption, day cells, task links, Previous, Today, Next). Verify: Playwright `tests/e2e/task-calendar.spec.ts` "calendar is operable with the keyboard".

## 2. CalDAV export

- [x] 2.1 Per-user key `export_tasks_to_caldav` (default off) in `TaskCalendarExportService`, applied by `SettingsController` (amended: `SettingsService` is at phpmd's limits), and the switch in `src/views/settings/UserSettings.vue`. Verify: PHPUnit `TaskCalendarExportServiceTest::testExportToCaldavDefaultsOff`, `testUnavailableWithoutTheDavBackend` and `SettingsControllerTest::testUpdateUserAppliesTheTaskExportSwitch`.
- [x] 2.2 Resolve the open question on the write path (design.md, Task 2.2 outcome), then build `lib/Service/TaskCalendarExportService.php` (list create and delete, write and delete per user) and `lib/Service/TaskVtodoBuilder.php` (VTODO mapping). Verify: PHPUnit `TaskVtodoBuilderTest::testTaskMapsToVtodo` (every property and status mapping) and `testManagedNoteAndUrlArePresent`.
- [x] 2.3 `lib/Listener/TaskCalendarExportListener.php` registered for the three OpenRegister object events; handles create, update, reassignment and delete; ignores a `calendarEventUid`-only update; catches every error. Verify: PHPUnit `TaskCalendarExportListenerTest::testReassignmentMovesTheVtodo`, `testDeleteRemovesTheVtodo`, `testUpdateRewritesTheWholeVtodo`, `testNoExportWhenSwitchedOff`, `testFirstExportStoresUid`, `testUidOnlyUpdateIsIgnored` and `testExportFailureDoesNotThrow`; wiring `BoardColumnWiringTest::testBootSubscribesBothBoardColumnListeners`.
- [x] 2.4 Backfill background job on switch-on and list removal on switch-off. Verify: PHPUnit `TaskCalendarExportServiceTest::testSwitchOnExportsExistingTasks` and `testSwitchOffRemovesTheList`.
- [ ] 2.5 Live check: with the switch on, a task assigned to the user appears in the Nextcloud Tasks app under "Planninq" with its due date, and editing its title in planninq changes it there. Verify: Playwright `tests/e2e/caldav-export.spec.ts` (reads the admin's CalDAV home, which is what the Tasks app shows) on the CI instance, plus one look in the Tasks app on a live instance.

## 3. Verification

- [x] 3.1 `openspec validate planning-calendar --type change --strict` passes.
- [ ] 3.2 Every scenario in specs/ is covered by a test named in the task above it, or carries an `@e2e exclude <reason>` note.
