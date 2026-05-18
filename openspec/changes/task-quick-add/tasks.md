# Tasks: Task Quick-Add

## Tasks

### Task 1: Render kanban columns in ProjectBoard.vue

**spec_ref**: `openspec/changes/task-quick-add/specs/kanban-board/spec.md` — Requirement: Column Layout Rendering [MVP]

**Description**:
Replace the "Board view coming soon" `NcEmptyContent` placeholder in `src/views/ProjectBoard.vue` with a real column layout. Fetch columns from OpenRegister filtered by `project.id`, render them in `order` sequence as a horizontal scrollable list. Show a "No columns yet" empty state when the project has no columns.

**Acceptance criteria**:
- [ ] The "Board view coming soon" `NcEmptyContent` is removed from the template
- [ ] `columns` computed property returns columns from `useObjectStore` filtered to the current project, sorted by `order`
- [ ] Columns render as a horizontal `<div class="project-board__columns">` with one `.project-board__column` per column entry
- [ ] Each column shows its `title` in a `.project-board__column-header`
- [ ] When `columns.length === 0` and not loading, an `NcEmptyContent` renders with text "No columns yet"
- [ ] A loading indicator (`NcLoadingIcon` or similar) is shown while `columnsLoading` is true

**files_likely_affected**:
- `src/views/ProjectBoard.vue`
- `src/store/store.js` (register column and task object types in `initializeStores()`)

**Notes**:
- Columns are fetched via the existing `useObjectStore.fetchObjects('column', { register, schema, 'object.project': projectId })` pattern
- After columns are loaded, fetch tasks per-column: `useObjectStore.fetchObjects('task', { register, schema, 'object.project': projectId })` and group them into `columnTasks` by `task.column`
- The `register` and `schema` values for columns must come from `useSettingsStore`. Check `src/store/modules/settings.js` for whether `columnsRegister` / `columnsSchema` keys already exist; if not, add them with the correct values from the app's OpenRegister configuration
- `registerObjectType('column', schemaId, registerId)` must be called in `initializeStores()` in `src/store/store.js`
- Horizontal scroll on `.project-board__columns`: `display: flex; overflow-x: auto; gap: 12px`

---

### Task 2: Create QuickAddTask.vue component

**spec_ref**: `openspec/changes/task-quick-add/specs/kanban-board/spec.md` — Requirement: Quick-Add Task in Column [MVP]

**Description**:
Create `src/components/QuickAddTask.vue`. The component renders a "+ Add task" trigger button. On click, it expands to an inline `<textarea>` with Save and Cancel buttons. This task covers the static structure and toggle behaviour (no API call yet — wired in Task 4).

**Acceptance criteria**:
- [ ] File `src/components/QuickAddTask.vue` exists with `name: 'QuickAddTask'`
- [ ] Props: `columnId: String` (required), `projectId: String` (required)
- [ ] Local state: `active: false`, `draft: ''`, `saving: false`, `errorMessage: ''`
- [ ] When `active === false`: renders an `NcButton` with label "Add task" and a `PlusIcon`
- [ ] When `active === true`: renders the expanded form with `<label>` (visually hidden), `<textarea>`, optional error span, Save button, Cancel button
- [ ] `activate()` sets `active = true`, clears `errorMessage`, and focuses the textarea via `$nextTick`
- [ ] `cancel()` sets `active = false`, clears `draft` and `errorMessage`, and returns focus to the trigger button
- [ ] `NcButton`, `NcLoadingIcon` are imported from `@conduction/nextcloud-vue` and registered in `components: {}`
- [ ] `PlusIcon` is imported from `vue-material-design-icons/Plus.vue`
- [ ] All user-visible strings use `t('planix', '...')`

**files_likely_affected**:
- `src/components/QuickAddTask.vue` (new)

---

### Task 3: Wire keyboard handling (Enter / Escape)

**spec_ref**: `openspec/changes/task-quick-add/specs/kanban-board/spec.md` — Scenarios: Enter key creates the task, Escape key cancels, Shift+Enter inserts newline

**Description**:
Add `@keydown` event handlers to the `<textarea>` in `QuickAddTask.vue` for the three keyboard interactions.

