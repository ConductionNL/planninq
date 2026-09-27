# Design: give projects a short key and tasks a readable key like ABC-123

## Context

Read at development `de35541`:

- `lib/Settings/planninq_register.json:524` project `key` and `:330` task `key`, both described as
  preserved from the source system on import; nothing in `src/` or `lib/` writes them.
- `src/dialogs/ProjectCreationDialog.vue:17-60`: title, description, colour, icon.
- `lib/Controller/ProjectController.php:221` `create()` checks the creation policy, forces
  `owner`, `members` and `status`, and saves with `_rbac: false`. It is the only project write that
  passes through planninq PHP, so it can check key uniqueness across projects the user cannot read.
- `src/components/TaskCard.vue:1-68` and `src/views/TaskDetail.vue:262-270` show no key.
- `lib/AppInfo/Application.php:466,500` register event listeners through the bootstrap context;
  `lib/Listener/TaskDependencyCleanupListener.php` is the pattern for a pre-event on the task schema.

## Goals / non-goals

Goals: a unique project key chosen at creation, stable task keys, shown everywhere.
Non-goals: renaming keys, key-based routing.

## Decisions

### Decision 1: the key format

Two to ten characters, A-Z and 0-9, starting with a letter, stored uppercase. The dialog suggests
one from the title's initials ("Vergunningen Centrum" becomes "VC") and the user can change it.

### Decision 2: uniqueness is checked on the server

`ProjectController::create` rejects a key already used by any project with 409 and the message
"This key is already used by another project." The dialog also checks as the user types through a
small `GET /api/projects/key-available?key=` on the same controller, which answers only true or
false and so leaks no project names.

### Decision 3: task numbers come from a counter on the project

A new integer `nextTaskNumber` on the project. A listener on `ObjectCreatingEvent` for the task
schema, when the task has no key and its project has one, takes a lock named after the project,
reads the counter, sets `key = {projectKey}-{n}`, writes `n + 1` back, and releases the lock.
Alternative: count existing tasks. Rejected: deletes make the count reuse numbers.

### Decision 4: first-time numbering runs in the background

When a project's key goes from empty to set, a queued job (`IJobList`) gives its keyless tasks
keys in creation order through the same counter. The sidebar shows "Numbering tasks" until it is
done.

### Decision 5: show the key wherever the title shows

The card shows the key before the title in a muted style; the task page shows it in the header
and in the document title; `matchesSearch` (from `tasks-search-and-bulk`) includes it.

## Risks / trade-offs

- [The lock needs a working locking provider] -> Nextcloud always has one (database-backed by
  default); the listener fails the create with a clear error rather than issuing a duplicate.
