# Tasks: Task Comments

## 1. Backend — CommentController

- [ ] 1.1 Create `lib/Controller/CommentController.php` — inject `ICommentsManager`, `IUserSession`, `IRequest`; add `@NoAdminRequired` annotation on all three methods; implement `listComments($taskId, $limit, $offset)` returning comments array with `id`, `message`, `actorId`, `actorDisplayName`, `creationDateTime` [@spec REQ-TC-001]
- [ ] 1.2 Implement `createComment($taskId)` in `CommentController` — validate message 1–5 000 chars (return 422 on violation), call `ICommentsManager::create()` with `actorType: COMMENT_ACTOR_TYPE_USERS`, return 201 with comment object [@spec REQ-TC-002]
- [ ] 1.3 Implement `deleteComment($taskId, $commentId)` in `CommentController` — fetch comment, check `actorId === $userId` OR user is NC admin (`$user->isAdmin()`), call `ICommentsManager::delete($commentId)`, return 204; return 403 if not authorized, 404 if not found [@spec REQ-TC-003]
- [ ] 1.4 Implement `getCommentCounts()` batch endpoint in `CommentController` — accept `?ids[]=...` query param, call `ICommentsManager::getNumberOfCommentsForObject('planix_task', $id)` for each, return map `{taskId: count}` [@spec REQ-TC-005]

## 2. Backend — Route Registration

- [ ] 2.1 Register routes in `appinfo/routes.php`: `GET /api/tasks/{id}/comments`, `POST /api/tasks/{id}/comments`, `DELETE /api/tasks/{id}/comments/{commentId}`, `GET /api/tasks/comment-counts` — all pointing to `CommentController` [@spec REQ-TC-001, REQ-TC-002, REQ-TC-003, REQ-TC-005]

## 3. Frontend — TaskComments.vue Component

- [ ] 3.1 Create `src/components/TaskComments.vue` — `props: { taskId: String }`, fetch comments via `GET /apps/planix/api/tasks/{taskId}/comments` on mount, store in local `ref([])` array [@spec REQ-TC-004]
- [ ] 3.2 Add comment display loop in `TaskComments.vue` — render `actorDisplayName`, relative timestamp (use `@nextcloud/moment` or date-fns already in project), and `message` for each comment [@spec REQ-TC-004]
- [ ] 3.3 Add compose area in `TaskComments.vue` — `<textarea>` bound to `draft` ref, "Post" button disabled when `draft.trim().length === 0` or `draft.length > 5000`, inline char-count warning at 5 000 [@spec REQ-TC-004]
- [ ] 3.4 Implement post action in `TaskComments.vue` — call `POST /apps/planix/api/tasks/{taskId}/comments`, optimistically prepend to list on 201, clear draft, show error toast on failure via `@nextcloud/dialogs` [@spec REQ-TC-004]
- [ ] 3.5 Add delete action in `TaskComments.vue` — show `…` menu on own comments (compare `actorId` to current NC user from `useCurrentUserStore` or `getRequestToken`/`OC.currentUser`), show confirmation dialog before calling `DELETE`, remove comment from list on 204 [@spec REQ-TC-003, REQ-TC-004]

## 4. Frontend — Wire Comments Tab into Task Detail Sidebar

- [ ] 4.1 Import `TaskComments.vue` into `TaskDetail.vue` (or the CnObjectSidebar host component) and add it as a tab alongside Details, Files, and Audit Trail — pass `taskId` as prop; label the tab "Comments" [@spec REQ-TC-004]

## 5. Frontend — Comment Count Badge on Task Card

- [ ] 5.1 Add `fetchCommentCounts(taskIds)` action in the tasks store (`src/store/tasks.js`) — calls `GET /apps/planix/api/tasks/comment-counts?ids[]=...`, stores result in a `commentCounts` map keyed by task ID [@spec REQ-TC-005-TASK]
- [ ] 5.2 Dispatch `fetchCommentCounts` in the board/list view after tasks are loaded — pass all visible task IDs in a single call [@spec REQ-TC-005-TASK]
- [ ] 5.3 Add comment count badge to `TaskCard.vue` — conditionally render a small `NcCounterBubble` (or equivalent) showing `commentCounts[task.id]` only when the count is `> 0` [@spec REQ-TC-005, REQ-TC-005-TASK]

## 6. Tests

- [ ] 6.1 Write PHPUnit unit test for `CommentController::listComments` — mock `ICommentsManager`, assert response structure and HTTP 200 [@spec REQ-TC-001]
- [ ] 6.2 Write PHPUnit unit test for `CommentController::createComment` — assert 201 on valid input, 422 on empty message, 422 on message > 5 000 chars [@spec REQ-TC-002]
- [ ] 6.3 Write PHPUnit unit test for `CommentController::deleteComment` — assert 204 for own comment, 204 for admin, 403 for non-owner non-admin, 404 for missing comment [@spec REQ-TC-003]
