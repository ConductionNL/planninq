# Design: plan tasks against a release and see a roadmap of releases and epics

## Context

What exists at de35541:

- No release or version concept. The seven schemas (`lib/Settings/planninq_register.json:22-30`) have no such property, and `grep -rni "release\|fixVersion\|milestone" src lib` finds only comments about app releases.
- `task.issueType` (`lib/Settings/planninq_register.json:324-329`) is a free string defaulting to `task`, meant to carry imported vocabularies such as `epic` or `story`. `task.epic` (`:376-383`) is a nullable `$ref: task` pointing at the epic-level task, "distinct from parent, which models sub-tasks". No code in `src/` or `lib/` reads or writes either field.
- `task.startDate` and `task.dueDate` (`:268-279`) are the only dates a task carries.
- `projectPhase` (`:584-752`) is the precedent for a project-scoped object: `project` is a `$ref`, and its authorization block (`:596-690`) grants every operation to members of that project.
- The `FeaturesRoadmap` page (`src/manifest.json:214-221`) is `type: "roadmap"`, rendered by `CnFeaturesAndRoadmapView` from `docs/features.json` and the GitHub proxy. It is the product roadmap. The `ProjectTimeline` note (`src/manifest.json:147`) records that the library's roadmap type "renders milestones on a track and has no vocabulary for task bars".
- The timeline already has pure layout helpers for bars on a day axis: `toScheduled` and `buildLayout` in `src/utils/timelineHelpers.js:75-103`, used by `src/views/ProjectTimeline.vue:219-233`. Its day, week and month zoom lives in `PX_PER_DAY` (`src/utils/timelineHelpers.js:24`).
- The project board header carries the Backlog and Timeline buttons (`src/views/ProjectBoard.vue:41-55`).

What is missing: a release object, a task-to-release link, a way to make and link epics in the UI, and a roadmap of the user's own work.

## Goals / non-goals

Goals:
- Plan tasks against a named, dated release and see how far each release is.
- One roadmap per project showing releases and epics on a time axis, with a list that works without the chart.

Non-goals:
- Cross-project roadmaps, release burndowns, several releases per task.
- Touching the product roadmap page.

## Decisions

### Decision 1: a `release` schema scoped to a project
Amended at build (29 Sep): the schema slug is `projectRelease`, next to `projectPhase` and `projectPortfolio`. `release` was free in the fleet slug contract (gate-106), but it is a word an app like versioniq can claim next, and learniq's claim on `portfolio` already cost this repo a rename. The task property stays `task.release`. The schema is added to the project-scoped lists (ProjectMembershipService::SCOPED_SCHEMAS, the membership subscription, ProjectMemberAccessListener's gated set), so the members list is stamped on every write and an outsider's create is refused, exactly as for `risk`.

Properties: `title` (required), `project` (required, `$ref: project`), `description`, `startDate` (nullable date), `releaseDate` (the target date, nullable date), `status` (`planned`, `released`, `archived`; default `planned`), `releasedAt` (nullable date-time). Schema.org `schema:CreativeWork`. Authorization copies `projectPhase`'s block. A release belongs to one project, like Jira's project fix versions and OpenProject's project versions. Sharing a release across projects (OpenProject's version sharing) is left out: it needs a cross-project authorization rule the register does not have today.

### Decision 2: one `task.release`, nullable
A task targets at most one release. The backlog filter "release = X" and the progress count stay scalar-equality reads, which is what `fetchEvery` (`src/store/projects.js:61-94`) and OpenRegister's aggregation support. The alternative, an array like OpenProject 17.8, needs a contains-filter that the store avoids on purpose (`src/store/projects.js:178-179` explains why the array filter is not sent to the server).

### Decision 3: epics reuse `issueType` and `epic`, no new schema
A task becomes an epic by setting `issueType: epic`. Any other task in the same project links to it through `task.epic`. The task detail offers a "This task is an epic" switch and an "Epic" picker listing the project's epics. This matches the fields the register already declares for imported Jira data, so imported and native epics look the same. An epic cannot point at another epic, and the picker offers only tasks of the same project.

### Decision 4: the user roadmap is a view of the Timeline tab, reusing the timeline helpers
Amended at build (29 Sep): no new manifest page. Every new `type: "custom"` page adds a finding to the gate-69 custom-page ratchet (17 custom pages at this point), and no typed page (index, detail, dashboard) or widget draws bars on a time axis. The Timeline tab already is the project's time-axis page, so the roadmap is its second view: a "Tasks / Roadmap" switch in the Timeline header, the same pattern as the board's "Board / List" switch, kept in the route as `/projects/:id/timeline?view=roadmap` so a link or a reload keeps it. The view is the component `src/components/ProjectRoadmap.vue`, not a page. The Timeline's zoom applies to both views. It is reached from the project tabs, which replaced the board header's Backlog and Timeline buttons, so no button is added to the board header (that would also grow the gate-114 header-action budget).

Original text: It reads the project's releases and epic tasks through the object store and lays them out with `buildLayout` from `src/utils/timelineHelpers.js`: epics as bars, releases as a marker on their `releaseDate`. An epic without its own dates spans from the earliest `startDate` to the latest `dueDate` of the tasks linked to it (a new pure helper `epicSpan`). The library's `type: "roadmap"` is not used: it is the product roadmap renderer, and the manifest already notes it has no vocabulary for bars. No controller is added: every read is an object-store read (ADR-022).

### Decision 5: releases are managed on the roadmap
The roadmap lists releases under the chart, in target-date order, each with its progress (done tasks out of all tasks with that `release`), and offers "New release", "Edit" and "Mark as released". The list is also the keyboard and screen-reader equivalent of the chart (ADR-059). Placement is Projecten > project > Roadmap (ADR-001): a project view, not a menu. ADR-001 lists "roadmap" under Portfolio; that is the cross-project roll-up, which this change leaves to lane C.

### Decision 6: shipping a release asks about unfinished work
"Mark as released" opens `ReleaseShipDialog`. When every task of the release is `done` or `cancelled`, it just sets `status: released` and `releasedAt`. When some are not, it lists them and offers: move them to another planned release, clear their release, or keep them on this release. The member decides; the dialog never moves work silently.

## Risks / trade-offs

- [Two pages called roadmap] -> Different labels and places: "Features & roadmap" in the footer for the product, "Roadmap" inside a project for the user's work.
- [Epics with no dated tasks] -> Listed under "Not scheduled yet", never dropped.
- [Epic links across projects] -> The picker offers only the same project's epics; a cross-project `epic` value from an import is shown as a plain link on the task and left off the roadmap.
- [Schema count assertions] -> Updated in the same PR.
