# Tasks: tasks

**Change ID:** tasks
**Status:** draft
**Created:** 2026-04-02

---

## Implementation Tasks

### Task 1: Setup and Prerequisites
- **spec_ref**: `openspec/specs/tasks.md#requirement-task-crud`
- **files**: `src/store/tasks.js`, `src/router/index.js`, `appinfo/routes.php`
- **acceptance_criteria**:
  - GIVEN a fresh Planix install WHEN the developer checks the OpenRegister admin UI THEN the `task` schema exists in the `planix` register (prerequisite from `register-schemas`)
  - GIVEN the developer inspects `@conduction/nextcloud-vue` WHEN checking exports THEN `CnDetailPage`, `CnObjectSidebar`, `CnDataTable`, `useObjectStore`, `useDetailView` are all available
  - GIVEN the developer runs `ls src/store/ src/components/dialogs/` THEN the required directories exist
- [ ] Verify `register-schemas` and `projects` changes are applied
- [ ] Confirm `@conduction/nextcloud-vue` exports: `CnDetailPage`, `CnObjectSidebar`, `CnDataTable`, `useObjectStore`, `useDetailView`
- [ ] Create directory structure: `src/components/dialogs/` (if not present from `projects` change)

---

### Task 2: Pinia Task Store — Base and CRUD
- **spec_ref**: `openspec/specs/tasks.md#requirement-task-crud`, `openspec/changes/tasks/specs/tasks/spec.md#requirement-loading-and-error-states`
- **files**: `src/store/tasks.js`
- **acceptance_criteria**:
  - GIVEN the store is imported WHEN `useTasksStore()` is called THEN it returns reactive state: `tasks`, `activeTask`, `loading`, `error`, `searchQuery`, `activeFilters`
  - GIVEN a project ID WHEN `fetchTasks({ project: id, column: null })` is called THEN OpenRegister returns only backlog tasks for that project
  - GIVEN a valid task ID WHEN `fetchTask(id)` is called THEN `activeTask` is populated with the full task object
  - GIVEN a task data object WHEN `createTask(data)` is called THEN a new task object is created in OpenRegister with `status: 'open'` and `column: null` by default
  - GIVEN an existing task WHEN `updateTask(id, data)` is called THEN the task is PATCHed in OpenRegister and `tasks` array is updated in place
  - GIVEN an existing task WHEN `deleteTask(id)` is called THEN time entries are deleted first, then the task; cascade includes sub-tasks if confirmed
- [ ] Create `src/store/tasks.js` with Pinia store `useTasksStore`
- [ ] Implement `fetchTasks(filters)` — calls `objectStore.getObjects(filters)`, sets `loading`/`error`
- [ ] Implement `fetchTask(id)` — calls `objectStore.getObject(id)`, handles 404
- [ ] Implement `createTask(data)` — merges defaults (`status: 'open'`, `column: null`, `priority: 'normal'`)
- [ ] Implement `updateTask(id, data)` — optimistic update with rollback on failure
- [ ] Implement `deleteTask(id)` — cascade: sub-task check → time entries → sub-tasks → task
- [ ] Implement `filteredTasks` computed — client-side search by title/description (case-insensitive)
- [ ] Implement `setSearch(query)` and `setFilters(filters)`
- [ ] Test

---

### Task 3: Pinia Task Store — Status Lifecycle
- **spec_ref**: `openspec/specs/tasks.md#requirement-task-crud`
- **files**: `src/store/tasks.js`
- **acceptance_criteria**:
  - GIVEN a task with status `open` WHEN `updateStatus(id, 'done')` is called THEN the task is PATCHed with `{ status: 'done', completedAt: <ISO timestamp> }`
  - GIVEN a task with status `done` WHEN `updateStatus(id, 'open')` is called THEN the task is PATCHed with `{ status: 'open', completedAt: null }`
  - GIVEN a task with status `done` WHEN attempting `updateStatus(id, 'in_progress')` THEN the transition is allowed (all non-terminal statuses are reachable from done)
- [ ] Implement `updateStatus(id, status)` in `src/store/tasks.js`
- [ ] Add `completedAt` logic: set to `new Date().toISOString()` when transitioning to `done`; clear to `null` when transitioning away from `done`
- [ ] Test

