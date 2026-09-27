---
kind: code
---

# Let each project's board run on its own columns

## Why

Every project board shows the same five lanes, Open, In progress, Blocked, Done and Cancelled,
because the board maps the task status enum to lanes (`src/views/ProjectBoard.vue:283-291`,
`BOARD_STATUSES` in `src/utils/taskHelpers.js:252`). Meanwhile each new project gets four real
`column` objects with titles, order, WIP limits and a done type
(`createDefaultColumns`, `src/store/projects.js:469`), and nothing ever reads them back. The
column schema is write-only. So a team cannot add a "Review" lane, rename a lane, set a WIP limit
it can see, or mark which lane means done, and the order of cards inside a lane is whatever the
API returns: `task.columnOrder` exists and is never read or written.

The admin setting for default columns is saved (`lib/Service/SettingsService.php:61`,
`src/views/settings/Settings.vue:439-495`) but does not reach project creation either: the store
reads it with `loadState('planninq', 'default_columns')` and planninq provides no initial state,
so the hard-coded four always win.

Configurable columns, WIP limits and card order are MVP in `docs/FEATURES.md` and every
competitor has them.

Parity rows: `brd-columns-edit`, `prj-default-columns`, `brd-done-column`, `brd-wip-limits`,
`brd-card-order` in planninq's `openspec/parity/capabilities.json`.
Decision: build. All five are in the boards and projects core areas; `brd-columns-edit` and
`brd-card-order` have five competitors yes, `prj-default-columns` and `brd-done-column` three,
`brd-wip-limits` two.

## What changes

- The board renders the project's own columns in their order.
- The project owner adds, renames, reorders, recolours and removes columns, sets a WIP limit and
  marks a column as done.
- A lane header shows its card count against the WIP limit and turns to a warning style when over.
  A drag is never blocked.
- A card moved into a column takes that column's status; a done column marks the task done and
  stamps `completedAt`.
- Cards keep the order a member gives them inside a lane.
- A new project starts with the admin's default columns.
- Existing boards keep every visible card: a one-time repair places each task in the column that
  matches its status.

## Evidence from the parity matrix

Matrix: `openspec/parity/capabilities.json` in ConductionNL/planninq (compared on 2026-09-26). Rows in this change: `brd-columns-edit`, `prj-default-columns`, `brd-done-column`, `brd-wip-limits`, `brd-card-order`.

### `brd-columns-edit`: Add, rename, reorder and remove a board's columns.

