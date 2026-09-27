# Design: project templates, project copy and a shared workflow

## Context

What exists at `de35541`:

- **Create.** `ProjectCreationDialog.vue` collects title, description, colour and icon and calls `createProject` (`src/store/projects.js:328-363`), which posts to `ProjectController::create` (`lib/Controller/ProjectController.php:221-278`) and then writes one `column` object per entry of the admin's `default_columns` (`projects.js:469-494`, fallback list at `:105-110`).
- **Columns.** `column` has `title`, `project` (required), `order`, `wipLimit`, `color` and `type` (`active` or `done`), with `required: ['title', 'project', 'order']` (`lib/Settings/planninq_register.json:754-902`, required at `:762`). The board does not read them yet: it draws one lane per value of `BOARD_STATUSES` (`src/utils/taskHelpers.js:252`, `src/views/ProjectBoard.vue:283-291`). Lane A's `boards-configurable-columns` makes the board render the project's `column` objects.
- **Labels.** `label` has `title`, `color` and `description` (`planninq_register.json:1060-1105`), is readable by every signed-in user and written by admins through `src/store/labels.js`. A task lists label ids in `labels`.
- **Estimates.** `task` carries `storyPoints` (number) and `estimatedDuration` (minutes) side by side, and `remainingEstimate`.
- **Phases and dependencies.** `projectPhase` belongs to one project (`required: ['title', 'project']`). `dependency` links `blocker` and `blocked` tasks and is written through `DependencyController`.
- **Store page.** `src/manifest.json:61-71` declares an OpenRegister-hosted Store that installs configsets and flows, and its description mentions project templates. Nothing in planninq produces such a template today.
- **Copy.** Nothing copies a project, a column set or a task tree.

## Goals / non-goals

Goals:

- Start a project from a template, and copy any project, with the references inside it intact.
- Share one workflow across many projects, so one edit reaches all of them.

Non-goals:

- Cross-organisation template exchange (open question below).
- Changing the fixed task `status` lifecycle. Columns are the configurable part; `status` stays the machine-readable state.

## Decisions

### Decision 1: a template is a project with `isTemplate: true`

A template is an ordinary project with a flag. It has columns, phases, tasks and people like any project, so it is edited on the same board. `ProjectList.vue` hides templates from the Active chip and shows them under a Templates chip. The dashboard KPIs filter on `status` only, so a template counts as active there unless it is excluded; the KPI filters gain `isTemplate: false`, which is still scalar equality as the manifest note at `src/manifest.json:170` requires.

Alternative considered: a separate `projectTemplate` schema. It would duplicate every project, column, phase and task field, and a template could not be tried out on a real board.

### Decision 2: copy runs on the server and remaps every reference

`POST /apps/planninq/api/projects/{id}/copy` takes a title, an optional key, a start date and the parts to include: columns, tasks, phases, dependencies and people. `ProjectCopyService`:

1. checks the creation policy through `SettingsService::canCurrentUserCreateProject`, and that the caller can read the source;
2. creates the project with the caller as owner and `metadata.copyState: 'copying'`;
3. copies columns, then phases, then tasks in parent-before-child order, keeping a map from old to new ids so `task.column`, `task.phase`, `task.parent` and `task.epic` point at the copies;
4. copies dependencies between copied tasks only;
5. shifts every date by the difference between the source's `startDate` and the requested one;
6. clears `copyState`.

A failure deletes what step 2 to 5 wrote. Copied tasks start with the status `open`, no time entries and no comments. People come along only when chosen, and never the source's owner in place of the caller.

"New project from template" calls the same endpoint with the template as source.

This is not a pass-through controller (ADR-022): it enforces the creation policy and does work the object API cannot do in one call.

Alternative considered: copying in the browser through the object store. It would take one request per object, leave a half copy on a closed tab, and put the id remapping in client code.

### Decision 3: a workflow is a schema that owns columns, labels and an estimate scale

`workflow` has `title`, `description`, `labels` (label ids on offer) and `estimateScale` (`none`, `hours`, `storyPoints`, `tshirt`) with `estimateValues` (the allowed values, for example 1, 2, 3, 5, 8). Its columns are `column` objects with `workflow` set and `project` empty; `column.required` becomes `['title', 'order']`, and a column has either a `project` or a `workflow`.

`project.workflow` is an optional reference. When set, the board renders the workflow's columns and the label picker offers the workflow's labels. When empty, the project keeps its own columns and all labels, as today. Admins edit workflows in Beheer; project managers pick one in the project settings.

Moving a project onto a workflow maps each task's column by column title, and tasks whose column has no match go to the first column. The dialog shows how many tasks that affects before it saves.

Alternative considered: copy the workflow's columns into each project and sync them on change. That keeps `column.project` required, but a missed sync leaves projects drifting from the scheme they claim to follow, which is the problem shared configuration exists to remove.

## Risks / trade-offs

- [The board loses its lanes when the workflow is deleted] -> Deleting a workflow that projects follow is refused with the number of projects, until they are moved off it.
- [A template's dates are meaningless] -> A template stores dates relative to its own `startDate`. The copy shifts them; a template without a `startDate` copies no dates.
- [Column rules differ between the two column sources] -> One helper resolves the column list for a project, used by the board, the backlog and the copy service.

## Open questions

- Should a template be publishable to the Store as a configset? The Store page text promises "a project template"; this needs the configset format from openregister and is left for a later change.
