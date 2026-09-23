# Tasks: Blocked Task Reason and Blocked Filter (issue #640)

## 1. data model

- [ ] 1.1 Add a nullable string property `blockedReason` to the `task` schema in `lib/Settings/planninq_register.json`, without touching `slug`, `tablePrefix` or `folder`
- [ ] 1.2 Bump the register `version` in `lib/Settings/planninq_register.json` so `SettingsService` re-imports
- [ ] 1.3 Confirm the `status` enum in `lib/Settings/planninq_register.json` is unchanged and still contains `"blocked"`

## 2. pure helpers

- [ ] 2.1 Add `isTaskBlocked(task)` to `src/utils/taskHelpers.js`, returning true only when `task.status === 'blocked'`
- [ ] 2.2 Add a reason truncation helper to `src/utils/taskHelpers.js` that returns the reason unchanged when short and a shortened line plus the full text when long
- [ ] 2.3 Add a pure blocked-filter helper next to the existing label filter helper, taking a task list and a blocked flag and returning the filtered list

## 3. store

- [ ] 3.1 Add a task patch action to `src/store/projects.js` that writes `status` and `blockedReason` through the shared object store, with optimistic update and rollback on failure
- [ ] 3.2 Clear `blockedReason` in the same action when the new status is not `blocked`

## 4. task detail surface

- [ ] 4.1 Add a blocked toggle and a reason text field to `src/views/TaskDetail.vue`, pre-filled from the task's `status` and `blockedReason`
- [ ] 4.2 Wire the save control to the store patch action from task 3.1
- [ ] 4.3 Render the blocked toggle and reason field read-only and hide the save control when the member has no write access
- [ ] 4.4 Show a message and keep the stored values when the save fails

## 5. board card

- [ ] 5.1 Render the blocked reason line on `src/components/TaskCard.vue` when `isTaskBlocked(task)` is true and a reason is present
- [ ] 5.2 Expose the full reason from the card (tooltip) when the reason line is truncated
- [ ] 5.3 Keep the existing status chip, `data-cy="kanban-board"`, `.task-card` and `.task-card__due-date-badge` hooks unchanged

## 6. board filter

- [ ] 6.1 Add a "Blocked" chip to the filter row in `src/views/ProjectBoard.vue`, following the `labelFilterChips` / `setLabelFilter` / `aria-pressed` idiom
- [ ] 6.2 Fold the blocked filter into `visibleTasks` so it composes with the label filter
- [ ] 6.3 Render the filter row even when the instance has no labels, so the blocked filter is not hidden by `v-if="labels.length"`
- [ ] 6.4 Show the board's empty state, not an error, when the blocked filter matches no task

## 7. list view

- [ ] 7.1 Show the same blocked indication and reason on the list-view row

## 8. l10n

- [ ] 8.1 Add the new strings to `l10n/en.json`
- [ ] 8.2 Add the same strings to `l10n/nl.json` so the parity gate passes

## 9. tests

- [ ] 9.1 Vitest unit test for `isTaskBlocked` covering `blocked`, `open` and a missing status
- [ ] 9.2 Vitest unit test for the reason truncation helper covering a short reason, a long reason and no reason
- [ ] 9.3 Vitest unit test for the blocked filter helper covering blocked-only, unblocked-only and composition with a label filter
- [ ] 9.4 Playwright e2e test in `tests/e2e/kanban-board.spec.ts` covering the blocked mark and the reason on the card
- [ ] 9.5 Playwright e2e test covering selecting the blocked filter, clearing it, and unblocking a task
- [ ] 9.6 Playwright e2e test covering that the blocked state survives a board reload

## 10. spec and verification

- [ ] 10.1 Check off the blocked-indicator acceptance criterion in `openspec/specs/kanban-board.md` once the mark, reason and filter are implemented
- [ ] 10.2 Run `openspec validate blocked-task-reason-and-filter --strict`
- [ ] 10.3 Run the vitest and playwright suites and confirm the new tests pass
