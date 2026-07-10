## 1. Build the seed fixture

- [x] 1.1 Create `tests/e2e/fixtures/seed.ts` exporting an async `seedFixtures(baseURL, opts)` that POSTs directly to the planix/OpenRegister REST API (mirroring `tests/integration/planix.postman_collection.json`'s request shapes) to create/ensure: one project (admin as member) with its default columns; 3 tasks (approaching due date, overdue, far-future) with assignee + priority; one label attached to the approaching task. NOTE: uses HTTP Basic auth (admin/admin) instead of the browser cookie jar — Basic-auth API calls bypass NC's session CSRF check, which the storage-state jar cannot satisfy. The `due_date_reminder` admin setting default is confirmed by the reminder spec directly (no seed write needed; defaults exist on install).
- [x] 1.2 Make every create call idempotent: check-by-known-title first, reuse if present, only create if absent (project/columns/label/tasks all guarded) — re-running against a persistent container does not accumulate duplicates
- [x] 1.3 Call `seedFixtures` from `tests/e2e/global-setup.ts` after the login step, before `context.storageState()` is persisted (wrapped in try/catch so a seed hiccup never aborts the suite)

## 2. Tighten the specs' skip guards

- [x] 2.1 `tests/e2e/kanban-board.spec.ts`: kept the "Planix not installed" skip; converted the board/cards guards to `await expect(board).toHaveCount(1)` / `await expect(cards).not.toHaveCount(0)`, and the badge counts to `await expect(dueSoon.first()).toBeVisible()` / `overdue` visible
- [x] 2.2 `tests/e2e/task-collaboration.spec.ts`: converted `taskLink`, `commentsTab`, `filesTab`, `auditTab` guards to hard assertions; kept the "Activity app not available" skip (genuinely optional dep) and converted the filter-presence guard to an assertion
- [x] 2.3 `tests/e2e/label-management.spec.ts`: converted `section`, `createBtn`, `editBtn`, `deleteBtn` guards to assertions; the seed label title is `E2E Bug`
- [x] 2.4 `tests/e2e/due-date-reminder-settings.spec.ts`: converted `gear`, `field` guards to assertions

## 3. Update docblocks

- [x] 3.1 Removed the "scaffolded for a future run" language from `label-management.spec.ts` and `due-date-reminder-settings.spec.ts` headers
- [x] 3.2 Updated `kanban-board.spec.ts` / `task-collaboration.spec.ts` headers similarly

## 4. Verify

- [~] 4.1 Run `npx playwright test` against a fresh Nextcloud + planix container — NEEDS LIVE INSTANCE (deferred per house rule: no deploy to the shared dev instance; no isolated planix+OR container available). Static proof done: `npx playwright test --list` compiles all specs (24 tests, 5 files) with the new seed import; all `test.skip` fixture-absence guards replaced by `expect(...)`.
- [~] 4.2 Run twice for idempotency — NEEDS LIVE INSTANCE. Seed is check-by-title-first / reuse-if-present for project, columns, label and tasks (verified by inspection); runtime proof requires a live container.
- [~] 4.3 `openspec validate e2e-seed-fixtures-for-real-assertions --strict` passes — DEFERRED: openspec CLI not installed in this worktree.
