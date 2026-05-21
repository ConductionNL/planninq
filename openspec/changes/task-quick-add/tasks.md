# Tasks: Task Quick-Add

## Tasks

### Task 1: Render kanban columns in ProjectBoard.vue

**spec_ref**: `openspec/changes/task-quick-add/specs/kanban-board/spec.md` — Requirement: Column Layout Rendering [MVP]

**Description**:
Replace the "Board view coming soon" `NcEmptyContent` placeholder in `src/views/ProjectBoard.vue` with a real column layout. Fetch columns from OpenRegister filtered by `project.id`, render them in `order` sequence as a horizontal scrollable list. Show a "No columns yet" empty state when the project has no columns.

**acceptance_criteria**:
- [x] The "Board view coming soon" `NcEmptyContent` is removed from the template
- [x] `columns` computed property returns columns from `useObjectStore` filtered to the current project, sorted by `order`
- [x] Columns render as a horizontal `<div class="project-board__columns">` with one `.project-board__column` per column entry
- [x] Each column shows its `title` in a `.project-board__column-header`
- [x] When `columns.length === 0` and not loading, an `NcEmptyContent` renders with text "No columns yet"
- [x] A loading indicator (`NcLoadingIcon` or similar) is shown while `columnsLoading` is true

**files_likely_affected**:
- `src/views/ProjectBoard.vue`
- `src/store/store.js` (register column and task object types in `initializeStores()`)

**Notes**:
- Columns are fetched via `useObjectStore.fetchObjects('column', { register, schema, 'object.project': projectId })` pattern
- After columns are loaded, fetch tasks per-column: `useObjectStore.fetchObjects('task', { register, schema, 'object.project': projectId })` and group them into `columnTasks` by `task.column`
- The `register` and `schema` values for columns must come from `useSettingsStore`. Check `src/store/modules/settings.js` for whether `columnsRegister` / `columnsSchema` keys already exist; if not, add them with the correct values from the app's OpenRegister configuration
- `registerObjectType('column', schemaId, registerId)` must be called in `initializeStores()` in `src/store/store.js`
- Horizontal scroll on `.project-board__columns`: `display: flex; overflow-x: auto; gap: 12px`

---

### Task 2: Create QuickAddTask.vue component

**spec_ref**: `openspec/changes/task-quick-add/specs/kanban-board/spec.md` — Requirement: Quick-Add Task in Column [MVP]

**Description**:
Create `src/components/QuickAddTask.vue`. The component renders a "+ Add task" trigger button. On click, it expands to an inline `<textarea>` with Save and Cancel buttons. This task covers the static structure and toggle behaviour (no API call yet — wired in Task 4).

**acceptance_criteria**:
- [x] File `src/components/QuickAddTask.vue` exists with `name: 'QuickAddTask'`
- [x] Props: `columnId: String` (required), `projectId: String` (required)
- [x] Local state: `active: false`, `draft: ''`, `saving: false`, `errorMessage: ''`
- [x] When `active === false`: renders an `NcButton` with label "Add task" and a `PlusIcon`
- [x] When `active === true`: renders the expanded form with `<label>` (visually hidden), `<textarea>`, optional error span, Save button, Cancel button
- [x] `activate()` sets `active = true`, clears `errorMessage`, and focuses the textarea via `$nextTick`
- [x] `cancel()` sets `active = false`, clears `draft` and `errorMessage`, and returns focus to the trigger button
- [x] `NcButton`, `NcLoadingIcon` are imported and registered in `components: {}`
- [x] `PlusIcon` is imported from `vue-material-design-icons/Plus.vue`
- [x] All user-visible strings use `t('planix', '...')`

**files_likely_affected**:
- `src/components/QuickAddTask.vue` (new)

---

### Task 3: Wire keyboard handling (Enter / Escape)

**spec_ref**: `openspec/changes/task-quick-add/specs/kanban-board/spec.md` — Scenarios: Enter key creates the task, Escape key cancels, Shift+Enter inserts newline

**Description**:
Add `@keydown` event handlers to the `<textarea>` in `QuickAddTask.vue` for the three keyboard interactions.

**acceptance_criteria**:
- [x] `@keydown.enter.prevent` calls `handleEnter(event)`
- [x] `handleEnter` does nothing if `draft.trim()` is empty or `saving === true`
- [x] `handleEnter` calls `submit()` otherwise
- [x] `@keydown.esc` calls `cancel()`
- [x] A `@keydown.shift.enter` guard (or `if (event.shiftKey) return` inside `handleEnter`) allows the default textarea newline behaviour and does NOT call `submit()`
- [x] Keyboard shortcuts work the same whether the user reaches them via mouse or keyboard navigation

**files_likely_affected**:
- `src/components/QuickAddTask.vue`

**Notes**:
- In Vue 2, `@keydown.esc` is the correct modifier; add `if (event.shiftKey) return` as the first line of `handleEnter`

