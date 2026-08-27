# Tasks: Implement Time Tracking MVP

## 1. Duration parsing utility

- [x] 1.1 Create `src/utils/durationParser.js`: `parseDuration(raw): number|null`
      accepting `"2h 30m"`, `"150m"`, `"1.5h"`, `"90"`, `"2h"`; returns `null`
      on unparseable/zero/negative input.
- [x] 1.2 `formatDuration(minutes): string` — human-readable round-trip
      (e.g. `90 → "1h 30m"`).
- [x] 1.3 Unit tests for both directions, including the invalid-input cases
      from the spec (`"lots"`, `"-5"`, `"0"`) — `tests/vitest/durationParser.spec.js` (24 tests, all green).

## 2. TimeEntry store

- [x] 2.1 Create `src/store/timeEntries.js` using the shared nextcloud-vue
      objectStore (same pattern as `src/store/projects.js`) against the
      `timeEntry` schema.
- [x] 2.2 Expose `fetchForTask(taskId)`, `fetchForCurrentUser()`, `create`,
      `update`, `delete`, plus a `canModify(entry)` owner/admin guard and a
      `totalMinutes` getter.

## 3. Task detail — estimate + log time + progress

- [x] 3.1 Estimate input in `src/views/TaskDetail.vue`, wired to
      `parseDuration`/`formatDuration`; inline validation error on invalid
      input; persists `estimatedDuration` via the new `projectsStore.updateTask` PATCH.
- [x] 3.2 "Log time" action opening `src/components/dialogs/TimeEntryDialog.vue`
      (new) — duration + date + optional description.
- [x] 3.3 Progress indicator ("1h 30m / 3h") on the task detail; red +
      overage label when logged > estimate.
- [x] 3.4 Estimate badge on the task card (`src/components/TaskCard.vue`,
      rendered by both `ProjectBoard.vue` and `ProjectBacklog.vue`).
- [x] 3.5 Edit/delete controls on a time entry render only for the owning
      user or an admin (`canModify` gates the `NcActions` menu) — client-side
      UX match for the existing schema RBAC.

## 4. Timesheet view

- [x] 4.1 Create `src/views/Timesheet.vue`: current user's entries grouped by
      date (newest first), each row: task title, project, duration, description.
- [x] 4.2 Daily total per date group; range total for the current filter.
- [x] 4.3 Date-range filter (default "This week"; presets last-week/all/custom).
- [x] 4.4 Click-through to task detail; the active filter is mirrored into the
      URL query so the browser back button returns to the same range. NOTE:
      exact scroll-position restoration is left to the browser's native
      history scroll restoration (best-effort); filter/range restoration is
      explicit via the query params.

## 5. Navigation

- [x] 5.1 Added a "Timesheet" entry to the live `MainMenu.vue` + a `/timesheet`
      route in `src/router/index.js`. (The manifest `menu[]` path applies only
      after `adopt-cnapproot-manifest-shell` lands, which it has not.)

## 6. Verification

- [~] 6.1 e2e (set estimate, log/edit/delete entries, timesheet, filter,
      navigate + back) — NEEDS LIVE INSTANCE (no isolated planninq+OR container;
      no deploy to shared dev). Component/store/util logic unit-tested; build green.
- [~] 6.2 e2e non-owner cannot edit/delete + direct API 403 — NEEDS LIVE
      INSTANCE. UI side proven: `canModify` gates the controls (owner/admin);
      the 403 is enforced by the existing schema RBAC (`match: { user: "$userId" }`),
      not touched here.
- [x] 6.3 Existing unit/vitest suite green (84 tests) + new suites cover
      `durationParser.js` (24) and timesheet grouping/totals/range (7).

## 7. Quality gates

- [x] 7.1 `npm run lint` (eslint src) — 0 errors on all changed files
      (fixed the pre-existing `Object<…>` jsdoc warning in ProjectBoard.vue too).
- [~] 7.2 18 hydra gates / gate-19 e2e-coverage for the new time-tracking
      Scenarios — DEFERRED: hydra gate runner not invoked in this worktree
      (diff-scoped, run in CI). No PHP/controller changes in this change, so
      the PHP-side gates are non-applicable; the e2e-coverage for the new
      Scenarios is the live-instance gap noted in 6.1/6.2.
