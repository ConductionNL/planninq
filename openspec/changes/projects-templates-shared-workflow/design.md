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
- Labels per project or per workflow. Label scope is app-wide by a recorded decision (`docs/ARCHITECTURE.md:226`).

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

Who may copy (settled while building, 2 Oct): the requirement names the project owner or manager, so step 1's read check is that: the owner, a manager, a member of an owning or manager group, or an admin. A template is open to anyone who may create a project, and a `read` rule `{"group": "authenticated", "match": {"isTemplate": true}}` lets every signed-in user see it in the picker; updating and deleting it keep the project rules. When columns are not chosen the copy gets the admin's default columns, so its board is never empty, and its tasks land in the backlog. With no requested start date the dates are kept as they are.

The dashboard figures filter `isTemplate: false`. A project stored before the flag has no value and scalar equality would drop it, so the repair step `BackfillProjectTemplateFlag` writes `false` on every project without one.

This is not a pass-through controller (ADR-022): it enforces the creation policy and does work the object API cannot do in one call.

Alternative considered: copying in the browser through the object store. It would take one request per object, leave a half copy on a closed tab, and put the id remapping in client code.

### Decision 3: a workflow defines columns and an estimate scale; each project keeps its own columns

Two recorded decisions bound this design. Labels are app-wide, not per project, so they can be filtered across projects (`docs/ARCHITECTURE.md:226`). One project is one board and its columns belong to the project directly (`docs/ARCHITECTURE.md:148`). So a workflow shares columns and an estimate scale, and it does not scope labels: every project on a workflow still offers every label.

`workflow` has `title`, `description`, `columns` (an ordered list of `{key, title, type, wipLimit, color}`), `estimateScale` (`none`, `hours`, `storyPoints`, `tshirt`) and `estimateValues` (the allowed values, for example 1, 2, 3, 5, 8). Admins edit workflows in Beheer; project managers pick one in the project settings.

`project.workflow` is an optional reference. A project that follows a workflow still owns its `column` objects, with `project` required as today. Each carries `workflowKey`, the key of the workflow column it was made from. `WorkflowColumnSyncListener` handles OpenRegister's update events: when a workflow's columns change, it adds, renames, reorders and retypes the matching columns of every project that follows it; when a project's `workflow` changes, it builds that project's columns from the workflow. It writes with `_rbac: false`, because the admin who edits the workflow is usually not on every project.

A workflow column that is removed is removed from each project only when it holds no tasks there; otherwise its tasks move to the first column first, and the sync logs how many moved.

Moving a project onto a workflow maps each task's column by column title, and tasks whose column has no match go to the first column. The dialog shows how many tasks that affects before it saves.

The board hides column editing on a project that follows a workflow and names the workflow instead. A WIP limit still counts the tasks of one project, because each project has its own column objects.

Alternative considered: columns owned by the workflow, with `column.project` optional, so every project on a workflow renders the same column objects. It removes the sync, but it breaks the recorded board model where columns belong to the project, and every query that reads columns by `project` would need a second path.

Alternative considered: a label set per workflow. It contradicts the recorded label scope and would break the cross-project label filter of `boards-filters` (lane A).

## Risks / trade-offs

- [The board loses its lanes when the workflow is deleted] -> Deleting a workflow that projects follow is refused with the number of projects, until they are moved off it.
- [A template's dates are meaningless] -> A template stores dates relative to its own `startDate`. The copy shifts them; a template without a `startDate` copies no dates.
- [The sync fails halfway through the projects of a workflow] -> The listener records each project it could not update in the log with the reason, and a repair command `occ planninq:workflow:resync` re-runs it for one workflow.
- [A copied project keeps its `workflowKey` values] -> The copy keeps `project.workflow` and the keys, so the copy follows the same workflow as its source.

## Open questions

- Should a template be publishable to the Store as a configset? The Store page text promises "a project template"; this needs the configset format from openregister and is left for a later change.
