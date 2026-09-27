---
kind: code
---

# Move a task, or a whole column, to another project

## Why

A task filed under the wrong project stays there. Nothing in `src/` writes `task.project` on an
existing task, and the main spec's "Move task to another project" scenario
(`openspec/specs/tasks.md`) has no change directory. The server half is written and unreachable:
`DependencyService::removeEdgesForTask()` documents itself as called from "the move-to-another-
project flow", and no such flow exists (`lib/Listener/TaskDependencyCleanupListener.php:10-16`
explains the delete half was wired, the move half never was).

A Deck user asked to move or copy a whole list with its cards to another board
(https://github.com/nextcloud/deck/issues/3579). In planninq a column is the project's `column`
object, which the board does not read yet (see `boards-configurable-columns`).

Parity rows: `tsk-move-project`, `brd-move-column` in planninq's
`openspec/parity/capabilities.json`.
Decision: build. `tsk-move-project` has four competitors yes; `brd-move-column` is in the boards
core area and carries a feature request.

## What changes

- A member of two projects moves a task from one to the other from the task page or the card
  menu. The task lands in the target project's backlog, its subtasks move with it, and its
  dependency links are removed with a warning first.
- A project member moves or copies a whole column with its tasks to another project they are a
  member of.

## Evidence from the parity matrix

Matrix: `openspec/parity/capabilities.json` in ConductionNL/planninq (compared on 2026-09-26). Rows in this change: `tsk-move-project`, `brd-move-column`.

### `tsk-move-project`: Move a task to another project.

- Area `tasks`. Planninq is rated `no`, built.state `none`, owner `ConductionNL/planninq`.
- Built evidence: "No UI or store action reassigns an existing task's project field. Searched src/ for any 'move to project' control or a store action writing task.project on an existing task; none exists (task.project is only ever set at creation time via the generic object API, which itself has no UI per tsk-create-edit)."
- Demand: none recorded on the row.
- Competitors rated yes (4):
  - Nextcloud Deck 1.18 (no URL in the cell; source or corpus citation only): "corpus: nextcloud-deck/round4/M1-column.md 2.13 'card#reorder moves a card to another stack or board; the UI offers Move card' ; source read at v1.19.0: src/components/cards/CardMenuEntries.vue:57 'Move/copy card'; src/CardMoveDialog.vue:9 'Select a board', :18 'Select a list', :26 'Move card', :104-107 moveCard; lib/Service/CardService.php:436-440 reorder to a stack on another board after edit checks on both"
  - OpenProject 16 Community (no URL in the cell; source or corpus citation only): "docs: opf/openproject HEAD 27a58131 docs/user-guide/work-packages/duplicate-move-delete/README.md:5 'How to duplicate, move to another project or delete a work package'; corpus: openproject/round4/M1-column.md 2.6 bulk move (cited source paths checked present at the released v17.8.0 tag.) ; source read at v17.8.0: frontend/src/app/features/work-packages/components/wp-table/context-menu-helper/wp-context-menu-helper.service.ts:66-68 'Move' action; config/routes.rb:1016-1021 work package move routes; app/controllers/work_packages/moves_controller.rb:47 create, :143-144 target project must be allowed_target_projects_on_move"
  - Kanboard 1.2 (no URL in the cell; source or corpus citation only): "corpus: kanboard/round4/menu-tree.md task actions 'Move to project' ; 'Duplicate to project' ; source read at v1.2.54: app/Template/task/sidebar.php:77 'Move to project', :72 'Duplicate to project'; app/Controller/TaskDuplicationController.php:47 move, :84 copy"
  - Jira Software Data Center 11 (https://confluence.atlassian.com/jirasoftwareserver/moving-an-issue-939938939.html): "corpus: jira-data-center/round4/M1-column.md 2.13 'The Move issue wizard allows you to choose any other project in your Jira instance to move your selected issue to' ; docs read 2026-09-26: https://confluence.atlassian.com/jirasoftwareserver/moving-an-issue-939938939.html 'you may want to move this issue to a different project. You can easily do this using the Move issue wizard' (read 2026-09-26)"

### `brd-move-column`: Move or copy a whole column with its tasks to another project.

- Area `boards`. Planninq is rated `no`, built.state `none`, owner `ConductionNL/planninq`.
- Built evidence: "Columns are the five task statuses, hard-coded at src/utils/taskHelpers.js:252 and mapped at src/views/ProjectBoard.vue:283-291. The per-project column objects written by src/store/projects.js:469 createDefaultColumns are never read back, so there is nothing to move or copy to another project."
- Note: "Demand row mined from nextcloud-deck (featureRequest) on 2026-09-26."
- Demand (featureRequest, via origin): https://github.com/nextcloud/deck/issues/3579
- Competitors rated yes: none.

## Scope

### In scope

- "Move to project" on TaskDetail and in the card action menu.
- "Move column to project" and "Copy column to project" in a column's header menu.
- Wiring `removeEdgesForTask` to a project change on a task.

### Out of scope

- Moving time entries. Booked time stays booked against the project it was logged on.
- Moving tasks between projects in bulk from the backlog; `tasks-search-and-bulk` can add it later.

## Impact

- `src/views/TaskDetail.vue`, `src/views/ProjectBoard.vue`, `src/store/projects.js` (move and
  copy actions), `lib/Listener/TaskDependencyCleanupListener.php` (react to a project change),
  `lib/AppInfo/Application.php` (register the updating event), `l10n/`.
- Depends on: `boards-configurable-columns` for the column half; the task half stands alone.

## Risks

### Risk 1: A moved task keeps an assignee who is not in the target project
**Severity**: Medium
**Mitigation**: the move dialog lists people who are not members of the target and clears them on
confirm; the member keeps access to nothing they should not.

### Risk 2: Moving breaks dependency links silently
**Severity**: Medium
**Mitigation**: cross-project links are not allowed (`DependencyService`), so the dialog names the
links that will be removed before the user confirms, and the listener removes them in the same
write.