**Acceptance criteria**:
- [ ] `@keydown.enter.prevent` calls `handleEnter(event)`
- [ ] `handleEnter` does nothing if `draft.trim()` is empty or `saving === true`
- [ ] `handleEnter` calls `submit()` otherwise
- [ ] `@keydown.esc` calls `cancel()`
- [ ] A `@keydown.shift.enter` (or guard `if (event.shiftKey) return` inside `handleEnter`) allows the default textarea newline behaviour and does NOT call `submit()`
- [ ] Keyboard shortcuts work the same whether the user reaches them via mouse or keyboard navigation

**files_likely_affected**:
- `src/components/QuickAddTask.vue`

**Notes**:
- In Vue 2, `@keydown.esc` is the correct modifier (not `.escape` — both work in Vue 2.7 but `.esc` is the conventional shorthand used in this codebase)
- Shift+Enter guard: add `if (event.shiftKey) return` as the first line of `handleEnter`, before the empty-check

---

### Task 4: Connect to task creation store / API

**spec_ref**: `openspec/changes/task-quick-add/specs/kanban-board/spec.md` — Scenario: Enter key creates the task

**Description**:
Implement `submit()` in `QuickAddTask.vue`. Check whether `useObjectStore` exposes a `createObject(type, payload)` action; if not, add it. Wire the submit method to POST to OpenRegister using `@nextcloud/axios`.

**Acceptance criteria**:
- [ ] `submit()` sets `saving = true` and clears `errorMessage` before the request
- [ ] The API call uses `axios` from `@nextcloud/axios` (NEVER raw `fetch()`)
- [ ] The request POSTs to `generateUrl('/apps/openregister/api/objects')` with body `{ register, schema, object: { title, column: columnId, project: projectId } }`
- [ ] `register` and `schema` values are read from `useSettingsStore` (keys for the task type)
- [ ] On success (HTTP 2xx): `$emit('task-created', { task: response.data })`, then `cancel()` is called
- [ ] The full `await` call is wrapped in `try/catch`
- [ ] On catch: `errorMessage` is set to `t('planix', 'Failed to create task. Please try again.')`, `saving = false` (draft is NOT cleared)
- [ ] `saving` is reset to `false` in `finally` or in both success and error branches
- [ ] EVERY `await` call has a `try/catch` with user-facing error feedback (ADR-004)

**files_likely_affected**:
- `src/components/QuickAddTask.vue`
- `src/store/modules/object.js` (add `createObject` action if missing)

**Notes**:
- Check `src/views/ProjectBacklog.vue` for the exact `settingsStore` key names used for tasks (`tasksRegister`, `tasksSchema`, or similar). Use those same keys — do not invent new ones.
- If `useObjectStore` in `object.js` does not have a `createObject` action, add one:
  ```js
  async createObject(type, data) {
    if (!this.objectTypes[type]) {
      console.warn(`Object type "${type}" is not registered`)
      return null
    }
    const { schema, register } = this.objectTypes[type]
    const url = new URL(this.baseUrl, window.location.origin)
    const response = await axios.post(url.toString(), { register, schema, object: data }, {
      headers: { requesttoken: OC.requestToken },
    })
    return response.data
  }
  ```
- If the action is added to the store, `QuickAddTask.vue` should call `objectStore.createObject('task', { title, column: columnId, project: projectId })` rather than calling axios directly

---

### Task 5: Add loading state and error feedback

**spec_ref**: `openspec/changes/task-quick-add/specs/kanban-board/spec.md` — Scenarios: Loading state during creation, Error feedback on creation failure

**Description**:
Wire the `saving` and `errorMessage` state into the template. Disable inputs during save. Show error text on failure. Style both states with Nextcloud CSS variables.

**Acceptance criteria**:
- [ ] `<textarea>` has `:disabled="saving"` binding
- [ ] Save button has `:disabled="saving || !draft.trim()"` binding
- [ ] Save button shows `NcLoadingIcon` and text "Saving…" when `saving === true`, and "Save" otherwise
- [ ] Cancel button has `:disabled="saving"` binding
- [ ] `<span v-if="errorMessage" role="alert" class="quick-add-task__error">{{ errorMessage }}</span>` renders between the textarea and the action buttons
- [ ] `.quick-add-task__error` uses `color: var(--color-error)` and `font-size: var(--font-size-small)` (or equivalent Nextcloud variable)
- [ ] After a successful save the error span is hidden (because `cancel()` clears `errorMessage`)

