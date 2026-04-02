# Design: tasks

**Change ID:** tasks
**Status:** draft
**Created:** 2026-04-02

---

## Context

The `register-schemas` change defined the Task schema in `planix_register.json`. The `projects` change built the project list, creation, settings, and a placeholder `ProjectBacklog.vue` view. This change builds the task interaction layer on top of those foundations.

Planix is a thin client: it owns no database tables and performs all data operations through the OpenRegister REST API. All task state lives in OpenRegister objects. The Vue frontend queries OpenRegister directly via `useObjectStore('planix', 'task')` from `@conduction/nextcloud-vue`. PHP is used only for the notification layer (which requires server-side Nextcloud integration) and SPA route registration.

---

## Goals

- Task CRUD: create, read, update, delete tasks via OpenRegister.
- Task detail view using `CnDetailPage` + `CnObjectSidebar`.
- Reusable `TaskCard` component for backlog and kanban board.
- Task creation dialog with full field support.
- Pinia task store with status lifecycle, search/filter, and bulk operations.
- Backlog list view using `CnDataTable` with column-less task filter.
- Search by title/description (client-side, debounced); filter by assignee, priority, label, status (server-side).
- Bulk status update and bulk assignee update from backlog.
- PHP `NotificationService` with `task_assigned` and `task_due_soon` subjects.
- PHP `TaskNotifier` for Nextcloud notification bell rendering.
- Full i18n coverage (en + nl).

## Non-Goals

- Kanban board drag-and-drop (separate `kanban-board` change — this change only provides `TaskCard`).
- Time tracking UI (separate `time-tracking` change — `estimatedDuration` and `percentComplete` fields are stored but not surfaced here).
- Dashboard / My Work view (separate `dashboard-my-work` change).
- Sub-task creation and tree navigation (V1 feature — the `parent` field is stored but no UI is built here).
- Task dependencies (V1 feature).
- PHP controllers for task data — OpenRegister is queried directly from the frontend.
- `task_overdue`, `task_commented`, `task_status_changed` notification subjects (V1 — declared in `SUBJECT_SETTING_MAP` with `V1` flag but not triggered).

---

## Decisions

### Decision 1: TaskCard is a pure display component with stable props

**Options considered:**
1. TaskCard fetches its own data (self-contained).
2. TaskCard receives a full task object as a prop (chosen).

**Rationale:** The `kanban-board` change will render many `TaskCard` instances simultaneously on a board. Having each card fetch its own data would result in N API calls per board render. The card must be a pure display component that receives its data from the parent (backlog list, kanban column). This also makes the component trivially testable and reusable.

The stable prop interface is:
```js
props: {
  task: { type: Object, required: true },   // full task object from store
  compact: { type: Boolean, default: false } // compact mode for kanban columns
}
```

Breaking changes to this interface require a coordinated update with the `kanban-board` change.

### Decision 2: Task detail view uses CnDetailPage + CnObjectSidebar with metadata panel

**Options considered:**
1. Full-page form for task editing.
2. `CnDetailPage` (main content) + `CnObjectSidebar` (metadata + actions) — chosen.

**Rationale:** Consistent with the pattern used in all other Conduction apps (pipelinq, openconnector, etc.). The main content area renders the task title and description (inline editable). The sidebar provides metadata panels: Assignee, Priority, Due Date, Labels, Project, Status — all inline editable. Standard `CnObjectSidebar` tabs (Files, Notes, Tags, Audit Trail) are appended after the metadata panel.

### Decision 3: NotificationService uses SUBJECT_SETTING_MAP pattern

**Options considered:**
1. Direct `INotificationManager` calls in the store action (PHP controller).
2. Dedicated `NotificationService` with subject→setting mapping (chosen, matches pipelinq pattern).

**Rationale:** A `SUBJECT_SETTING_MAP` centralises notification configuration — each subject maps to the user setting key that controls whether that notification type is enabled. This makes it easy to add V1 subjects later and ensures all notification logic is in one place. The service handles: resolving user notification preferences, suppressing self-notifications, and constructing the `INotification` object.

The MVP subjects:
| Subject | Setting key | Default |
|---------|------------|---------|
| `task_assigned` | `notify_assigned` | `true` |
| `task_due_soon` | `notify_due_reminder` | `true` |

