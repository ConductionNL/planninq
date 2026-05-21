# Design: Task Quick-Add

## Context

`ProjectBoard.vue` currently renders a "Board view coming soon" `NcEmptyContent` placeholder. This change replaces it with a real column layout and adds the first interactive board feature: an inline quick-add input in each column's footer.

The change is frontend-only. No backend routes, no OpenRegister schema changes, no PHP work. Task creation uses the existing OpenRegister object API already configured in `useObjectStore`.

## Goals / Non-Goals

**Goals:**
- Real column rendering loop in `ProjectBoard.vue` (horizontal scroll, columns fetched from OpenRegister)
- `QuickAddTask.vue`: inline text input per column, Enter to create, Escape to cancel
- Loading state while the POST is in-flight
- Error feedback without leaving stale input on failure
- WCAG AA keyboard accessibility, all strings internationalised

**Non-Goals:**
- Drag-and-drop — kanban-board change
- WIP limit violations / threshold indicators — kanban-board change
- WIP limit UI — kanban-board change
- View toggle (kanban ↔ list) — kanban-board change
- Task form modal (full creation form) — separate component

---

## Component Design

### QuickAddTask.vue

**File**: `src/components/QuickAddTask.vue`

**Props**:

| Prop | Type | Required | Description |
|------|------|----------|-------------|
| `columnId` | `String` | Yes | OpenRegister UUID of the target column |
| `projectId` | `String` | Yes | OpenRegister UUID of the parent project (used to scope the created task) |

**Emitted events**:

| Event | Payload | When |
|-------|---------|------|
| `task-created` | `{ task }` | After successful POST — parent can optimistically prepend the new card |
| `error` | `{ message }` | After a failed POST — parent may surface a toast if it chooses |

**Local state**:

| Property | Type | Initial | Purpose |
|----------|------|---------|---------|
| `active` | `Boolean` | `false` | Whether the input is expanded (vs. showing the button) |
| `draft` | `String` | `''` | Current value of the text input |
| `saving` | `Boolean` | `false` | True while the POST is in-flight |
| `errorMessage` | `String` | `''` | Non-empty when the last save attempt failed |

**Template sketch**:

```html
<!-- Collapsed state -->
<div v-if="!active" class="quick-add-task__trigger">
  <NcButton type="tertiary" :aria-label="t('planix', 'Add task')" @click="activate">
    <template #icon><PlusIcon :size="16" /></template>
    {{ t('planix', 'Add task') }}
  </NcButton>
</div>

<!-- Expanded state -->
<div v-else class="quick-add-task__form" role="form" :aria-label="t('planix', 'Quick add task')">
  <label :for="inputId" class="sr-only">{{ t('planix', 'Task title') }}</label>
  <textarea
    :id="inputId"
    ref="inputRef"
    v-model="draft"
    class="quick-add-task__input"
    :placeholder="t('planix', 'Task title — press Enter to save, Escape to cancel')"
    :disabled="saving"
    rows="2"
    @keydown.enter.prevent="handleEnter"
    @keydown.esc="cancel" />
  <span v-if="errorMessage" role="alert" class="quick-add-task__error">
    {{ errorMessage }}
  </span>
  <div class="quick-add-task__actions">
    <NcButton type="primary" :disabled="saving || !draft.trim()" @click="submit">
      <template v-if="saving" #icon><NcLoadingIcon :size="16" /></template>
      {{ saving ? t('planix', 'Saving…') : t('planix', 'Save') }}
    </NcButton>
    <NcButton type="tertiary" :disabled="saving" @click="cancel">
      {{ t('planix', 'Cancel') }}
    </NcButton>
  </div>
</div>
```

**Key methods**:

- `activate()` — sets `active = true`, `errorMessage = ''`, calls `this.$nextTick(() => this.$refs.inputRef.focus())`
- `cancel()` — sets `active = false`, `draft = ''`, `errorMessage = ''`, `saving = false`
- `handleEnter(event)` — guard: do nothing if `draft.trim()` is empty or `saving` is true; otherwise call `submit()`
- `submit()` — sets `saving = true`, calls the store, on success: `$emit('task-created', { task })`, calls `cancel()`, on failure: sets `errorMessage`, sets `saving = false` (does NOT call `cancel()` so the user can retry or copy the draft)