**files_likely_affected**:
- `src/components/QuickAddTask.vue`

---

### Task 6: Accessibility and i18n

**spec_ref**: `openspec/changes/task-quick-add/specs/kanban-board/spec.md` — Non-Functional Requirements (Accessibility, Internationalisation)

**Description**:
Verify and complete all WCAG AA and i18n requirements across both `QuickAddTask.vue` and the column rendering in `ProjectBoard.vue`.

**Acceptance criteria**:
- [ ] Every user-visible string in `QuickAddTask.vue` and the column block in `ProjectBoard.vue` is wrapped in `t('planix', '...')`
- [ ] The `<textarea>` has an associated `<label>` via matching `for`/`id` pair (not just a `placeholder`)
- [ ] The expanded form has `role="form"` and `aria-label="t('planix', 'Quick add task')"`
- [ ] The trigger `NcButton` has `:aria-label="t('planix', 'Add task')"` so icon-only rendering is still accessible
- [ ] The error span has `role="alert"` so screen readers announce errors immediately
- [ ] Focus management is correct: textarea receives focus on expand; trigger button receives focus on cancel/success
- [ ] Tab order in the expanded form is: textarea → Save → Cancel (natural DOM order; no manual `tabindex` needed)
- [ ] Color is never the sole error indicator — the error text is always present alongside the red color
- [ ] All new l18n strings added to `l10n/` translation source (or noted in a comment for the translator pass)

**files_likely_affected**:
- `src/components/QuickAddTask.vue`
- `src/views/ProjectBoard.vue`
- `l10n/` (translation strings — add a `/* i18n */` comment or update the source catalog if the project uses one)

---

### Task 7: Create basic TaskCard.vue component

**spec_ref**: `openspec/changes/task-quick-add/specs/kanban-board/spec.md` — Requirement: Column Layout Rendering [MVP]

**Description**:
Create `src/components/TaskCard.vue` — a minimal read-only card that renders the core task fields inside a column. This gives the board visual substance without pulling in the full drag-and-drop/WIP-limit work from the kanban-board change.

**Acceptance criteria**:
- [ ] File `src/components/TaskCard.vue` exists with `name: 'TaskCard'`
- [ ] Prop: `task: Object` (required)
- [ ] Card renders `task.title` as the primary text
- [ ] Card renders a priority indicator dot using Nextcloud CSS variables for color (urgent = `var(--color-error)`, high = `var(--color-warning)`, normal = `var(--color-primary-element)`, low = `var(--color-text-maxcontrast)`)
- [ ] Card renders `task.dueDate` if present — red text using `var(--color-error)` if the date is in the past
- [ ] Card renders `task.assignee` if present (display name, or UUID last-8 as fallback)
- [ ] All user-visible strings use `t('planix', '...')`
- [ ] Card is keyboard-focusable (`tabindex="0"`) for future navigation wiring
- [ ] Component is imported and registered in `ProjectBoard.vue`'s `components: {}`

**files_likely_affected**:
- `src/components/TaskCard.vue` (new)
- `src/views/ProjectBoard.vue`

**Notes**:
- No click handler in this change — task detail navigation is a separate concern
- No drag-and-drop handle needed — that's the kanban-board change
- Use `dayjs` (already a likely dep via Nextcloud's ecosystem) or `new Date()` for due-date comparison

---

## DEFERRED_QUESTIONS

- **Column settings keys**: The exact `useSettingsStore` key names for `columnsRegister` and `columnsSchema` are unknown at spec time. The builder must check `src/store/modules/settings.js` and `src/views/ProjectBacklog.vue` to find existing keys, and add new ones only if they are missing.
- **`useObjectStore.createObject` existence**: The local `object.js` store only has `fetchObjects` at spec time. The builder must check whether a `createObject` (or `saveObject` / `postObject`) action was added after this spec was written, and reuse it rather than duplicating logic.
- **Column body task-card placeholder**: This change intentionally leaves the column body empty (no task cards). If the kanban-board change has already landed on the branch, the column body rendering may already exist — the builder should not overwrite it.
- **Horizontal scroll breakpoint**: The column layout uses `overflow-x: auto`. The minimum column width has not been specified; `280px` is a reasonable default. Adjust based on visual review in the actual Nextcloud UI.
