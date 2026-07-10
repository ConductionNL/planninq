# Tasks: Implement Time Tracking MVP

## 1. Duration parsing utility

- [ ] 1.1 Create `src/utils/durationParser.js`: `parseDuration(raw): number|null`
      accepting `"2h 30m"`, `"150m"`, `"1.5h"`, `"90"`, `"2h"`; returns `null`
      on unparseable/zero/negative input.
- [ ] 1.2 `formatDuration(minutes): string` — human-readable round-trip
      (e.g. `90 → "1h 30m"`).
- [ ] 1.3 Unit tests for both directions, including the invalid-input cases
      from the spec (`"lots"`, `"-5"`, `"0"`).

## 2. TimeEntry store

- [ ] 2.1 Create `src/store/timeEntries.js` using the existing
      `createObjectStore` pattern (see `src/store/projects.js`) against the
      `timeEntry` schema.
- [ ] 2.2 Expose `entriesForTask(taskId)`, `entriesForUser(userId, range)`,
      `create`, `update`, `delete`.

## 3. Task detail — estimate + log time + progress

- [ ] 3.1 Add an estimate input to `src/views/TaskDetail.vue`, wired to
      `parseDuration`/`formatDuration`; inline validation error on invalid
      input; persists `estimatedDuration` via the existing task store.
- [ ] 3.2 Add a "Log time" action opening
      `src/components/dialogs/TimeEntryDialog.vue` (new) — duration + date +
      optional description.
- [ ] 3.3 Add a progress indicator ("1h 30m / 3h") on the task detail;
      red + overage label when logged > estimate.
- [ ] 3.4 Show the estimate badge on the task card in `ProjectBoard.vue` /
      `ProjectBacklog.vue`.
- [ ] 3.5 Edit/delete controls on a time entry render only for the owning
      user or an admin (client-side UX match for the existing schema RBAC).

## 4. Timesheet view

- [ ] 4.1 Create `src/views/Timesheet.vue`: entries for the current user
      grouped by date (newest first), each row: task title, project,
      duration, description.
- [ ] 4.2 Daily total per date group; weekly total for the current filter.
- [ ] 4.3 Date-range filter (default "This week"; custom range).
- [ ] 4.4 Click-through to task detail; back-navigation restores scroll
      position and the active date filter.

## 5. Navigation

- [ ] 5.1 Add a "Timesheet" entry to whatever navigation component is live
      at merge time (current `MainMenu.vue`, or the manifest `menu[]` if
      `adopt-cnapproot-manifest-shell` has already landed).

## 6. Verification

- [ ] 6.1 e2e: set estimate, log multiple entries, edit, delete, view
      timesheet, filter by range, navigate to task and back.
- [ ] 6.2 e2e: non-owner cannot edit/delete another user's entry (UI hides
      controls) and a direct API call is rejected 403 by existing RBAC.
- [ ] 6.3 Existing unit/vitest suite green; new suite covers
      `durationParser.js` and the timesheet grouping/totals logic.

## 7. Quality gates

- [ ] 7.1 `npm run lint` green.
- [ ] 7.2 18 hydra gates green; gate-19 e2e-coverage green for every new
      Scenario in `specs/time-tracking/spec.md`.