**ADR compliance checklist**:
- `axios` from `@nextcloud/axios` used by `useObjectStore` under the hood — direct `fetch()` never used
- `t('planix', '...')` wraps every user-visible string
- All CSS via `var(--color-primary-element)`, `var(--color-border)`, etc.
- `try/catch` around every `await store.action()` with `errorMessage` set on failure
- `NcButton` / `NcLoadingIcon` imported from `@conduction/nextcloud-vue` (never `@nextcloud/vue` directly)
- Every component in `<template>` imported AND registered in `components: {}`
- `<label>` associated to input via matching `for`/`id` pair (WCAG AA)

---

### ProjectBoard.vue (modified)

The "Board view coming soon" `NcEmptyContent` placeholder is replaced with a column-rendering block.

**New computed properties**:

- `columns` — derived from `useObjectStore` objects filtered to `type: 'column'` and `project === project.id`, sorted by `order`
- `columnsLoading` — `useObjectStore().loading['column']`

**New data**:

- `columnTasks: {}` — a map of `columnId → task[]`, populated after columns are loaded by fetching tasks filtered by `project.id`.
- `columnTasksLoading: false` — true while initial task fetch is in-flight.

**TaskCard.vue (new basic component)**:

A minimal read-only task card that shows the core fields available from the OpenRegister task object:

| Slot | What to show |
|------|-------------|
| Title | `task.title` (always present) |
| Assignee | NC user display name or `task.assignee` UUID (show as avatar initial if no display name) |
| Due date | `task.dueDate` formatted with `moment` or `dayjs` — red text if overdue |
| Priority dot | Colored dot: red = urgent, orange = high, blue = normal, grey = low |

Props: `task: Object` (required).

No click handler in this change (task detail navigation is a separate concern). The card is display-only for now. Drag-and-drop is out of scope.

**Template change** — replace the placeholder `NcEmptyContent` with:

```html
<!-- No columns yet -->
<NcEmptyContent
  v-if="!columnsLoading && columns.length === 0"
  :name="t('planix', 'No columns yet')"
  :description="t('planix', 'Add columns in project settings to set up your board.')">
  <template #icon><ViewColumnOutline :size="20" /></template>
</NcEmptyContent>

<!-- Column list -->
<div v-else class="project-board__columns">
  <div
    v-for="column in columns"
    :key="column.id"
    class="project-board__column">
    <div class="project-board__column-header">
      <span class="project-board__column-title">{{ column.title }}</span>
    </div>
    <div class="project-board__column-body">
      <TaskCard
        v-for="task in columnTasks[column.id] || []"
        :key="task.id"
        :task="task" />
      <NcEmptyContent
        v-if="!columnTasksLoading && !(columnTasks[column.id] || []).length"
        :name="t('planix', 'No tasks')"
        :description="t('planix', 'Use the button below to add your first task.')" />
    </div>
    <div class="project-board__column-footer">
      <QuickAddTask
        :column-id="column.id"
        :project-id="project.id"
        @task-created="onTaskCreated(column.id, $event.task)" />
    </div>
  </div>
</div>
```

---

## Store / API Design

### Task creation call

`QuickAddTask.vue` calls the local `useObjectStore` (from `src/store/modules/object.js`, NOT the conduction one — see `store/store.js` which configures both) to create a task object.

Since the local `useObjectStore` in `object.js` does not currently expose a `createObject` action, the component calls the OpenRegister API directly via `axios` from `@nextcloud/axios`:

```js
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

// Inside submit():
const url = generateUrl('/apps/openregister/api/objects')
const response = await axios.post(url, {
  register: settingsStore.tasksRegister,   // from useSettingsStore
  schema: settingsStore.tasksSchema,       // from useSettingsStore
  object: {
    title: this.draft.trim(),
    column: this.columnId,
    project: this.projectId,
  },
}, {
  headers: { requesttoken: OC.requestToken },
})
```

