# Design: let each project's board run on its own columns

## Context

Read at development `de35541`:

- `src/views/ProjectBoard.vue:283-291` `columns()` maps `BOARD_STATUSES`
  (`src/utils/taskHelpers.js:252`: open, in_progress, blocked, done, cancelled) to lanes;
  `tasksByStatus` groups with `groupTasksByStatus` (`:268`); `applyStatusMove` (`:577`) PATCHes
  `status` through `updateTaskStatus` (`src/store/projects.js:835`). Lane header count at `:118`.
- Schema `column`: `title`, `project`, `order`, `wipLimit` (null means unlimited), `color`, `type`
  (active, done). Authorization: every project member may create, update and delete columns.
- Schema `task`: `column` (uuid, "null = backlog"), `columnOrder` ("sort order within the column"),
  `completedAt` (date-time, never set).
- `src/store/projects.js:96-111` `getDefaultColumns()` reads `loadState('planninq',
  'default_columns')`, else four hard-coded columns; `:469` `createDefaultColumns` saves them.
  `grep provideInitialState lib/` finds nothing, so the state is never there.
- `lib/Service/SettingsService.php:61` stores `default_columns` as a JSON list of titles, edited in
  `src/views/settings/Settings.vue:439-495`.
- `openspec/specs/tasks.md` scenarios "Assign task to column", "Mark task as done" and "WIP limit
  exceeded" describe this behaviour; `docs/ARCHITECTURE.md` section 5 question 6 fixes soft limits.

## Goals / non-goals

Goals: a board driven by the project's columns, managed by the owner, with visible WIP limits, a
done column, stable card order, admin defaults, and no lost cards.
Non-goals: hard WIP limits, automation, swimlanes, the backlog page.

## Decisions

### Decision 1: the column decides the lane, the column maps a status

A column gains an optional `status` (one of the task status enum). The board fetches the
project's columns (`{ project }`, sorted by `order`) and groups tasks by `column`. Moving a card
PATCHes `column`, `columnOrder` and, when the column has one, `status`. A column of type `done`
maps to `done`. Alternative:
keep status lanes and drop the column schema. Rejected: FEATURES.md lists configurable columns as
MVP and the schema and store already carry them.

### Decision 1b: the server stamps `completedAt`

`completedAt` is set on the server, in the same save as the status change, by a pre-save listener
`lib/Listener/TaskCompletionListener.php` on Open Register's `ObjectCreatingEvent` and
`ObjectUpdatingEvent` for planninq tasks: when `status` becomes `done` it merges `completedAt: now`
into the object, and when `status` leaves `done` it merges `completedAt: null`. It merges with what
other planninq pre-save listeners set and never replaces it. So the finish time is right whichever
client moved the task: the board, the API, a flow or an import. Alternative: send `completedAt` from
the board's PATCH. Rejected: it only covers the board. (Adopted from the lane B design review in the
OpenSpec pass; flow reports and the dashboard's "completed today" depend on it.)

### Decision 2: the project owner manages columns

A lane header menu (owner only) offers Rename, Set WIP limit, Mark as done column, Colour, Move
left, Move right and Remove, and an "Add column" button ends the row. The settings sidebar gets a
"Columns" tab with the same actions as a keyboard-friendly list. The column schema's create,
update and delete rules are narrowed to the project owner and admins, so the menu and the server
agree. Members still move cards.

### Decision 3: removing a column moves its cards first

Remove is offered on an empty column directly; on a column with cards it asks for a target column
(or the backlog) and moves them before deleting. There must always be one done column: removing
the last one is refused with a message.

### Decision 4: soft WIP limits

The header shows "{count} / {limit}" when a limit is set and switches to the warning token
(`--color-warning`) with a text suffix "over limit" when the count exceeds it, so colour is not the
only signal. A drop is never refused.

### Decision 5: sparse card order

`columnOrder` uses steps of 1000; a drop between two cards takes the midpoint and renumbers the
lane only when no integer fits. The card menu gains "Move up" and "Move down" for keyboard users.

### Decision 6: admin defaults reach creation

The page controller provides `default_columns` as initial state, mapped from the admin's list of
titles to objects: every title active, the last one type `done`, statuses mapped by position
(first open, middle in_progress, last done). The fallback stays for an empty setting.

### Decision 7: a repair step keeps existing cards on the board

A new repair step, once per project: create default columns if the project has none, then give
every task without a column (except cancelled) the first column whose `status` matches, else the
first active column; done tasks go to the done column. It is idempotent: tasks with a column are
untouched.

## Risks / trade-offs

- [The Blocked and Cancelled lanes disappear for projects on the defaults] -> blocked is shown by
  the badge (`planning-dependencies-on-task-page`); cancelled tasks are in the backlog's cancelled
  filter; an owner can add a lane mapped to either status.
- [Narrowing column writes to the owner] -> members who today could write columns through the API
  lose that; no screen used it.
