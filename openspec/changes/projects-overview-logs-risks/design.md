# Design: project overview, project logs and a risk register

## Context

What exists at `de35541`:

- **Project pages.** `src/manifest.json` declares `ProjectBoard` at `/projects/:id` (`:145`), `ProjectBacklog` at `/projects/:id/backlog` (`:146`), `ProjectTimeline` at `/projects/:id/timeline` (`:147`) and `TaskDetail` (`:148`), each a `type: "custom"` page resolved through `src/registry.js:42-51`. The board header links to Backlog and Timeline with two buttons and opens settings with a cog (`src/views/ProjectBoard.vue:40-62`). There is no overview page and no shared tab row.
- **Project data.** `project` carries `title`, `description`, `status`, `owner`, `members`, `startDate`, `endDate`, the budget fields and `metadata` (`lib/Settings/planninq_register.json:445-580`). `projects.js` reads tasks per project with `fetchTasks` (`src/store/projects.js:775-785`).
- **Progress.** `task.status` has `open`, `in_progress`, `blocked`, `done` and `cancelled`. `task.percentComplete` exists and nothing in `src/` reads it.
- **Notes.** The task detail mounts `CnObjectSidebar` with notes, files and audit trail from OpenRegister's per-object endpoints (`src/views/TaskDetail.vue:124-137`). The project has no such sidebar.
- **Tasks as actions.** `task.issueType` is a free string with default `task` (`planninq_register.json:324-329`).
- **Risk.** Nothing named risk, likelihood, impact or countermeasure exists in `src/`, `lib/` or the register.

What OpenRegister offers, read at `ConductionNL/openregister` development `c53dd0685`:

- `x-openregister-calculations` declares a typed, optionally materialised value from an `expression` over the object's own properties (`lib/Service/Calculation/CalculationAnnotationValidator.php:150-200`; injected before persistence per `lib/AppInfo/Application.php:3326`).

## Goals / non-goals

Goals:

- One screen that answers "where does this project stand".
- A log that keeps issues, lessons, meetings and decisions per project, with actions that are ordinary tasks.
- A scored risk register per project and across projects.

Non-goals:

- A configurable widget grid.
- Per-portfolio risk scales (next change in this series).

## Decisions

### Decision 1: the overview is a tab next to the board, not a replacement for it

The overview lives at `/projects/:id/overview`. The board stays at `/projects/:id`, because the deep links in `src/manifest.json:272-302` point there. A new `ProjectTabs` component replaces the Backlog and Timeline buttons in the board header and renders Overview, Board, Backlog, Timeline, Risks and Log as links with `aria-current` on the active one. The settings cog stays.

The overview shows, in this order: title and status, description, planned start and end dates, the people on the project with their display names, progress, the three highest-scored open risks, and the five latest log entries. Each block links to its tab.

Progress is the number of `done` tasks over all tasks that are not `cancelled`, read from `fetchTasks`. `percentComplete` is not used, because nothing sets it and a mean of unset values reads as zero.

Alternative considered: make the overview the project's landing page at `/projects/:id` and move the board to `/projects/:id/board`. It matches OpenProject, but it breaks every stored deep link.

### Decision 2: a log entry is its own schema; an action is a task

`projectLogEntry` has `project` (`$ref` project), `type` (`issue`, `lesson`, `meeting`, `decision`), `title`, `body` (Markdown), `date`, `status` (`open`, `closed`, used for issues), `attendees` (user ids, used for meetings) and `actions` (task ids). The author and time come from OpenRegister's own object metadata, so the client cannot fake them.

An action is not a log entry type. "Add action" on an entry creates a task in the same project with `issueType: 'action'` and appends its id to `actions`. The Log tab's "Actions" filter lists the project's tasks with that `issueType`, so an action is assigned, dated, dragged and closed like any task. This follows ADR-001 rule 3: one task model.

Alternative considered: OpenRegister notes on the project object, as the task sidebar uses. Notes cannot carry a type, a status or linked actions, so the tender's four logs would be one undifferentiated stream.

### Decision 3: a risk is scored by a declared calculation

`risk` has `project`, `title`, `description`, `category`, `likelihood` and `impact` (integers from 1 to the scale's size), `score`, `status` (`open`, `mitigating`, `closed`, `occurred`), `owner` (user id), `response` (`avoid`, `reduce`, `transfer`, `accept`), `countermeasures` (Markdown) and `reviewDate`.

`score` is a materialised `x-openregister-calculations` entry, `likelihood × impact`. Because it is stored, the index sorts and filters on it without the browser computing anything, and no client can write a score that disagrees with its inputs.

### Decision 4: the scale is data, the colours are tokens

`risk_scale` is an admin setting in Beheer: the number of levels (3 to 5), a label per level for likelihood and for impact, and two score thresholds that split low, medium and high. The heat map colours the cells with NL Design tokens for those three bands, never hard-coded colours, and every cell also carries its count and band as text, so colour is never the only signal (WCAG 1.4.1).

### Decision 5: the cross-project Risks page is declarative

`RiskIndex` at `/projects/risks` is a `type: "index"` page over the `risk` schema, sorted by `score` descending, with the project, owner, status and review date as columns. OpenRegister's read rule decides which risks a user sees, so the page needs no component of its own. The per-project Risks tab is a custom page because the heat map is not an index.

## Risks / trade-offs

- [The heat map and the list disagree after a scale change] -> The scale bounds `likelihood` and `impact`. Lowering the number of levels is refused while a risk uses a higher value, with the count of risks in the way.
- [Log entries grow without bound] -> The Log tab pages through OpenRegister with `_limit`, and the overview reads only the latest five.
- [A risk owner who leaves the project] -> The Risks tab marks an owner who is no longer on the project, and the member clean-up of `projects-members-and-roles` leaves the risk in place for a manager to reassign.
