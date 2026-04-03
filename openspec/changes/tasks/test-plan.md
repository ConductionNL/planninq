# Test Plan: tasks

## Test Cases

### TC-1: Task detail view renders with all elements
- **spec_ref**: `openspec/changes/tasks/specs/tasks/spec.md#scenario-render-task-detail`
- **type**: functional
- **persona**: any authenticated Nextcloud user with access to the project
- **preconditions**: A task exists with title, description, assignee, priority, due date, and labels; user navigates to `/tasks/:id`
- **steps**: Navigate to `/apps/planix/tasks/:id`
- **expected result**: `CnDetailPage` renders with the task title and description in the main area; `TaskMetaSidebar` appears as the first `CnObjectSidebar` panel; standard tabs Files, Notes, Tags, Audit Trail are present
- **test command**: /test-functional

### TC-2: Inline title editing saves on blur
- **spec_ref**: `openspec/changes/tasks/specs/tasks/spec.md#scenario-inline-title-editing`
- **type**: functional
- **persona**: any authenticated Nextcloud user with task edit access
- **preconditions**: Task detail view is rendered
- **steps**: Click the task title; edit the text; press Tab (or click elsewhere to blur)
- **expected result**: The title becomes an inline input on click; on blur the store calls `updateTask(id, { title })`; the page header reflects the updated title without a route reload
- **test command**: /test-functional

### TC-3: Task not found renders empty state
- **spec_ref**: `openspec/changes/tasks/specs/tasks/spec.md#scenario-task-not-found`
- **type**: functional
- **persona**: any authenticated Nextcloud user
- **preconditions**: No task exists with the given ID
- **steps**: Navigate to `/apps/planix/tasks/nonexistent-id`
- **expected result**: `NcEmptyContent` is shown with title "Task not found" and a "Back to tasks" link; no partial task data is exposed
- **test command**: /test-functional

### TC-4: TaskCard renders all visual elements in standard mode
- **spec_ref**: `openspec/changes/tasks/specs/tasks/spec.md#scenario-taskcard-renders-all-visual-elements`
- **type**: functional
- **persona**: any authenticated Nextcloud user
- **preconditions**: A task exists with title, assignee, dueDate in the future, priority "high", and 4 labels
- **steps**: View the task backlog or kanban board where the TaskCard is rendered
- **expected result**: Left-edge priority bar uses `--color-warning` (high); title is displayed (max 2 lines); 3 label chips shown with "+1 more"; assignee avatar (24 px `NcAvatar`) shown; due date chip shows future state
- **test command**: /test-functional

### TC-5: TaskCard overdue highlighting
- **spec_ref**: `openspec/changes/tasks/specs/tasks/spec.md#scenario-taskcard-overdue-highlighting`
- **type**: functional
- **persona**: any authenticated Nextcloud user
- **preconditions**: A task exists with `dueDate` in the past and `status === 'open'`
- **steps**: View the TaskCard in the backlog
- **expected result**: Due date chip uses `--color-error` background and shows "Overdue:" prefix; a task with `status === 'done'` and past due date does NOT show overdue styling
- **test command**: /test-functional

### TC-6: TaskCard compact mode hides labels and reduces avatar
- **spec_ref**: `openspec/changes/tasks/specs/tasks/spec.md#scenario-taskcard-compact-mode`
- **type**: functional
- **persona**: any authenticated Nextcloud user
- **preconditions**: TaskCard is rendered with `compact: true` (e.g., in kanban column)
- **steps**: View a kanban column with tasks
- **expected result**: Labels are hidden; assignee avatar is reduced to 20 px; card height is reduced (no description line)
- **test command**: /test-functional

### TC-7: Create task — validation prevents submit without title
- **spec_ref**: `openspec/changes/tasks/specs/tasks/spec.md#scenario-create-task-field-validation`
- **type**: functional
- **persona**: any authenticated Nextcloud user
- **preconditions**: Task creation dialog is open
- **steps**: Open `TaskCreateDialog`; leave title empty; click "Create"
- **expected result**: Inline error "Title is required" is shown; submit button remains disabled while `title.trim().length === 0`
- **test command**: /test-functional

### TC-8: Create task — placed in backlog by default
- **spec_ref**: `openspec/changes/tasks/specs/tasks/spec.md#scenario-create-task-default-backlog-placement`
- **type**: functional
- **persona**: any authenticated Nextcloud user
- **preconditions**: Task creation dialog is open with no column selected
- **steps**: Enter a valid title; click "Create"
- **expected result**: Task is created with `column: null`; task appears in the project backlog immediately
- **test command**: /test-functional

### TC-9: Create task — success toast and dialog closes
- **spec_ref**: `openspec/changes/tasks/specs/tasks/spec.md#scenario-create-task-success`
- **type**: functional
- **persona**: any authenticated Nextcloud user
- **preconditions**: Task creation dialog is open with a valid title
- **steps**: Submit the dialog
- **expected result**: Dialog closes; success toast "Task created" is shown
- **test command**: /test-functional

### TC-10: Create task — error preserves dialog state
- **spec_ref**: `openspec/changes/tasks/specs/tasks/spec.md#scenario-create-task-error`
- **type**: functional
- **persona**: any authenticated Nextcloud user
- **preconditions**: OpenRegister returns an error during task creation
- **steps**: Submit the creation dialog when the API is set to error
- **expected result**: Error toast is shown; dialog remains open with all user-entered values preserved
- **test command**: /test-functional

