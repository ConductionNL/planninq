# Design: plan work in sprints, with a sprint board, a sprint goal and a burndown

## Context

What exists at de35541:

- The register lists seven schemas: `task`, `project`, `projectPhase`, `column`, `plannedTimeEntry`, `label`, `dependency` (`lib/Settings/planninq_register.json:22-30`). None is a sprint. `tests/unit/Settings/PlanninqRegisterSchemaTest.php:370-397` asserts exactly those seven, and `openspec/specs/project-delivery/spec.md:68-72` says the same.
- `projectPhase` (`lib/Settings/planninq_register.json:584-752`) is the nearest precedent for a project-scoped planning object: it has `title`, `project` (`$ref: project`), `status`, `order`, `startDate`, `endDate`, and an authorization block that grants read, create, update and delete to members of the referenced project (`:596-690`). A sprint is exactly as sensitive as its project, so it copies that block.
- The task schema already has `storyPoints` (`:355-360`), `estimatedDuration` in minutes (`:280-284`), `remainingEstimate` (`:361-366`) and `completedAt` (`:317-323`). It has no sprint reference.
- Nothing writes `completedAt`. `updateTaskStatus` PATCHes `{ status }` only (`src/store/projects.js:835-859`); the board is its only caller (`src/views/ProjectBoard.vue:587`). The one other mention is a read in `lib/Portal/PortalContributionProvider.php:179`.
- The board groups every task of the project by status: `columns()` maps `BOARD_STATUSES` (`src/views/ProjectBoard.vue:283-292`, `src/utils/taskHelpers.js:252`) and `loadTasks` reads `fetchTasks(projectId)` (`src/views/ProjectBoard.vue:400-410`, `src/store/projects.js:775-783`). The board header has Backlog and Timeline buttons (`src/views/ProjectBoard.vue:41-62`).
- The backlog page is a placeholder empty state (`src/views/ProjectBacklog.vue:24-30`). The lane A change `backlog-list` turns it into a real ordered task list; this change builds on that page.
- Project settings live in `src/components/ProjectSettingsSidebar.vue` (a Details tab at `:8-58`). Project updates are owner-only by schema (`lib/Settings/planninq_register.json:421-442`).
- `docs/ARCHITECTURE.md:149` records "No sprints, Flow-based, continuous delivery, Kanban-only per user decision".

What is missing: a sprint object, a way to put a task in a sprint, a board filtered to the running sprint, a goal on that board, a finish time on tasks, and a chart.

## Goals / non-goals

Goals:
- Time-boxed planning for the projects that want it, without changing the projects that do not.
- One running sprint per project, visible on the board the team already uses.
- A burndown computed from data the task already carries.

Non-goals:
- Velocity, sprint reports, release burndown.
- Sprints that span projects.
- Replacing the status lanes; the sprint board is the same board with fewer cards.

## Decisions

### Decision 1: sprints are opt-in per project
A new `project.sprintsEnabled` boolean, default `false`, set by the project owner in the Details tab of `ProjectSettingsSidebar`. With it off, the backlog, the board and the timeline behave exactly as today. This keeps the recorded flow-first decision (`docs/ARCHITECTURE.md:149`) as the default and matches Plane, which ships cycles off by default per project. The alternative, sprints for every project, would change every existing board the day it ships.

### Decision 2: a `sprint` schema, and a single `task.sprint` reference
`sprint` properties: `title` (required), `project` (required, `$ref: project`), `goal` (string), `startDate` and `endDate` (`format: date`, required), `status` (`planned`, `active`, `completed`; default `planned`), `order` (integer). Schema.org `schema:Event` with `startDate`/`endDate`. Authorization is the `projectPhase` block verbatim.

`task.sprint` is a nullable `$ref: sprint`. A task sits in at most one sprint at a time; null means the backlog. The alternative, an array of sprints per task (Jira keeps the history of every sprint a task passed through), buys a report this change does not build and makes "the current sprint's tasks" a contains-query that `fetchEvery` cannot express as scalar equality. The audit trail already records every change of `task.sprint`.

