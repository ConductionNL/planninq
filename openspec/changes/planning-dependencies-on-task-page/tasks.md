# Tasks: planning-dependencies-on-task-page

## 1. Task page

- [ ] 1.1 Mount `TaskDependencies.vue` on `src/views/TaskDetail.vue` with the project's tasks and edges. Verify: Playwright e2e adds and removes a "Blocked by" link and checks both tasks' lists.
- [ ] 1.2 Show a refused link's reason inline. Verify: Playwright e2e tries a two-task cycle and sees the server message.

## 2. Board

- [ ] 2.1 Load edges in `src/views/ProjectBoard.vue`, derive blocked ids once, and render `BlockedBadge` in `src/components/TaskCard.vue`. Verify: Playwright e2e sees the badge, moves the blocker to done, and sees it clear.

## 3. Link types

- [ ] 3.1 Accept `type` in `DependencyController` and `DependencyService::create`; cycle check for `blocks` only. Verify: PHPUnit tests in `tests/Unit/Service/` for a `relates` edge and a `blocks` cycle.
- [ ] 3.2 Type choice in the picker, a "Related" group in the section, and `blocks`-only derivation in `src/utils/taskHelpers.js`. Verify: vitest spec that a `relates` edge does not block.

## 4. Timeline

- [ ] 4.1 A Playwright e2e creates a link on the task page and sees the arrow on /projects/:id/timeline. Verify: the test itself in `tests/e2e/project-timeline.spec.ts`.

## 5. Copy and verification

- [ ] 5.1 New strings in `l10n/en.json` and `l10n/nl.json`. Verify: `npm run check:l10n` exits 0.
- [ ] 5.2 `openspec validate planning-dependencies-on-task-page --type change --strict` passes.
