---
kind: code
---

# Turn the backlog placeholder into a working backlog

## Why

Every project board has a Backlog button (`src/views/ProjectBoard.vue:43-48`), and it opens a page
that says "Backlog view coming soon. Task management will be available in a future update."
(`src/views/ProjectBacklog.vue:20-30`). The manifest describes "an ordered task list scoped to :id"
(`src/manifest.json:146`), and the main spec keeps the placeholder on purpose until task
management lands (`openspec/specs/projects.md`, "Project Backlog Route", note: retire it "when task
management lands"). With `tasks-create-edit-delete` and `boards-configurable-columns` in this pass,
that moment is here.

The task schema already defines the backlog: a task whose `column` is empty ("null = backlog").
Four matrix rows are marked `specified` although no change directory ever carried them; this change
is that directory.

Parity rows: `bkl-backlog`, `bkl-sort-filter`, `bkl-to-board`, `bkl-rank` in planninq's
`openspec/parity/capabilities.json`.
Decision: build. The first three were marked specified with no change directory (a claim the pass
must back or retract); `bkl-backlog` and `bkl-sort-filter` have two competitors yes, `bkl-to-board`
and `bkl-rank` three.

## What changes

- The backlog page lists the project's tasks that are on no column, in rank order.
- A member creates a task straight into the backlog.
- A member ranks the backlog by dragging rows or with "Move up" and "Move down".
- A member sorts by rank, priority, due date or creation date, and filters with the board's filter
  bar; cancelled tasks show only with the "Cancelled" filter.
- A member moves a task onto the board with "Move to board" and a column choice, and takes a card
  back to the backlog from the board.

## Evidence from the parity matrix

Matrix: `openspec/parity/capabilities.json` in ConductionNL/planninq (compared on 2026-09-26). Rows in this change: `bkl-backlog`, `bkl-sort-filter`, `bkl-to-board`, `bkl-rank`.

### `bkl-backlog`: Keep tasks that are not yet planned in a backlog apart from the board.

- Area `backlog`. Planninq is rated `no`, built.state `specified`, owner `ConductionNL/planninq`.
- Built evidence: "src/views/ProjectBacklog.vue:20-26 is a literal placeholder: NcEmptyContent with name 'Backlog view coming soon' and description 'Task management will be available in a future update.' Component-level comment confirms: 'Stays a placeholder until tasks#REQ-Task-CRUD lands.'"
- Demand: none recorded on the row.
- Competitors rated yes (2):
  - OpenProject 16 Community (no URL in the cell; source or corpus citation only): "corpus: openproject/round4/menu-tree.md 'Inside a project > Backlogs sprints and story points'; docs: opf/openproject HEAD 27a58131 docs/user-guide/backlogs-scrum/README.md:14 'record and prioritize work packages in sprints and the backlog' (cited source paths checked present at the released v17.8.0 tag.) ; source read at v17.8.0: modules/backlogs/config/routes.rb:65 project backlog page and :73 backlog buckets; modules/backlogs/app/components/backlogs/inbox_component.rb:32 'Backlog inbox', modules/backlogs/config/locales/en.yml:53, of unplanned work packages; modules/backlogs/app/controllers/backlogs/backlog_controller.rb:40 show"
  - Jira Software Data Center 11 (https://confluence.atlassian.com/jirasoftwareserver/using-your-kanban-backlog-938845378.html): "docs: https://confluence.atlassian.com/jirasoftwareserver/using-your-kanban-backlog-938845378.html 'The Kanban backlog shows issues in both Backlog and Selected for Development sections' and team members can 'focus on your work-in-progress on the Kanban board, without the distraction of items in planning' ; docs read 2026-09-26: https://confluence.atlassian.com/jirasoftwareserver/using-your-kanban-backlog-938845378.html 'sample Kanban backlog ... with Backlog and Selected for Development as board columns'; team members focus on the Kanban board 'without the distraction' of the backlog (read 2026-09-26)"

### `bkl-sort-filter`: Sort and filter the backlog.

- Area `backlog`. Planninq is rated `no`, built.state `specified`, owner `ConductionNL/planninq`.
- Built evidence: "Same placeholder page, src/views/ProjectBacklog.vue:20-26; no sort or filter controls exist."
- Demand: none recorded on the row.
- Competitors rated yes (2):
  - Plane Community 1.4 (no URL in the cell; source or corpus citation only): "corpus: plane/round4/code-census.md §9 IssueView filters and display_filters ; usability.md 'Layouts, filtering ... are all good' ; source read at v1.4.2: apps/web/core/components/issues/issue-layouts/filters/header/display-filters/display-filters-selection.tsx:112-118 order_by selector; apps/web/core/components/work-item-filters/filters-hoc/project-level.tsx:206 filters; apps/api/plane/utils/issue_filters.py:298-300 backlog type; apps/api/plane/utils/order_queryset.py:12 order_by allowlist"
  - Jira Software Data Center 11 (https://confluence.atlassian.com/jirasoftwareserver/configuring-quick-filters-938845301.html, https://confluence.atlassian.com/jirasoftwareserver/using-your-kanban-backlog-938845378.html): "docs: https://confluence.atlassian.com/jirasoftwareserver/configuring-quick-filters-938845301.html quick filters apply on 'a Scrum board or Kanban board' including its backlog; order is Rank (docs: https://confluence.atlassian.com/jirasoftwareserver/using-your-kanban-backlog-938845378.html 'the issues you're creating and ranking for your team') ; docs read 2026-09-26: https://confluence.atlassian.com/jirasoftwareserver/configuring-quick-filters-938845301.html quick filters 'further filter the collection of issues appearing on a Scrum board or Kanban board'; https://confluence.atlassian.com/jirasoftwareserver/using-your-kanban-backlog-938845378.html 'Drag and drop an issue to rank it' (read ... (shortened; full text in the matrix row)"

### `bkl-to-board`: Move a task from the backlog onto the board.

- Area `backlog`. Planninq is rated `no`, built.state `specified`, owner `ConductionNL/planninq`.
- Built evidence: "Same placeholder page, src/views/ProjectBacklog.vue:20-26; no task list, no move-to-board action exists at all."
- Demand: none recorded on the row.
- Competitors rated yes (3):
  - OpenProject 16 Community (no URL in the cell; source or corpus citation only): "docs: opf/openproject HEAD 27a58131 docs/user-guide/backlogs-scrum/README.md:234 'Your sprint is set in motion by clicking the Start sprint button ... it will open the sprint board'; basic boards add an existing work package as a card (agile-boards README:139) (cited source paths checked present at the released v17.8.0 tag.) ; source read at v17.8.0: modules/backlogs/config/routes.rb:105-107 move and move_to_sprint_dialog; modules/backlogs/app/controllers/backlogs/work_packages_controller.rb:71 move to sprint, :97-103 move via Backlogs::WorkPackages::UpdateService; modules/backlogs/app/services/backlogs/sprints/start_service.rb:66 starting the sprint builds its board, which shows the moved ... (shortened; full text in the matrix row)"
  - Plane Community 1.4 (no URL in the cell; source or corpus citation only): "corpus: plane/round4/code-census.md §2 State group; moving a work item from a backlog-group state to an unstarted or started state is an ordinary state change (drag or state menu) ; source read at v1.4.2: apps/web/core/components/issues/issue-layouts/kanban/kanban-group.tsx:136,171 drop a card from a backlog-group column into another state column; apps/web/core/components/issues/issue-modal/components/default-properties.tsx:90 state_id dropdown; apps/api/plane/app/views/issue/base.py:628 partial_update; apps/api/plane/db/models/state.py:15 BACKLOG group"
  - Jira Software Data Center 11 (https://confluence.atlassian.com/jirasoftwareserver/using-your-kanban-backlog-938845378.html): "docs: https://confluence.atlassian.com/jirasoftwareserver/using-your-kanban-backlog-938845378.html 'This makes it easy for you to move issues from the backlog to selected for development. Issues selected for development will then appear in your Kanban board' ; docs read 2026-09-26: https://confluence.atlassian.com/jirasoftwareserver/using-your-kanban-backlog-938845378.html 'This makes it easy for you to move issues from the backlog to selected for development. Issues selected for development will then appear in your Kanban board' (read 2026-09-26)"

### `bkl-rank`: Rank the backlog by dragging items up and down.

- Area `backlog`. Planninq is rated `no`, built.state `none`, owner `ConductionNL/planninq`.
- Built evidence: "No backlog list exists to rank (src/views/ProjectBacklog.vue:20-26 placeholder) and no ordering field/drag UI for a backlog exists anywhere."
- Demand: none recorded on the row.
- Competitors rated yes (3):
  - OpenProject 16 Community (no URL in the cell; source or corpus citation only): "docs: opf/openproject HEAD 27a58131 docs/user-guide/backlogs-scrum/README.md:170 'You can prioritize work packages within the Inbox backlog, a backlog bucket, or a sprint by dragging and dropping them' (cited source paths checked present at the released v17.8.0 tag.) ; source read at v17.8.0: modules/backlogs/app/components/backlogs/work_package_card_list_component.rb:41-59 drag_and_drop on the card list; modules/backlogs/app/controllers/backlogs/work_packages_controller.rb:97-110 move stores the new position, same-list moves reorder"
  - Plane Community 1.4 (no URL in the cell; source or corpus citation only): "corpus: plane/round4/code-census.md §2 Issue 'sort_order float (:158)'; list and board support manual drag ordering (kanban/block.tsx:206 draggable) ; source read at v1.4.2: apps/web/core/components/issues/issue-layouts/list/block.tsx:117 draggable rows in the list layout and apps/web/core/components/issues/issue-layouts/kanban/block.tsx:206 on the board; apps/web/core/components/issues/issue-layouts/utils.tsx:491-518 new sort_order between neighbours; apps/api/plane/db/models/issue.py:158 sort_order"
  - Jira Software Data Center 11 (https://confluence.atlassian.com/jirasoftwareserver/using-your-kanban-backlog-938845378.html): "docs: https://confluence.atlassian.com/jirasoftwareserver/using-your-kanban-backlog-938845378.html 'an optimized list view of the issues you're creating and ranking for your team' ; docs read 2026-09-26: https://confluence.atlassian.com/jirasoftwareserver/using-your-kanban-backlog-938845378.html 'Drag and drop an issue to rank it. You can also right-click the issue to open a menu that allows you to send it to the top or the bottom of the backlog' (read 2026-09-26)"

## Scope

### In scope

- `src/views/ProjectBacklog.vue` as a list with rank, sort, filter, create and move.
- "Move to backlog" on a board card.
- Retiring the placeholder scenario of the main spec's "Project Backlog Route" at archive time.

### Out of scope

- Bulk selection: `tasks-search-and-bulk` adds it to this list.
- Sprints and releases: `backlog-sprints` and `backlog-releases-roadmap` build on this list.
- Backlog statistics (V1 in docs/FEATURES.md, no matrix row).

## Impact

- `src/views/ProjectBacklog.vue`, `src/views/ProjectBoard.vue` (card action), `src/store/projects.js`
  (backlog query and rank writes), `src/utils/` (sorting), `l10n/`.
- Depends on: `boards-configurable-columns` (a task with no column is the backlog; the move target
  is a column), `tasks-create-edit-delete` (the task dialog), `boards-filters` (the filter bar).

## Risks

### Risk 1: The backlog and the board show the same task
**Severity**: Medium
**Mitigation**: the board shows tasks with a column, the backlog tasks without one; the repair step
in `boards-configurable-columns` gives every existing active task a column first, so the backlog
starts empty rather than duplicating the board.

### Risk 2: Rank writes on every drag
**Severity**: Low
**Mitigation**: the same sparse ordering as board lanes (steps of 1000, midpoint on drop).
