---
kind: code
depends_on:
  - kanban-task-detail-keyboard-navigation   # closes the TaskDetail-unreachable root cause task-collaboration.spec.ts hits
---

# Proposal: e2e Specs Skip-Clean Instead of Asserting — Seed Real Fixtures

## Why

Four of planix's five Playwright specs under `tests/e2e/` self-skip whenever
the environment lacks pre-existing planix data, rather than creating that
data. Verified against `HEAD`:

- `tests/e2e/kanban-board.spec.ts:20-22` (docblock): *"The board surface
  depends on planix being installed with at least one project the admin is a
  member of; these tests skip cleanly when the app or a board is not
  reachable in this environment rather than failing the suite."*
- `tests/e2e/task-collaboration.spec.ts:25-27`: *"The task detail surface and
  the Activity entries depend on planix being installed with seeded data;
  these tests skip cleanly..."*
- `tests/e2e/label-management.spec.ts:20-22`: *"Planix is not installed in
  the dev container at the time of writing; these tests are scaffolded for a
  future run and skip cleanly..."*
- `tests/e2e/due-date-reminder-settings.spec.ts:17-19`: same pattern, same
  admission.

Every one of these specs has at least one `test.skip(count === 0, '…not
present/reachable…')` guard (24 occurrences total across the four files:
3 in kanban-board, 7 in task-collaboration, 10 in label-management, 4 in
due-date-reminder-settings) and
**none of them, nor `tests/e2e/global-setup.ts`, ever creates a project, task,
label, or admin setting**. `global-setup.ts:34-52` only ensures the webpack
bundle is built and does a real browser login — no API call or UI flow seeds
any planix data.

The shared CI job (`Conduction/.github/.github/workflows/quality.yml`,
referenced in `global-setup.ts:36-38`) runs `npm ci` + `npx playwright
install` — never `npm run build` and never anything that installs/seeds
planix — so on every CI run today these specs hit their `test.skip()` guards
and are reported **skipped, not failed**. Playwright/JUnit report a skip as
non-failing; the pipeline shows green. This is the textbook "phantom green"
failure mode: the 2026-05-24 kanban-board, 2026-06-14 label-management,
2026-06-14 task-collaboration, and 2026-06-14 due-date-reminder-dispatch
changes each claim Playwright e2e coverage (`gate-19 e2e-coverage`
annotations cite these exact spec files), and gate-19 is satisfied because
the `@e2e` tag points at a real file — but the gate does not (cannot,
mechanically) verify the referenced scenario actually *executed* an
assertion versus hitting a skip guard on every run.

This is compounded by (not caused by) the separate
`kanban-task-detail-keyboard-navigation` change: `task-collaboration.spec.ts`
also skips because there is no reachable link to `TaskDetail` at all today
(a real missing-feature bug, not just a fixture gap) — fixing that closes one
skip path but the seeding gap remains for all four files independent of it.

## What Changes

- Add a fixture-seeding step to `tests/e2e/global-setup.ts` (or a new
  `tests/e2e/fixtures/seed.ts` invoked from it) that, after login, calls the
  planix/OpenRegister REST API directly (matching the pattern
  `tests/integration/planix.postman_collection.json` already uses for
  Newman) to create: one project with the admin as a member, its default
  columns, at least one task with an assignee/due date/priority, at least
  one label attached to a task, and confirms the admin section is enabled —
  so every `test.skip((await X.count()) === 0, …)` guard in the four affected
  spec files evaluates to **not-skip** on a fresh CI run.
- Make seeding idempotent (check-then-create, or delete-and-recreate) so
  repeated CI runs against a persistent dev container don't accumulate
  duplicate fixture projects.
- Tighten the four specs' skip guards: keep the "app not installed" /
  "response >= 400" skip (a legitimate environment-absence signal) but remove
  or convert to a hard `expect(...).not.toHaveCount(0)` failure the
  "feature/board/section not present" guards that exist purely because no
  fixture was seeded — once seeding lands, those conditions should never be
  true, and a false `.count() === 0` after seeding is a real regression that
  must fail the suite, not skip it.
- Update `tests/e2e/kanban-board.spec.ts`, `task-collaboration.spec.ts`,
  `label-management.spec.ts`, `due-date-reminder-settings.spec.ts` docblocks
  to drop the "scaffolded for a future run" language once they exercise real
  assertions on every CI run.
- Document in each spec's header which `@e2e` scenarios now execute
  unconditionally (not gated behind environment happenstance).

**Not BREAKING**: test-only change; no application behavior changes.