V1 subjects (declared but not triggered in this change):
| Subject | Setting key | Default |
|---------|------------|---------|
| `task_overdue` | `notify_overdue` | `true` |
| `task_commented` | `notify_commented` | `true` |
| `task_status_changed` | `notify_status_changed` | `false` |

### Decision 4: Notifications triggered via PHP endpoint, not directly from frontend

**Options considered:**
1. Frontend triggers notification directly (not possible — PHP-only API).
2. Frontend calls a lightweight PHP controller endpoint that delegates to `NotificationService` (chosen).

**Rationale:** Nextcloud notifications are a server-side PHP API (`OCP\Notification\IManager`). The frontend cannot call it directly. A minimal `TaskController` with a single `POST /notify` action receives `{ subject, taskId, targetUserId }` and calls `NotificationService::notify()`. This keeps the controller thin and the logic in the service.

### Decision 5: Backlog uses CnDataTable with row-level and bulk actions

**Options considered:**
1. Custom list component.
2. `CnDataTable` from `@conduction/nextcloud-vue` (chosen).

**Rationale:** `CnDataTable` provides built-in row selection (for bulk ops), sortable columns, pagination, and loading states. The backlog is a tabular view of tasks (title, assignee, priority, status, due date). Bulk action bar appears when rows are selected — this is standard `CnDataTable` behaviour.

### Decision 6: New tasks go to backlog (column: null) by default

