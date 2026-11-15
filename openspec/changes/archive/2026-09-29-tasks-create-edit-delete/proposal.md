---
kind: code
---

# Create, edit and delete a task from the board and the task page

## Why

Nobody can create a task in planninq today. The store has `fetchTask`, `fetchTasks`,
`updateTaskStatus` and `updateTask` (`src/store/projects.js:285`, `:775`, `:835`, `:871`) and
no create action, and no dialog in `src/dialogs/` creates or edits a task. A board only fills
up through an import or a direct call to the OpenRegister objects API. The board has no add
control in a column (`src/views/ProjectBoard.vue:103-175`), the task page shows title and
description read-only (`src/views/TaskDetail.vue:262-270`), and a single task can only be
deleted as a side effect of deleting its whole project (`src/store/projects.js:554-561`).

The description is plain text everywhere it shows (`src/components/TaskCard.vue:9`,
`src/views/TaskDetail.vue:48`). Every competitor in the comparison except Deck lets a team
write it with headings, lists and links.

This is the first thing a buyer tries, and it is the core of the product's own main spec
(`openspec/specs/tasks.md`, "Task CRUD"), which has no change directory. Most other changes
in this pass depend on it: dates, assignment, labels, subtasks, the backlog and quick add all
need a task to exist first.

Parity rows: `tsk-create-edit`, `brd-quick-add`, `tsk-rich-text` in planninq's
`openspec/parity/capabilities.json`.
Decision: build. `tsk-create-edit` is rated partial with five competitors rated yes on the
missing half; `brd-quick-add` sits in the boards core area with four competitors yes;
`tsk-rich-text` has five competitors yes.

## What changes

- A project member adds a task straight into a board column with a one-line quick add.
- A project member creates a task with a title, a Markdown description, a status and a
  priority from a "New task" dialog.
- A project member edits the title, the description and the status on the task page.
- The task's reporter, the project owner or an admin deletes a single task after a
  confirmation. A task with logged time is not deleted; the dialog offers to cancel it instead.
- The description renders as formatted Markdown on the task page and as a short plain-text
  excerpt on the card.

## Evidence from the parity matrix

Matrix: `openspec/parity/capabilities.json` in ConductionNL/planninq (compared on 2026-09-26). Rows in this change: `tsk-create-edit`, `brd-quick-add`, `tsk-rich-text`.

### `tsk-create-edit`: Create, edit and delete a task with a title, description and status.

