## 1. Card click/keyboard navigation to TaskDetail

- [ ] 1.1 In `src/views/ProjectBoard.vue`, add a `navigateToTask(task)` method that calls `this.$router.push({ name: 'TaskDetail', params: { id: this.project.id, taskId: task.id } })`, mirroring `ProjectList.vue:265` `navigateToProject`
- [ ] 1.2 On the card wrapper (`ProjectBoard.vue:88-97`), add `role="button"`, `tabindex="0"`, `:aria-label="task.title"`, `data-testid="task-card"`, `@click="navigateToTask(task)"`, `@keydown.enter="navigateToTask(task)"`, `@keydown.space.prevent="navigateToTask(task)"` — keep the existing `draggable`/`@dragstart`/`@dragend` bindings unchanged. Note: `tests/e2e/task-collaboration.spec.ts:42` already looks for `a[href*="/tasks/"], [data-testid="task-card"]` — the `data-testid` satisfies that selector without needing to change the card to an `<a>` element (which would conflict with `draggable`)
- [ ] 1.3 Add a visible `:focus-visible` outline in the card's scoped `<style>` (CSS var, no hardcoded color, per CLAUDE.md/ADR-010) so keyboard focus is perceivable

## 2. Keyboard-operable status change (drag alternative)

- [ ] 2.1 Add a per-card "Move to…" control (e.g. `NcActions` with one `NcActionButton` per other column) to `TaskCard.vue` or the `ProjectBoard.vue` card wrapper, reachable by keyboard (native NC component semantics)
- [ ] 2.2 Wire its selection handler to call the existing `updateTaskStatus(taskId, status)` action in `src/store/projects.js:697` — the same method the drag-and-drop path already calls — so optimistic update + rollback + RBAC behavior is identical between drag and keyboard
- [ ] 2.3 Ensure the "Move to…" trigger does not itself become draggable (it is a click/keyboard-only control nested inside the draggable card)

## 3. Spec + acceptance criteria

- [ ] 3.1 In `openspec/specs/kanban-board.md`, check off the existing "- [ ] Board is keyboard-navigable (WCAG AA)" acceptance criterion (line 155)
- [ ] 3.2 Add the MODIFIED requirement in `specs/kanban-board/spec.md` (this change) to the canonical spec

## 4. Verify

- [ ] 4.1 Tab to a card without a mouse; confirm Enter/Space opens `TaskDetail` at `/projects/:id/tasks/:taskId`
- [ ] 4.2 Tab to a card's "Move to…" control; confirm selecting a column updates the task's status identically to a drag-and-drop move (check via `NcChip` status label update)
- [ ] 4.3 Confirm existing drag-and-drop still works unchanged (regression check)
- [ ] 4.4 `openspec validate kanban-task-detail-keyboard-navigation --strict` passes
- [ ] 4.5 Note for the companion change `e2e-seed-fixtures-for-real-assertions`: once `navigateToTask` exists, `tests/e2e/task-collaboration.spec.ts`'s `taskLink` selector guard can find a real link — re-verify that spec's skip conditions after this change lands
