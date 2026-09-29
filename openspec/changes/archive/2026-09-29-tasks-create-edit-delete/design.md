# Design: create, edit and delete a task from the board and the task page

## Context

Read at development `de35541`:

- Task schema: `lib/Settings/planninq_register.json`, schema `task`, required `title` and
  `status`; `status` enum open, in_progress, blocked, done, cancelled; `priority` enum low,
  normal, high, urgent; `description` is a string; `reporter` holds the creator's uid and is
  never set by the app; `column` and `columnOrder` exist and are unused by the board.
- Task authorization in the same file: read, create, update and delete are granted to any
  authenticated member of the task's project (a `$lookup` on `project.members`) and to admins.
  Since planninq#681 that rule is a denormalised `members` list on the task, matched with
  `{"members": {"$contains": "$userId"}}`, and a create is checked against the project by `ProjectMemberAccessListener`.
- Store: `src/store/projects.js` has `fetchTask` (`:285`), `fetchTasks` (`:775`),
  `updateTaskStatus` (`:835`) and `updateTask` (`:871`). Both updates PATCH
  `/apps/openregister/api/objects/planninq/task/{id}` because a PUT nulls every missing
  property. There is no create or single delete action. `deleteProject` deletes tasks and their
  time entries one by one (`:537-561`).
- Board: `src/views/ProjectBoard.vue:104-175` renders one `<section>` per status lane from
  `BOARD_STATUSES` (`src/utils/taskHelpers.js:252`); a card opens TaskDetail
  (`navigateToTask`, `:527`). There is no add control.
- Task page: `src/views/TaskDetail.vue:262-270` builds read-only label and value pairs; only the
  estimate is editable (`saveEstimate`, `:557`). The page title is `taskTitle`.
- Card: `src/components/TaskCard.vue:9` interpolates the description as text.
- Dialog pattern: `src/dialogs/ProjectCreationDialog.vue` (NcDialog, NcTextField, NcTextArea).
- Dependency edges of a deleted task are removed by `lib/Listener/TaskDependencyCleanupListener.php`
  on `ObjectDeletingEvent`.
- Time entries (`plannedTimeEntry`) can only be updated or deleted by the user who logged them
  (schema authorization), and the hours are moving to humaniq's `TimeEntry`
  (open change `plannedtimeentry-reads-humaniqs-hours`).

## Goals / non-goals

Goals: a member can create, edit and delete a task without leaving planninq; the description
supports Markdown.

Non-goals: dates, people, labels, subtasks (sibling changes in this pass); a WYSIWYG editor.

## Decisions

### Decision 1: write through the object store, no planninq controller

`createTask(projectId, data)` calls the shared object store's `saveObject('task', ...)` with
`project`, `status` (default `open`), `priority` (default `normal`) and `reporter` (the current
uid). OpenRegister enforces the schema's create rule, so a non-member is refused without any
planninq PHP. Alternative: a `TaskController::create`. Rejected by ADR-022: it would be a
pass-through.

### Decision 2: one dialog for create and edit

`src/dialogs/TaskFormDialog.vue` takes an optional `task` prop. Without it the dialog creates;
with it the dialog PATCHes only the changed fields through `updateTask`. The board's "New task"
header button and TaskDetail's "Edit" button both open it. The dialog is its own file (ADR-004
modal isolation).

### Decision 3: quick add sets the lane's status until columns exist

Each lane gets a text field at its foot ("Add a task"). Enter creates a task with that title and
the lane's status and keeps focus in the field for the next one. When
`boards-configurable-columns` lands, the same control also writes the lane's `column` and a
`columnOrder` after the last card.

### Decision 4: delete refuses a task with logged time

`TaskDeleteDialog.vue` reads the task's `plannedTimeEntry` count first. With no time logged it
deletes through the object store and removes the card. With time logged it says so and offers
"Cancel task" (status `cancelled`) instead. Alternative: cascade the time entries like
`deleteProject`. Rejected: a member may not delete someone else's entry, and the hours are
another person's record.

The delete action shows for the reporter, the project owner and admins. The schema's delete rule
is narrowed to match (`reporter` equals `$userId`, or the project's `owner`, or admin), so the
button and the server agree. Tasks imported without a reporter can be deleted by the project
owner and admins only.

### Decision 5: Markdown with NcRichText

The description stays a string, now treated as Markdown. TaskDetail renders it with
`NcRichText` (`use-markdown`), which escapes raw HTML. The card shows the first 140 characters
with Markdown syntax stripped. The dialog edits the source in `NcTextArea` with a preview
toggle. Alternative: the Nextcloud Text editor. Rejected for now: it is an optional app and
far heavier than a description needs.

## Amendments at build time (29 Sep 2026, development 395ae3c)

The design was read at `de35541`; four things had changed by the time it was built.

- **The board is column based** (`boards-configurable-columns`). Quick add writes the lane's
  `column`, a `columnOrder` after the last card and the lane's mapped status
  (`buildMovePatch`). "New task" in the header puts the task at the bottom of the first lane,
  so the create dialog has no status field; the edit dialog has one.
- **`createTask` already existed** (`backlog-list`). It now applies the defaults (`status: open`,
  `priority: normal`) itself; the callers keep their payloads.
- **Decision 4, the delete rule, is a listener, not a schema rule.** OpenRegister matches a rule
  against the object's own fields, and the project owner lives on the project, so "reporter or
  project owner" cannot be written in the task schema. The schema keeps members plus admin;
  `lib/Listener/TaskReporterGuardListener.php` holds the narrower rule on `ObjectDeletingEvent`
  (answer 403, code `planninq-task-delete-not-allowed`) and refuses a task with time entries
  (409, `planninq-task-has-logged-time`), as `ColumnOwnerGuardListener` does for columns. It is
  subscribed before `TaskDependencyCleanupListener`, so a refused delete keeps its links.
- **`reporter` is stamped by the server.** The same listener sets it to the caller on create and
  keeps the stored value on every update, so a member cannot make themselves the reporter of
  someone else's task (and so its deleter), and a PUT that nulls the unsent field keeps it.
- **Vitest runs in the node environment** with no component mounting, so the dialog tests of
  tasks 2.1, 2.2 and 4.2 are tests of the pure helpers in `src/utils/taskEditing.js`
  (`editPatch`, `deleteRefusal`, `descriptionExcerpt`) plus a source check that TaskDetail
  renders the description through `NcRichText` with Markdown on and has no `v-html`. The browser
  behaviour is in `tests/e2e/task-editing.spec.ts`.

## Risks / trade-offs

- [Quick add creates tasks with only a title] -> that is the point; the card opens TaskDetail for
  the rest.
- [Narrowing the delete rule changes behaviour for API clients] -> it only removes the right of
  a plain member to delete a task someone else reported; noted in the release notes.
- [Existing descriptions contain characters Markdown interprets] -> plain text renders the same
  in Markdown except for leading `#`, `*` and `-`; acceptable, and the edit dialog shows a preview.
