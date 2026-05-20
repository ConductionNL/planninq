# Tasks: Kanban Board

## 0. Deduplication Check

- [ ] 0.1 Search `openspec/specs/` and existing Vue components for any prior kanban board, column entity, or view-toggle implementation — document findings (expected: none)
- [ ] 0.2 Confirm `createObjectStore`, `CnEmptyState`, `CnDataTable`, `CnFormDialog`, `CnStatusBadge`, `CnDetailPage` are used instead of custom equivalents for each applicable surface

## 1. Column Schema — OpenRegister Register Template

- [ ] 1.1 Add `Column` schema to `lib/Settings/planix_register.json` with properties: `title` (string, required), `project` (OpenRegister relation, required), `order` (integer, required, default 0), `wipLimit` (integer|null, default null), `color` (string hex, optional), `type` (enum: active|done, default active)
- [ ] 1.2 Patch the existing `Task` schema in `planix_register.json` to add optional properties: `column` (OpenRegister relation → Column) and `columnOrder` (integer)
- [ ] 1.3 Add 5 seed Column objects to `planix_register.json` using `@self` envelope with Dutch values: "Backlog", "In uitvoering" (wipLimit: 3), "Review" (wipLimit: 2), "Gereed" (type: done), "In progress" — using realistic Dutch project slugs
- [ ] 1.4 Verify schema import is idempotent: re-import with `force: false` must not create duplicate Column or Task schema entries (slug matching)

## 2. Column Pinia Store

- [ ] 2.1 Create `src/store/modules/columns.js` using `createObjectStore('column')` — no hand-rolled CRUD
- [ ] 2.2 Register the column store in `src/store/store.js` via `objectStore.registerObjectType('column', 'column', 'planix')` — exactly once, kebab-case slug
- [ ] 2.3 Verify every `await columnStore.action()` call in consuming components is wrapped in `try/catch` with `showError` user feedback

## 3. KanbanCard Component

- [ ] 3.1 Create `src/components/kanban/KanbanCard.vue` — displays: title, assignee avatar (NC user avatar via `NcAvatar`), due date (red if overdue using `var(--color-error)`), priority dot, label chips (`CnStatusBadge` for status)
- [ ] 3.2 Card emits `@click` → parent navigates to task detail route; card emits `@drag-start` / `@drag-end` events for the column drop zone
- [ ] 3.3 Add SPDX header `<!-- SPDX-License-Identifier: EUPL-1.2 -->` as first line
- [ ] 3.4 All user-visible strings translated via `this.t('planix', '...')` — no hardcoded Dutch or English strings in template

## 4. KanbanColumn Component

- [ ] 4.1 Create `src/components/kanban/KanbanColumn.vue` — renders column title, task count badge, WIP limit indicator, and a `vue-draggable` drop zone containing `KanbanCard` instances
- [ ] 4.2 WIP limit violation: when `tasks.length >= wipLimit && wipLimit !== null`, apply `var(--color-error)` to the column header and show ARIA-labelled tooltip "WIP-limiet (N) overschreden"
- [ ] 4.3 Empty column shows `CnEmptyState` with "+ Taak toevoegen" button; clicking emits `@add-task` event with the column id so the parent pre-selects it in `CnFormDialog`
- [ ] 4.4 Add SPDX header; translate all strings

## 5. BoardToolbar Component

- [ ] 5.1 Create `src/components/kanban/BoardToolbar.vue` — view toggle buttons (kanban | list) and filter controls (assignee, label, priority dropdowns)
- [ ] 5.2 View toggle writes `#view=kanban` or `#view=list` to the URL hash on click (use `this.$router.replace` with hash); active button is visually indicated
- [ ] 5.3 Filter changes write `#filter=<key>:<value>` to URL hash; multiple filters are comma-separated
- [ ] 5.4 On `created()`, read URL hash and emit initial view + filter values to the parent via `@view-change` and `@filter-change` events
- [ ] 5.5 Add SPDX header; translate all strings; NEVER use `NcSelect` without `inputLabel` prop

## 6. KanbanBoardView Page

- [ ] 6.1 Create `src/views/KanbanBoardView.vue` — uses `vue-draggable` to render `KanbanColumn` components in `column.order` sequence; horizontally scrollable when columns exceed viewport
- [ ] 6.2 On mount: fetch columns for current project (`columnStore.findObjects({ project: projectId })`) and tasks (`taskStore.findObjects({ project: projectId })`); group tasks by `column.id`
- [ ] 6.3 Drag-and-drop handler: on card drop, optimistically update `task.column` and `task.columnOrder` in store, call `taskStore.saveObject(task)`, and on error revert + `showError`
- [ ] 6.4 Drag from backlog panel: accept external drag from backlog list; assign `task.column` to target column on drop
- [ ] 6.5 Empty board (no columns): show `CnEmptyState` "Nog geen kolommen"; show "Kolom toevoegen" button only to project creator/admin (check `isAdmin` from settings store)
- [ ] 6.6 Include `BoardToolbar` and handle `@view-change` (swap view) and `@filter-change` (apply in-place filter: dim/hide non-matching cards)
- [ ] 6.7 Add SPDX header; translate all strings; wrap all store calls in `try/catch`