---

### Task 4: Connect to task creation store / API

**spec_ref**: `openspec/changes/task-quick-add/specs/kanban-board/spec.md` — Scenario: Enter key creates the task

**Description**:
Implement `submit()` in `QuickAddTask.vue`. Check whether `useObjectStore` exposes a `createObject(type, payload)` action; if not, add it. Wire the submit method to POST to OpenRegister using `@nextcloud/axios`.

**acceptance_criteria**:
- [x] `submit()` sets `saving = true` and clears `errorMessage` before the request
- [x] The API call uses `axios` from `@nextcloud/axios` (NEVER raw `fetch()`)
- [x] The request POSTs to `generateUrl('/apps/openregister/api/objects')` with body `{ register, schema, object: { title, column: columnId, project: projectId } }`
- [x] `register` and `schema` values are resolved from the registered object type
- [x] On success (HTTP 2xx): `$emit('task-created', { task: response.data })`, then `cancel()` is called
- [x] The full `await` call is wrapped in `try/catch`
- [x] On catch: `errorMessage` is set, `saving = false` (draft is NOT cleared)
- [x] `saving` is reset to `false` in both success and error branches

**files_likely_affected**:
- `src/components/QuickAddTask.vue`
- `src/store/modules/object.js` (add `createObject` action if missing)

**Notes**:
- Check `src/views/ProjectBacklog.vue` for the exact `settingsStore` key names used for tasks. If `useObjectStore` in `object.js` does not have a `createObject` action, add one following the existing fetch pattern.

---

### Task 5: Add loading state and error feedback

**spec_ref**: `openspec/changes/task-quick-add/specs/kanban-board/spec.md` — Scenarios: Loading state during creation, Error feedback on creation failure

**Description**:
Wire the `saving` and `errorMessage` state into the template. Disable inputs during save. Show error text on failure. Style both states with Nextcloud CSS variables.

**acceptance_criteria**:
- [x] `<textarea>` has `:disabled="saving"` binding
- [x] Save button has `:disabled="saving || !draft.trim()"` binding
- [x] Save button shows `NcLoadingIcon` and text "Saving…" when `saving === true`, and "Save" otherwise
- [x] Cancel button has `:disabled="saving"` binding
- [x] Error span with `role="alert"` renders between textarea and action buttons when `errorMessage` is set
- [x] `.quick-add-task__error` uses `color: var(--color-error)`
- [x] After a successful save the error span is hidden (because `cancel()` clears `errorMessage`)

**files_likely_affected**:
- `src/components/QuickAddTask.vue`

---

### Task 6: Accessibility and i18n

**spec_ref**: `openspec/changes/task-quick-add/specs/kanban-board/spec.md` — Non-Functional Requirements

**Description**:
Verify and complete all WCAG AA and i18n requirements across both `QuickAddTask.vue` and the column rendering in `ProjectBoard.vue`.

**acceptance_criteria**:
- [x] Every user-visible string uses `t('planix', '...')`
- [x] The `<textarea>` has an associated `<label>` via matching `for`/`id` pair
- [x] The expanded form has `role="form"` and `aria-label`
- [x] The trigger `NcButton` has `:aria-label` so icon-only rendering is accessible
- [x] The error span has `role="alert"` so screen readers announce errors immediately
- [x] Focus management is correct: textarea receives focus on expand; trigger button receives focus on cancel/success
- [x] Color is never the sole error indicator — error text is always present alongside the red color

**files_likely_affected**:
- `src/components/QuickAddTask.vue`
- `src/views/ProjectBoard.vue`
- `l10n/`

---

### Task 7: Create basic TaskCard.vue component

**spec_ref**: `openspec/changes/task-quick-add/specs/kanban-board/spec.md` — Requirement: Column Layout Rendering [MVP]

**Description**:
Create `src/components/TaskCard.vue` — a minimal read-only card that renders the core task fields inside a column.

**acceptance_criteria**:
- [x] File `src/components/TaskCard.vue` exists with `name: 'TaskCard'`
- [x] Prop: `task: Object` (required)
- [x] Card renders `task.title` as the primary text
- [x] Card renders a priority indicator dot using Nextcloud CSS variables for color
- [x] Card renders `task.dueDate` if present — red text using `var(--color-error)` if the date is in the past
- [x] Card renders `task.assignee` if present (display name, or UUID last-8 as fallback)
- [x] All user-visible strings use `t('planix', '...')`
- [x] Card is keyboard-focusable (`tabindex="0"`) for future navigation wiring
- [x] Component is imported and registered in `ProjectBoard.vue`'s `components: {}`

**files_likely_affected**:
- `src/components/TaskCard.vue` (new)
- `src/views/ProjectBoard.vue`

**Notes**:
- No click handler in this change — task detail navigation is a separate concern
- Use `new Date()` for due-date comparison
