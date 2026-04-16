# Proposal: Task Due Date Warning

## Summary

Add visual warning badges to task cards on the kanban board and task list, indicating when tasks are approaching their due date (within 2 days) or overdue. This gives users immediate visual feedback about deadline urgency without opening individual tasks.

## Motivation

Planix already stores `dueDate` on tasks (iCalendar VTODO `DUE` field), but the kanban board and task list provide no visual cue when a deadline is near or missed. Users must open each task to check its due date, making it easy to miss deadlines — especially on busy boards with many cards. A color-coded badge ("Due soon" / "Overdue") on the task card solves this at a glance, matching the MVP feature "Overdue task highlight (red border/badge on card)" from FEATURES.md.

## Affected Projects

- [x] Project: `planix` — Frontend-only: add due date status helper and badge component to TaskCard.vue

## Scope

### In Scope

- Computed `dueDateStatus` helper function returning `null`, `"approaching"`, or `"overdue"`
- Yellow "Due soon" badge on task cards when due date is within 2 days
- Red "Overdue" badge on task cards when due date is in the past
- CSS styling using Nextcloud theming variables
- Unit tests for the helper function

### Out of Scope

- Backend changes or API modifications — `dueDate` already exists on the task entity
- Notification system for due dates (separate MVP feature: `task_due_soon` notification)
- Configurable warning threshold (hardcoded at 2 days for now)
- Dashboard "Overdue task list" and "Tasks due this week" views (separate changes)

## Approach

Add a pure JavaScript helper (`dueDateStatus`) that compares a task's `dueDate` to the current date. Use this in `TaskCard.vue` to conditionally render an `NcChip` (or similar badge) from `@nextcloud/vue` with appropriate color and label. No new API calls, no schema changes, no backend work.

## New Dependencies

None

## Impact

- `TaskCard.vue` — gains a due date badge in the card layout
- New file `src/utils/taskHelpers.js` (or addition to existing utils) — `dueDateStatus` helper
- New unit test file for the helper

## Cross-Project Dependencies

None

## Risks

### Risk 1: Date comparison edge cases
**Severity:** Low — **Mitigation:** Unit tests cover all boundary conditions (today, tomorrow, 2 days out, yesterday, no due date). Use date-only comparison (ignore time component).

## Rollback Strategy

Revert the single commit. No data changes, no schema changes — purely additive frontend code.
