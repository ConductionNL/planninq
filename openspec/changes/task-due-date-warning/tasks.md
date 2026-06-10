# Tasks: Task Due Date Warning

## Tasks

- [~] Add `dueDateStatus` computed helper to `src/utils/taskHelpers.js` (or similar) that takes a task object and returns `null | "approaching" | "overdue"` based on `dueDate` vs today — deferred to downstream cycle (handoff)
- [~] Add due date badge to `TaskCard.vue` — show a colored chip/badge based on `dueDateStatus`: — deferred to downstream cycle (handoff)
  - Yellow chip "Due soon" when approaching (within 2 days)
  - Red chip "Overdue" when past due
  - No chip otherwise
- [~] Add CSS styling for the two badge states using Nextcloud theming variables — deferred to downstream cycle (handoff)
- [~] Add unit test for `dueDateStatus` helper covering: no due date, future date (>2 days), approaching date (1-2 days), today, past date — deferred to downstream cycle (handoff)
