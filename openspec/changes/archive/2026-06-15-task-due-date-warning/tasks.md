# Tasks: Task Due Date Warning

## Tasks

- [x] Add `dueDateStatus` computed helper to `src/utils/taskHelpers.js` (or similar) that takes a task object and returns `null | "approaching" | "overdue"` based on `dueDate` vs today — implemented at `src/utils/taskHelpers.js` (pure function, accepts ISO string / Date / null, optional clock injection for testability).
- [x] Add due date badge to `TaskCard.vue` — shows a colored chip/badge based on `dueDateStatus`:
  - Yellow chip "Due soon" when approaching (within 2 days)
  - Red chip "Overdue" when past due
  - No chip otherwise

  *Done (build/kanban-board-2026-06-15):* `src/components/TaskCard.vue` now renders the badge via an `NcChip` (`warning` for approaching, `error` for overdue) and is rendered inside the real kanban board (`src/views/ProjectBoard.vue`, columns by task status). The previously-deferred board host now exists, so the badge is visible.
- [x] Add CSS styling for the two badge states using Nextcloud theming variables — *done:* the `NcChip` `warning` / `error` types map to the Nextcloud theme colours; the card and column layout use `var(--color-*)` theme variables throughout (`TaskCard.vue`, `ProjectBoard.vue`).
- [x] Add unit test for `dueDateStatus` helper covering: no due date, future date (>2 days), approaching date (1-2 days), today, past date — *done:* `tests/vitest/dueDateStatus.spec.js` covers every boundary with an injected clock (17 cases), plus `tests/vitest/boardGrouping.spec.js` for the board grouping. The vitest harness is now present in planix.