---

### Task 4: Pinia Task Store — Assignment and Bulk Operations
- **spec_ref**: `openspec/specs/tasks.md#requirement-bulk-task-operations`, `openspec/changes/tasks/specs/tasks/spec.md#requirement-bulk-task-operations`
- **files**: `src/store/tasks.js`
- **acceptance_criteria**:
  - GIVEN a task and a target user UID WHEN `assignTask(id, userUid)` is called THEN the task is PATCHed with `{ assignedTo: userUid }` and `notifyAssignment` is triggered if `userUid !== currentUserUid`
  - GIVEN 3 task IDs and a status WHEN `bulkUpdateStatus([id1, id2, id3], status)` is called THEN each task is PATCHed sequentially; errors are collected and returned
  - GIVEN 3 task IDs and a user UID WHEN `bulkUpdateAssignee([id1, id2, id3], userUid)` is called THEN each task is PATCHed and one notification preference check is made for the target user (not one per task)
- [ ] Implement `assignTask(id, userUid)` with self-notification guard
- [ ] Implement `notifyAssignment(taskId, targetUserId)` — POSTs to `/planix/tasks/{taskId}/notify`
- [ ] Implement `bulkUpdateStatus(ids, status)` — sequential PATCHes with error collection
- [ ] Implement `bulkUpdateAssignee(ids, assignedTo)` — sequential PATCHes + single notification check
- [ ] Test

---

### Task 5: TaskCard Component
- **spec_ref**: `openspec/changes/tasks/specs/tasks/spec.md#requirement-taskcard-component`
- **files**: `src/components/TaskCard.vue`
- **acceptance_criteria**:
  - GIVEN a task object with all fields WHEN `TaskCard` renders in standard mode THEN it shows: priority bar (color-coded), title (max 2 lines), up to 3 label chips (+N more if >3), assignee avatar (24 px), due date chip
  - GIVEN `compact: true` WHEN `TaskCard` renders THEN labels are hidden, avatar is 20 px, card is shorter
  - GIVEN a task with `dueDate` yesterday and `status: 'open'` WHEN `TaskCard` renders THEN due date chip uses `--color-error` and shows "Overdue: {date}"
  - GIVEN a task with `dueDate` yesterday and `status: 'done'` WHEN `TaskCard` renders THEN due date chip is rendered without overdue styling
  - GIVEN the user clicks the card WHEN rendered in backlog THEN a `click` event is emitted and the parent navigates to `/tasks/:id`
- [ ] Create `src/components/TaskCard.vue` with props `{ task: Object, compact: Boolean }`
- [ ] Implement priority left-edge bar using CSS variables (`--color-info`, `--color-warning`, `--color-error`)
- [ ] Implement title truncation (2 lines max, `text-overflow: ellipsis`)
- [ ] Implement label chips (max 3 visible, `+N more` overflow)
- [ ] Implement assignee avatar using `NcAvatar` (24 px standard, 20 px compact)
- [ ] Implement due date chip with future / today / overdue states
- [ ] Emit `click` event; no internal navigation (caller handles routing)
- [ ] Test

---

### Task 6: TaskStatusBadge Component
- **spec_ref**: `openspec/specs/tasks.md#requirement-task-crud`
- **files**: `src/components/TaskStatusBadge.vue`
- **acceptance_criteria**:
  - GIVEN any of the 5 status values WHEN `TaskStatusBadge` renders THEN it shows the correct human-readable label and a color-coded chip
  - GIVEN `status: 'in_progress'` THEN the badge shows "In progress" (not "in_progress")
- [ ] Create `src/components/TaskStatusBadge.vue` with prop `{ status: String }`
- [ ] Map each status to a label (via `t('planix', ...)`) and a CSS variable color
- [ ] Use `NcChip` or equivalent for rendering
- [ ] Test

---