- Area `tasks`. Planninq is rated `partial`, built.state `built`, owner `ConductionNL/planninq`.
- Built evidence: "No task create or edit form exists anywhere in src/ (dialogs/ holds only LabelEditDialog, LabelDeleteDialog, ProjectCreationDialog, ProjectDeleteDialog, ProjectLeaveDialog, TimeEntryDialog; registry.js has no task dialog/component). Status changes via drag/keyboard move only: src/views/ProjectBoard.vue:562-593 (applyStatusMove) and :152-165 (move menu). Estimate is the only editable field: src/views/TaskDetail.vue:557-570 (saveEstimate). Delete only happens as a project-delete cascade: ... (shortened; full text in the matrix row)"
- Note: "creating a task, editing its title/description, and deleting a single task have no UI path at all; only the status (drag/move) and the time estimate are editable, so only part of the sentence is true."
- Demand: none recorded on the row.
- Competitors rated yes (5):
  - Nextcloud Deck 1.18 (no URL in the cell; source or corpus citation only): "corpus: nextcloud-deck/round4/journeys.md 2 'A card becomes a case' ; menu-tree.md card action menu 'Delete' ; done or open is the status (code-census.md Card done, archived) ; source read at v1.19.0: src/components/board/Stack.vue:74 'Add card' and :135 'Card name'; src/components/card/CardSidebar.vue:231-238 title edit; src/components/card/Description.vue description; src/components/cards/CardMenuEntries.vue:49 'Mark as done', :78 'Delete card'; lib/Controller/CardController.php:49 create, :67 update, :83 delete, :106 done"
  - OpenProject 16 Community (no URL in the cell; source or corpus citation only): "corpus: openproject/round4/journeys.md 'Creating a work package, giving it a start date and a duration ... is the best-supported path'; corpus: openproject/round4/M1-column.md 1.2 work package create form; 2.17 delete service ; source read at v17.8.0: frontend/src/app/features/work-packages/components/wp-new/wp-new-full-view.component.ts:35 create view; config/initializers/permissions.rb:324 add_work_packages, :333 edit_work_packages, :436 delete_work_packages; app/services/work_packages/create_service.rb, update_service.rb and delete_service.rb do the writes; app/models/work_package.rb:59 status"
  - Plane Community 1.4 (no URL in the cell; source or corpus citation only): "corpus: plane/round4/journeys.md 'create a work item, give it a state, a priority, assignees, labels, a start date and a target date' ; code-census.md §2 Issue fields ; source read at v1.4.2: apps/web/core/components/issues/issue-modal/components/title-input.tsx:53 title, apps/web/core/components/issues/issue-modal/form.tsx:238 description_html, apps/web/core/components/issues/issue-modal/components/default-properties.tsx:90 state_id; apps/api/plane/app/views/issue/base.py:405 create, :628 partial_update, :717 destroy; apps/api/plane/db/models/issue.py:136 name"
  - Kanboard 1.2 (no URL in the cell; source or corpus citation only): "corpus: kanboard/round4/menu-tree.md task 'Edit the task ... Open this task / Close this task · Remove' ; journeys.md 1 createTask ; source read at v1.2.54: app/Template/task/sidebar.php:36 'Edit the task', :92 'Close this task', :96 'Open this task', :102 'Remove'; app/Controller/TaskCreationController.php:51 save; app/Controller/TaskModificationController.php:83 edit; app/Controller/TaskSuppressionController.php:35 remove"
  - Jira Software Data Center 11 (https://confluence.atlassian.com/jirasoftwareserver/creating-issues-and-sub-tasks-939938925.html, https://confluence.atlassian.com/adminjiraserver/managing-project-permissions-938847145.html): "corpus: jira-data-center/round4/M1-column.md 1.2 'Select Create at the top of the screen to open the Create issue dialog box'; 2.17 'Delete issues Permission'; statuses 2.7 ; docs read 2026-09-26: https://confluence.atlassian.com/jirasoftwareserver/creating-issues-and-sub-tasks-939938925.html 'Select Create at the top of the screen to open the Create issue dialog box'; https://confluence.atlassian.com/adminjiraserver/managing-project-permissions-938847145.html lists Edit and Delete issues permissions (read 2026-09-26)"

### `brd-quick-add`: Add a card straight into a column from the board.

- Area `boards`. Planninq is rated `no`, built.state `none`, owner `ConductionNL/planninq`.
- Built evidence: "no add-card/add-task control exists on src/views/ProjectBoard.vue. Wider check: there is no task-creation code path anywhere in the app, no createTask action in src/store/projects.js (only fetchTasks/updateTaskStatus/updateTask), no POST to the task schema anywhere in src/, and src/views/ProjectBacklog.vue:24-30 is an explicit placeholder pending future task CRUD."
- Defect: "no task-creation UI or store action anywhere in src/ (checked src/store/projects.js, src/views/ProjectBoard.vue, src/views/ProjectBacklog.vue)"
- Note: "this is broader than the board: the whole app currently has no way to create a task at all, so tasks on the board can only exist via import/migration or direct OpenRegister API use."
- Demand: none recorded on the row.
- Competitors rated yes (4):
  - Nextcloud Deck 1.18 (no URL in the cell; source or corpus citation only): "corpus: nextcloud-deck/round4/menu-tree.md 'each with Add card' ; source read at v1.19.0: src/components/board/Stack.vue:74 'Add card' in each list, :128-135 inline 'Card name' input; src/components/KeyboardShortcuts.vue:106-107 'KeyN' new card; appinfo/routes.php:47 card#create"
  - OpenProject 16 Community (no URL in the cell; source or corpus citation only): "docs: opf/openproject HEAD 27a58131 docs/user-guide/agile-boards/README.md:139 'Click + under the lists title to add a card: create a new card or choose an existing work package' (cited source paths checked present at the released v17.8.0 tag.) ; source read at v17.8.0: frontend/src/app/features/boards/board/add-card-dropdown/add-card-dropdown-menu.directive.ts:61 'Add new card' and :69 'Add existing'; config/locales/js-en.yml:189 'Add new card'; frontend/src/app/features/boards/board/board-list/board-inline-create.service.ts creates the card inside the list's query"
  - Plane Community 1.4 (no URL in the cell; source or corpus citation only): "source: apps/web/core/components/issues/issue-layouts/kanban/kanban-group.tsx:47,333 QuickAddIssueRoot per column ; corpus: plane/round4/menu-tree.md 'New work item' global create ; source read at v1.4.2: apps/web/core/components/issues/issue-layouts/kanban/kanban-group.tsx:47,333 QuickAddIssueRoot at the foot of each column; apps/api/plane/app/views/issue/base.py:405 create"
  - Kanboard 1.2 (no URL in the cell; source or corpus citation only): "corpus: kanboard/round4/menu-tree.md 'Add a new task' ; source: app/Template/board/table_column.php add task per column ; source read at v1.2.54: app/Template/board/table_column.php:19 per column add button; app/Helper/TaskHelper.php:325-334 getNewBoardTaskButton 'Add a new task' opening TaskCreationController in that column; app/Template/board/table_column.php:35 'Create tasks in bulk'; app/Controller/TaskCreationController.php:51 save"

### `tsk-rich-text`: Write a task description in rich text or Markdown with headings, lists and links.

- Area `tasks`. Planninq is rated `no`, built.state `built`, owner `ConductionNL/planninq`.
- Built evidence: "task.description is rendered as plain text interpolation everywhere it appears: src/components/TaskCard.vue:9 ({{ task.description }}) and src/views/TaskDetail.vue:48/269 ({{ field.value }}). No markdown/rich-text renderer or editor is used, and (per tsk-create-edit) there is no description edit form at all."
- Demand: none recorded on the row.
- Competitors rated yes (5):
  - Nextcloud Deck 1.18 (no URL in the cell; source or corpus citation only): "corpus: nextcloud-deck/round4/usability.md 'cards with a title, a markdown description' ; menu-tree.md 'the markdown description with checkboxes' ; source read at v1.19.0: src/components/card/Description.vue:47-53 VueEasymde markdown editor, :69-87 markdown-it renders headings, lists, task checkboxes and links, :120 uses the Text app editor when installed, :45 'Write a description …'; lib/Db/Card.php:74 description"
  - OpenProject 16 Community (no URL in the cell; source or corpus citation only): "docs: opf/openproject HEAD 27a58131 docs/user-guide/wysiwyg/README.md:27 'The CKEditor5 build in OpenProject supports basic text styles, such as bold and italic formatting, headings' and :63 Markdown '#, ##, ###' (GFM tables :50) (cited source paths checked present at the released v17.8.0 tag.) ; source read at v17.8.0: frontend/src/app/shared/components/editor/components/ckeditor/op-ckeditor.component.ts the CKEditor 5 field on descriptions and comments; lib/open_project/text_formatting/filters/markdown_filter.rb:68 GFM with table, autolink and tasklist, so text is stored as Markdown"
  - Plane Community 1.4 (no URL in the cell; source or corpus citation only): "corpus: plane/round4/code-census.md §2 description_json / description_html; usability.md driven editor ; source: packages/editor rich text editor with markdown copy (i18n 'Copy markdown') ; source read at v1.4.2: apps/web/core/components/issues/issue-modal/components/description-editor.tsx:23,184 RichTextEditor for the description; packages/editor/src/core/extensions/extensions.ts:65 CoreEditorExtensions with lists and task lists; apps/api/plane/db/models/issue.py:137-138 description_json and description_html"
  - Kanboard 1.2 (no URL in the cell; source or corpus citation only): "source: app/Helper/TextHelper.php:5,43 Markdown transformation of descriptions and comments ; corpus: kanboard/round4/M1-column.md 6.1 'comments in markdown' ; source read at v1.2.54: app/Helper/TaskHelper.php:63 description uses the textEditor; app/Helper/FormHelper.php:228 textEditor, assets/js/components/text-editor.js:79 editor with preview; app/Helper/TextHelper.php:43-49 Markdown rendering"
  - Jira Software Data Center 11 (https://confluence.atlassian.com/jirasoftwareserver/visual-editing-939938940.html): "docs: https://confluence.atlassian.com/jirasoftwareserver/visual-editing-939938940.html Visual mode 'gives you a What You See Is What You Get (WYSIWYG) experience' and 'you can still enter wiki markup syntax' ; docs read 2026-09-26: https://confluence.atlassian.com/jirasoftwareserver/visual-editing-939938940.html 'Formatting content in Visual mode gives you a What You See Is What You Get (WYSIWYG) experience' (read 2026-09-26)"

## Scope

### In scope

- `createTask` and `deleteTask` actions in the projects store, writing to OpenRegister.
- `src/dialogs/TaskFormDialog.vue` for create and edit, `src/dialogs/TaskDeleteDialog.vue`
  for the confirmation.
- Quick add at the bottom of each board column.
- Markdown rendering of the description on TaskDetail and a plain-text excerpt on TaskCard.
- Narrowing the task schema's delete rule to reporter, project owner and admin.

### Out of scope

- Due and start dates, assignee, priority editing beyond the create dialog's default, labels:
  `tasks-dates` and `tasks-assignment-priority-labels`.
- Subtasks and the delete guard for a parent task: `tasks-subtasks-checklist`.
- Creating a task in the backlog page: `backlog-list` reuses this dialog.
- Column-driven lanes: `boards-configurable-columns`. Until that lands, quick add sets the
  lane's status.

## Impact

- `src/store/projects.js` (new actions), `src/views/ProjectBoard.vue`, `src/views/TaskDetail.vue`,
  `src/components/TaskCard.vue`, two new dialogs, `lib/Settings/planninq_register.json`
  (task delete rule, `reporter` set on create), `l10n/`.
- Depends on: nothing in this pass. Many other changes depend on this one.

## Risks

### Risk 1: A PUT on update wipes required fields
**Severity**: High
**Mitigation**: edits go through the existing PATCH path (`updateTask`, `src/store/projects.js:871`),
never `saveObject` with a partial body. A unit test asserts the request method.

### Risk 2: Deleting a task erases hours that belong to someone else
**Severity**: Medium
**Mitigation**: a task with logged time cannot be deleted; the dialog offers "Cancel task"
instead. Hours stay with the person who logged them, which also keeps this change compatible
with the move of hours to humaniq (`plannedtimeentry-reads-humaniqs-hours`).

### Risk 3: Markdown opens a script injection path
**Severity**: Medium
**Mitigation**: render with `NcRichText` and `use-markdown`, which escapes raw HTML. No `v-html`.
