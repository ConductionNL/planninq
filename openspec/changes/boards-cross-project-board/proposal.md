---
kind: code
---

# Work tasks from several projects on one shared board

## Why

A team that works across several projects cannot put their work on one board. Every board in planninq belongs to exactly one project: `ProjectBoard` is mounted on `/projects/:id` (`src/manifest.json:145`) and loads its cards with `fetchTasks(projectId)`, a read filtered to one `project` id (`src/store/projects.js:775-783`). The Borden page (`src/views/Boards.vue`) is a list of those per-project boards, one card per active member project, each routing to its own board (`:22-40`, `fetchProjects` at `:90`, `openBoard` at `:110`). An IT team with a "Servers" project and a "Workplace" project, or a person who serves three projects in one week, switches between boards and never sees the whole queue in one place.

Jira boards are built on a filter and "display issues from one or more projects".

Parity rows: `brd-cross-project-board` in planninq's `openspec/parity/capabilities.json`.
Decision: build, because boards are a core area of planninq and a board limited to one project leaves cross-project teams without one.

This change extends the flat spec `openspec/specs/kanban-board.md` through a new capability, `shared-boards`.

## What changes

- A user can create a shared board, give it a name, and choose which of their projects it shows.
- The owner can share the board with other people, who then find it on the Borden page.
- The shared board shows the tasks of all its projects in status columns, with each card naming its project. Moving a card between columns changes its status, as on a project board.
- Nobody sees a task through a shared board that they could not see in its own project.

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

- A `board` schema: name, owner, the people it is shared with, and the projects it shows.
- A "Shared boards" section on the Borden page with "New shared board", and a shared board page at `/boards/:id`.
- Status columns, drag and the keyboard move menu, the project chip on each card, and the existing label filter.

### Out of scope

- Custom columns on a shared board. Each project may have its own columns (`boards-configurable-columns`), so the shared board uses the one vocabulary all projects share: the task status.
- Saved queries beyond "these projects" (Jira's JQL filters).
- Creating a task from the shared board. A task needs one project; it is created on that project's board.
- Column rules (`boards-column-automation`); they belong to a project's columns.

## Impact

- Schema: new `board`, with authorization that lets the owner and the people it is shared with read it, and only the owner change or delete it.
- Views: `src/views/Boards.vue` gains the shared boards section; a new `SharedBoard` page (manifest and `src/registry.js`) at `/boards/:id`.
- Components: `TaskCard` gains an optional project chip; the status grouping reuses `groupTasksByStatus` (`src/utils/taskHelpers.js:268-283`).
- Dialogs: `src/dialogs/SharedBoardEditDialog.vue`.
- Specs and tests: the exact schema count in `openspec/specs/project-delivery/spec.md:68-72` and `tests/unit/Settings/PlanninqRegisterSchemaTest.php:370` moves up by one.
- Depends on: `boards-filters` (lane A) for assignee and priority filters, which the shared board reuses once they exist.

## Risks

### Risk 1: a shared board leaks tasks of a project the viewer is not in
**Severity**: High
**Mitigation**: the board stores project ids only; the tasks are read with the viewer's own rights through OpenRegister, and the task schema only returns tasks of projects the viewer is a member of. The board says how many of its projects are hidden from the viewer, without naming them.

### Risk 2: a big shared board is slow
**Severity**: Medium
**Mitigation**: one paged read per project, in parallel, through the same `fetchEvery` the project board uses. The board shows each project's cards as its read finishes, and caps a board at 20 projects.

### Risk 3: schema count assertions break
**Severity**: Low
**Mitigation**: updated in the same PR as the schema, in whatever order the schema-adding changes of this pass land.