The `register` and `schema` values are read from `useSettingsStore` (already populated by `initializeStores()` at app bootstrap). The exact settings keys must match those used by `ProjectBacklog.vue` for task creation — check `src/views/ProjectBacklog.vue` or `src/store/modules/settings.js` for the authoritative key names before implementing.

**Why direct axios instead of the store action?** The local `useObjectStore` in `object.js` only has `fetchObjects` — no `createObject`. Adding `createObject` to the store is the cleaner long-term path, but adding it is in-scope for Task 4 of this change. `QuickAddTask.vue` can be written against a `createObject(type, payload)` store action; the builder must check whether that action exists and add it if missing.

### Column fetching

`ProjectBoard.vue` loads columns in `mounted()` after the project is loaded:

```js
await this.objectStore.fetchObjects('column', {
  register: this.settingsStore.columnsRegister,
  schema: this.settingsStore.columnsSchema,
  'object.project': this.project.id,
})
```

Columns are not yet registered as an object type in `store/store.js`. The builder must add `objectStore.registerObjectType('column', ...)` in `initializeStores()` using the correct register/schema values from settings. If the settings keys are not yet defined, stub them from `useSettingsStore` state defaults; the full settings integration is out of scope for this change.

---

## UX Behavior

### Focus management

- When the user clicks "+ Add task", the `<textarea>` receives focus immediately (via `$nextTick`).
- When `cancel()` is called (Escape or Cancel button), focus returns to the "+ Add task" button. Use `this.$refs.addButton.focus()` (add a `ref="addButton"` to the button).
- When `submit()` succeeds, `cancel()` is called internally — same focus return.

### Enter / Escape handling

- **Enter (without Shift)**: submit. `@keydown.enter.prevent` prevents the default newline in the textarea.
- **Shift+Enter**: insert a newline (allow default — not prevented).
- **Escape**: cancel. `@keydown.esc` (Vue 2 shorthand for `@keydown.escape`).

### Loading state

While `saving === true`:
- Textarea is `disabled`
- Save button shows `NcLoadingIcon` + "Saving…" text and is `disabled`
- Cancel button is `disabled` (prevent cancel mid-flight — avoids orphaned requests)

### Error feedback

On failure:
- `errorMessage` is set to a localised string, e.g. `t('planix', 'Failed to create task. Please try again.')`
- The `<span role="alert">` renders `errorMessage` immediately below the textarea — screen readers announce it
- `draft` is NOT cleared — the user can retry or copy their text
- `saving` is set back to `false`
- On the next `submit()` call, `errorMessage` is cleared before the request

---

## Declarative-vs-Imperative Decision (ADR-031)

ADR-031 asks whether any business logic in this change should be expressed declaratively (state machine, schema-driven rules, aggregation rules) rather than imperatively in component code.

**Conclusion: no declarative behaviors needed.** This change is pure UI interaction:
- No business rules beyond "title must not be blank"
- No aggregation or derived data
- No state machine with branching workflows — the interaction is a simple linear flow: idle → active → saving → idle (success) or active (error)

The local `active / saving / errorMessage` state is appropriate imperative component state. ADR-031 does not apply here.

---

## Accessibility

- The collapsed trigger is an `NcButton` with an explicit `aria-label` (not just icon-only)
- The expanded form has `role="form"` and `aria-label` so screen readers announce the context
- The `<label>` is visually hidden (`.sr-only`) but present and associated via `for`/`id`
- The error span uses `role="alert"` for immediate announcement
- Keyboard-only users can activate the trigger with Space/Enter, type a title, submit with Enter, cancel with Escape — no mouse required
- Color is never the sole indicator — error text is always present alongside any color styling

---

## CSS Variables

All color and spacing use Nextcloud CSS variables:

| Purpose | Variable |
|---------|----------|
| Column background | `var(--color-background-dark)` |
| Column border | `var(--color-border)` |
| Column header text | `var(--color-main-text)` |
| Input background | `var(--color-main-background)` |
| Input border | `var(--color-border-dark)` |
| Error text | `var(--color-error)` |
| Focus ring | `var(--color-primary-element)` |

---

## Open Questions

None at design time. Deferred questions are captured in DEFERRED_QUESTIONS at the end of the tasks file.