### TC-11: Task assignment notification — happy path
- **spec_ref**: `openspec/changes/tasks/specs/tasks/spec.md#scenario-task-assigned-notification-happy-path`
- **type**: functional
- **persona**: user A (assigner) and user B (assignee)
- **preconditions**: Two users exist; `notify_assigned` is enabled for user B (default); user A is logged in; a task exists
- **steps**: User A assigns the task to user B via the assignee field in `TaskMetaSidebar`
- **expected result**: User B receives a notification in the Nextcloud notification bell; notification text reads "{assigner} assigned you to task "{title}""; notification link points to `/apps/planix/tasks/{taskId}`
- **test command**: /test-functional

### TC-12: Task assignment notification — self-assignment suppressed
- **spec_ref**: `openspec/changes/tasks/specs/tasks/spec.md#scenario-task-assigned-notification-self-assignment-suppressed`
- **type**: functional
- **persona**: any authenticated Nextcloud user
- **preconditions**: User is logged in; a task exists
- **steps**: User assigns the task to themselves
- **expected result**: No notification is created; the assignment still succeeds
- **test command**: /test-functional

### TC-13: Task assignment notification — user preference disabled
- **spec_ref**: `openspec/changes/tasks/specs/tasks/spec.md#scenario-task-assigned-notification-user-preference-disabled`
- **type**: functional
- **persona**: user B with `notify_assigned` disabled
- **preconditions**: User B has set `notify_assigned = false`; user A assigns a task to user B
- **steps**: User A assigns the task to user B
- **expected result**: No notification is created; the assignment succeeds
- **test command**: /test-functional

### TC-14: Backlog renders column-less tasks
- **spec_ref**: `openspec/changes/tasks/specs/tasks/spec.md#scenario-backlog-renders-column-less-tasks`
- **type**: functional
- **persona**: any authenticated Nextcloud user
- **preconditions**: A project exists with some tasks having `column: null` and others assigned to columns
- **steps**: Navigate to `/projects/:id/backlog`
- **expected result**: Only tasks with `column: null` are displayed; `CnDataTable` shows columns Title, Assignee, Priority, Status, Due Date
- **test command**: /test-functional

### TC-15: Backlog search filters by title
- **spec_ref**: `openspec/changes/tasks/specs/tasks/spec.md#scenario-backlog-search`
- **type**: functional
- **persona**: any authenticated Nextcloud user
- **preconditions**: Backlog has at least 3 tasks with distinct titles
- **steps**: Type a partial title string into the search bar; wait up to 300 ms
- **expected result**: Task list filters in real time by title/description; no page reload occurs
- **test command**: /test-functional

### TC-16: Bulk status update
- **spec_ref**: `openspec/changes/tasks/specs/tasks/spec.md#scenario-bulk-status-update`
- **type**: functional
- **persona**: any authenticated Nextcloud user
- **preconditions**: Backlog has at least 3 tasks
- **steps**: Check 3 task rows; the `TaskBulkActionBar` appears; select a new status from "Update status"; confirm
- **expected result**: All 3 tasks are PATCHed with the new status; success toast shows "{count} tasks updated"; partial failure shows "{failed} tasks could not be updated"
- **test command**: /test-functional

### TC-17: Optimistic update rollback on task field update failure
- **spec_ref**: `openspec/changes/tasks/specs/tasks/spec.md#scenario-task-update-error-optimistic-update-rollback`
- **type**: functional
- **persona**: any authenticated Nextcloud user
- **preconditions**: Task detail view is open; API is set to fail the PATCH request
- **steps**: Change the priority field in `TaskMetaSidebar`
- **expected result**: Priority updates optimistically; when API fails, the field reverts to its previous value; error toast "Failed to save changes" is shown
- **test command**: /test-functional

### TC-18: Task detail loading state shows skeleton
- **spec_ref**: `openspec/changes/tasks/specs/tasks/spec.md#scenario-loading-state`
- **type**: functional
- **persona**: any authenticated Nextcloud user
- **preconditions**: API response is delayed
- **steps**: Navigate to `/tasks/:id` with a slow connection/simulated delay
- **expected result**: A skeleton loading state is rendered; the `CnDetailPage` header shows a placeholder while the title loads; no blank page is shown
- **test command**: /test-functional

## Coverage Summary

| Requirement | Scenarios Covered | Test Cases |
|-------------|-------------------|------------|
| Task Detail View UI [MVP] | Render, inline edit, not found, loading | TC-1, TC-2, TC-3, TC-18 |
| TaskCard Component [MVP] | Standard mode, overdue, compact, click | TC-4, TC-5, TC-6 |
| Task Creation Dialog [MVP] | Validation, backlog, success, error, loading | TC-7, TC-8, TC-9, TC-10 |
| Notification Delivery [MVP] | Happy path, self-suppress, preference | TC-11, TC-12, TC-13 |
| Backlog List Integration [MVP] | Column-less tasks, search, empty state | TC-14, TC-15 |
| Bulk Task Operations [MVP] | Bulk status update | TC-16 |
| Loading and Error States [MVP] | Loading skeleton, optimistic rollback | TC-17, TC-18 |
| i18n Coverage [MVP] | Not covered in browser test (see Out of Scope) | — |

## Out of Scope

- i18n translation completeness — verified via build-time linting, not browser tests
- `TaskNotifier::prepare()` rendering — verified via PHP unit test (not browser); the notification bell result is covered in TC-11
- Bulk assignee update with per-task notification throttling — covered as an extension of TC-16 in regression testing once `admin-user-settings` is applied
- `task_due_soon` cron trigger — V1 feature; the notification subject and service method are implemented but the cron job is deferred
