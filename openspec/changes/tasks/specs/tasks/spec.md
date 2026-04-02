# Delta Spec: tasks

**Capability:** tasks
**Change ID:** tasks
**Delta type:** implementation
**Base spec:** [openspec/specs/tasks.md](../../../../specs/tasks.md)
**Status:** draft
**Created:** 2026-04-02

---

## Summary

This delta captures implementation-specific requirements added when building the task management UI and PHP notification layer. The base spec (`openspec/specs/tasks.md`) defines all business requirements, scenarios, user stories, and acceptance criteria. The delta below documents:

1. UI component patterns required by the implementation architecture.
2. TaskCard component anatomy and reuse contract with the `kanban-board` change.
3. Loading/error state requirements not explicit in the base spec.
4. PHP notification delivery requirements.
5. i18n requirements.
6. Constraints introduced by the `thin-client` + `useObjectStore` + `INotifier` architecture.

All base spec requirements are implemented as-is. No base spec requirement is modified or removed.

---

## ADDED Requirements

### Requirement: Task Detail View UI [MVP]

The task detail view MUST be implemented using `CnDetailPage` + `CnObjectSidebar` from `@conduction/nextcloud-vue`.

#### Scenario: Render task detail
- GIVEN the user navigates to `/tasks/:id`
- WHEN the `TaskDetail` component mounts
- THEN the view MUST fetch the task object via `useTasksStore.fetchTask(taskId)`
- AND display the task title and description in the main `CnDetailPage` content area
- AND render `TaskMetaSidebar.vue` as the first panel inside `CnObjectSidebar`
- AND append standard `CnObjectSidebar` tabs: Files, Notes, Tags, Audit Trail

#### Scenario: Inline title editing
- GIVEN the task detail view is rendered
- WHEN the user clicks on the task title
- THEN the title MUST become an inline editable input (not a separate dialog)
- AND on blur or Enter, the store MUST call `updateTask(id, { title })` automatically
- AND the page header MUST reflect the updated title without a full route reload

#### Scenario: Metadata sidebar — assignee
- GIVEN the task detail view is rendered
- WHEN the user clicks on the Assignee field in `TaskMetaSidebar`
- THEN a user search input MUST appear (using Nextcloud Users API)
- AND selecting a user MUST call `useTasksStore.assignTask(id, userUid)`
- AND the assignee avatar MUST update immediately (optimistic update)
- AND if the assigned user differs from the current user, a `task_assigned` notification MUST be triggered

#### Scenario: Metadata sidebar — priority
- GIVEN the task detail view is rendered
- WHEN the user changes the Priority field
- THEN the store MUST call `updateTask(id, { priority })`
- AND the priority indicator on `TaskCard` (if visible elsewhere) MUST reflect the new value

#### Scenario: Metadata sidebar — due date
- GIVEN the task detail view is rendered
- WHEN the user sets or clears a due date
- THEN the store MUST call `updateTask(id, { dueDate })`
- AND the due date chip on `TaskCard` MUST update immediately

#### Scenario: Metadata sidebar — labels
- GIVEN the task detail view is rendered
- WHEN the user adds or removes a label
- THEN the store MUST call `updateTask(id, { labels })`
- AND label chips MUST appear/disappear on `TaskCard` immediately

#### Scenario: Loading state
- GIVEN the user navigates to `/tasks/:id`
- WHEN the task fetch is in progress
- THEN the view MUST render a skeleton loading state (not a blank page)
- AND the `CnDetailPage` header MUST show a placeholder while title loads

#### Scenario: Task not found
- GIVEN the user navigates to `/tasks/:id` where the task does not exist (404 from OpenRegister)
- WHEN the fetch resolves with a 404
- THEN the view MUST show `NcEmptyContent` with title "Task not found" and a "Back to tasks" link
- AND MUST NOT expose any partial task data

---

### Requirement: TaskCard Component [MVP]

`TaskCard.vue` MUST be a pure display component (no data fetching) with a stable prop interface.

#### Stable prop interface (contract with kanban-board change)
```js
props: {
  task:    { type: Object,  required: true },   // full task object from store
  compact: { type: Boolean, default: false },   // compact mode for kanban columns
}
emits: ['click']
```
Breaking changes to this interface MUST be coordinated with the `kanban-board` change and noted in that change's PR description.

#### Scenario: TaskCard renders all visual elements
- GIVEN a task object with title, assignee, dueDate, priority, and labels
- WHEN `TaskCard` is rendered in standard (non-compact) mode
- THEN the card MUST display:
  - A left-edge priority color bar (CSS variable mapping: `low` → `--color-info`, `normal` → none, `high` → `--color-warning`, `urgent` → `--color-error`)
  - Task title (max 2 lines, `overflow: hidden; text-overflow: ellipsis`)
  - Up to 3 label chips; if more than 3 labels, show `+N more`
  - Assignee avatar (24 px, `NcAvatar`) or an unassigned placeholder icon
  - Due date chip with appropriate state (future / today / overdue)