## 7. ListViewPage

- [ ] 7.1 Create `src/views/ListViewPage.vue` — renders tasks for the current project as a `CnDataTable` with columns: title (router-link to task detail), assignee, due date, status, priority, labels
- [ ] 7.2 Include `BoardToolbar`; handle `@view-change` and `@filter-change` the same way as `KanbanBoardView`
- [ ] 7.3 Row click navigates to task detail route via `this.$router.push({ name: 'TaskDetail', params: { id: task.id } })`; browser back (Vue router history) returns to list view URL (hash preserved)
- [ ] 7.4 Active filter from URL hash is applied to `CnDataTable` on mount
- [ ] 7.5 Add SPDX header; translate all strings; wrap all store calls in `try/catch`

## 8. DeleteColumnDialog

- [ ] 8.1 Create `src/dialogs/DeleteColumnDialog.vue` (NcDialog-based, per ADR-004 modal isolation) — shows "Deze kolom bevat {N} taken. Wat wil je doen met deze taken?"
- [ ] 8.2 Dialog offers two radio options: "Naar backlog verplaatsen" and "Naar andere kolom verplaatsen" (with column picker `NcSelect` — use `inputLabel` prop)
- [ ] 8.3 On confirm with "Naar backlog verplaatsen": call `taskStore.saveObject` for each task clearing `column`; then call `columnStore.deleteObject` for the column
- [ ] 8.4 On confirm with "Naar andere kolom verplaatsen": call `taskStore.saveObject` for each task updating `column` to selected column; then delete the column
- [ ] 8.5 Column is NOT deleted until user confirms — "Annuleren" closes dialog with no changes
- [ ] 8.6 Add SPDX header; translate all strings; wrap all store calls in `try/catch` with `showError`

## 9. Router Integration

- [ ] 9.1 Add named route `KanbanBoard` → `KanbanBoardView.vue` at `/projects/:projectId/board` in `src/router/index.js`
- [ ] 9.2 Add named route `ListView` → `ListViewPage.vue` at `/projects/:projectId/list`
- [ ] 9.3 Ensure specific routes are registered BEFORE any wildcard `{slug}` routes (per ADR-003)
- [ ] 9.4 Add matching PHP routes in `appinfo/routes.php` for both paths

## 10. Column Management in Project Settings

- [ ] 10.1 Add a "Kolommen" section to the project settings view (or create `src/components/settings/ColumnSettings.vue`) — lists columns with reorder handle, edit, and delete actions
- [ ] 10.2 "Kolom toevoegen" opens `CnFormDialog` bound to the Column schema; on save calls `columnStore.saveObject`
- [ ] 10.3 Reorder: `vue-draggable` handles column order; on reorder, patch `order` on all affected columns via `columnStore.saveObject`
- [ ] 10.4 Delete: if column has tasks, open `DeleteColumnDialog`; if empty, use `CnDeleteDialog` directly
- [ ] 10.5 All settings mutations check `isAdmin` from settings store — non-admins MUST NOT see edit/delete controls

## 11. Accessibility and NL Design System Compliance

- [ ] 11.1 Verify board is keyboard-navigable: tab to cards, Enter/Space to open detail, Escape to cancel drag
- [ ] 11.2 WIP limit warning: confirm colour + text/icon combination (colour is NOT the sole indicator — WCAG 1.4.1)
- [ ] 11.3 All `NcSelect` instances in board/column components have `inputLabel` prop set
- [ ] 11.4 All interactive elements have associated accessible labels; run axe-core or similar in browser tests

## 12. SPDX and Pre-commit Verification

- [ ] 12.1 Verify SPDX header on every new file: `KanbanCard.vue`, `KanbanColumn.vue`, `BoardToolbar.vue`, `KanbanBoardView.vue`, `ListViewPage.vue`, `DeleteColumnDialog.vue`, `columns.js`
- [ ] 12.2 Verify zero `from '@nextcloud/vue'` imports — all must use `@conduction/nextcloud-vue`
- [ ] 12.3 Verify every `<NcFoo>` and `<CnFoo>` in templates is imported AND listed in `components: {}`
- [ ] 12.4 Verify every `await store.action()` call is in `try/catch` with user-facing error feedback
- [ ] 12.5 Verify no hardcoded strings — all user-visible text via `this.t('planix', '...')`
- [ ] 12.6 Run `npm run lint` and confirm zero errors before committing
