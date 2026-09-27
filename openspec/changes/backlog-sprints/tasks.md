# Tasks: backlog-sprints

## 1. Schema

- [ ] 1.1 Add the `sprint` schema to `lib/Settings/planninq_register.json` (title, project, goal, startDate, endDate, status, order; `projectPhase` authorization block) and list it in the register's `schemas`. Verify: PHPUnit `PlanninqRegisterSchemaTest::testSprintSchemaIsProjectScoped` asserts the properties, the required fields and that the authorization block equals `projectPhase`'s.
- [ ] 1.2 Add nullable `task.sprint` (`$ref: sprint`) and `project.sprintsEnabled` (boolean, default false). Verify: PHPUnit `PlanninqRegisterSchemaTest::testTaskSprintReferenceIsNullable`.
- [ ] 1.3 Bump the register and schema versions so the import runs. Verify: PHPUnit `testRegisterVersionBumped` (existing version-skip logic, `openspec/specs/register-schemas/spec.md`).
- [ ] 1.4 Move `testRegisterDeclaresExactlySevenSchemas` to eight schemas and update the scenario in `openspec/specs/project-delivery/spec.md:68-72` in the same PR. Verify: `composer test:unit` green.

## 2. Store

- [ ] 2.1 `lib/Listener/TaskCompletionListener.php` on `ObjectUpdatingEvent` and `ObjectCreatingEvent`, registered in `Application.php`: merges `completedAt` (now) on entering `done` and `completedAt: null` on leaving it. Verify: PHPUnit `TaskCompletionListenerTest::testEnteringDoneStampsCompletedAt`, `testLeavingDoneClearsCompletedAt`, `testOtherChangesLeaveCompletedAt` and `testMergesWithDataFromAnotherListener`; Playwright `tests/e2e/sprints.spec.ts` "dragging a card to done stamps the finish time".
- [ ] 2.2 Add `fetchSprints(projectId)`, `saveSprint(sprint)`, `startSprint(id)`, `completeSprint(id, target)` and `planTask(taskId, sprintId)`; `saveSprint` rejects an end date before the start date and `startSprint` rejects a second active sprint. Verify: vitest `tests/vitest/sprints.spec.js` "rejects a second active sprint" and "rejects end before start".
- [ ] 2.3 `completeSprint` moves unfinished tasks to the chosen target and leaves the sprint active when any move fails. Verify: vitest `tests/vitest/sprints.spec.js` "partial failure keeps the sprint active".

## 3. Screens

- [ ] 3.1 Sprints switch in the Details tab of `src/components/ProjectSettingsSidebar.vue`, shown to the project owner. Verify: Playwright `tests/e2e/sprints.spec.ts` "owner turns sprints on" and "a project without sprints is unchanged".
- [ ] 3.2 `src/dialogs/SprintEditDialog.vue` (name, start, end, goal) and the sprint sections on the backlog page (one section per planned or active sprint, plus the unplanned backlog), with a keyboard-operable "Move to sprint" action on each task row. Verify: Playwright `tests/e2e/sprints.spec.ts` "member creates a sprint", "end before start is refused", "member plans a backlog task into a sprint with the keyboard" and "goal is changed during the sprint".
- [ ] 3.3 "Start sprint" and `src/dialogs/SprintCompleteDialog.vue`. Verify: Playwright `tests/e2e/sprints.spec.ts` "a second active sprint is refused" and "completing a sprint moves unfinished tasks to the backlog"; the failed-move scenario is covered by vitest task 2.3.
- [ ] 3.4 `ProjectBoard` loads only the active sprint's tasks when sprints are on, shows the sprint title, goal and days left in the header, and offers a "Whole project" toggle. Verify: Playwright `tests/e2e/sprints.spec.ts` "sprint board shows only sprint tasks and the goal", "member switches back to the whole project" and "without an active sprint the board shows the whole project".
- [ ] 3.5 `src/components/SprintBurndown.vue` with a pure helper `src/utils/burndown.js` (series per day, ideal line, unit choice, count of tasks without `completedAt`) and a data table equivalent. Verify: vitest `tests/vitest/burndown.spec.js` covers points, hours and count units and the placed-at-end count; Playwright `tests/e2e/sprints.spec.ts` "burndown plots remaining story points" and "burndown has a table equivalent".

## 4. Docs

- [ ] 4.1 Rewrite the "No sprints" row in `docs/ARCHITECTURE.md:149` to "Sprints are opt-in per project; flow is the default" and add a sprints row under Backlog management in `docs/FEATURES.md` (V1). Verify: reviewer reads the diff.

## 5. Verification

- [ ] 5.1 `openspec validate backlog-sprints --type change --strict` passes.
- [ ] 5.2 Every scenario in specs/ is covered by a test named in the task above it, or carries an `@e2e exclude <reason>` note.
