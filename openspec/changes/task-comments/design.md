## Context

Planix tasks currently have no built-in discussion channel. `ICommentsManager` is the standard Nextcloud mechanism for attaching threaded comments to arbitrary objects (Files, Talk rooms, Deck cards all use it). It handles storage, actor resolution, pagination, and delete-permission checks — Planix needs only a thin controller layer and a frontend tab to surface it.

The existing `CnObjectSidebar` in the task detail view already has a tab slot pattern (Details, Files, Audit Trail). Adding a Comments tab follows exactly the same convention with no structural changes to the sidebar host.

## Goals / Non-Goals

**Goals:**
- Expose `GET / POST / DELETE` comment endpoints on tasks via a new `CommentController`
- Render comments in a new `TaskComments.vue` tab in the task detail sidebar
- Show a comment count badge on task cards so teams see active discussions at a glance
- Reuse `ICommentsManager` completely — no new database tables or OpenRegister objects

**Non-Goals:**
- @mention support in comments (V1)
- Comment notifications via `NotificationService` (V1)
- Talk integration or rich-text comments (V1)
- Comment threading / replies (V1)
- Editing an existing comment (V1 — ICommentsManager supports it but it is out of scope here)

## Decisions

### Decision 1: Use ICommentsManager, not OpenRegister

**Choice**: Store comments via `OCP\Comments\ICommentsManager`.

**Rationale**: OpenRegister is Planix's data layer for domain objects (tasks, projects, columns). Comments are cross-cutting platform infrastructure — Files, Deck, Bookmarks, and Nextcloud core already use `ICommentsManager` for the same pattern. Storing comments in OpenRegister would mean duplicating schema definitions, pagination helpers, and actor-resolution logic that already exist in core. ICommentsManager provides `objectType / objectId` namespacing, so Planix's comment space is fully isolated.

**Alternative considered**: Custom OpenRegister schema `planix_comment`. Rejected: adds schema maintenance burden, bypasses platform permissions, and re-implements actor display logic that ICommentsManager already provides.

### Decision 2: Backend controller, not direct frontend API call

**Choice**: Thin PHP `CommentController` in `lib/Controller/` with three routes registered in `appinfo/routes.php`.

**Rationale**: `ICommentsManager` is a server-side OCP interface. The frontend cannot call it directly. The controller adds minimal logic: validate input, delegate to ICommentsManager, return JSON. Delete permission is enforced in the controller (own comment, or Nextcloud admin check via `IUserSession`).

**Routes**:
```
GET  /apps/planix/api/tasks/{id}/comments          → list (paginated: ?limit=20&offset=0)
POST /apps/planix/api/tasks/{id}/comments          → create
DELETE /apps/planix/api/tasks/{id}/comments/{cid}  → delete
```

All routes require authentication (`@NoAdminRequired`, NOT `@NoCSRFRequired` for POST/DELETE).

### Decision 3: Comment count on task card is a best-effort display field

**Choice**: Fetch comment count via `ICommentsManager::getNumberOfCommentsForObject('planix_task', $id)` in a lightweight endpoint or include it in the task list response from the backend.

**Rationale**: Task cards are rendered from OpenRegister task objects. `commentCount` is not a stored field on the task — it is a derived integer from ICommentsManager. Adding a dedicated batch endpoint (`GET /api/tasks/comment-counts?ids[]=...`) avoids N+1 calls when rendering a board. The count is display-only; stale by a few seconds is acceptable (no real-time subscription in MVP).

**Alternative considered**: Include `commentCount` in every task API response (computed per task on the backend). Rejected for board performance: loading 50 tasks would trigger 50 `getNumberOfCommentsForObject` calls. A batch endpoint is cheaper.

### Decision 4: Frontend comment store — lightweight local state, not a Pinia store

**Choice**: `TaskComments.vue` manages its own `comments` array via `ref()` and calls the controller endpoints directly using `axios` (already used by the app).

**Rationale**: Comments are always scoped to one task at a time (the open sidebar). A global Pinia store adds complexity without benefit — there is no cross-component comment state. The Audit Trail tab follows the same pattern.

**Alternative considered**: New `useCommentsStore` Pinia store. Rejected for this change; can be introduced if cross-component needs emerge in V1.

## Risks / Trade-offs

- **ICommentsManager actor types**: The `create()` call requires `$actorType = ICommentsManager::COMMENT_ACTOR_TYPE_USERS`. If Planix ever allows service/bot actors, this will need revisiting. For MVP, user-only actors are correct.
- **Comment count batch endpoint scope creep**: If the board has >100 tasks, one batch call may still be slow. Mitigation: paginate the board and only request counts for visible tasks; cache client-side for the session duration.
- **Delete authorization edge case**: ICommentsManager does not enforce "only own comment" at the storage level — it returns any comment by ID. The controller MUST check `$comment->getActorId() === $this->userId` before deleting, with an admin override via `$this->userSession->getUser()->isAdmin()`. Missing this check would allow any authenticated user to delete any comment.
- **No real-time updates**: Comments posted by other users will not appear until the tab is re-opened or manually refreshed. V1 note for notifications/polling.

## Migration Plan

No database migrations. No OpenRegister schema changes. Deployment steps:

1. Deploy the updated app (new controller + routes + Vue component).
2. The Comments tab appears automatically in the task sidebar for all users.
3. Rollback: revert the deploy. ICommentsManager data persists in Nextcloud's `comments` table but is simply no longer surfaced in the Planix UI.

## Open Questions

- Should the comment count badge on task cards auto-refresh on a polling interval, or only update when the task is opened? Decision: no polling in MVP — badge reflects count at page load / board refresh.
- Should deleted comments leave a tombstone ("comment deleted by admin") or disappear silently? Decision: silent delete in MVP, matching Deck behavior.