### Task 7: Task Creation Dialog
- **spec_ref**: `openspec/changes/tasks/specs/tasks/spec.md#requirement-task-creation-dialog`
- **files**: `src/components/dialogs/TaskCreateDialog.vue`
- **acceptance_criteria**:
  - GIVEN the dialog is open WHEN the user submits without a title THEN an inline error "Title is required" appears and submit remains disabled
  - GIVEN the user submits with title only WHEN the store creates the task THEN `column: null` is used (backlog placement)
  - GIVEN the user submits with a column selected WHEN the store creates the task THEN `column: selectedColumnId` is used
  - GIVEN creation is in progress WHEN the user tries to close the dialog THEN the X button and ESC are disabled
  - GIVEN creation succeeds WHEN the dialog closes THEN a success toast "Task created" is shown
  - GIVEN creation fails WHEN the dialog remains open THEN all user-entered values are preserved
- [ ] Create `src/components/dialogs/TaskCreateDialog.vue` using `NcDialog`
- [ ] Add fields: Title (required), Description (optional), Priority (select), Due date (date picker), Assignee (user search), Labels (multi-select), Column (optional dropdown listing project columns)
- [ ] Implement title validation (inline error on blur + disabled submit)
- [ ] Implement loading state (spinner on submit, disabled X/ESC)
- [ ] Implement success: close dialog, show toast, trigger `notifyAssignment` if assignee set
- [ ] Implement error: show toast, preserve field values
- [ ] Test

---

### Task 8: Task Delete Dialog
- **spec_ref**: `openspec/specs/tasks.md#requirement-task-crud`
- **files**: `src/components/dialogs/TaskDeleteDialog.vue`
- **acceptance_criteria**:
  - GIVEN a task with no sub-tasks WHEN `TaskDeleteDialog` opens THEN it shows "This will permanently delete the task and all its time entries. This cannot be undone."
  - GIVEN a task with 2 sub-tasks WHEN `TaskDeleteDialog` opens THEN it shows "This task has 2 sub-tasks. Deleting it will also delete all sub-tasks and their time entries."
  - GIVEN the user confirms deletion WHEN the store's `deleteTask` resolves THEN the dialog closes and a success toast is shown
- [ ] Create `src/components/dialogs/TaskDeleteDialog.vue`
- [ ] On open, fetch sub-task count (`tasks where parent === taskId`)
- [ ] Show appropriate warning message based on sub-task count
- [ ] Confirm button uses `NcButton` type `error` (red)
- [ ] On confirm, call `tasksStore.deleteTask(id)`; show toast and navigate/close on success
- [ ] Test

---

### Task 9: TaskMetaSidebar Component
- **spec_ref**: `openspec/changes/tasks/specs/tasks/spec.md#requirement-task-detail-view-ui`
- **files**: `src/components/TaskMetaSidebar.vue`
- **acceptance_criteria**:
  - GIVEN the task detail view WHEN `TaskMetaSidebar` renders THEN it shows: Assignee, Priority, Due date, Labels, Project, Status — all as editable fields
  - GIVEN the user changes the assignee WHEN the input resolves THEN `assignTask` is called and a notification is triggered
  - GIVEN the user changes any other metadata field WHEN the input blurs or selection changes THEN `updateTask` is called immediately (no separate save button)
- [ ] Create `src/components/TaskMetaSidebar.vue`
- [ ] Assignee field: user search input (NC Users API) with avatar display; calls `assignTask`
- [ ] Priority field: select with 4 options (`low`, `normal`, `high`, `urgent`) using `t()` labels
- [ ] Due date field: date picker; calls `updateTask(id, { dueDate })`
- [ ] Labels field: multi-select from available labels (fetch from `label` schema); calls `updateTask(id, { labels })`
- [ ] Project field: read-only display with link to project; editable dropdown for moving task (clears column on change)
- [ ] Status field: select using `TaskStatusBadge` styling; calls `updateStatus`
- [ ] Test

---

### Task 10: TaskBulkActionBar Component
- **spec_ref**: `openspec/changes/tasks/specs/tasks/spec.md#requirement-bulk-task-operations`
- **files**: `src/components/TaskBulkActionBar.vue`
- **acceptance_criteria**:
  - GIVEN rows are selected in the backlog `CnDataTable` WHEN `TaskBulkActionBar` renders THEN it shows: "{count} tasks selected", "Update status" dropdown, "Update assignee" button, "Clear selection" button
  - GIVEN the user selects a new status and confirms WHEN `bulkUpdateStatus` completes THEN a success toast "{count} tasks updated" is shown
  - GIVEN the bulk update partially fails WHEN the store returns errors THEN a warning toast "{failed} tasks could not be updated" is shown