New tasks created via `TaskCreateDialog` always have `column: null` (backlog). The user may optionally assign them to a column at creation time via the "Column" dropdown (which lists the current project's columns). This is consistent with the spec requirement that tasks are in the backlog by default.

### Decision 7: Task deletion prompts if the task has sub-tasks (V1 guard)

The `parent` field is supported in the schema but sub-task creation UI is V1. At MVP, a task may have sub-tasks created via direct API. The `TaskDeleteDialog` checks for `children` (tasks with `parent === taskId`) and shows a blocking warning: "This task has {N} sub-tasks. Deleting it will also delete all sub-tasks. Continue?" The delete cascade includes all sub-tasks and their time entries.

### Decision 8: Overdue due date shown in red on TaskCard

Due date display logic on `TaskCard`:
- Future due date: neutral color chip (`NcChip`).
- Today: amber chip.
- Past due date: red chip with "Overdue" prefix if `status !== 'done' && status !== 'cancelled'`.

This is pure CSS/computed logic — no server-side date processing needed.

### Decision 9: Bulk operations only available from backlog view

Bulk status update and bulk assignee update are only exposed in the `ProjectBacklog.vue` view via `CnDataTable` row selection. They are not available from the global task list (`TaskList.vue`) in MVP. The global task list is read-only (navigate to task detail on click).

---

## Component Architecture

```
src/
  views/
    TaskList.vue              # /tasks — global CnDataTable task list
    TaskDetail.vue            # /tasks/:id — CnDetailPage + CnObjectSidebar
  components/
    TaskCard.vue              # Reusable card (backlog + kanban board)
    TaskMetaSidebar.vue       # Metadata panel in CnObjectSidebar
    TaskStatusBadge.vue       # Status chip with colour coding
    TaskBulkActionBar.vue     # Bulk action toolbar for CnDataTable selection
    dialogs/
      TaskCreateDialog.vue    # NcDialog — create task
      TaskDeleteDialog.vue    # NcDialog — delete with sub-task warning
  store/
    tasks.js                  # Pinia store (useObjectStore wrapper)
  router/
    index.js                  # Modified — add task routes
  navigation/
    MainMenu.vue              # Modified — add Tasks nav entry

lib/
  Service/
    NotificationService.php   # SUBJECT_SETTING_MAP, notify(), notifyDueSoon()
  Notifier/
    TaskNotifier.php          # INotifier implementation
  AppInfo/
    Application.php           # Modified — register TaskNotifier

appinfo/
  routes.php                  # Modified — add /tasks, /tasks/{id} routes
```

---

## Pinia Store: `useTasksStore`

```js
// src/store/tasks.js
import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { useObjectStore } from '@conduction/nextcloud-vue'

export const useTasksStore = defineStore('tasks', () => {
  const objectStore = useObjectStore('planix', 'task')

  // State
  const tasks = ref([])
  const activeTask = ref(null)
  const loading = ref(false)
  const error = ref(null)
  const searchQuery = ref('')
  const activeFilters = ref({})

  // Getters
  const filteredTasks = computed(() => {
    let result = tasks.value
    if (searchQuery.value) {
      const q = searchQuery.value.toLowerCase()
      result = result.filter(t =>
        t.title?.toLowerCase().includes(q) ||
        t.description?.toLowerCase().includes(q)
      )
    }
    return result
  })

  // Actions
  async function fetchTasks(filters = {}) { /* ... */ }
  async function fetchTask(id) { /* ... */ }
  async function createTask(data) { /* ... */ }
  async function updateTask(id, data) { /* ... */ }
  async function deleteTask(id) {
    // 1. Check for sub-tasks (tasks with parent === id)
    // 2. Fetch and delete TimeEntries for this task (and sub-tasks)
    // 3. Delete sub-tasks (if confirmed)
    // 4. Delete the task
  }
  async function updateStatus(id, status) { /* PATCH status + completedAt logic */ }
  async function bulkUpdateStatus(ids, status) { /* sequential PATCHes */ }
  async function bulkUpdateAssignee(ids, assignedTo) { /* sequential PATCHes + notifications */ }
  async function assignTask(id, userUid) { /* PATCH + trigger notification */ }
  async function notifyAssignment(taskId, targetUserId) {
    // POST /planix/tasks/{taskId}/notify with { subject: 'task_assigned', targetUserId }
  }
  function setSearch(query) { searchQuery.value = query }
  function setFilters(filters) { activeFilters.value = filters }

  return {
    tasks, activeTask, loading, error, searchQuery, activeFilters, filteredTasks,
    fetchTasks, fetchTask, createTask, updateTask, deleteTask,
    updateStatus, bulkUpdateStatus, bulkUpdateAssignee, assignTask,
    setSearch, setFilters,
  }
})
```

---

## Status Lifecycle

| From | To | Side Effect |
|------|----|-------------|
| `open` | `in_progress` | — |
| `open` | `blocked` | — |
| `open` | `done` | Set `completedAt = now()` |
| `open` | `cancelled` | — |
| `in_progress` | `blocked` | — |
| `in_progress` | `done` | Set `completedAt = now()` |
| `in_progress` | `cancelled` | — |
| `blocked` | `in_progress` | — |
| `blocked` | `done` | Set `completedAt = now()` |
| `done` | `open` | Clear `completedAt` |
| `cancelled` | `open` | — |

Moving a task to a `done`-type kanban column automatically transitions status to `done` (handled by the `kanban-board` change, not this change). This change handles status transitions from the task detail view and backlog bulk actions.

---

## TaskCard Component Anatomy

```
┌─────────────────────────────────────────────┐
│ [priority indicator bar — left edge color]  │
│                                             │
│  Task title (truncated to 2 lines)          │
│                                             │
│  [label chip 1] [label chip 2]  ...         │
│                                             │
│  [assignee avatar]    [due date chip]       │
└─────────────────────────────────────────────┘
```

Priority indicator bar colors (CSS variables):
- `low`: `--color-info`
- `normal`: transparent / none
- `high`: `--color-warning`
- `urgent`: `--color-error`

Due date chip states:
- Future: default `NcChip` (neutral)
- Today: `NcChip` with `--color-warning` background
- Overdue and not done/cancelled: `NcChip` with `--color-error` background and "Overdue:" prefix

---

## PHP NotificationService

```php
// lib/Service/NotificationService.php

class NotificationService {
    private const SUBJECT_SETTING_MAP = [
        'task_assigned'       => 'notify_assigned',
        'task_due_soon'       => 'notify_due_reminder',
        // V1 — declared but not triggered in this change:
        'task_overdue'        => 'notify_overdue',
        'task_commented'      => 'notify_commented',
        'task_status_changed' => 'notify_status_changed',
    ];

    public function notify(string $subject, array $params, string $targetUserId): void {
        // 1. Look up setting key from SUBJECT_SETTING_MAP
        // 2. Check user's notification preference (default: true for MVP subjects)
        // 3. Guard: if targetUserId === currentUserId, skip (no self-notifications)
        // 4. Create and send INotification
    }

    public function notifyDueSoon(string $taskId, string $taskTitle, string $assignedUserId): void {
        // Convenience wrapper for task_due_soon subject
    }
}
```

---

## PHP Notifier

```php
// lib/Notifier/TaskNotifier.php implements INotifier

public function getID(): string { return 'planix'; }

public function getName(): string { return $this->l10n->t('Planix'); }

public function prepare(INotification $notification, string $languageCode): INotification {
    // Route by subject:
    // 'task_assigned'  → '{assigner} assigned you to task "{title}"'
    // 'task_due_soon'  → 'Task "{title}" is due {dueDate}'
    // Set rich objects (task link, user)
}
```

---

## Vue Router Routes

| Path | Name | Component | Props |
|------|------|-----------|-------|
| `/tasks` | `Tasks` | `TaskList` | — |
| `/tasks/:id` | `TaskDetail` | `TaskDetail` | `route => ({ taskId: route.params.id })` |

---

## PHP Routing

```php
// appinfo/routes.php additions
['name' => 'page#tasks',      'url' => '/tasks',       'verb' => 'GET'],
['name' => 'page#task',       'url' => '/tasks/{id}',  'verb' => 'GET'],
['name' => 'task#notify',     'url' => '/tasks/{id}/notify', 'verb' => 'POST'],
```

---

## i18n String Inventory

| Key context | Example string |
|-------------|----------------|
| Navigation | `Tasks` |
| List header | `All Tasks`, `My Tasks` |
| Backlog header | `Backlog` |
| Empty backlog | `No tasks in backlog`, `Add your first task` |
| Create dialog title | `Create task` |
| Create dialog fields | `Title`, `Description`, `Priority`, `Due date`, `Assignee`, `Labels`, `Column` |
| Create dialog actions | `Create`, `Cancel` |
| Priority labels | `Low`, `Normal`, `High`, `Urgent` |
| Status labels | `Open`, `In progress`, `Blocked`, `Done`, `Cancelled` |
| Due date | `Due {date}`, `Overdue: {date}`, `Due today` |
| Detail view | `Task details`, `Mark as done`, `Unassigned` |
| Sidebar sections | `Assignee`, `Priority`, `Due date`, `Labels`, `Project`, `Status` |
| Delete confirm title | `Delete task` |
| Delete confirm — no sub-tasks | `This will permanently delete the task and all its time entries. This cannot be undone.` |
| Delete confirm — sub-tasks | `This task has {count} sub-tasks. Deleting it will also delete all sub-tasks and their time entries.` |
| Bulk action bar | `{count} tasks selected`, `Update status`, `Update assignee`, `Clear selection` |
| Bulk success | `{count} tasks updated` |
| Notification — assigned | `{assigner} assigned you to task "{title}"` |
| Notification — due soon | `Task "{title}" is due {dueDate}` |
| Error states | `Failed to load tasks`, `Failed to create task`, `Failed to delete task` |
| Validation | `Title is required` |

---

## Risks and Trade-offs

| Risk | Likelihood | Mitigation |
|------|-----------|-----------|
| `TaskCard` props interface changes break `kanban-board` change | Low | Document stable interface in this spec; treat as a contract. Any change requires coordinated PR with kanban-board. |
| Bulk operations on large sets are slow (sequential PATCHes) | Low | MVP task sets are small. Future: batch API in OpenRegister. Show progress indicator for bulk > 10 tasks. |
| Notification endpoint adds PHP complexity for thin-client app | Medium | Keep `TaskController` minimal — single action, delegates entirely to `NotificationService`. No business logic in controller. |
| `completedAt` set client-side may drift from server time | Low | Use `new Date().toISOString()` in the store action. Acceptable at MVP — not used for billing or SLA enforcement. |
| Self-notification suppression relies on Nextcloud session user | Low | Use `\OC::$server->getUserSession()->getUser()->getUID()` in `NotificationService`. Well-established NC pattern. |