- Area `boards`. Planninq is rated `no`, built.state `none`, owner `ConductionNL/planninq`.
- Built evidence: "src/views/ProjectBoard.vue:283-291 columns() is a hardcoded map over BOARD_STATUSES (src/utils/taskHelpers.js:252); there is no add/rename/reorder/remove-column UI anywhere in src/, and the `column` OpenRegister schema is never fetched by the frontend (see brd-wip-limits evidence)."
- Note: "a board's columns are fixed by the task status enum and cannot be added, renamed, reordered or removed from any screen."
- Demand: none recorded on the row.
- Competitors rated yes (5):
  - Nextcloud Deck 1.18 (no URL in the cell; source or corpus citation only): "corpus: nextcloud-deck/round4/menu-tree.md 'Add list ... each with Add card and an Actions menu (rename, reorder, mark as done column, delete)' ; source read at v1.19.0: src/components/Controls.vue:39-56 'Add list', src/components/board/Stack.vue:43 'Edit list title', :69 'Delete list'; src/components/board/Board.vue:61-67 lists are draggable with @drop onDropStack; appinfo/routes.php:38-41 stack create, update, reorder, delete"
  - OpenProject 16 Community (no URL in the cell; source or corpus citation only): "docs: opf/openproject HEAD 27a58131 docs/user-guide/agile-boards/README.md:118 'Click + add list to add lists to your board', lists named freely on Basic boards (:122), removed via 'delete lists' (OpenProject-Boards_delete-lists image), reordered by drag (cited source paths checked present at the released v17.8.0 tag.) ; source read at v17.8.0: frontend/src/app/features/boards/board/board-partitioned-page/board-list-container.component.html:45-50 'Add list'; :7-10 drag lists to reorder (moveList); frontend/src/app/features/boards/board/board-list/board-list.component.html:22-25 rename a list inline; frontend/src/app/features/boards/board/board-list/board-list-menu.component.ts:66 'Delete ... (shortened; full text in the matrix row)"
  - Plane Community 1.4 (no URL in the cell; source or corpus citation only): "corpus: plane/round4/menu-tree.md 'Settings ... States' ; code-census.md §2 State per project with name, color, sequence; 'Groups are not user-definable' (six fixed groups), so columns within groups are free ; source read at v1.4.2: apps/web/app/(all)/[workspaceSlug]/(settings)/settings/projects/[projectId]/states route; apps/web/core/components/project-states/root.tsx:30-32 createState, moveStatePosition, updateState and apps/web/core/components/project-states/state-delete-modal.tsx:30,42 deleteState; apps/api/plane/app/views/state/base.py:47 create and :114 destroy"
  - Kanboard 1.2 (no URL in the cell; source or corpus citation only): "corpus: kanboard/round4/menu-tree.md Configure this project 'Columns' ; source read at v1.2.54: app/Template/column/index.php:5 'Add a new column', :29 'Change column position' drag handle, :34 Edit, :38 Remove; app/Controller/ColumnController.php:60 save, :114 update, :151 move, :186 remove"
  - Jira Software Data Center 11 (https://confluence.atlassian.com/jirasoftwareserver/configuring-columns-938845277.html): "docs: https://confluence.atlassian.com/jirasoftwareserver/configuring-columns-938845277.html 'Adding: Click the Add Column button', 'Renaming: Click the column name area', 'Moving: ... drag it left or right', 'Deleting: Select Delete from the column header' ; docs read 2026-09-26: https://confluence.atlassian.com/jirasoftwareserver/configuring-columns-938845277.html 'Click the Add Column button', 'Change the name of a column', 'Move a column ... drag the column left or right', 'Delete a column' (read 2026-09-26)"

### `prj-default-columns`: Start a new project with a ready-made set of board columns.

- Area `projects`. Planninq is rated `no`, built.state `built`, owner `ConductionNL/planninq`.
- Built evidence: "src/store/projects.js:328/354 createProject() -> createDefaultColumns() (line 469) writes 4 `column` objects (title/order/wipLimit/type) via saveObject(COLUMN_SCHEMA,...). But src/views/ProjectBoard.vue:283-291 builds its columns purely from the hardcoded BOARD_STATUSES status enum (src/utils/taskHelpers.js:252) and never fetches or reads the `column` schema (confirmed: no COLUMN_SCHEMA reference outside src/store/projects.js:28,138-139,476,564,566, which only write/delete it, never read it ... (shortened; full text in the matrix row)"
- Defect: "src/views/ProjectBoard.vue:283-291 columns() ignores the `column` schema entirely and hardcodes BOARD_STATUSES"
- Defect: "src/store/projects.js:96-111 getDefaultColumns() also has a dead admin-override path: it calls loadState('planninq','default_columns', null) but no PHP code anywhere calls provideInitialState for that key (confirmed: zero matches for provideInitialState in lib/), so the admin's configured column titles in src/views/settings/Settings.vue:430-495 never reach the frontend and the hardcoded 4-column ... (shortened; full text in the matrix row)"
- Note: "a new project does get a ready-made set of `column` OpenRegister objects written, but they are dead data: the kanban board the user opens is a hardcoded 5-status board, not driven by those column records at all, so the capability the row describes (a board that starts with a ready-made set of columns) does not actually reach the user."
- Demand: none recorded on the row.
- Competitors rated yes (3):
  - Plane Community 1.4 (no URL in the cell; source or corpus citation only): "corpus: plane/round4/code-census.md §2 'DEFAULT_STATES at state.py:24-62 seeds five plus triage'; states are the board columns ; source read at v1.4.2: apps/api/plane/db/models/state.py:24 DEFAULT_STATES list; apps/api/plane/app/views/project/base.py:293 project create bulk-creates a State per entry of DEFAULT_STATES, so every new project starts with the seeded columns"
  - Kanboard 1.2 (no URL in the cell; source or corpus citation only): "source: app/Template/config/project.php:11 'Default columns for new projects (Comma-separated)', app/Model/BoardModel.php:34 board_columns ; corpus: kanboard/round4/menu-tree.md Settings 'Project settings' ; source read at v1.2.54: app/Template/config/project.php:11-12 'Default columns for new projects (Comma-separated)'; app/Model/BoardModel.php:23 default Backlog, Ready, Work in progress, Done; :34 reads board_columns when a project is created"
  - Jira Software Data Center 11 (https://confluence.atlassian.com/jirasoftwareserver/configuring-columns-938845277.html): "docs: https://confluence.atlassian.com/jirasoftwareserver/configuring-columns-938845277.html 'By default on Scrum boards, this Done column maps to Resolved, Closed statuses, while Simplified Workflow boards map it to Done'; project templates ship preconfigured workflows (Creating a project: 'Jira comes with several default project types with preconfigured workflows and issue types') ; docs read 2026-09-26: https://confluence.atlassian.com/jirasoftwareserver/configuring-columns-938845277.html 'If your board's project is using the Jira default workflow: Default column ... To Do Open, Reopened In Progress In Progress Done Resolved, Closed' (read 2026-09-26)"

### `brd-done-column`: Mark a column as the done column so tasks in it count as finished.

- Area `boards`. Planninq is rated `no`, built.state `none`, owner `ConductionNL/planninq`.
- Built evidence: "the `column` schema declares a `type` enum ('active'/'done', src/store/projects.js:96-111 getDefaultColumns) but columns are never rendered or editable (see brd-columns-edit), so there is no user action to mark any column as the done column. The 'Done' status lane is simply hardcoded in BOARD_STATUSES (src/utils/taskHelpers.js:252)."
- Note: "tasks with status 'done' do count as finished, but that is a hardcoded status value, not something a user marks on a column they configured."
- Demand: none recorded on the row.
- Competitors rated yes (3):
  - Nextcloud Deck 1.18 (no URL in the cell; source or corpus citation only): "corpus: nextcloud-deck/round4/menu-tree.md 'mark as done column' ; source: lib/Service/CardService.php:454-455 a card moved into the done column gets setDone ; source read at v1.19.0: src/components/board/Stack.vue:66 list menu 'Set cards as "done"'; lib/Db/Stack.php:35 isDoneColumn; lib/Service/CardService.php:454-462 a card moved into the done list gets setDone, leaving it clears done; appinfo/routes.php:158 stack_ocs#setDoneStack"
  - Plane Community 1.4 (no URL in the cell; source or corpus citation only): "corpus: plane/round4/code-census.md §2 'A state change to a completed group state sets Issue.completed_at (issue.py:236-249)'; the completed group is fixed rather than chosen, but any state placed in it counts as done ; source read at v1.4.2: apps/web/core/components/project-states/group-list.tsx lists states under fixed groups, a state added to the completed group counts as done; apps/api/plane/db/models/issue.py:240-247 _sync_completed_at sets completed_at when the state group is COMPLETED. The done group is fixed, any state in it is a done column"
  - Jira Software Data Center 11 (https://confluence.atlassian.com/jirasoftwareserver/configuring-columns-938845277.html): "docs: https://confluence.atlassian.com/jirasoftwareserver/configuring-columns-938845277.html 'The rightmost column is always green and represents items in a successful state ... this Done column maps to Resolved, Closed statuses' ; docs read 2026-09-26: https://confluence.atlassian.com/jirasoftwareserver/configuring-columns-938845277.html 'The single right-most column will always be green, representing items in a successful state'; default mapping 'Done Resolved, Closed' (read 2026-09-26)"

### `brd-wip-limits`: Set a work in progress limit per column and see when a column goes over it.

- Area `boards`. Planninq is rated `no`, built.state `built`, owner `ConductionNL/planninq`.
- Built evidence: "`wipLimit` is written on `column` objects at project creation (src/store/projects.js:106-110 getDefaultColumns, e.g. 3/2), but src/views/ProjectBoard.vue never fetches the `column` schema at all (only COLUMN_SCHEMA writes/deletes exist in src/store/projects.js:28,138-139,476,564,566) and there is no wipLimit reference anywhere in src/ (grep confirmed zero matches outside the store's own write path)."
- Defect: "src/views/ProjectBoard.vue: no WIP-limit badge or read of the `column` schema's wipLimit field"
- Note: "same root cause as prj-default-columns: the `column` schema (which carries wipLimit) is write-only, never read back by the board."
- Demand: none recorded on the row.
- Competitors rated yes (2):
  - Kanboard 1.2 (no URL in the cell; source or corpus citation only): "corpus: kanboard/round4/code-census.md 'phases, WIP: columns with task_limit' ; source: app/Template/board/table_column.php:113-114 count/limit in the header ; _round4/compare/promoted-rows-batch7.md 9.14 'task_limit, which colours the column header when exceeded' ; source read at v1.2.54: app/Template/column/index.php:19 'Task limit' column setting; app/Template/board/table_column.php:113-114 count over limit in the header; app/Template/board/table_tasks.php:6 board-task-list-limit class when the column is over its limit"
  - Jira Software Data Center 11 (https://confluence.atlassian.com/jirasoftwareserver/configuring-columns-938845277.html): "docs: https://confluence.atlassian.com/jirasoftwareserver/configuring-columns-938845277.html 'Column colored red: Maximum number of issues exceeded' and 'Column colored yellow: Minimum number of issues not met' (column constraints) ; docs read 2026-09-26: https://confluence.atlassian.com/jirasoftwareserver/configuring-columns-938845277.html 'If a column constraint is exceeded, then the column will change color ... Column colored red, Maximum number of issues exceeded' (read 2026-09-26)"

### `brd-card-order`: Reorder cards within a column to show which comes first.

- Area `boards`. Planninq is rated `no`, built.state `none`, owner `ConductionNL/planninq`.
- Built evidence: "the task schema declares `columnOrder` ("Sort order within the column") but grep across src/ finds zero references to columnOrder anywhere in the frontend; src/views/ProjectBoard.vue groups tasks by status (groupTasksByStatus, src/utils/taskHelpers.js:268) with no sort by columnOrder and no drag-to-reorder-within-column handler (onDrop only changes status, src/views/ProjectBoard.vue:510-515)."
- Defect: "schema field task.columnOrder is declared but never read or written anywhere in src/"
- Demand: none recorded on the row.
- Competitors rated yes (5):
  - Nextcloud Deck 1.18 (no URL in the cell; source or corpus citation only): "corpus: nextcloud-deck/round4/code-census.md Card and Stack carry an order ; source: appinfo/routes.php card#reorder ; source read at v1.19.0: src/components/board/Stack.vue:104-114 drag within a list calls onDropCard; lib/Service/CardService.php:436 reorder(id, stackId, order); appinfo/routes.php:52 card#reorder"
  - OpenProject 16 Community (no URL in the cell; source or corpus citation only): "docs: opf/openproject HEAD 27a58131 docs/user-guide/agile-boards/README.md:152 'Move cards with drag and drop within a list'; Basic board lists 'order your work packages within' (:55) (cited source paths checked present at the released v17.8.0 tag.) ; source read at v17.8.0: frontend/src/app/features/work-packages/components/wp-card-view/services/wp-card-drag-and-drop.service.ts:161-175 same-list drop is a pure reorder; lib/api/v3/queries/order/query_order_api.rb:34, :73 PATCH the manual order; app/models/ordered_work_package.rb:31-35 stored position"
  - Plane Community 1.4 (no URL in the cell; source or corpus citation only): "corpus: plane/round4/code-census.md §2 'sort_order float (:158)' on Issue ; source: kanban/block.tsx:206 drag within a group reorders by sort_order under manual ordering ; source read at v1.4.2: apps/web/core/components/issues/issue-layouts/kanban/block.tsx:206 draggable card; apps/web/core/components/issues/issue-layouts/utils.tsx:491-518 drop computes a new sort_order between neighbours under manual ordering; apps/api/plane/db/models/issue.py:158 sort_order FloatField"
  - Kanboard 1.2 (no URL in the cell; source or corpus citation only): "corpus: kanboard/round4/journeys.md 1 moveTaskPosition with a position ; source: app/Controller/TaskMovePositionController.php ; source read at v1.2.54: assets/js/src/BoardDragAndDrop.js:37 save sends the new position; app/Controller/BoardAjaxController.php:18-46 saves task positions from the drag and drop; app/Template/task_move_position/show.php:14-15 'Insert before this task', 'Insert after this task'"
  - Jira Software Data Center 11 (https://confluence.atlassian.com/jirasoftwareserver/creating-a-board-938845220.html, https://confluence.atlassian.com/jirasoftwareserver/using-your-kanban-backlog-938845378.html): "corpus: jira-data-center/round4/M1-column.md 3.18 ranking by drag; Jira Software boards and backlog order by Rank (drag cards within a column) ; docs read 2026-09-26: https://confluence.atlassian.com/jirasoftwareserver/creating-a-board-938845220.html board filter 'ORDER BY Rank ASC'; https://confluence.atlassian.com/jirasoftwareserver/using-your-kanban-backlog-938845378.html 'Drag and drop an issue to rank it' (read 2026-09-26)"

## Scope

### In scope

- Reading `column` objects on the board; column management in a lane header menu and a "Columns"
  tab in the project settings sidebar.
- A `status` mapping on each column, and `completedAt` on entering a done column.
- `columnOrder` on drag within a lane and on "Move up" and "Move down" in the card menu.
- Passing the admin's default columns to project creation.
- A repair step that assigns a column to every task that has none and is not cancelled.

### Out of scope

- Blocking a drag at the WIP limit. Row `brd-wip-enforce` is decided no: `docs/ARCHITECTURE.md`
  section 5 question 6 keeps limits soft.
- Column automation: `boards-column-automation`. Swimlanes and card colours: `boards-card-display`.
- The backlog page itself: `backlog-list` shows tasks without a column.

## Impact

- `src/views/ProjectBoard.vue`, `src/store/projects.js`, `src/components/ProjectSettingsSidebar.vue`,
  `src/utils/taskHelpers.js`, `lib/Settings/planninq_register.json` (column `status`; column
  write rule narrowed to the project owner), `lib/Settings/AdminSettings.php` or the page
  controller (initial state), a new repair step in `lib/Repair/`, `l10n/`.
- Depends on: nothing. `backlog-list`, `boards-filters`, `boards-card-display`,
  `boards-column-automation`, `boards-cross-project-board`, `tasks-move-between-projects` and
  `projects-templates-shared-workflow` build on it.

## Risks

### Risk 1: Cards vanish from existing boards
**Severity**: High
**Mitigation**: the repair step runs before the new board ships and assigns every task without a
column to the first column whose status matches (open to the first active column, done to the
done column). Cancelled tasks stay without a column and remain findable in the backlog's
cancelled filter. A PHPUnit test runs the repair on a fixture of every status.

### Risk 2: Status and column disagree
**Severity**: Medium
**Mitigation**: the column decides the lane; moving a card writes both `column` and the column's
mapped status in one PATCH. Readers that use status (reminders, portfolio counts, activity) keep
working.
