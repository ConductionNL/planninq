# Design: import a Gantt plan made in Microsoft Project

## Context

What exists at de35541:

- `RegisterImportService` (`lib/Service/RegisterImportService.php:43-160`) imports planninq's own register configuration from `lib/Settings/planninq_register.json`; it has nothing to do with user plans.
- The register holds every object a plan needs:
  - `task` (`lib/Settings/planninq_register.json:39-390`): `title`, `description`, `startDate`, `dueDate`, `estimatedDuration` (minutes), `percentComplete` (0 to 100), `parent` (one sub-task level, `docs/ARCHITECTURE.md` section 5 question 2), `phase`, `issueType` (free string), `metadata` (declared catch-all for source-system fields such as import provenance, `:384-389`).
  - `projectPhase` (`:584-752`): `title`, `project`, `status`, `order`, `startDate`, `endDate`; no `metadata` yet.
  - `dependency` (`:1106-1167`): `blocker`, `blocked`, `type` (`blocks`, `relates`, and others). Edges are created through `DependencyService::create`, which rejects self edges, duplicates, cross-project edges, cycles and non-members (`lib/Service/DependencyService.php:112-141`).
- The timeline (`src/views/ProjectTimeline.vue`, fed by `GET /api/projects/{projectId}/timeline`) draws tasks with dates and the dependency edges between them; it is where a user looks at an imported plan.
- Controllers that carry real logic exist next to the object API: `DependencyController` (validation) and `ProjectController` (creation policy). The hydra gates require every `#[NoAdminRequired]` method to guard access per object.

Microsoft Project's XML format (MSPDI) lists `Task` elements with `UID`, `Name`, `OutlineLevel`, `Summary`, `Milestone`, `Start`, `Finish`, `Duration`, `PercentComplete`, `Notes` and `PredecessorLink` children (`PredecessorUID`, `Type` 0 to 3 for finish-to-finish, finish-to-start, start-to-finish and start-to-start, `LinkLag`).

## Goals / non-goals

Goals:
- A contractor's plan in planninq in one confirmed step, with a clear list of what did not carry over.
- A newer version of the same plan updates what the first import created.

Non-goals:
- `.mpp`, resources, calendars, costs, baselines, export, deleting tasks missing from a newer file.

## Decisions

### Decision 1: the XML format only
The import reads MSPDI XML. `.mpp` is a closed binary format with no PHP reader; Project saves the same plan with "File, Save as, XML format", and the import dialog says so. The parser uses PHP's XML reader with external entities and network access off, rejects any DOCTYPE, and enforces 10 MB and 2,000 tasks before mapping.

### Decision 2: a fixed mapping onto planninq's model
- A summary task at outline level 1 becomes a `projectPhase` (title, start, end, order by position).
- A task at outline level 2 under such a phase, or any non-summary task at level 1, becomes a `task` with `phase` set when it has one.
- A task at level 3 or deeper becomes a sub-task (`parent`) of its level-2 ancestor; its outline path is kept at the top of its description. Planninq allows one sub-task level, so deeper levels collapse.
- A milestone becomes a task with `issueType: milestone` and `startDate` equal to `dueDate`.
- `Start` and `Finish` become `startDate` and `dueDate` (dates only), `Duration` becomes `estimatedDuration` in minutes, `PercentComplete` becomes `percentComplete`, and `Notes` becomes `description`. A task at 100 percent gets status `done`, one above 0 `in_progress`, otherwise `open`.
- A finish-to-start link becomes a `dependency` of type `blocks`; any other link type becomes `relates`, and every lag is dropped. Links are created through `DependencyService`, so its checks apply.
- Every created object stores `metadata.msProjectUid` and `metadata.msProjectFile` (the plan's `Name` and `SaveVersion`); `projectPhase` gains the same declared `metadata` object.

### Decision 3: preview first, then commit, both stateless
`ProjectImportController` has `POST /api/projects/{projectId}/import/msproject/preview` and `POST /api/projects/{projectId}/import/msproject`, both `#[NoAdminRequired]` with an in-method check that the caller is the project's owner or an admin (a plan rewrites a project's structure, so the owner decides, like managing columns). Preview parses and maps and returns counts per kind, the first 50 tasks with their dates, and the list of losses ("3 start-to-start links become related links", "12 tasks had resources, which are not imported", "2 tasks at level 4 were placed under their level-2 task"). Commit takes the same file again and writes in order: phases, tasks, sub-tasks, dependencies, each through `ObjectService` with the caller's rights. Nothing is held on the server between the two calls.

### Decision 4: re-import updates by Project UID
Before writing, commit reads the project's tasks and phases with a `metadata.msProjectUid`. A matching object is updated (title, dates, duration, progress, notes, phase or parent); an unmatched one is created; an existing imported object whose UID is missing from the new file is listed in the result as "no longer in the plan" and left alone. The same rule makes a failed import safe to run again.

### Decision 5: the entry point is the timeline
"Import from Microsoft Project" sits in the timeline header for the project owner and opens `MsProjectImportDialog` (in `src/dialogs/`): a file field, the preview with its losses, and "Import". After import the timeline reloads and shows the plan. Placement is Projecten > project > timeline (ADR-001); this is a user action on one project, not integration configuration, so it is not in Beheer.

## Risks / trade-offs

- [Malicious XML] -> No entities, no network, no DOCTYPE, size and count caps (Decision 1).
- [Model mismatch] -> Fixed mapping, every loss counted in the preview (Decision 2).
- [Half-finished import] -> Ordered writes, UID matching, safe to rerun (Decision 4).
- [Large plans in one request] -> The 2,000-task cap keeps a commit within a normal request; larger plans are refused with that reason.

## Amendments at build (29 Sep 2026)

The design was written at de35541. What the code at 5e33023 needed, and what changed while building:

- **Links go through `DependencyService::createImported()`**, not `create()` once per link. `create()` reads every task and edge of the project for each link, which is 4,000 searches for a large plan, and its membership step refuses an admin who is not on the project although Decision 3 lets admins import. `createImported()` reads the project's edges once and applies the same duplicate, self-link and cycle checks to each link against that list as it grows. The controller has already checked owner-or-admin, and every task id is one the import created or matched in the project.
- **Related links never block.** Nothing read `dependency.type` before this change, so an imported `relates` link would have blocked its task and counted in cycle checks. `DependencyGraph` and `src/utils/taskHelpers.js` now count only edges without a type or of type `blocks`; the timeline receives `type` and draws a related link as a dashed line without an arrow.
- **Imported tasks have no board column.** The board is column-based since boards-configurable-columns: a task without `column` is in the backlog, and that is where imported tasks land. "Move to board" puts them in a lane.
- **Imported phases have no status.** A phase closes only with its concluding document (planning-phase-gate-document), so the import never writes one; a phase starts as `open`.
- **A re-import leaves `status` alone** and changes only what the plan owns (title, dates, duration, progress, notes, phase or parent); what members set in planninq stays.
- **The outline path is kept only for a collapsed task** (level 4 and deeper). A level-3 task sits under its level-2 task where it was, so its path adds nothing.
- **Provenance keys:** `metadata.msProjectUid`, `metadata.msProjectFile` (the plan's `Name`) and `metadata.msProjectSaveVersion`.
- **The fixture** `tests/fixtures/msproject/contractor-plan.xml` is written by hand in the MSPDI format, not exported from Project; no copy of Project was available to the build.
- **Refusals carry a reason code** (`mpp`, `unsafe`, `tooLarge`, `tooManyTasks`, `notAPlan`, `noFile`) that the dialog explains; an `.mpp` file is refused in the browser before upload as well.
