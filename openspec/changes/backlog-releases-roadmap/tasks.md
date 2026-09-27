# Tasks: backlog-releases-roadmap

## 1. Schema

- [ ] 1.1 Add the `release` schema (title, project, description, startDate, releaseDate, status, releasedAt; `projectPhase` authorization block) and list it in the register. Verify: PHPUnit `PlanninqRegisterSchemaTest::testReleaseSchemaIsProjectScoped`; Newman request "non-member release read is refused" in `tests/integration/planninq.postman_collection.json`.
- [ ] 1.2 Add nullable `task.release` (`$ref: release`) and bump the register and schema versions. Verify: PHPUnit `PlanninqRegisterSchemaTest::testTaskReleaseReferenceIsNullable`.
- [ ] 1.3 Raise the exact schema count in `testRegisterDeclaresExactlySevenSchemas` and `openspec/specs/project-delivery/spec.md:68-72` by one. Verify: `composer test:unit` green.

## 2. Store and helpers

- [ ] 2.1 `fetchReleases(projectId)`, `saveRelease`, `shipRelease(id, unfinishedTarget)` and `setTaskRelease(taskId, releaseId)` in `src/store/projects.js`. Verify: vitest `tests/vitest/releases.spec.js` "ship with unfinished tasks moves them to the chosen release" and "ship with everything done only sets status".
- [ ] 2.2 `releaseProgress(release, tasks)` and `epicSpan(epic, tasks)` pure helpers in `src/utils/roadmapHelpers.js`. Verify: vitest `tests/vitest/roadmapHelpers.spec.js` "progress counts done and cancelled", "epic without dates spans its tasks" and "epic without dated tasks is unscheduled".
- [ ] 2.3 `setTaskEpic(taskId, epicId)` refuses an epic of another project and an epic pointing at an epic. Verify: vitest `tests/vitest/releases.spec.js` "epic picker refuses another project's epic".

## 3. Screens

- [ ] 3.1 `ProjectRoadmap` page in `src/manifest.json` and `src/registry.js`, Roadmap button in the `ProjectBoard` header. Verify: Playwright `tests/e2e/roadmap.spec.ts` "member opens the roadmap from the board".
- [ ] 3.2 Chart (epic bars, release markers, zoom) and the release list with progress. Verify: Playwright `tests/e2e/roadmap.spec.ts` "roadmap shows releases and epics over time", "epic with no dates is listed as not scheduled", "progress counts done and cancelled tasks" and "release list works without the chart".
- [ ] 3.3 `src/dialogs/ReleaseEditDialog.vue` and `src/dialogs/ReleaseShipDialog.vue`. Verify: Playwright `tests/e2e/roadmap.spec.ts` "member creates a release", "shipping with everything done" and "shipping asks about unfinished tasks".
- [ ] 3.4 Release, "This task is an epic" and Epic fields on `TaskDetail`. Verify: Playwright `tests/e2e/roadmap.spec.ts` "member plans a task against a release", "release picker offers only this project's releases", "member links a task to an epic" and "epic picker offers only this project's epics".
- [ ] 3.5 The product roadmap page keeps its label and route. Verify: Playwright `tests/e2e/roadmap.spec.ts` "features and roadmap page is unchanged".

## 4. Verification

- [ ] 4.1 `openspec validate backlog-releases-roadmap --type change --strict` passes.
- [ ] 4.2 Every scenario in specs/ is covered by a test named in the task above it, or carries an `@e2e exclude <reason>` note.