- [ ] Create `src/components/TaskBulkActionBar.vue` with props `{ selectedIds: Array }`
- [ ] "Update status" dropdown calls `tasksStore.bulkUpdateStatus(selectedIds, status)` on confirm
- [ ] "Update assignee" opens a user search modal, calls `tasksStore.bulkUpdateAssignee(selectedIds, uid)` on confirm
- [ ] "Clear selection" emits `clear` event to parent
- [ ] Show success/warning toast based on store return value
- [ ] Test

---

### Task 11: Task Detail View
- **spec_ref**: `openspec/changes/tasks/specs/tasks/spec.md#requirement-task-detail-view-ui`
- **files**: `src/views/TaskDetail.vue`
- **acceptance_criteria**:
  - GIVEN the user navigates to `/tasks/:id` WHEN the component mounts THEN `fetchTask(taskId)` is called and a skeleton loading state is shown during fetch
  - GIVEN the task loads WHEN the view renders THEN `CnDetailPage` shows the task title (inline editable) and description; `CnObjectSidebar` shows `TaskMetaSidebar` + Files + Notes + Tags + Audit Trail tabs
  - GIVEN the user edits the title inline WHEN they press Enter or blur THEN `updateTask(id, { title })` is called and the header updates without a route reload
  - GIVEN the API returns 404 WHEN the fetch resolves THEN `NcEmptyContent` "Task not found" with "Back to tasks" link is shown
- [ ] Create `src/views/TaskDetail.vue`
- [ ] Use `CnDetailPage` as outer shell with task title in header (inline editable)
- [ ] Render description as inline editable textarea (auto-save on blur)
- [ ] Use `CnObjectSidebar` with `TaskMetaSidebar` as first panel; append standard tabs (Files, Notes, Tags, Audit Trail)
- [ ] Implement skeleton loading state
- [ ] Implement 404 empty state
- [ ] Wire `useDetailView` composable for sidebar open/close state
- [ ] Test

---

### Task 12: Global Task List View
- **spec_ref**: `openspec/specs/tasks.md#requirement-task-search`
- **files**: `src/views/TaskList.vue`
- **acceptance_criteria**:
  - GIVEN the user navigates to `/tasks` WHEN the component mounts THEN all tasks visible to the current user are fetched and displayed in `CnDataTable`
  - GIVEN the user types in the search bar WHEN 300 ms have passed THEN the task list filters by title/description (client-side)
  - GIVEN the user clicks a task row WHEN the click event fires THEN the router navigates to `/tasks/:id`
  - GIVEN no tasks exist WHEN the list renders THEN `NcEmptyContent` "No tasks yet" is shown
- [ ] Create `src/views/TaskList.vue`
- [ ] Use `CnDataTable` with columns: Title, Project, Assignee, Priority, Status, Due Date
- [ ] Bind `filteredTasks` computed from store (client-side search)
- [ ] Add search bar with 300 ms debounce calling `tasksStore.setSearch(query)`
- [ ] Add filter chips: Status, Priority, Assignee (server-side, calls `fetchTasks` with params)
- [ ] Row click navigates to `/tasks/:id`
- [ ] Empty state with `NcEmptyContent`
- [ ] Test

---

### Task 13: Backlog View Implementation
- **spec_ref**: `openspec/changes/tasks/specs/tasks/spec.md#requirement-backlog-list-integration`
- **files**: `src/views/ProjectBacklog.vue`
- **acceptance_criteria**:
  - GIVEN the user navigates to `/projects/:id/backlog` WHEN the component mounts THEN `fetchTasks({ project: id, column: null })` is called and results appear in `CnDataTable`
  - GIVEN tasks are fetched WHEN the user types in the search bar THEN the list filters client-side within 300 ms
  - GIVEN no tasks are in the backlog WHEN the list renders THEN `NcEmptyContent` "Backlog is empty" with "Add task" action is shown
  - GIVEN the user selects multiple rows WHEN `TaskBulkActionBar` appears THEN bulk status and assignee update work correctly
