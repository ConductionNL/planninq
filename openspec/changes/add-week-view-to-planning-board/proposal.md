---
kind: code
---

# Proposal: A Week View with Drag and Drop and Current-User Highlighting on the Planning Board

## Why

The planning board (`src/views/ProjectBoard.vue`) shows a project's tasks grouped by
status, one lane per status. There is no Monday-to-Sunday view anywhere in the board,
so a planner who wants to know what a week looks like has to open each day separately
and loses the overview in the process. Tasks the current user is responsible for are
not marked in any week context, and moving a task to another day means opening the
target day first. The issue asks for a week view, a highlight for the current user's
tasks, and a drag from one day to another that survives a reload.

## What changes

- The board gains a third view mode, `week`, alongside `kanban` and `list`. The mode
  is chosen from the existing view toggle and is persisted in the URL, so a reload
  returns to the week view.
- The week view renders seven day columns, Monday through Sunday, for one week, and
  places every task of the project in the column of its day. Tasks without a day are
  shown in a separate "Unscheduled" rail rather than being dropped.
- Every task whose `assignedTo` is the current Nextcloud user is visually marked, with
  a mark that is not colour alone.
- A task card can be dragged from one day column and dropped on another. The drop
  writes the new day to the task's `dueDate` through the existing task update path,
  optimistically, and reverts the card if the write fails.
- The change is visible in the day view too: after a drag, opening the target day shows
  the task there.

## Out of scope

- Touch and pen drag. The board's existing column drag is native HTML5 drag-and-drop,
  which does not fire on touch. Making drag work on touch is a change to the whole
  board, not to the week view, and is not part of this change.
- A new page, route, manifest entry or backend endpoint. The board already fetches the
  whole task collection and already has a task update action; the week view is a second
  grouping of the same collection.
- A second date field on the task schema. `dueDate` is already the day for the due-date
  badge, the timeline and the portfolio count.
- Week navigation beyond the current week (previous/next week, date picker). The first
  version shows the current week.
- Changing the `taskDueSoon` notification behaviour. A drag writes `dueDate`, so it can
  fire a due-soon notification; that is accepted and documented in `design.md`, not
  changed here.
- Repairing the stale statement in `openspec/specs/kanban-board.md` that a drop writes
  `column`/`columnOrder` while the code writes `status`. Pre-existing, tracked
  separately.

## Impact

- App: `planninq`. Capability: `kanban-board` (existing spec, `openspec/specs/kanban-board.md`).
- Files touched: `src/views/ProjectBoard.vue` (view mode, week render, day drop,
  highlight), `src/utils/taskHelpers.js` (pure day-grouping helper), and tests under
  `tests/vitest/` and `tests/e2e/kanban-board.spec.ts`.
- Reused, not reimplemented: `currentWeekRange()` from `src/utils/timesheetHelpers.js`
  for the Monday-to-Sunday bounds, `MS_PER_DAY`/`parseDay()` from
  `src/utils/timelineHelpers.js` for day arithmetic, `updateTask(taskId, patch)` from
  `src/store/projects.js` for the write, and `getCurrentUser` from `@nextcloud/auth`
  for the highlight.
- No schema change, no register version bump, no migration. The register slug stays
  `planninq`.
- Behaviour change outside the board: writing `dueDate` also moves the due-date badge,
  the portfolio overdue count and the timeline bar. That is intended, but a drag is not
  a private board action.
- Not breaking: the kanban and list views keep their current behaviour.
