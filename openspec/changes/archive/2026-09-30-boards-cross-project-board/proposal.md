---
kind: code
---

# Work tasks from several projects in one cross-project view

## Why

A team that works across several projects cannot see and move their work in one place. Every board in planninq belongs to exactly one project: `ProjectBoard` is mounted on `/projects/:id` (`src/manifest.json:145`) and loads its cards with `fetchTasks(projectId)`, a read filtered to one `project` id (`src/store/projects.js:775-783`). The Borden page (`src/views/Boards.vue`) is a list of those per-project boards, one card per active member project, each routing to its own board (`:22-40`, `fetchProjects` at `:90`, `openBoard` at `:110`). An IT team with a "Servers" project and a "Workplace" project, or a person who serves three projects in one week, switches between boards and never sees the whole queue at once.

`docs/ARCHITECTURE.md:148` records the board model: "1 project = 1 kanban board; columns belong to project directly". This change keeps that rule. It adds no second board to any project: it adds a saved view that shows the tasks of several projects together and moves a card only through its own project's columns, with the viewer's own rights.

Jira boards are built on a filter and "display issues from one or more projects".

Parity rows: `brd-cross-project-board` in planninq's `openspec/parity/capabilities.json`.
Decision: build, because boards are a core area of planninq and a board limited to one project leaves cross-project teams without a shared view of their work.

This change extends the flat spec `openspec/specs/kanban-board.md` through a new capability, `cross-project-view`.

## What changes

- A user can save a cross-project view: a name and a list of their projects.
- The owner can share the view with other people, who find it on the Borden page.
- The view shows the tasks of all its projects in status lanes, with each card naming its project, and the filter bar of the project boards.
- Moving a card to another lane moves the task into the matching column of its own project, exactly as a move on that project's board would, so every project keeps one board and one truth.
- Nobody sees or moves a task through a view that they could not see or move in its own project.

## Evidence from the parity matrix

Matrix: `openspec/parity/capabilities.json` in ConductionNL/planninq (compared on 2026-09-26). Rows in this change: `brd-cross-project-board`.

### `brd-cross-project-board`: Work tasks from several projects on one shared board.

- Area `boards`. Planninq is rated `no`, built.state `none`, owner `ConductionNL/planninq`.
- Built evidence: "src/views/ProjectBoard.vue is mounted per project (route /projects/:id, fetchTasks(projectId) at src/store/projects.js:775, filtered to one `project` id); there is no board that aggregates tasks across multiple projects."
- Demand: none recorded on the row.
- Competitors rated yes (1):
  - Jira Software Data Center 11 (https://confluence.atlassian.com/jirasoftwareserver/creating-a-board-938845220.html): "docs: https://confluence.atlassian.com/jirasoftwareserver/creating-a-board-938845220.html 'A board displays issues from one or more projects' and 'base your board on an existing Saved Filter' ; docs read 2026-09-26: https://confluence.atlassian.com/jirasoftwareserver/creating-a-board-938845220.html 'A board displays issues from one or more projects'; board filter 'project = "[YOUR PROJECT(S)]" ORDER BY Rank ASC' (read 2026-09-26)"


## Scope

### In scope

- A `boardView` schema: name, owner, the people it is shared with, and the projects it shows. It holds no columns, no card order and no tasks.
- A "Cross-project views" section on the Borden page with "New view", and a view page at `/boards/views/:id`.
- Status lanes, drag and the keyboard move menu, the project chip on each card, and the filter bar.

### Out of scope

- Columns, WIP limits, card order or column rules of the view's own. Those belong to each project's one board.
- Creating a task on the view. A task needs one project; it is created on that project's board.
- Saved query languages beyond "these projects" plus the filter bar (Jira's JQL).

## Impact

- Schema: new `boardView`, with authorization that lets the owner and the people it is shared with read it, and only the owner change or delete it.
- Views: `src/views/Boards.vue` gains the cross-project views section; a new `ProjectsView` page (manifest and `src/registry.js`) at `/boards/views/:id`.
- Components: `TaskCard` gains an optional project chip; lanes group with `groupTasksByStatus` (`src/utils/taskHelpers.js:268-283`).
- Dialogs: `src/dialogs/ProjectsViewEditDialog.vue`.
- Specs and tests: the exact schema count in `openspec/specs/project-delivery/spec.md:68-72` and `tests/unit/Settings/PlanninqRegisterSchemaTest.php:370` moves up by one.
- Depends on: `boards-configurable-columns` (each project's columns and the `status` each column maps, which a move on the view resolves to); `boards-filters` (the filter model and `matchesFilter` helper the view reuses).

## Risks

### Risk 1: a view leaks tasks of a project the viewer is not in
**Severity**: High
**Mitigation**: the view stores project ids only; tasks are read and written with the viewer's own rights through OpenRegister, and the task schema only returns and accepts tasks of projects the viewer is a member of. The view says how many of its projects are hidden from the viewer, without naming them.

### Risk 2: a move on the view and the project board disagree
**Severity**: Medium
**Mitigation**: the view never writes `status` alone. A move resolves to the first column of the task's own project that maps the target status and writes the same `column`, `columnOrder` and `status` a move on that board writes, so column rules and the `completedAt` stamp run as usual. A project with no such column refuses the move for that card and says so.

### Risk 3: a big view is slow
**Severity**: Medium
**Mitigation**: one paged read per project, in parallel, through the same `fetchEvery` the project board uses; cards render per project as reads finish, and a view holds at most 20 projects.
