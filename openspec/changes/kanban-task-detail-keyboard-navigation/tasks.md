## 1. Card click/keyboard navigation to TaskDetail

- [x] 1.1 In `src/views/ProjectBoard.vue`, add a `navigateToTask(task)` method that calls `this.$router.push({ name: 'TaskDetail', params: { id: this.project?.id ?? this.$route.params.id, taskId: task.id } })`, mirroring `ProjectList` `navigateToProject`
- [x] 1.2 On the card wrapper, add `role="button"`, `tabindex="0"`, `:aria-label="task.title"`, `data-testid="task-card"`, `@click`, `@keydown.enter`, `@keydown.space.prevent` all invoking `navigateToTask(task)` — existing `draggable`/`@dragstart`/`@dragend` bindings unchanged. The `data-testid` satisfies `task-collaboration.spec.ts`'s `[data-testid="task-card"]` selector without changing the card to an `<a>`
- [x] 1.3 Add a `:focus-visible` outline in the card's scoped `<style>` (`var(--color-primary-element)`, no hardcoded color)

## 2. Keyboard-operable status change (drag alternative)

- [x] 2.1 Added a per-card "Move to…" control (`NcActions` with one `NcActionButton` per other column, `:force-menu="true"`) in the `ProjectBoard.vue` card wrapper, reachable by keyboard
- [x] 2.2 Wired its handler to `moveTask(task, status)` → shared `applyStatusMove` which calls the existing `updateTaskStatus` store action (the same method the drag path uses via the refactored `onDrop`) — optimistic update + rollback + RBAC identical between drag and keyboard
- [x] 2.3 The "Move to…" wrapper is `draggable="false"` with `@dragstart.stop`, `@click.stop`, `@keydown.enter/.space.stop` so it never starts a drag or navigates to detail

## 3. Spec + acceptance criteria

- [x] 3.1 In `openspec/specs/kanban-board.md`, checked off "Board is keyboard-navigable (WCAG AA)" (line 155)
- [~] 3.2 Merge the MODIFIED requirement from `specs/kanban-board/spec.md` into the canonical spec — DEFERRED to archive: per house rule, delta→canonical merge is the opsx-archive step (out of apply scope). The delta is authored and well-formed.

## 4. Verify

- [~] 4.1 Tab to a card, Enter/Space opens `TaskDetail` — NEEDS LIVE INSTANCE (no isolated planninq+OR container; no deploy to shared dev). Static proof: build + eslint clean; handler pushes the `TaskDetail` route.
- [~] 4.2 "Move to…" changes status identically to drag — NEEDS LIVE INSTANCE. Code proof: both paths call the single `applyStatusMove` → `updateTaskStatus`.
- [~] 4.3 Drag-and-drop still works unchanged — NEEDS LIVE INSTANCE. Code proof: `onDrop` refactored to delegate to `applyStatusMove` with identical semantics; drag bindings untouched.
- [~] 4.4 `openspec validate kanban-task-detail-keyboard-navigation --strict` — DEFERRED: openspec CLI not installed in this worktree.
- [x] 4.5 Companion note: `navigateToTask` + `data-testid="task-card"` now give `task-collaboration.spec.ts`'s `taskLink` selector a real target; that spec's fixture-absence guards were converted to assertions in the e2e-seed change.