- [ ] Modify `src/views/ProjectBacklog.vue` — replace placeholder with full implementation
- [ ] Use `CnDataTable` with columns: Title, Assignee, Priority, Status, Due Date, Actions
- [ ] Bind `tasksStore.fetchTasks({ project: projectId, column: null })` on mount
- [ ] Add `TaskBulkActionBar` — appears when `CnDataTable` selection is non-empty
- [ ] Add "New task" button opening `TaskCreateDialog` (pre-fills project)
- [ ] Add filter chips: Priority, Assignee, Status
- [ ] Empty state with `NcEmptyContent`
- [ ] Test

---

### Task 14: PHP NotificationService
- **spec_ref**: `openspec/changes/tasks/specs/tasks/spec.md#requirement-notification-delivery`
- **files**: `lib/Service/NotificationService.php`
- **acceptance_criteria**:
  - GIVEN user A assigns task T to user B (B ≠ A) WHEN `notify('task_assigned', params, userBUid)` is called THEN an `INotification` is created and sent to user B (if `notify_assigned` setting is enabled for user B)
  - GIVEN the target user is the same as the current user WHEN `notify()` is called THEN no notification is created and the method returns silently
  - GIVEN user B has `notify_assigned` disabled WHEN `notify()` is called THEN no notification is created
  - GIVEN a task with `dueDate` within 48 hours WHEN `notifyDueSoon(taskId, taskTitle, assignedUserId)` is called THEN the `task_due_soon` subject is sent to the assigned user
- [ ] Create `lib/Service/NotificationService.php`
- [ ] Define `SUBJECT_SETTING_MAP` constant array (MVP + V1 subjects with V1 flagged)
- [ ] Inject `INotificationManager`, `IUserSession`, `IConfig` via constructor
- [ ] Implement `notify(string $subject, array $params, string $targetUserId): void`
  - Look up setting key from `SUBJECT_SETTING_MAP`
  - Check user preference via `IConfig::getUserValue()` (default: `'yes'` for MVP subjects)
  - Self-notification guard: `if ($targetUserId === $currentUserId) return`
  - Create `INotification`, set app, subject, object type/id, params; call `INotificationManager::notify()`
- [ ] Implement `notifyDueSoon(string $taskId, string $taskTitle, string $assignedUserId): void`
- [ ] Run `composer check:strict`
- [ ] Test

---

### Task 15: PHP TaskNotifier
- **spec_ref**: `openspec/changes/tasks/specs/tasks/spec.md#requirement-notification-delivery`
- **files**: `lib/Notifier/TaskNotifier.php`, `lib/AppInfo/Application.php`
- **acceptance_criteria**:
  - GIVEN a `task_assigned` notification WHEN `TaskNotifier::prepare()` is called THEN the notification subject line is: `{assigner} assigned you to task "{title}"`
  - GIVEN a `task_due_soon` notification WHEN `TaskNotifier::prepare()` is called THEN the subject line is: `Task "{title}" is due {dueDate}`
  - GIVEN an unsupported subject WHEN `prepare()` is called THEN an `InvalidArgumentException` is thrown
  - GIVEN the notifier is registered WHEN the Nextcloud notification system initialises THEN `TaskNotifier` is included
- [ ] Create `lib/Notifier/TaskNotifier.php` implementing `OCP\Notification\INotifier`
- [ ] Implement `getID(): string` returning `'planix'`
- [ ] Implement `getName(): string` returning translated app name
- [ ] Implement `prepare(INotification $notification, string $languageCode): INotification`
  - Route by subject: `task_assigned`, `task_due_soon`
  - Use `IL10N` for language-aware message formatting
  - Set notification link to `/apps/planix/tasks/{taskId}`
  - Throw `InvalidArgumentException` for unknown subjects
- [ ] Modify `lib/AppInfo/Application.php` to register `TaskNotifier` via `INotificationManager::registerNotifierService()`
- [ ] Run `composer check:strict`
- [ ] Test

---

