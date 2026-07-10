# Test / e2e Reality — Fixture Seeding

**Spec refs**: ADR-008 (testing, hydra/openspec/architecture) — "every spec scenario → browser test (GIVEN/WHEN/THEN verified via Playwright)"; ADR-020 companion note on gate-19 e2e-coverage (`@e2e` tag existence is mechanical, not a proof of execution)

## ADDED Requirements

### Requirement: e2e specs execute real assertions on a fresh environment

Playwright specs under `tests/e2e/` that depend on planix application data (projects, tasks, labels, admin settings) MUST NOT rely on that data having
been created manually or by a prior, unrelated test run. `global-setup.ts`
MUST seed the minimum fixture data (one project with the admin as a member
and its default columns, at least two tasks spanning due-date states, at
least one label attached to a task, admin due-date-reminder defaults
confirmed) idempotently before any spec runs, so that a fresh CI environment
(a Nextcloud instance with planix newly installed and nothing else) produces
the same assertions as a long-lived dev container.

A `test.skip()` guard on one of these specs MUST be reserved for a genuine
environment-absence signal (the app is not installed, or an optional
dependency app like Activity is absent) — never for "the feature/fixture I
need to assert against doesn't exist yet," which after seeding is a real
regression and MUST fail the test, not skip it.

**Feature tier**: MVP

#### Scenario: Fresh CI environment exercises the kanban board badge scenarios

- GIVEN a Nextcloud instance with planix newly installed and no manually
  created data
- WHEN `tests/e2e/kanban-board.spec.ts` runs
- THEN `global-setup.ts` MUST have seeded a project with tasks in
  "approaching" and "overdue" due-date states
- AND the spec MUST assert the yellow/red badges are shown, not skip because
  no board or cards were reachable

#### Scenario: Fresh CI environment exercises task collaboration

- GIVEN the same fresh environment
- WHEN `tests/e2e/task-collaboration.spec.ts` runs
- THEN a reachable task link (via the keyboard/click navigation added in
  `kanban-task-detail-keyboard-navigation`, present because a task was
  seeded) MUST let the spec open task detail and assert against the
  comments/files/audit-trail tabs, not skip

#### Scenario: Fresh CI environment exercises label management

- GIVEN the same fresh environment
- WHEN `tests/e2e/label-management.spec.ts` runs
- THEN a seeded label attached to a seeded task MUST let the spec assert
  usage counts, creation, rename/recolor propagation, and deletion, not skip

#### Scenario: A regression after seeding fails the suite, not skips it

- GIVEN fixtures are seeded per the above
- WHEN a feature regresses such that a previously-reachable element (board,
  task link, label section) is no longer present
- THEN the spec MUST fail (hard `expect`), not silently skip via a
  `test.skip((await X.count()) === 0, …)` guard