#### Scenario: TaskCard overdue highlighting
- GIVEN a task with `dueDate` in the past AND `status` is NOT `done` or `cancelled`
- WHEN `TaskCard` is rendered
- THEN the due date chip MUST use `--color-error` background and prefix text "Overdue:"
- AND the chip MUST NOT show the overdue state if `status === 'done'` or `status === 'cancelled'`

#### Scenario: TaskCard compact mode
- GIVEN `compact: true` prop
- WHEN `TaskCard` is rendered
- THEN labels MUST be hidden
- AND the assignee avatar MUST be reduced to 20 px
- AND the card height MUST be reduced (no description line)

#### Scenario: TaskCard click navigation
- GIVEN a `TaskCard` is rendered
- WHEN the user clicks the card
- THEN the `click` event MUST be emitted
- AND the default handler (when used in backlog) MUST navigate to `/tasks/:id`

---

### Requirement: Task Creation Dialog [MVP]

Task creation MUST be implemented as a modal dialog (`NcDialog`), not a separate route.

#### Scenario: Open task creation dialog
- GIVEN the user is on the backlog view or task list
- WHEN the user clicks "New task" or "Add task"
- THEN `TaskCreateDialog.vue` MUST open as a modal over the current view

#### Scenario: Create task — field validation
- GIVEN the task creation dialog is open
- WHEN the user submits without a title
- THEN the form MUST display an inline validation error: "Title is required"
- AND the submit button MUST remain disabled until `title.trim().length > 0`

#### Scenario: Create task — default backlog placement
- GIVEN the user submits the creation dialog with a valid title (no column selected)
- WHEN the store creates the task
- THEN the task MUST be created with `column: null` (backlog)
- AND the task MUST appear in the project backlog immediately (optimistic update or re-fetch)

#### Scenario: Create task — optional column assignment
- GIVEN the task creation dialog is open and a project is selected
- WHEN the user selects a column from the "Column" dropdown
- THEN the task MUST be created with `column: selectedColumnId`
- AND `columnOrder` MUST be set to the current maximum `columnOrder` in that column + 1

#### Scenario: Create task — loading state
- GIVEN the user has clicked "Create" in the dialog
- WHEN the store is processing the creation
- THEN the submit button MUST show a spinner and be disabled
- AND the dialog MUST NOT be closable (X button and ESC key disabled)

#### Scenario: Create task — success
- GIVEN the task creation succeeds
- WHEN the store returns the new task object
- THEN the dialog MUST close
- AND a success toast MUST be shown: `t('planix', 'Task created')`
- AND if the task has an assignee different from the current user, a `task_assigned` notification MUST be triggered

#### Scenario: Create task — error
- GIVEN the task creation fails (OpenRegister API error)
- WHEN the store rejects with an error
- THEN an error toast MUST be shown
- AND the dialog MUST remain open with all user-entered values preserved

---

### Requirement: Notification Delivery [MVP]

#### Scenario: Task assigned notification — happy path
- GIVEN user A assigns task T to user B (B ≠ A)
- WHEN the store calls `assignTask(taskId, userBUid)`
- THEN the store MUST POST to `/planix/tasks/{taskId}/notify` with `{ subject: 'task_assigned', targetUserId: userBUid }`
- AND the PHP `TaskController` MUST delegate to `NotificationService::notify()`
- AND `NotificationService` MUST check user B's `notify_assigned` setting (default: enabled)
- AND if enabled, MUST create and send an `INotification` to user B
- AND user B MUST see the notification in the Nextcloud notification bell

#### Scenario: Task assigned notification — self-assignment suppressed
- GIVEN the current user assigns a task to themselves
- WHEN `NotificationService::notify()` is called with `targetUserId === currentUserId`
- THEN NO notification MUST be created or sent
- AND the assignment MUST still succeed

#### Scenario: Task assigned notification — user preference disabled
- GIVEN user B has set `notify_assigned` to `false` in their Nextcloud notification settings
- WHEN user A assigns a task to user B
- THEN `NotificationService` MUST check the preference and skip notification creation
- AND the assignment MUST still succeed

#### Scenario: TaskNotifier renders assigned notification
- GIVEN user B has received a `task_assigned` notification
- WHEN the Nextcloud notification API calls `TaskNotifier::prepare()`
- THEN the notification MUST be rendered as: `{assigner} assigned you to task "{title}"`
- AND the notification link MUST point to `/apps/planix/tasks/{taskId}`

