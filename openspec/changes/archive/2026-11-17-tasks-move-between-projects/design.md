# Design: move a task, or a whole column, to another project

## Context

Read at development `de35541`:

- Schema `task`: `project` (uuid, `$ref` project), `column` (uuid, null means backlog),
  `columnOrder`, `parent`. Task authorization reads and writes are scoped to members of the task's
  project, so a user must be a member of both the source and the target to move a task.
- `lib/Service/DependencyService.php` rejects cross-project edges and has
  `removeEdgesForTask()`. `lib/Listener/TaskDependencyCleanupListener.php` calls it on
  `ObjectDeletingEvent` only (`:10-16` records that the move flow never called it).
- The archived change `2026-06-14-task-dependencies` specified "Project move removes edges"
  (`specs/task-dependencies/spec.md`, requirement "Dependency lifecycle follows tasks").
- `plannedTimeEntry.project` is denormalised from the task, and only the user who logged an entry
  may update it.
- `src/store/projects.js:871` `updateTask` PATCHes a task; `fetchProjects` (`:170`) lists the
  projects the user is a member of.

## Goals / non-goals

Goals: move a task (with its subtasks) and move or copy a column (with its tasks) between
projects the user belongs to. Non-goals: moving booked time, bulk moves.

## Decisions

### Decision 1: a move lands in the target's backlog

Moving a task PATCHes `project` to the target and clears `column` and `columnOrder`, as the main
spec scenario says. Subtasks (tasks with this `parent`) are PATCHed the same way. The dialog lists
the target projects the user is a member of, excluding the current one.

### Decision 2: the server removes dependency links on a project change

`TaskDependencyCleanupListener` also listens to `ObjectUpdatingEvent` for the task schema and, when
`project` changes, calls `removeEdgesForTask()`. A pre-event keeps the cascade inside the write, as
the listener's own header argues for deletes. The dialog shows the links first.

### Decision 3: people who are not target members are cleared

`assignedTo` and `sharedWith` entries that are not in the target's `members` are listed in the
dialog and cleared on confirm.

### Decision 4: booked time stays where it was booked

Time entries keep their `project`. The task page of a moved task shows "2h logged under
{previous project}" for entries whose project differs. Alternative: rewrite the entries'
`project`. Rejected: only each entry's author may write it, and a booking is a record of what was
charged where.

### Decision 5: a column moves with its tasks, a copy copies them

"Move column to project" PATCHes the column's `project` and `order` (appended last in the target)
and moves every task in it under Decision 1, except that tasks keep the moved column instead of
going to the backlog. "Copy column to project" creates a new column in the target and copies each
task (title, description, priority, labels, checklist) into it, with status open and no people.

## Risks / trade-offs

- [A move of a big column is many writes] -> progress in the dialog and a per-task result, as in
  the bulk bar.
