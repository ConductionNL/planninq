# Tasks: Tasks Specification

## Deduplication Check

- [ ] Search `openspec/specs/` and `openregister/lib/Service/` for any existing Task entity, task controller, or task CRUD capability that overlaps with this change before writing any new code. Document findings (expected: no overlap — Task is new to Planix).

---

## 1. Backend — Schema Registration

- [ ] 1.1 Add `task` schema to `lib/Settings/planix_register.json` with all properties from the context brief: `title`, `description`, `status` (enum: open/in_progress/blocked/done/cancelled), `priority` (enum: low/normal/high/urgent), `project` (relation), `column` (relation), `columnOrder`, `assignedTo`, `dueDate`, `startDate`, `estimatedDuration`, `percentComplete`, `labels` (array), `parent` (relation), `calendarEventUid`, `completedAt`
- [ ] 1.2 Set `status` default to `open` and `priority` default to `normal` in the schema definition
- [ ] 1.3 Mark `title` and `status` as `required: true` in the schema

---

## 2. Backend — Seed Data

- [ ] 2.1 Add 5 seed task objects to `lib/Settings/planix_register.json` under `components.objects[]` using the `@self` envelope (`register: planix`, `schema: task`, unique `slug` per object). Use the Dutch values defined in `design.md` (Seed 1–5). Verify slugs are unique and idempotent (re-import skips existing by slug).

---

## 3. Backend — Routes

- [ ] 3.1 Add Task API routes to `appinfo/routes.php`:
  - `GET    /api/tasks` → `TaskController::index`
  - `POST   /api/tasks` → `TaskController::create`
  - `GET    /api/tasks/{id}` → `TaskController::show`
  - `PUT    /api/tasks/{id}` → `TaskController::update`
  - `PATCH  /api/tasks/{id}` → `TaskController::patch`
  - `DELETE /api/tasks/{id}` → `TaskController::destroy`
  - `POST   /api/tasks/bulk` → `TaskController::bulk`
- [ ] 3.2 Ensure specific routes are declared BEFORE any existing wildcard `{slug}` routes in `routes.php` (ADR-003)

---

## 4. Backend — TaskController

- [ ] 4.1 Create `lib/Controller/TaskController.php` with methods: `index`, `create`, `show`, `update`, `patch`, `destroy`, `bulk`. Each method MUST be ≤ 10 lines (routing + validation + response only — no business logic). Add `@spec openspec/changes/tasks/tasks.md` PHPDoc tag on class and each public method.
- [ ] 4.2 Inject `TaskService` via constructor DI (`private readonly TaskService $taskService`)
- [ ] 4.3 `index` passes filter params (project, column, status, priority, assignee, search) to `TaskService::findAll()`
- [ ] 4.4 `bulk` accepts `{ ids: string[], patch: { status?, assignedTo? } }` and delegates to `TaskService::bulkUpdate()`

---

## 5. Backend — TaskService

- [ ] 5.1 Create `lib/Service/TaskService.php`. Add `@spec` PHPDoc tag on class and all public methods.
- [ ] 5.2 Implement `save(array $data, string $userId): object` — applies defaults (`status: open`, `priority: normal` if absent), delegates to `ObjectService::saveObject()`
- [ ] 5.3 Implement `handleColumnMove(object $task, ?string $newColumnId): void` — if target column `type === 'done'` set `status = 'done'` and `completedAt = now()` server-side; evaluate WIP soft-limit (return warning flag, do NOT block save)
- [ ] 5.4 Implement `handleProjectMove(object $task, string $newProjectId): void` — if `project` changes, set `column = null` and `columnOrder = 0`
- [ ] 5.5 Implement `notifyAssigned(object $task, string $requestingUserId): void` — skip if `$task->assignedTo === $requestingUserId`; skip if `notify_assigned` user setting is `false`; otherwise dispatch `NotificationService` with subject `task_assigned`
- [ ] 5.6 Implement `destroy(string $taskId, string $userId): void` — verify requester is task creator, project creator, or NC admin (HTTP 403 otherwise); delete all linked TimeEntry objects; then call `ObjectService::deleteObject()`
- [ ] 5.7 Implement `bulkUpdate(array $ids, array $patch, string $userId): int` — iterate ids, apply patch fields (`status`, `assignedTo`) per task, trigger `notifyAssigned` for each task where `assignedTo` changes; return count of updated tasks
- [ ] 5.8 Implement `findAll(array $filters): array` — delegates to `ObjectService::findAll()` passing filter params; no custom query builder

---

## 6. Frontend — Task Store

- [ ] 6.1 Create `src/store/modules/tasks.js` using `createObjectStore('tasks')` with `selectionPlugin` (bulk ops) and `relationsPlugin` (project/column lookups). Register in `store/store.js` with `objectStore.registerObjectType('tasks', 'task', 'planix')`

---

## 7. Frontend — TaskCard Component

- [ ] 7.1 Create `src/components/TaskCard.vue` — kanban card displaying: title, assignee avatar (`NcAvatar`), due date (formatted), priority color chip (`CnStatusBadge`), and label chips. All user-visible strings via `t(appName, '...')`.
- [ ] 7.2 Priority color MUST use Nextcloud CSS variables (`var(--color-error)` for urgent, `var(--color-warning)` for high, etc.) — NO hardcoded hex values
- [ ] 7.3 Priority color MUST be accompanied by a text label (WCAG 1.4.1 — color is not the sole indicator)
- [ ] 7.4 Expose `title`, `status`, `priority`, and `assignedTo` to screen readers via `aria-label` or visible text

