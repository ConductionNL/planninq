## 1. Build the seed fixture

- [ ] 1.1 Create `tests/e2e/fixtures/seed.ts` exporting an async `seedFixtures(baseURL, storageState)` that, using the same auth cookie jar `global-setup.ts` persists, POSTs directly to the planix/OpenRegister REST API (mirroring `tests/integration/planix.postman_collection.json`'s request shapes) to create/ensure:
  - one project (admin as member) with its default columns
  - at least 2 tasks in different columns, one with a due date in the "approaching" window and one "overdue" (for `kanban-board.spec.ts`'s badge assertions), an assignee, and a priority
  - at least one label, attached to one of the seeded tasks (for `label-management.spec.ts`)
  - confirm/enable the admin `due_date_reminder` setting defaults (for `due-date-reminder-settings.spec.ts`)
- [ ] 1.2 Make every create call idempotent: check-by-known-title/slug first, reuse if present, only create if absent — so re-running against a persistent dev container does not accumulate duplicate fixture projects/tasks/labels
- [ ] 1.3 Call `seedFixtures` from `tests/e2e/global-setup.ts` after the existing login step, before `context.storageState()` is persisted

## 2. Tighten the specs' skip guards

- [ ] 2.1 `tests/e2e/kanban-board.spec.ts`: keep the "Planix not installed" skip (`res === null || res.status() >= 400`) as a legitimate environment-absence signal; convert the `test.skip((await board.count()) === 0, …)` and `test.skip((await cards.count()) === 0, …)` guards to `await expect(board).toHaveCount(1)` / `await expect(cards).not.toHaveCount(0)` — after seeding, these must always resolve
- [ ] 2.2 `tests/e2e/task-collaboration.spec.ts`: same treatment for the `taskLink`, `commentsTab`, `filesTab`, `auditTab` guards; keep the "Activity app not available" skip only if the Activity app is genuinely an optional dependency in the test environment
- [ ] 2.3 `tests/e2e/label-management.spec.ts`: same treatment for the `section`, `createBtn`, `editBtn`, `deleteBtn` guards
- [ ] 2.4 `tests/e2e/due-date-reminder-settings.spec.ts`: same treatment for the `gear`, `field` guards

## 3. Update docblocks

- [ ] 3.1 Remove the "Planix is not installed in the dev container at the time of writing; these tests are scaffolded for a future run" language from `label-management.spec.ts` and `due-date-reminder-settings.spec.ts` headers once they exercise real assertions unconditionally
- [ ] 3.2 Update `kanban-board.spec.ts` / `task-collaboration.spec.ts` headers similarly

## 4. Verify

- [ ] 4.1 Run `npx playwright test` against a fresh Nextcloud + planix container (no manually-created projects/tasks) and confirm all four specs execute real assertions (0 skips from the tightened guards; only the legitimate "app not installed" skip remains reachable, and only when planix truly isn't installed)
- [ ] 4.2 Run twice in a row against the same persistent container and confirm no duplicate fixture projects/tasks/labels accumulate (idempotency check from 1.2)
- [ ] 4.3 `openspec validate e2e-seed-fixtures-for-real-assertions --strict` passes