### Task 16: PHP Task Notify Controller
- **spec_ref**: `openspec/changes/tasks/specs/tasks/spec.md#requirement-notification-delivery`
- **files**: `lib/Controller/TaskController.php`, `appinfo/routes.php`
- **acceptance_criteria**:
  - GIVEN a POST to `/planix/tasks/{taskId}/notify` with body `{ subject, targetUserId }` WHEN the request is authenticated THEN `NotificationService::notify()` is called with the provided params
  - GIVEN an invalid subject WHEN the endpoint is called THEN a 400 Bad Request response is returned
  - GIVEN an unauthenticated request WHEN the endpoint is called THEN a 401 response is returned (handled by Nextcloud middleware)
- [ ] Create `lib/Controller/TaskController.php` (or add action to existing controller if present)
- [ ] Add single `notify(string $taskId, string $subject, string $targetUserId): JSONResponse` action
- [ ] Validate `$subject` against `NotificationService::SUBJECT_SETTING_MAP` keys; return 400 if invalid
- [ ] Delegate to `NotificationService::notify()`; return 200 on success
- [ ] Add route to `appinfo/routes.php`: `['name' => 'task#notify', 'url' => '/tasks/{taskId}/notify', 'verb' => 'POST']`
- [ ] Run `composer check:strict`
- [ ] Test

---

### Task 17: Navigation and Routing
- **spec_ref**: `openspec/changes/tasks/specs/tasks/spec.md#requirement-task-detail-view-ui`
- **files**: `src/router/index.js`, `src/navigation/MainMenu.vue`, `appinfo/routes.php`
- **acceptance_criteria**:
  - GIVEN the user is in Planix WHEN they look at the main navigation THEN a "Tasks" entry is visible and clicking it navigates to `/tasks`
  - GIVEN the user navigates to `/tasks/:id` WHEN Vue Router resolves the route THEN `TaskDetail.vue` is rendered with `taskId` prop set from route params
  - GIVEN the user navigates to `/tasks` or `/tasks/123` in the browser (direct URL) WHEN Nextcloud serves the request THEN the SPA shell is returned and Vue Router takes over
- [ ] Add routes to `src/router/index.js`:
  - `{ path: '/tasks', name: 'Tasks', component: () => import('../views/TaskList.vue') }`
  - `{ path: '/tasks/:id', name: 'TaskDetail', component: () => import('../views/TaskDetail.vue'), props: route => ({ taskId: route.params.id }) }`
- [ ] Add Tasks nav entry to `src/navigation/MainMenu.vue` (`NcAppNavigationItem`, icon: `CheckboxMarkedOutline`, `:to="{ name: 'Tasks' }"`)
- [ ] Add PHP routes to `appinfo/routes.php`:
  - `['name' => 'page#tasks', 'url' => '/tasks', 'verb' => 'GET']`
  - `['name' => 'page#task', 'url' => '/tasks/{id}', 'verb' => 'GET']`
- [ ] Test

---

### Task 18: i18n — English Strings
- **spec_ref**: `openspec/changes/tasks/specs/tasks/spec.md#requirement-i18n-coverage`
- **files**: `l10n/en.json`
- **acceptance_criteria**:
  - GIVEN the `l10n/en.json` file WHEN inspected THEN all strings listed in the i18n inventory in `design.md` are present as keys
  - GIVEN any Vue template or PHP file in this change WHEN all user-visible strings are checked THEN each uses `t('planix', '...')` / `$this->l10n->t('...')` and the key exists in `en.json`
- [ ] Add all task-related strings to `l10n/en.json` (see i18n inventory in `design.md`)
- [ ] Verify no hardcoded English strings remain in any new component or PHP file
- [ ] Test

---

### Task 19: i18n — Dutch Translations
- **spec_ref**: `openspec/changes/tasks/specs/tasks/spec.md#requirement-i18n-coverage`
- **files**: `l10n/nl.json`
- **acceptance_criteria**:
  - GIVEN the `l10n/nl.json` file WHEN compared to `l10n/en.json` THEN every key added by this change in `en.json` also exists in `nl.json`
  - GIVEN the Dutch translations WHEN reviewed by a native speaker THEN they are natural Dutch (not literal translations or English placeholders)
