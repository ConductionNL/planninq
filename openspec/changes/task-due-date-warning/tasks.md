# Tasks: Task Due Date Warning

## Tasks

- [x] Add `dueDateStatus` computed helper to `src/utils/taskHelpers.js` (or similar) that takes a task object and returns `null | "approaching" | "overdue"` based on `dueDate` vs today — implemented at `src/utils/taskHelpers.js` (pure function, accepts ISO string / Date / null, optional clock injection for testability).
- [~] Add due date badge to `TaskCard.vue` — show a colored chip/badge based on `dueDateStatus`:
  - Yellow chip "Due soon" when approaching (within 2 days)
  - Red chip "Overdue" when past due
  - No chip otherwise

  *Deferred:* `TaskCard.vue` does not exist yet — the kanban board view (`src/views/ProjectBoard.vue`) is an `NcEmptyContent` placeholder ("Board view coming soon") and the Tasks CRUD/board card components have not yet been built (`ProjectBacklog.vue:42` explicitly notes "tasks#REQ-Task-CRUD lands [later]"). The helper is ready and the badge can be wired in a single Vue computed call once the card component lands.
- [~] Add CSS styling for the two badge states using Nextcloud theming variables — *deferred* with the badge integration above; will use `var(--color-warning)` (approaching) and `var(--color-error)` (overdue) on `NcChip` once `TaskCard.vue` exists.
- [~] Add unit test for `dueDateStatus` helper covering: no due date, future date (>2 days), approaching date (1-2 days), today, past date — *deferred:* planix ships no JS unit-test harness today (`package.json` declares `lint` / `stylelint` / `test:e2e` only; no `jest` / `vitest` / `mocha` dev-dependency or script). The helper is small, pure, and uses an injectable clock so it is straightforward to test once a JS test runner is introduced (cross-cutting concern, deferred to the fleet-level Vitest rollout).
