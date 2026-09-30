# Design: work tasks from several projects in one cross-project view

## Context

What exists at de35541:

- `docs/ARCHITECTURE.md:148`: "Board model: 1 project = 1 kanban board; columns belong to project directly."
- `ProjectBoard` (`src/views/ProjectBoard.vue`) renders one project's tasks in status lanes: `columns()` maps `BOARD_STATUSES` (`:283-292`, `src/utils/taskHelpers.js:252`), `tasksByStatus()` groups with `groupTasksByStatus` (`:303-305`, `src/utils/taskHelpers.js:268-283`), and drag and the per-card "Move task to another column" menu call `applyStatusMove`, which PATCHes through `updateTaskStatus` and reverts on failure (`:577-595`, `src/store/projects.js:835-859`). A label filter row sits above the lanes (`:66-95`). Cards are `TaskCard` (`src/components/TaskCard.vue`).
- Tasks of one project are read with `fetchTasks(projectId)` (`src/store/projects.js:775-783`) through `fetchEvery`, which follows every page (`:61-94`).
- The task schema lets an authenticated user read and update a task only when it belongs to a project whose `members` contain them (`lib/Settings/planninq_register.json:52-120`).
- The project schema shows how OpenRegister expresses "readable by the people in a list" (`members $contains $userId`, `:404-418`) and "writable by the owner" (`owner: $userId`, `:421-442`).
- Merged on development after de35541 and relied on here: `boards-configurable-columns` moves each project board onto its own `column` objects, gives a column an optional `status`, and makes a card move write `column`, `columnOrder` and the column's `status`; `boards-filters` adds a filter model with a pure `matchesFilter` helper in `src/utils/boardFilter.js` and the filter in the query string.

What is missing: a way to see and move the tasks of several projects together.

## Goals / non-goals

Goals:
- One screen across several projects, shareable with a team, with the drag, keyboard and filter behaviour of a project board.
- No second board per project, and no task visible or movable through the view that its project would not allow.

Non-goals:
- Columns, WIP limits, card order or rules of the view's own; task creation on the view; query languages.

## Decisions

### Decision 1: a view, not a board
The architecture rule "1 project = 1 kanban board" stays. The new object is a `boardView`: a saved selection of projects to look at together. It has no columns, no WIP limits, no card order and no rules, and no task ever belongs to it. Everything that places a card (its column, its order, its column's status) stays on the task and on its project's one board. The view only reads, and moves a card through its own project's board model (Decision 4). The alternative, a board object with its own columns spanning projects, would give a task two positions on two boards, which is exactly what the rule forbids.

### Decision 2: `boardView` stores project ids and people, never tasks
Properties: `title` (required), `owner` (user id, set on create), `members` (user ids the view is shared with, default `[]`), `projects` (array of project uuids, 1 to 20). The owner is stamped on create and kept on update by the listener that already does this for saved filters (`BoardFilterOwnerListener`, now covering both schemas), so a client cannot claim or take over a view. `boardView` is not project-scoped: its `members` is chosen by the owner, not kept by planninq. Authorization: read when `owner` is the user or `members` contains the user, create for any authenticated user, update and delete for the owner and admins. Schema.org `schema:ItemList`.

### Decision 3: tasks are read with the viewer's own rights
The view page reads each listed project's tasks with `fetchTasks(projectId)`, in parallel. The task schema returns nothing for a project the viewer is not a member of, so a view shared with someone outside a project shows them none of its tasks. It also reads the listed projects; a project the viewer cannot read is counted, not named: "1 project in this view is hidden from you." OpenRegister answers a read of a project the viewer may not see and a read of a project that no longer exists with the same 404 (`ObjectsController::show`, RBAC inside `find()`), so the view cannot tell the two apart and counts both as hidden, never naming them (amended 30 Sep at build time; the earlier "no longer exists" count cannot be computed with the viewer's rights). The alternative, a planninq controller that reads as the view's owner, would lend the owner's access to everyone the view is shared with.

### Decision 4: lanes are statuses, and a move goes through the task's own project columns
Lanes are the task status values, the one vocabulary all projects share (projects may name and order their columns differently). Moving a card to a lane resolves, in the task's own project, the first column by `order` whose `status` is that lane's status, and writes `column`, `columnOrder` (the end of that column) and `status`: the same write a move on the project's board makes, sent with the viewer's rights through the object API. So column rules (`boards-column-automation`) and the server-side `completedAt` stamp (`boards-configurable-columns`) run as they would on the project board. If the project has no column for that status, the card goes back and the view says "Servers has no column for In progress." Amended 30 Sep at build time: since `boards-configurable-columns`, `ProjectBoard` lanes are the project's own column objects, not statuses, so there are no status lanes left in it to extract. The view gets its own `StatusLanes` component (lanes, drag and the keyboard "Move task to another column" menu, with the same markup and announcement pattern as the board) and `ProjectBoard` stays untouched, which removes the regression risk of splitting an 1,800-line page. The pure parts (merging the reads, the lanes, the move resolution, the save payload) live in `src/utils/projectsView.js`.

### Decision 5: every card names its project, filters come from the board filter bar
`TaskCard` gets an optional `project` prop; on the view it renders a chip with the project's title and its colour swatch next to the text, so colour is never the only signal. The filter bar of `boards-filters` is reused with its `matchesFilter` helper and query-string state; saved filters stay per project, as that change defines them.

### Decision 6: found on the Borden page
`Boards.vue` gets a "Cross-project views" section above the project boards, listing views the user owns or that are shared with them, and a "New view" button that opens `ProjectsViewEditDialog` (in `src/dialogs/`): name, projects (a picker offering only the user's member projects) and people (`MemberSearch`, `src/components/MemberSearch.vue`). The owner edits and deletes from the view's header. Route `/boards/views/:id`, under Borden (ADR-001: "alle borden"), not a new menu.

## Risks / trade-offs

- [Leaking tasks] -> The view stores ids only; reads and writes use the viewer's rights (Decisions 2 and 3).
- [A move that the project board would place differently] -> Resolved through the project's own columns (Decision 4); refused when no column fits.
- [Many projects] -> Parallel paged reads, progressive rendering, a cap of 20 projects per view.
