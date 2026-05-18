## Why

Tasks in Planix have no built-in discussion mechanism. Team members must leave Planix and use external tools (email, chat) to discuss task details, decisions, or blockers — breaking the workflow and losing context. Adding inline comments via Nextcloud's native `ICommentsManager` brings discussion to where the work lives, using infrastructure already present in the platform.

## What Changes

- **New capability**: `task-comments` — authenticated users can list, post, and delete comments on individual tasks. Comments are stored by Nextcloud's `ICommentsManager` using `objectType: 'planix_task'` and `objectId: <task uuid>`. A new `CommentController` exposes three REST endpoints. A new `TaskComments.vue` tab renders in the `CnObjectSidebar` task detail view alongside the existing Details, Files, and Audit Trail tabs.
- **Modified capability**: `tasks` — task cards and list rows gain a `commentCount` badge (read-only integer derived at display time from the ICommentsManager count) so teams can see at a glance which tasks have discussion.

## Capabilities

### New Capabilities

- `task-comments`: Covers listing comments on a task (paginated), posting a new comment (max 5 000 chars), and deleting a comment (own comment or admin). Backed by `OCP\Comments\ICommentsManager` — no OpenRegister objects involved.

### Modified Capabilities

- `tasks`: Task card and list display gains a `commentCount` indicator. The spec delta adds REQ-TC-005 (comment count visible on card). No changes to the task data model itself — the count is fetched from ICommentsManager at render time, not stored on the task object.

## Impact

- **New file**: `lib/Controller/CommentController.php` — wraps `ICommentsManager` for list/create/delete
- **Modified file**: `appinfo/routes.php` — three new routes under `/api/tasks/{id}/comments`
- **New file**: `src/components/TaskComments.vue` — comments tab component
- **Modified file**: `src/views/TaskDetail.vue` (or equivalent CnObjectSidebar host) — wires the new tab
- **Modified file**: `src/components/TaskCard.vue` — adds comment count badge
- **Dependencies**: No new PHP or JS dependencies; `ICommentsManager` is part of Nextcloud OCP
- **Notifications**: `NotificationService` is NOT extended in this change — comment notifications are V1 scope
- **No database migrations**: ICommentsManager handles its own storage; Planix adds zero tables
