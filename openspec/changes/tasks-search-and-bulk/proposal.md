---
kind: code
---

# Search tasks and change several tasks at once

## Why

There is no way to find a task by its text inside planninq. The project list searches project
titles (`src/views/ProjectList.vue:32-37`) and the member picker searches users, but a board with
eighty cards has no search field. Nextcloud's unified search does return tasks through Open
Register's provider (matrix row `col-unified-search`), yet it lands on the board with the card
highlighted, and it is not where a team triaging its own board looks.

Changing several tasks at once is not possible either. There is no multi-select anywhere
(`src/views/ProjectBoard.vue` moves one card at a time; the backlog page is a placeholder,
`src/views/ProjectBacklog.vue:20-30`). The main spec asks for both ("Task Search" and "Bulk Task
Operations" in `openspec/specs/tasks.md`) and has no change directory.

Parity rows: `tsk-search`, `tsk-bulk` in planninq's `openspec/parity/capabilities.json`.
Decision: build. `tsk-search` has four competitors yes and `tsk-bulk` three.

## What changes

- A search field on the board and on the backlog narrows the visible tasks by title,
  description and task key as the user types.
- On the backlog list, a member selects several tasks and changes their status, assignee,
  priority or labels in one action, and sees how many were updated and which failed.

## Evidence from the parity matrix

Matrix: `openspec/parity/capabilities.json` in ConductionNL/planninq (compared on 2026-09-26). Rows in this change: `tsk-search`, `tsk-bulk`.

### `tsk-search`: Find a task by searching its text.

- Area `tasks`. Planninq is rated `no`, built.state `none`, owner `ConductionNL/planninq`.
- Built evidence: "No task text search exists in src/. The only search-like UI found is src/components/MemberSearch.vue (searches Nextcloud users) and the project title search in src/views/ProjectList.vue; neither searches task text."
- Demand: none recorded on the row.
- Competitors rated yes (4):
  - Nextcloud Deck 1.18 (no URL in the cell; source or corpus citation only): "corpus: nextcloud-deck/round4/menu-tree.md 'Unified search ... Verified live' ; M1-column.md 9.1 'Dorpsstraat found the card' ; source read at v1.19.0: src/components/Controls.vue:65-72 board search field with 'Clear search'; lib/AppInfo/Application.php:136-137 registers DeckProvider and CardCommentProvider for unified search; lib/Search/DeckProvider.php:38 search, lib/Service/SearchService.php:35 searchCards"
  - OpenProject 16 Community (no URL in the cell; source or corpus citation only): "corpus: openproject/round4/M1-column.md 9.1 'app/controllers/search_controller.rb over eight registered searchables: work packages ...' ; source read at v17.8.0: config/routes.rb:326 search; app/controllers/search_controller.rb:141 search_types from lib/redmine/search.rb:43; app/models/work_package.rb:152 acts_as_searchable over subject and description; frontend/src/app/core/global_search/global-search-work-packages.component.ts the header search results"
  - Kanboard 1.2 (no URL in the cell; source or corpus citation only): "corpus: kanboard/round4/M1-column.md 9.1 'the header search box (SearchController::index) runs the same query language across every project' ; source read at v1.2.54: app/Template/dashboard/overview.php:5-9 search box posting to SearchController; app/Controller/SearchController.php:16 index; app/ServiceProvider/FilterProvider.php:227-231 TaskSearchFilter and TaskTitleFilter as the default text match"
  - Jira Software Data Center 11 (https://confluence.atlassian.com/jirasoftwareserver/quick-searching-939938728.html, https://confluence.atlassian.com/jirasoftwareserver/search-syntax-for-text-fields-939938747.html): "corpus: jira-data-center/round4/M1-column.md 9.1 'start typing to search through all your issues and projects'; 4.17 'the text field allows you to search all text fields' ; docs read 2026-09-26: https://confluence.atlassian.com/jirasoftwareserver/quick-searching-939938728.html 'The Search box is located at the top right of your screen ... just start typing what you're looking for'; https://confluence.atlassian.com/jirasoftwareserver/search-syntax-for-text-fields-939938747.html (read 2026-09-26)"

### `tsk-bulk`: Change the status, assignee or labels of several tasks at once.

- Area `tasks`. Planninq is rated `no`, built.state `none`, owner `ConductionNL/planninq`.
- Built evidence: "No multi-select or bulk-action UI exists anywhere in src/ (ProjectBoard.vue has only single-card drag/move; ProjectBacklog.vue is a placeholder, src/views/ProjectBacklog.vue:20-26)."
- Demand: none recorded on the row.
- Competitors rated yes (3):
  - OpenProject 16 Community (no URL in the cell; source or corpus citation only): "corpus: openproject/round4/M1-column.md 2.6 'bulk edit, move, copy and delete on a work package selection' ; source read at v17.8.0: frontend/src/app/features/work-packages/components/wp-table/context-menu-helper/wp-context-menu-helper.service.ts:60-72 'Bulk edit', 'Bulk move', 'Bulk duplicate' on a selection; config/routes.rb:999 work_packages bulk edit, update, destroy; app/controllers/work_packages/bulk_controller.rb"
  - Kanboard 1.2 (no URL in the cell; source or corpus citation only): "corpus: kanboard/round4/M1-column.md 2.6 'TaskBulkController, TaskBulkChangePropertyController ... change assignee, category, color, priority, or move a set of tasks' ; source read at v1.2.54: app/Template/task_list/header.php:20 'Move selected tasks to another column or swimlane', :23 'Edit tasks in bulk'; app/Template/task_bulk_change_property/show.php:25 assignee, :54 'Just add these tag(s)'; app/Template/board/table_column.php:41 'Close all tasks in this column and this swimlane'"
  - Jira Software Data Center 11 (https://confluence.atlassian.com/jirasoftwareserver/editing-multiple-issues-at-the-same-time-939938937.html): "corpus: jira-data-center/round4/M1-column.md 2.6 'You can do this by performing a bulk change operation' with edit, move, delete, watch and transition ; docs read 2026-09-26: https://confluence.atlassian.com/jirasoftwareserver/editing-multiple-issues-at-the-same-time-939938937.html 'At some point, you may need to change multiple issues at the same time. You can do this by performing a bulk change operation' (read 2026-09-26)"

## Scope

### In scope

- A search field in the board header and the backlog header, client-side on the loaded tasks.
- Selection and a bulk action bar on the backlog list.

### Out of scope

- Search across all projects: unified search already covers it (`col-unified-search`), and "my
  tasks" lives in `portfolio-my-work-dashboard`.
- Bulk selection on the kanban board itself.
- Saved searches: `boards-filters` owns saved filters.

## Impact

- `src/views/ProjectBoard.vue`, `src/views/ProjectBacklog.vue` (after `backlog-list`),
  `src/utils/taskHelpers.js` (match helper), `src/store/projects.js` (bulk patch), `l10n/`.
- Depends on: `backlog-list` (the list the bulk bar sits on), `tasks-assignment-priority-labels`
  (the people and label pickers the bar reuses).

## Risks

### Risk 1: A bulk change half succeeds
**Severity**: Medium
**Mitigation**: each task is PATCHed on its own; the toast reports "{n} tasks updated" and lists
the ones that failed, which stay selected so the user can retry.

### Risk 2: Search and label filter disagree on counts
**Severity**: Low
**Mitigation**: both narrow the same `visibleTasks` list, so lane counts follow both.