- [ ] Add Dutch translations for all task strings to `l10n/nl.json`
- [ ] Key translations: `Tasks` → `Taken`, `New task` → `Nieuwe taak`, `Backlog` → `Backlog`, `Create task` → `Taak aanmaken`, `Delete task` → `Taak verwijderen`, `Mark as done` → `Markeren als gereed`, `Assignee` → `Toegewezen aan`, `Priority` → `Prioriteit`, `Due date` → `Vervaldatum`, `Labels` → `Labels`, `Overdue:` → `Verlopen:`, `Due today` → `Vandaag te voltooien`
- [ ] Test

---

### Task 20: BUG — Fix Task Status Enum Validation (from test-app 2026-04-04)
- **spec_ref**: `openspec/specs/tasks.md#requirement-task-crud`
- **files**: `lib/Settings/planix_register.json`, `src/store/tasks.js`
- **acceptance_criteria**:
  - GIVEN a POST to the task collection endpoint with `{ title: "Test", status: "in_progress" }` WHEN the API processes the request THEN it returns HTTP 200/201 (not 400 with "Property 'status' should be one of: , but is 'in_progress'")
  - GIVEN the `task` schema in `planix_register.json` WHEN the status field enum is inspected THEN it contains all valid values: `["open", "in_progress", "blocked", "done", "cancelled"]`
- **bug_details**: API test agent found that POST to create a task returns 400: "Property 'status' should be one of: , but is 'in_progress'" — the status enum appears empty or not loading in OpenRegister. This blocks ALL task creation.
- **severity**: HIGH
- [ ] Inspect `lib/Settings/planix_register.json` — verify the `status` field on the `task` schema has its enum values populated (not empty array)
- [ ] If enum values are defined in JSON but not loading: check whether `ConfigurationService::importFromApp()` correctly imports enum constraints, or whether OpenRegister drops them during schema registration
- [ ] After fix, verify via API: `POST /index.php/apps/openregister/api/objects/task` with `{ title: "Test", status: "open" }` returns 200/201
- [ ] Test

---

### Task 21: BUG — Support PATCH for Partial Task Updates (from test-app 2026-04-04)
- **spec_ref**: `openspec/specs/tasks.md#requirement-task-crud`
- **files**: `src/store/tasks.js`
- **acceptance_criteria**:
  - GIVEN an existing task WHEN `updateTask(id, { priority: "high" })` is called THEN only the changed fields are sent (not the entire object)
  - GIVEN a PUT request with only `{ priority: "high" }` WHEN OpenRegister processes the request THEN it does NOT require all required fields (title, status) to be resent
- **bug_details**: API test agent found that PUT requires the full object to be resent — sending only changed fields fails because required fields are missing. The store's `updateTask` should either use PATCH (if OpenRegister supports it) or merge changed fields with the existing object before sending PUT.
- **severity**: MEDIUM
- [ ] Check if OpenRegister supports PATCH method on object endpoints
- [ ] If PATCH supported: update `updateTask()` in `src/store/tasks.js` to use PATCH
- [ ] If PATCH not supported: update `updateTask()` to merge `data` with current task object before sending PUT
- [ ] Test

---

## Verification
- [ ] All tasks checked off
- [ ] Manual testing against acceptance criteria

## Tests (company-wide ADR-009)
- [ ] PHPUnit unit tests for `NotificationService` (subject routing, self-notification guard, preference check)
- [ ] PHPUnit unit tests for `TaskNotifier` (prepare method for each subject, invalid subject exception)
- [ ] PHPUnit unit tests for `TaskController` (notify action, 400 on invalid subject)
- [ ] Browser tests (Playwright MCP) for task creation dialog (happy path + validation)
- [ ] Browser tests (Playwright MCP) for task detail view (load, inline edit, metadata sidebar)
- [ ] Browser tests (Playwright MCP) for backlog list (search, filter, bulk selection, bulk status update)
- [ ] Browser tests (Playwright MCP) for TaskCard (overdue highlighting, compact mode)
- [ ] All tests pass

## Documentation (company-wide ADR-010)
- [ ] Feature documentation updated (task management section in `docs/`)
- [ ] Screenshot captured: task detail view, backlog with bulk selection, TaskCard overdue state

## i18n (company-wide ADR-005)
- [ ] Dutch and English translation strings added (Tasks 18 and 19)