#### Scenario: Task due soon notification
- GIVEN a task has `dueDate` set to a date within 48 hours and `assignedTo` is set
- WHEN a background check (future cron/job — V1) or explicit trigger calls `NotificationService::notifyDueSoon()`
- THEN the assigned user MUST receive a `task_due_soon` notification: `Task "{title}" is due {dueDate}`
- NOTE: The cron job to trigger this automatically is V1. The notification subject and service method are implemented in this change to unblock V1 implementation.

---

### Requirement: Backlog List Integration [MVP]

#### Scenario: Backlog renders column-less tasks
- GIVEN the user navigates to `/projects/:id/backlog`
- WHEN `ProjectBacklog.vue` mounts
- THEN the view MUST fetch tasks where `project === projectId AND column === null`
- AND display them using `CnDataTable` with columns: Title, Assignee, Priority, Status, Due Date
- AND each row MUST show a `TaskCard` thumbnail or inline task representation

#### Scenario: Backlog search
- GIVEN the backlog is rendered with multiple tasks
- WHEN the user types in the search bar
- THEN the task list MUST filter in real-time (client-side) by title and description
- AND the search MUST be debounced (300 ms)
- AND no page reload MUST occur

#### Scenario: Backlog filter chips
- GIVEN the backlog is rendered
- WHEN the user activates a filter (Priority: High, Assignee: self, Status: open)
- THEN the task list MUST re-fetch with the corresponding server-side filter parameters
- AND the active filter chips MUST be visible and dismissible

#### Scenario: Backlog empty state
- GIVEN all tasks in the project have been moved to columns (none remain in backlog)
- WHEN the backlog renders
- THEN `NcEmptyContent` MUST be shown with title "Backlog is empty" and action "Add task"

---

### Requirement: Bulk Task Operations [MVP]

#### Scenario: Select tasks for bulk action
- GIVEN the backlog `CnDataTable` is rendered
- WHEN the user checks one or more task rows
- THEN `TaskBulkActionBar.vue` MUST appear above the table
- AND the bar MUST show: "{count} tasks selected", "Update status" dropdown, "Update assignee" button, "Clear selection" button

#### Scenario: Bulk status update
- GIVEN 3 tasks are selected in the backlog
- WHEN the user selects a new status from the "Update status" dropdown and confirms
- THEN the store MUST call `bulkUpdateStatus([id1, id2, id3], newStatus)`
- AND MUST PATCH each task individually (sequential, with error collection)
- AND on completion MUST show: `t('planix', '{count} tasks updated')` success toast
- AND on partial failure MUST show: `t('planix', '{failed} tasks could not be updated')`

#### Scenario: Bulk assignee update — notifications
- GIVEN 3 tasks are selected and the user bulk-assigns them to user B
- WHEN `bulkUpdateAssignee([id1, id2, id3], userBUid)` runs
- THEN each task MUST be PATCHed with `assignedTo: userBUid`
- AND for each task where userB ≠ currentUser, a `task_assigned` notification MUST be triggered
- AND the notification service MUST respect user B's `notify_assigned` preference (one check per target user, not per task)

---

### Requirement: i18n Coverage [MVP]

#### Scenario: All user-visible strings use t()
- GIVEN any Vue component or PHP file in this change
- WHEN it contains a string visible to the end user
- THEN the string MUST be wrapped in `t('planix', '...')` (Vue) or `$this->l10n->t('...')` (PHP)
- AND the key MUST be present in both `l10n/en.json` and `l10n/nl.json`
- AND NO English text MUST appear as a hardcoded string in templates or PHP output

#### Scenario: Dutch translation completeness
- GIVEN the `l10n/nl.json` file
- WHEN checked against `l10n/en.json`
- THEN every key present in `en.json` introduced by this change MUST also be present in `nl.json`
- AND all Dutch translations MUST be human-readable Dutch (no English placeholders, no machine-translation artifacts)

---

### Requirement: Loading and Error States [MVP]

#### Scenario: Task list loading
- GIVEN the user navigates to `/tasks` or `/projects/:id/backlog`
- WHEN the fetch is in progress
- THEN the view MUST render a skeleton or spinner loading state
- AND the "New task" button MUST remain enabled during loading (allow pre-emptive creation)

#### Scenario: Task fetch error
- GIVEN the OpenRegister API returns a non-404 error for a task list fetch
- WHEN the store sets `error` to the error message
- THEN the view MUST display `NcEmptyContent` with title "Failed to load tasks" and a "Retry" button
- AND clicking Retry MUST re-invoke the fetch action

#### Scenario: Task update error (optimistic update rollback)
- GIVEN the user edits a task field in the detail view (e.g. changes priority)
- WHEN the store applies the update optimistically AND the API call fails
- THEN the store MUST revert the field to its previous value
- AND an error toast MUST be shown: `t('planix', 'Failed to save changes')`