---

## 8. Frontend — TaskBoardView

- [ ] 8.1 Create `src/views/TaskBoardView.vue` — renders kanban columns using `vue-draggable`. Fetches columns and tasks for the active project in parallel (`Promise.all`).
- [ ] 8.2 On drag `@end`: dispatch store PATCH with `{ column: newColumnId, columnOrder: newIndex }`. Show `NcLoadingIcon` on the card during the save.
- [ ] 8.3 If WIP limit is exceeded after drop: show `CnStatusBadge` warning on the column header (soft limit — task is still saved). Warning disappears when task count drops back to or below `wipLimit`.
- [ ] 8.4 Filter bar (`CnFilterBar`) for `priority`, `assignedTo`, and `labels` — applied without page reload using `useListView` composable
- [ ] 8.5 All `await store.action()` calls MUST be wrapped in `try/catch` with user-facing error feedback (`NcDialogs.showError` or toast)

---

## 9. Frontend — TaskBacklogView

- [ ] 9.1 Create `src/views/TaskBacklogView.vue` — list of tasks where `column === null` for the active project. Use `CnDataTable` via `useListView(entityType, { sidebarState, objectStore })`.
- [ ] 9.2 Add `CnMassActionBar` for bulk operations: "Change status" (opens status picker) and "Assign to" (opens user picker). On confirm, call `taskStore.bulkUpdate(selectedIds, patch)`.
- [ ] 9.3 On bulk complete, show toast: `t(appName, 'N tasks updated', { n: count })`. `try/catch` on the store call with error feedback.
- [ ] 9.4 Search field drives `useListView` search — filters by `title` and `description`, case-insensitive, without page reload

---

## 10. Frontend — TaskDetailView

- [ ] 10.1 Create `src/views/TaskDetailView.vue` — supports two modes: new task (`id === 'new'`) and existing task (`id` from route param). Use `CnFormDialog` for create/edit form (schema-driven field generation).
- [ ] 10.2 Render read mode with `CnDetailPage` + `CnDetailCard` sections for: task properties, assigned project/column, and sub-tasks placeholder (V1 — empty state with note "Sub-tasks coming in V1").
- [ ] 10.3 Attach `CnObjectSidebar` for files, notes, audit trail tabs.
- [ ] 10.4 Header actions: Edit button (switches to form mode) and Delete button (opens `CnDeleteDialog`). Delete MUST check for sub-tasks client-side and show the V1 guard dialog (`src/dialogs/DeleteTaskDialog.vue`) if sub-tasks exist.
- [ ] 10.5 Create `src/dialogs/DeleteTaskDialog.vue` (NcDialog-based) — "Delete all sub-tasks" or "Move sub-tasks to backlog" options. MUST NOT be inline in the parent component (ADR-004 modal isolation rule).

---

## 11. Frontend — Router

- [ ] 11.1 Add named routes to `src/router/index.js`:
  - `/tasks` → `TaskBacklogView` (name: `TaskBacklog`)
  - `/tasks/:id` → `TaskDetailView` (name: `TaskDetail`, props via arrow function)
- [ ] 11.2 Add "Board" and "Backlog" nav items to `MainMenu.vue` with appropriate MDI icons

---

## 12. Frontend — Translations

- [ ] 12.1 Add all new translation keys to `l10n/en.json` (English source)
- [ ] 12.2 Add Dutch translations for all keys to `l10n/nl.json`, including: status labels, priority labels, bulk action labels, notification strings, dialog prompts, toast messages, and ARIA labels

---

## 13. Spec Traceability

- [ ] 13.1 Verify every new PHP class and public method has `@spec openspec/changes/tasks/tasks.md` PHPDoc tag (ADR-003)
- [ ] 13.2 File-level `@spec` tag in header docblock of `TaskController.php` and `TaskService.php`

---

## 14. Verify

- [ ] 14.1 Run `composer audit` — no new CVEs introduced
- [ ] 14.2 Run PHP lint / static analysis on `TaskController.php` and `TaskService.php`
- [ ] 14.3 Confirm `lib/Settings/planix_register.json` is valid JSON and the `task` schema slug matches the store registration (`objectStore.registerObjectType('tasks', 'task', 'planix')`)
- [ ] 14.4 Confirm the 5 seed objects have unique slugs and realistic Dutch field values
- [ ] 14.5 Confirm Task routes in `appinfo/routes.php` appear before any wildcard `{slug}` route
- [ ] 14.6 Confirm no `window.confirm()`, `window.alert()`, raw `fetch()`, or `document.getElementById` appear in new Vue files
- [ ] 14.7 Confirm `CnDeleteDialog` and `DeleteTaskDialog.vue` are in their own files (not inline in parent)
- [ ] 14.8 Confirm `NcSelect` inputs use `inputLabel` prop — no manual `<label>` elements
- [ ] 14.9 Confirm all `await store.action()` calls in new views are wrapped in `try/catch`
- [ ] 14.10 Confirm `AdminRoot.vue` (if any) is NOT added to vue-router