### Decision 3: the rules that OpenRegister cannot declare live in one store action
"End date after start date" and "at most one active sprint per project" are cross-field and cross-object rules. The store actions `startSprint` and `saveSprint` in `src/store/projects.js` check them before writing and show the error inline in the dialog. No pass-through controller is added (ADR-022). If OpenRegister gains a declarative uniqueness rule per parent reference, the schema carries it instead.

### Decision 4: completing a sprint moves unfinished work in one confirmed step
`SprintCompleteDialog` (in `src/dialogs/`) lists the tasks whose status is not `done` or `cancelled` and offers two targets: the next planned sprint of the project, or the backlog (`sprint: null`). The store PATCHes each task, then the sprint's `status` to `completed`. A partial failure keeps the sprint `active` and names the tasks that did not move, so a sprint is never marked completed with work silently left in it.

### Decision 5: the sprint board is the project board with a filter
When the project has sprints on and an `active` sprint, `ProjectBoard` loads tasks with `{ project, sprint: <active id> }` instead of `{ project }`, and the header shows the sprint title, its goal and the days left. A "Whole project" toggle in the header returns to all tasks. No second board component (ADR-001 rule 3: one task model). The alternative, a separate `SprintBoard` view, would duplicate the drag, keyboard move and label filter the board already has.

### Decision 6: the burndown is computed on the client from the sprint's tasks
`SprintBurndown` (a component opened from the sprint header and from the sprint row on the backlog page) reads the sprint and its tasks and plots, per day from `startDate` to `endDate`, the remaining work: story points when at least one task in the sprint has `storyPoints`, otherwise `estimatedDuration` in hours, otherwise a task count. It draws the ideal straight line from the starting total to zero. A task counts as burned on the date of its `completedAt`. OpenRegister's aggregation endpoint answers scalar-equality counts and sums (`src/manifest.json` `TaskStatusReport` note), not a series over time, so a declarative dashboard widget cannot draw this. The chart has a text equivalent: a table of the same numbers per day, for screen readers and keyboard users (ADR-059).

### Decision 7: `completedAt` is stamped on the server in the same save
A pre-save listener, `lib/Listener/TaskCompletionListener.php`, listens to OpenRegister's `ObjectUpdatingEvent` and `ObjectCreatingEvent` for planninq tasks. When `status` becomes `done` it merges `completedAt: <now>` into the object with `setModifiedData()`; when `status` leaves `done` it merges `completedAt: null`. OpenRegister merges that data before it stores the object (`lib/Db/MagicMapper.php:7302-7319` in OpenRegister, read at 63ddfd5), so the status and the finish time are one saved version, whichever client moved the task: the board, the API, a flow or an import. The alternative, sending `completedAt` from `updateTaskStatus` in the Vue store, only covers the board; the alternative of a post-save listener writes every move twice. The listener merges with data other planninq pre-save listeners set (see `boards-column-automation`), never replaces it.

### Decision 8: placement follows ADR-001
Sprint planning sits on the project's backlog page (Projecten > project > Backlog). The sprint board is the project's board (Borden). The burndown opens from the sprint, not from Portfolio: it is a team view of one project, not a PMO roll-up. No new menu.

## Risks / trade-offs

- [The build decision overrides `docs/ARCHITECTURE.md:149`] -> Opt-in per project, off by default; the doc row is rewritten in the same PR; see the open question.
- [Tasks finished before this change have no `completedAt`] -> The burndown places them on the sprint's last day and says how many it placed that way.
- [Moving 50 unfinished tasks at sprint completion is 50 PATCHes] -> Sequential with a progress line in the dialog; a failure stops and reports, and the sprint stays active.
- [Schema count assertions] -> Updated in the same PR (task 1.4).

## Open questions

- `docs/ARCHITECTURE.md:149` says "No sprints ... Kanban-only per user decision". The parity decision for these rows is build. Does the product owner confirm that sprints may exist as a per-project opt-in? Implementation should not start before that answer.
