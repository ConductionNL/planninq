---
kind: code
---

# Design: Week View on the Planning Board

## Context

The board is one view, `src/views/ProjectBoard.vue`, mounted by the manifest page
`ProjectBoard` (`src/manifest.json`, `type: "custom"`, route `/projects/:id`) and
resolved through `src/registry.js`. It already renders one lane per task status and
already owns drag-and-drop, the label filter, the access guard and the card markup. The
week view is a second grouping axis over the same fetched task collection, so it belongs
inside that view as a view-mode toggle, not as a new page.

## Decisions

### The week view is a third value of the existing view toggle

`openspec/specs/kanban-board.md` already declares a kanban-to-list toggle with URL
persistence. The week mode is a third value of that requirement. A separate week page
would be a second toggle mechanism and a second place to keep the filter, the access
guard and the card markup in step.

### The day is `task.dueDate`

`dueDate` (`format: date`) is already the day for the due-date badge (`dueDateStatus` in
`src/utils/taskHelpers.js`), for the timeline bar (`TimelineController::timelineRow()`,
`toScheduled`) and for the portfolio overdue count (`summariseProjectTasks`). A second
date field would fork the meaning of "when is this task" across four surfaces and would
need a register version bump and a migration. `startDate` is not used as the day.

### The write path is the existing task update

`src/store/projects.js` has `updateTask(taskId, patch)`, a PATCH to
`/apps/openregister/api/objects/planninq/task/{id}`. Moving a task to another day is
`updateTask(task.id, { dueDate })`. No new endpoint, controller or store action. The
register slug stays `planninq`; the app id `planninq` is not interchangeable with it.

### The read path is the existing task fetch

`fetchTasks(projectId)` reads the whole collection through `fetchEvery`, so all seven
days' tasks are already in the view's task list. The week view does not fetch anything
of its own.

### Week bounds and day arithmetic are reused

`currentWeekRange()` from `src/utils/timesheetHelpers.js` is explicitly Monday to Sunday
and returns local `YYYY-MM-DD` dates, avoiding the `toISOString` day shift.
`MS_PER_DAY` and `parseDay()` from `src/utils/timelineHelpers.js` do the day arithmetic.
`src/views/Timesheet.vue` is already a Monday-to-Sunday week view over
`plannedTimeEntry`; the board must not grow a second week calculation beside it.

### The grouping helper is pure

`groupTasksByDay(tasks, weekStart)` goes next to `groupTasksByStatus` and
`BOARD_STATUSES` in `src/utils/taskHelpers.js`, takes the week start as an argument and
touches no store, so it is testable the way `tests/vitest/boardGrouping.spec.js` tests
the status grouping. If it grows past roughly forty lines it moves to a sibling
`src/utils/weekHelpers.js`.

### The drag reuses the existing idiom

`ProjectBoard.vue` uses native HTML5 `draggable` with `@dragstart`/`@dragover`/`@drop`,
an optimistic update and a rollback. The day drop calls the same shape of
optimistic-then-revert path, so a failed write puts the card back and tells the user.

### Tasks without a day get an "Unscheduled" area

A task with no `dueDate` has no day and would vanish from a seven-column week. The
timeline solved this with an "unscheduled" rail (`TimelineController::forProject`
returns `unscheduled` separately); the week view follows that precedent and shows such
tasks in an "Unscheduled" area beside the seven columns, from which they can be dragged
onto a day.

### The highlight is a wrapper class plus a non-colour mark

The existing deep-link highlight is a wrapper class on `.kanban-column__card`
(`kanban-column__card--highlight`). The current-user mark follows that pattern, with a
text or icon mark added so the mark is perceivable without colour (WCAG 1.4.1). The
existing `isHighlighted(task)` predicate for the `?task=` deep link is not shadowed; the
current-user predicate is a separate function.

## Risks and accepted consequences

- **`dueDate` is read by three other surfaces.** Writing it on drop changes the "Due
  soon"/"Overdue" badge, the portfolio overdue count and the timeline bar position. That
  is intended, but a drag is not a private board action.
- **`dueDate` drives a notification.** The `task` schema carries
  `x-openregister-notifications.taskDueSoon` with a `dueDate withinNext PT24H` filter, so
  dragging a task onto tomorrow can fire a notification to the assignee. This change
  accepts that behaviour and does not suppress it; suppressing it would be a separate
  product decision.
- **Native HTML5 drag does not fire on touch.** The existing column drag has the same
  limitation. Touch drag is out of scope for this change and would be a change to the
  whole board.
- **The register slug `planninq` is frozen in data.** Any new literal uses it, not the
  app id.

## Rejected alternatives

- **A new `src/views/ProjectWeek.vue` page, a `ProjectWeek` entry in
  `src/manifest.json` and `src/registry.js`, and a `GET /api/projects/{id}/week`
  controller in `lib/Controller/`.** The board already fetches the entire task
  collection client-side, `updateTask` already PATCHes arbitrary fields, and the spec
  already declares a URL-persisted view toggle. A new route would re-implement the
  fetch, the `accessDenied` guard, the label filter and the card rendering, and the
  controller would be a pass-through to `ObjectService` — dead code the moment the
  frontend uses the shared store. It would also add a second week calculation beside
  `currentWeekRange`.
- **A `day` or `plannedDate` field on the `task` schema.** `dueDate` already is the day
  for the badge, the timeline and the portfolio count. A second date field would fork
  the meaning of "when is this task" across four surfaces and need a register version
  bump plus a migration.

## Open questions carried into review

1. Which field is "the day" when a task has only `startDate` and no `dueDate`? This
   change anchors on `dueDate`; such a task lands in "Unscheduled".
2. Must drag work on touch? Out of scope here; the issue does not state it.
3. Should a day drag be allowed to fire the `taskDueSoon` notification? Accepted as-is
   in this change.
4. Pre-existing and out of scope: `openspec/specs/kanban-board.md` describes the drop as
   writing `column`/`columnOrder` while `ProjectBoard.vue` writes `status`. The spec is
   stale relative to the code.
