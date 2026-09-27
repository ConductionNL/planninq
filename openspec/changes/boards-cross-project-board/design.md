# Design: work tasks from several projects on one shared board

## Context

What exists at de35541:

- `ProjectBoard` (`src/views/ProjectBoard.vue`) renders one project's tasks in status lanes: `columns()` maps `BOARD_STATUSES` (`:283-292`, `src/utils/taskHelpers.js:252`), `tasksByStatus()` groups with `groupTasksByStatus` (`:303-305`, `src/utils/taskHelpers.js:268-283`), drag and the per-card "Move task to another column" menu call `applyStatusMove`, which PATCHes `status` through `updateTaskStatus` and reverts on failure (`:577-595`, `src/store/projects.js:835-859`). A label filter row sits above the lanes (`:66-95`). Cards are `TaskCard` (`src/components/TaskCard.vue`).
- Tasks of one project are read with `fetchTasks(projectId)` (`src/store/projects.js:775-783`) through `fetchEvery`, which follows every page (`:61-94`).
- The task schema lets an authenticated user read a task only when it belongs to a project whose `members` contain them (`lib/Settings/planninq_register.json:52-74`).
- The project schema shows how OpenRegister expresses "readable by the people in a list" (`members $contains $userId`, `:404-418`) and "writable by the owner" (`owner: $userId`, `:421-442`).
- `Boards.vue` lists the user's active member projects as board cards (`:22-40`, `fetchProjects` at `:90`).

What is missing: an object that says "these projects, on one board", a page for it, and a way to find it.

## Goals / non-goals

Goals:
- One board across several projects, shareable with a team, with the same drag and keyboard behaviour as a project board.
- No task visible through the board that its project would not show.

Non-goals:
- Custom columns, query-based boards, task creation on the shared board.

## Decisions

### Decision 1: a `board` schema that stores project ids and people, never tasks
Properties: `title` (required), `owner` (user id, set on create), `members` (user ids the board is shared with, default `[]`), `projects` (array of project uuids, 1 to 20). Authorization: read when `owner` is the user or `members` contains the user, create for any authenticated user, update and delete for the owner and admins. Schema.org `schema:ItemList`. The board holds no copy of task data, so it cannot go stale and cannot leak.

### Decision 2: tasks are read with the viewer's own rights
`SharedBoard` reads each listed project's tasks with `fetchTasks(projectId)`, in parallel. The task schema's authorization returns nothing for a project the viewer is not a member of, so a board shared with someone outside a project shows them none of its tasks. The board also reads the listed projects; a project the viewer cannot read is counted, not named: "2 projects on this board are hidden from you." The alternative, a planninq controller that reads tasks as the board owner, would hand the owner's access to everyone the board is shared with.

### Decision 3: status lanes, the vocabulary every project shares
The shared board groups by `status` with `groupTasksByStatus`, the same lanes the project board has today. When `boards-configurable-columns` moves project boards onto their own columns, the shared board keeps status lanes: projects may have different columns, but they all share the task status enum. Moving a card changes its status through `updateTaskStatus`, with the same optimistic move and revert. The drag handlers, move menu and label filter are extracted from `ProjectBoard` into a `StatusLanes` component that both pages use, so there is one implementation of the keyboard path.

### Decision 4: every card names its project
`TaskCard` gets an optional `project` prop; on a shared board it renders a chip with the project's title and its colour swatch next to the text, so colour is never the only signal. Selecting a card opens the task page in its project, as on a project board.

### Decision 5: found on the Borden page
`Boards.vue` gets a "Shared boards" section above the project boards, listing boards the user owns or that are shared with them, and a "New shared board" button that opens `SharedBoardEditDialog` (in `src/dialogs/`): name, projects (a picker offering only the user's member projects), and people (`MemberSearch`, `src/components/MemberSearch.vue`). The owner edits and deletes from the board page header. Placement is Borden (ADR-001: "alle borden, bord-detail"), not a new menu.

## Risks / trade-offs

- [Leaking tasks] -> The board stores ids only; reads use the viewer's rights (Decision 2).
- [Many projects] -> Parallel paged reads, progressive rendering, a cap of 20 projects per board.
- [A project is deleted or archived] -> Its id stays on the board and is skipped with "1 project on this board no longer exists", and the owner can remove it.
