---
kind: code
---

# Proposal: Kanban Cards Need a Click/Keyboard Path to Task Detail (Today There Is None)

## Why

`openspec/specs/kanban-board.md:155` already carries an unchecked acceptance
criterion — **"Board is keyboard-navigable (WCAG AA)"** — dating back to the
original kanban-board implementation. No change since has closed it, and
ADR-010 (`hydra/openspec/architecture/adr-010-nl-design.md`) states
company-wide: *"WCAG AA mandatory: keyboard-navigable... color not sole
conveyor"*. Verified against `HEAD`, the gap is worse than "not
keyboard-navigable" — **the task card has no interaction path to task detail
at all, mouse or keyboard**:

- `src/views/ProjectBoard.vue:88-97` renders each card as a plain `<div>`
  with `draggable="true"` plus `@dragstart`/`@dragend` only. There is no
  `@click`, no `role`, no `tabindex`, no `@keydown` handler anywhere on the
  card or on `TaskCard.vue` (`src/components/TaskCard.vue:1-42`, a pure
  display component with zero event bindings).
- The `TaskDetail` route is registered
  (`src/router/index.js:29-32`, path `/projects/:id/tasks/:taskId`) and
  `src/views/TaskDetail.vue` is fully built (collaboration sidebar with
  comments/files/audit-trail tabs), but:

  ```
  $ grep -rn "TaskDetail" src/
  src/store/projects.js:172:   * Used by the task detail view (TaskDetail.vue) ...
  src/views/TaskDetail.vue:89:  name: 'TaskDetail',
  src/router/index.js:31:      name: 'TaskDetail',
  src/router/index.js:32:      component: () => import('../views/TaskDetail.vue'),
  ```

  No file calls `$router.push({ name: 'TaskDetail', ... })` or otherwise
  builds a link to that route. `ProjectBoard.vue` (the kanban board) and
  `ProjectBacklog.vue` (the backlog list) both navigate elsewhere
  (`Projects`, `ProjectBoard`) but never to `TaskDetail`. **The only way to
  reach a task's detail page is typing the URL directly** — it is
  unreachable from any rendered UI element.
- The *only* board interaction that exists — dragging a card between
  columns to change status (`onDragStart`/`onDrop`, `ProjectBoard.vue:290-360`,
  calling `updateTaskStatus`) — is native HTML5 drag-and-drop with no
  keyboard equivalent, so even the one thing the board supports today is
  mouse/pointer-only.

This is also the root cause of the always-skipping
`tests/e2e/task-collaboration.spec.ts` (see the companion change
`e2e-seed-fixtures-for-real-assertions`): its guard
`test.skip((await taskLink.count()) === 0, 'No task detail surface reachable
in this environment')` trips on every run precisely because no `taskLink`
selector exists anywhere — not a fixture-data problem, a missing-feature
problem.

## What Changes

- Make each kanban card (`ProjectBoard.vue` card wrapper +
  `TaskCard.vue`) a focusable, activatable control: `role="button"`,
  `tabindex="0"`, `:aria-label` naming the task title, `@click` and
  `@keydown.enter`/`@keydown.space` all invoking the same
  `navigateToTask(task)` handler that pushes
  `{ name: 'TaskDetail', params: { id: project.id, taskId: task.id } }` —
  mirroring the existing `navigateToProject` pattern in `ProjectList.vue:265`.
- Add a keyboard-operable status-change control as the accessible
  equivalent to drag-and-drop: a small per-card "Move to…" affordance
  (e.g. an `NcActions`/menu listing the other columns) that calls the
  existing `updateTaskStatus(taskId, status)` action
  (`src/store/projects.js:697`) — the same store method the drag handler
  already uses, so behavior (optimistic update + rollback, RBAC) is
  identical between drag and keyboard paths.
- Preserve existing drag-and-drop behavior unchanged; the click/keyboard
  path is additive, not a replacement.
- Update `openspec/specs/kanban-board.md`: check off the "Board is
  keyboard-navigable (WCAG AA)" acceptance criterion once implemented, and
  add the MODIFIED requirement below with explicit scenarios.

**Not BREAKING**: additive interaction paths; no existing behavior removed.
