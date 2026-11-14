# Tasks: planning-dependencies-on-task-page

## 1. Task page

- [x] 1.1 Mount `TaskDependencies.vue` on `src/views/TaskDetail.vue` with the project's tasks and edges. Verify: `tests/e2e/task-dependencies.spec.ts` "add a blocked by link, see it on both tasks, and remove it".
- [x] 1.2 Show a refused link's reason inline. Verify: `tests/e2e/task-dependencies.spec.ts` "a cycle is refused with the server message".

## 2. Board

- [x] 2.1 Load edges in `src/views/ProjectBoard.vue`, derive blocked ids once, and render `BlockedBadge` in `src/components/TaskCard.vue`. Verify: `tests/e2e/task-dependencies.spec.ts` "a blocked task shows a badge on the board until its blocker is done".

## 3. Link types

- [x] 3.1 Accept `type` in `DependencyController` and `DependencyService::create`; cycle check for `blocks` only. Verify: `tests/unit/Service/DependencyServiceTest.php::testCreateStoresARelatedLinkWithoutACycleCheck` and `testCreateChecksBlockingLinksAndRefusesAnUnknownType`.
- [x] 3.2 Type choice in the picker, a "Related" group in the section, and `blocks`-only derivation in `src/utils/taskHelpers.js`. Verify: `tests/vitest/taskDependencies.spec.js` linkGroups cases ("a related link does not block").

## 4. Timeline

- [x] 4.1 A Playwright e2e creates a link on the task page and sees the arrow on /projects/:id/timeline. Verify: `tests/e2e/task-dependencies.spec.ts` "a related link does not block, and a link shows on the timeline" (kept with the other link tests).

## 5. Copy and verification

- [x] 5.1 New strings in every locale the app ships. Verify: `npm run check:l10n` exits 0.
- [x] 5.2 `openspec validate planning-dependencies-on-task-page --type change --strict` passes.
