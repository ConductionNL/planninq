# Task Comments Specification

**Status**: in-progress

**Standards**: Schema.org Comment, OCP ICommentsManager
**Feature tier**: MVP #38 "Notes/comments on tasks (ICommentsManager)"

**OpenSpec changes:**
- [task-comments](../../) — introduces this capability

## Purpose

Allow team members to post, read, and delete text comments on individual tasks so that task-level discussion stays in context alongside the work, using Nextcloud's native `ICommentsManager` for storage and actor resolution.

## ADDED Requirements

### Requirement: REQ-TC-001 — List Comments on a Task [MVP]

The system MUST allow any authenticated Planix user to retrieve the list of comments for a task they have access to.

The endpoint `GET /apps/planix/api/tasks/{id}/comments` MUST return comments in reverse-chronological order (newest first). The response MUST support pagination via `limit` (default 20, max 100) and `offset` query parameters.

Each comment in the response MUST include:
- `id` — the ICommentsManager comment ID
- `message` — the comment text
- `actorId` — the Nextcloud user UID of the author
- `actorDisplayName` — the resolved display name of the author
- `creationDateTime` — ISO 8601 timestamp

The endpoint MUST return HTTP 404 if the task does not exist, and HTTP 403 if the user does not have access to the task's project.

Uses: `OCP\Comments\ICommentsManager::getComments('planix_task', $taskId)`
Schema.org: `schema:Comment`

#### Scenario: Authenticated user lists comments

- **GIVEN** a task exists with two comments
- **WHEN** an authenticated user calls `GET /apps/planix/api/tasks/{id}/comments`
- **THEN** the system MUST return HTTP 200 with a JSON array of 2 comment objects in reverse-chronological order

#### Scenario: Pagination with limit and offset

- **GIVEN** a task has 25 comments
- **WHEN** the user calls `GET /apps/planix/api/tasks/{id}/comments?limit=10&offset=10`
- **THEN** the system MUST return HTTP 200 with exactly 10 comment objects starting from the 11th newest

#### Scenario: Task not found

- **GIVEN** no task exists with id `00000000-0000-0000-0000-000000000000`
- **WHEN** the user calls `GET /apps/planix/api/tasks/00000000-0000-0000-0000-000000000000/comments`
- **THEN** the system MUST return HTTP 404

#### Scenario: Empty comment list

- **GIVEN** a task exists with no comments
- **WHEN** an authenticated user calls `GET /apps/planix/api/tasks/{id}/comments`
- **THEN** the system MUST return HTTP 200 with an empty JSON array `[]`

---

### Requirement: REQ-TC-002 — Post a Comment on a Task [MVP]

The system MUST allow any authenticated Planix user to post a text comment on a task they have access to.

The endpoint `POST /apps/planix/api/tasks/{id}/comments` MUST accept a JSON body with a `message` field (string). The message MUST be between 1 and 5 000 characters (inclusive). The system MUST reject messages exceeding this limit with HTTP 422.

The system MUST store the comment using `ICommentsManager::create()` with `actorType: ICommentsManager::COMMENT_ACTOR_TYPE_USERS`, `actorId: $currentUserId`, `objectType: 'planix_task'`, `objectId: $taskId`.

On success the endpoint MUST return HTTP 201 with the newly created comment object (same fields as list response).

Uses: `OCP\Comments\ICommentsManager::create()`
Schema.org: `schema:Comment`

#### Scenario: User posts a valid comment

- **GIVEN** an authenticated user with access to a task
- **WHEN** the user calls `POST /apps/planix/api/tasks/{id}/comments` with body `{"message": "Looks good to me"}`
- **THEN** the system MUST return HTTP 201 with the comment object including `actorId` set to the posting user's UID

#### Scenario: Message exceeds 5 000 characters

- **GIVEN** an authenticated user with access to a task
- **WHEN** the user posts a message with 5 001 characters
- **THEN** the system MUST return HTTP 422 with an error body explaining the maximum length

#### Scenario: Empty message rejected

- **GIVEN** an authenticated user with access to a task
- **WHEN** the user posts a message with an empty string `""`
- **THEN** the system MUST return HTTP 422

#### Scenario: Unauthenticated request rejected

- **GIVEN** no authenticated session
- **WHEN** a request is made to `POST /apps/planix/api/tasks/{id}/comments`
- **THEN** the system MUST return HTTP 401

---

### Requirement: REQ-TC-003 — Delete a Comment [MVP]

The system MUST allow a comment to be deleted. The endpoint `DELETE /apps/planix/api/tasks/{id}/comments/{commentId}` MUST enforce the following authorization rules:

- A user MAY delete their own comment (where `actorId` matches the requesting user's UID).
- A Nextcloud admin MAY delete any comment on any task.
- Any other user MUST receive HTTP 403.

On success the endpoint MUST return HTTP 204 (no body). If the comment does not exist the endpoint MUST return HTTP 404.

Uses: `OCP\Comments\ICommentsManager::delete()`, `OCP\IUserSession` for admin check.

#### Scenario: Author deletes own comment

- **GIVEN** UserA posted a comment with id `comment-123`
- **WHEN** UserA calls `DELETE /apps/planix/api/tasks/{id}/comments/comment-123`
- **THEN** the system MUST return HTTP 204 and the comment MUST no longer appear in the list

#### Scenario: Admin deletes another user's comment

- **GIVEN** a Nextcloud admin and a comment posted by UserB
- **WHEN** the admin calls `DELETE /apps/planix/api/tasks/{id}/comments/{commentId}`
- **THEN** the system MUST return HTTP 204

#### Scenario: Non-author non-admin delete rejected

- **GIVEN** UserA posted a comment and UserB is not an admin
- **WHEN** UserB calls `DELETE /apps/planix/api/tasks/{id}/comments/{commentA_id}`
- **THEN** the system MUST return HTTP 403

#### Scenario: Delete non-existent comment

- **GIVEN** no comment exists with id `00000000-0000-0000-0000-000000000099`
- **WHEN** a user calls `DELETE /apps/planix/api/tasks/{id}/comments/00000000-0000-0000-0000-000000000099`
- **THEN** the system MUST return HTTP 404

---

### Requirement: REQ-TC-004 — Frontend Comments Tab in Task Sidebar [MVP]

The system MUST display a "Comments" tab in the `CnObjectSidebar` of the task detail view. The tab MUST:

- Load comments by calling `GET /apps/planix/api/tasks/{id}/comments` when the tab is first opened.
- Display each comment with: author display name, relative timestamp (e.g., "2 hours ago"), and message text.
- Provide a text input area at the bottom of the tab for composing a new comment, with a "Post" button.
- Disable the "Post" button and show an inline validation message when the draft message is empty or exceeds 5 000 characters.
- Optimistically append the new comment to the list on successful POST (HTTP 201).
- Show an error toast if the POST fails.
- Allow the comment author (or admin) to delete their comment via a contextual action (e.g., a `…` menu or trash icon on hover), with a confirmation prompt before deletion.
- Remove the deleted comment from the displayed list on successful DELETE (HTTP 204).

The tab MUST follow the same slot/prop conventions as the existing Audit Trail tab in `CnObjectSidebar`.

#### Scenario: Comments tab opens and loads existing comments

- **GIVEN** a task has 3 comments and the task detail sidebar is open
- **WHEN** the user clicks the "Comments" tab
- **THEN** the system MUST display all 3 comments with author name and timestamp

#### Scenario: User posts a new comment via the tab

- **GIVEN** the Comments tab is open for a task
- **WHEN** the user types a message and clicks "Post"
- **THEN** the system MUST call `POST /apps/planix/api/tasks/{id}/comments`
- **AND** the new comment MUST appear immediately at the top of the comment list

#### Scenario: Post button disabled for empty input

- **GIVEN** the Comments tab is open
- **WHEN** the draft message input is empty
- **THEN** the "Post" button MUST be disabled

#### Scenario: User deletes own comment in the tab

- **GIVEN** the Comments tab shows a comment authored by the current user
- **WHEN** the user activates the delete action and confirms the prompt
- **THEN** the system MUST call `DELETE /apps/planix/api/tasks/{id}/comments/{commentId}`
- **AND** the comment MUST be removed from the displayed list

---

### Requirement: REQ-TC-005 — Comment Count on Task Card [MVP]

The system MUST display the number of comments on a task card (kanban board and task list) when the count is greater than zero.

A comment count badge MUST:
- Show the total comment count as a small integer badge (e.g., `💬 3`) on the task card.
- Be omitted (no badge rendered) when the task has zero comments.
- Update when the task card is re-rendered (e.g., after board reload or task detail close).

The count is display-only — it does not affect task filtering, sorting, or status.

Uses: `OCP\Comments\ICommentsManager::getNumberOfCommentsForObject('planix_task', $taskId)` via a batch endpoint.

#### Scenario: Task with comments shows badge

- **GIVEN** a task has 4 comments
- **WHEN** the task card is rendered on the kanban board or task list
- **THEN** the card MUST display a comment count badge showing `4`

#### Scenario: Task with no comments shows no badge

- **GIVEN** a task has 0 comments
- **WHEN** the task card is rendered
- **THEN** the card MUST NOT display a comment count badge
