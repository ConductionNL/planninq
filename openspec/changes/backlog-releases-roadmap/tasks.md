# Tasks: backlog-releases-roadmap

## 1. Schema

- [x] 1.1 Add the `projectRelease` schema (title, project, description, startDate, releaseDate, status, releasedAt; `projectPhase` authorization block) and list it in the register; stamp and gate it like the other project-scoped schemas. Verify: PHPUnit `PlanninqRegisterSchemaTest::testReleaseSchemaIsProjectScoped`, `ProjectMemberAccessListenerTest::testReleaseIsStampedAndGated`; Newman folder "Releases" in `tests/integration/planninq.postman_collection.json` (create, plan a task, list, mark released, remove). The non-member read runs on the members rule asserted by `testProjectScopedSchemasMatchTheMembersList`; the Newman suite signs in as the admin only.
- [x] 1.2 Add nullable `task.release` (`$ref: projectRelease`) and bump the register and schema versions. Verify: PHPUnit `PlanninqRegisterSchemaTest::testTaskReleaseReferenceIsNullable`.
- [x] 1.3 Raise the exact schema count in `testRegisterDeclaresExactlyFifteenSchemas` and `openspec/specs/project-delivery/spec.md` by one; three demo releases in `lib/Settings/planninq_mock_register.json`. Verify: `composer test:unit` green, `DemoDatasetTest`.

## 2. Store and helpers

- [x] 2.1 `fetchReleases(projectId)`, `saveRelease`, `shipRelease(release, tasks, choice, targetId)` and `setTaskRelease(taskId, releaseId)` in `src/store/projects.js`. Verify: vitest `tests/vitest/releases.spec.js` "ship with unfinished tasks moves them to the chosen release" and "ship with everything done only sets status".
- [x] 2.2 `releaseProgress(release, tasks)`, `epicSpan(epic, tasks)` and `roadmapLayout` pure helpers in `src/utils/roadmapHelpers.js`. Verify: vitest `tests/vitest/roadmapHelpers.spec.js` "progress counts done and cancelled", "epic without dates spans its tasks" and "epic without dated tasks is unscheduled".
- [x] 2.3 `setTaskEpic(task, epic)` refuses an epic of another project and an epic pointing at an epic. Verify: vitest `tests/vitest/releases.spec.js` "epic picker refuses another project's epic" and `roadmapHelpers.spec.js` "refuses an epic of another project".

## 3. Screens

- [x] 3.1 A "Tasks / Roadmap" switch on the Timeline tab (`?view=roadmap`), rendering `src/components/ProjectRoadmap.vue` (design decision 4, amended: no new custom page). Verify: Playwright `tests/e2e/roadmap.spec.ts` "member opens the roadmap from the project tabs".
- [x] 3.2 Chart (epic bars, release markers, zoom) and the release and epic lists with progress. Verify: Playwright `tests/e2e/roadmap.spec.ts` "roadmap shows releases and epics over time", "epic with no dates is listed as not scheduled", "progress counts done and cancelled tasks" and "release list works without the chart".
- [x] 3.3 `src/dialogs/ReleaseEditDialog.vue` and `src/dialogs/ReleaseShipDialog.vue`. Verify: Playwright `tests/e2e/roadmap.spec.ts` "member creates a release", "shipping with everything done" and "shipping asks about unfinished tasks".
- [x] 3.4 Release, "This task is an epic" and Epic fields on `TaskDetail`. Verify: Playwright `tests/e2e/roadmap.spec.ts` "member plans a task against a release and links it to an epic", "pickers offer only this project's releases and epics".
- [x] 3.5 The product roadmap page keeps its label and route. Verify: Playwright `tests/e2e/roadmap.spec.ts` "features and roadmap page is unchanged".

## 4. Verification

- [x] 4.1 `openspec validate backlog-releases-roadmap --type change --strict` passes.
- [x] 4.2 Every scenario in specs/ is covered by a test named in the task above it, or carries an `@e2e exclude <reason>` note.
