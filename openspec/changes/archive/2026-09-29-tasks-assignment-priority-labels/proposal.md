---
kind: code
---

# Assign people, set priority and attach labels on a task

## Why

A task's assignee, priority and labels are shown but can never be set. `assignedTo` is read for
display only (`src/components/TaskCard.vue:65-66`, `src/views/TaskDetail.vue:267`) and as a
query filter (`src/store/projects.js:642`); nothing writes it. `priority` is a chip on the card
and a line on the task page, never an input. Labels render as chips and filter the board, but
the generic tag tab that could attach one is hidden on purpose
(`src/utils/taskHelpers.js:34`) and nothing replaced it. So the due reminder, which is sent to
`assignedTo`, and the Activity entry for a new assignee
(`lib/Listener/TaskActivityListener.php:197-230`) have nobody to reach unless an import set the
field.

A task also holds one person only (`assignedTo` is a string). Deck and Plane let a pair share a
card.

Parity rows: `tsk-assign`, `tsk-multi-assign`, `tsk-priority`, `tsk-labels` in planninq's
`openspec/parity/capabilities.json`.
Decision: build. `tsk-assign` has six competitors yes, `tsk-priority` four, `tsk-multi-assign`
two, and `tsk-labels` is rated partial with three competitors yes on the missing half
(attaching a label).

## What changes

- A project member assigns a task to one project member, the person responsible, and can add
  more members who share the work.
- Names show as avatars with display names, not raw user ids.
- A project member sets a task's priority on the task page and from the card's action menu.
- A project member attaches and removes labels on the task page from the app-wide label list.

## Evidence from the parity matrix

Matrix: `openspec/parity/capabilities.json` in ConductionNL/planninq (compared on 2026-09-26). Rows in this change: `tsk-assign`, `tsk-multi-assign`, `tsk-priority`, `tsk-labels`.

### `tsk-assign`: Assign a task to a person.

- Area `tasks`. Planninq is rated `no`, built.state `built`, owner `ConductionNL/planninq`.
- Built evidence: "task.assignedTo is a plain string schema property (lib/Settings/planninq_register.json). It is only ever read for display: src/components/TaskCard.vue:65-66 and src/views/TaskDetail.vue:267 (fields()). No component, dialog or store action writes assignedTo anywhere in src/ (only read as a query filter in src/store/projects.js:642 getMemberTaskCount)."
- Demand: none recorded on the row.
- Competitors rated yes (6):
  - Nextcloud Deck 1.18 (no URL in the cell; source or corpus citation only): "corpus: nextcloud-deck/round4/M1-column.md 2.4 'assignUser and unassignUser per card ... Verified live' ; source read at v1.19.0: src/components/card/AssignmentSelector.vue:17-18 'Assign a user to this card…' mounted from src/components/card/CardSidebarTabDetails.vue:15-19; lib/Controller/CardController.php:126 assignUser, lib/Service/AssignmentService.php:48"
  - OpenProject 16 Community (no URL in the cell; source or corpus citation only): "corpus: openproject/round4/M1-column.md 2.4 'assigned_to and a separate responsible, both Principal (app/models/work_package.rb:57-81)' ; source read at v17.8.0: app/models/work_package.rb:61 assigned_to (a Principal); lib/api/v3/work_packages/schema/work_package_schema_representer.rb:451-453 available assignees feed the assignee picker; lib/api/v3/work_packages/work_packages_api.rb:99 AvailableAssigneesAPI"
  - Plane Community 1.4 (no URL in the cell; source or corpus citation only): "corpus: plane/round4/M1-column.md 2.4 'Issue.assignees M2M (issue.py:149) ... editable from the work item peek and the spreadsheet layout' ; source read at v1.4.2: apps/web/core/components/issues/issue-modal/components/default-properties.tsx:126 assignee_ids dropdown; apps/web/core/components/issues/issue-layouts/properties/all-properties.tsx:121 updateIssue assignee_ids from the card; apps/api/plane/db/models/issue.py:149 assignees M2M; apps/api/plane/app/views/issue/base.py:628 partial_update"
  - Kanboard 1.2 (no URL in the cell; source or corpus citation only): "corpus: kanboard/round4/M1-column.md 2.4 'yes' ; journeys.md 5 ; source: app/Model/TaskCreationModel.php:67 owner_id ; source read at v1.2.54: app/Helper/TaskHelper.php:126-130 'Assignee' select with 'Assign to me'; app/Controller/TaskModificationController.php:17 assignToMe; app/Model/TaskCreationModel.php:67 owner_id"
  - Jira Software Data Center 11 (https://confluence.atlassian.com/adminjiraserver/managing-project-permissions-938847145.html): "corpus: jira-data-center/round4/M1-column.md 2.4 'Assign issues Permission to assign issues to users' ; docs read 2026-09-26: https://confluence.atlassian.com/adminjiraserver/managing-project-permissions-938847145.html 'Assign issues Permission to assign issues to users. This permissions also allows autocompletion of users in the Assign Issue dropdown' (read 2026-09-26)"
  - Untis, WebUntis and Untis Mobile (https://help.untis.at/hc/de/articles/360015324359): "docs read 2026-09-27: https://help.untis.at/hc/de/articles/360015324359 'Die Aufgabe kann nun vom Sachbearbeiter mit einer Bemerkung versehen, einem anderen Benutzer zugewiesen ... werden' and staff can take a ticket for themselves"

### `tsk-multi-assign`: Assign one task to several people.

- Area `tasks`. Planninq is rated `no`, built.state `none`, owner `ConductionNL/planninq`.
- Built evidence: "assignedTo is declared type: string (single value), not an array (lib/Settings/planninq_register.json properties.assignedTo), and as tsk-assign shows there is no assignment UI at all."
- Demand: none recorded on the row.
- Competitors rated yes (2):
  - Nextcloud Deck 1.18 (no URL in the cell; source or corpus citation only): "corpus: nextcloud-deck/round4/journeys.md 2 'jdevries and the group as assignees' ; code-census.md 'Assignment: user, group, team, remote' ; source read at v1.19.0: src/components/card/AssignmentSelector.vue:14 :multiple="true", :6 'Assign to users/groups/team'; lib/Db/Assignment.php:17-20 user, group, circle and remote assignees"
  - Plane Community 1.4 (no URL in the cell; source or corpus citation only): "corpus: plane/round4/code-census.md §2 'assignees M2M (:149)'; journeys.md 'assignees' plural set in the driven journey ; source read at v1.4.2: apps/web/core/components/issues/issue-modal/components/default-properties.tsx:126-139 assignee_ids dropdown with multiple; apps/api/plane/db/models/issue.py:149 assignees ManyToManyField"

### `tsk-priority`: Give a task a priority.

- Area `tasks`. Planninq is rated `no`, built.state `built`, owner `ConductionNL/planninq`.
- Built evidence: "priority is only displayed, never set: src/components/TaskCard.vue:32-36 (chip) and :189-211 (priorityLabel/priorityVariant), src/views/TaskDetail.vue:266 (fields()). No form or control anywhere writes task.priority."
- Demand: none recorded on the row.
- Competitors rated yes (4):
  - OpenProject 16 Community (no URL in the cell; source or corpus citation only): "corpus: openproject/round4/M1-column.md 8.2 'IssuePriority as a separate administered vocabulary'; 3.9 custom action kinds include priority ; source read at v17.8.0: app/models/work_package.rb:64 belongs_to priority IssuePriority; app/models/issue_priority.rb:31; config/routes.rb:771 admin work_package_priorities; config/initializers/menus.rb:421-422 'Priorities' admin page"
  - Plane Community 1.4 (no URL in the cell; source or corpus citation only): "corpus: plane/round4/code-census.md §2 'priority from PRIORITY_CHOICES urgent/high/medium/low/none' ; source read at v1.4.2: apps/web/core/components/issues/issue-modal/components/default-properties.tsx:109-119 PriorityDropdown; apps/api/plane/db/models/issue.py:107 PRIORITY_CHOICES and :141-143 priority field"
  - Kanboard 1.2 (no URL in the cell; source or corpus citation only): "source: app/Filter/TaskPriorityFilter.php, app/Template/board/task_footer.php:135 renderPriority, app/Action/TaskAssignColorPriority.php ; corpus: kanboard/round4/M1-column.md 2.6 bulk 'change ... priority' ; source read at v1.2.54: app/Helper/TaskHelper.php:174-179 'Priority' select over the project range; app/Template/board/task_footer.php:135 renderPriority on the card; app/Filter/TaskPriorityFilter.php; app/Action/TaskAssignColorPriority.php:23"
  - Jira Software Data Center 11 (https://confluence.atlassian.com/jirasoftwareserver/advanced-searching-fields-reference-939938743.html, https://confluence.atlassian.com/adminjiraserver/translating-resolutions-priorities-statuses-and-issue-types-938847111.html): "corpus: jira-data-center/round4/M1-column.md 8.2 'priority is a separate list'; 1.11 issue collector standard fields include 'Priority' ; docs read 2026-09-26: https://confluence.atlassian.com/jirasoftwareserver/advanced-searching-fields-reference-939938743.html 'Syntax priority Field Type PRIORITY'; https://confluence.atlassian.com/adminjiraserver/translating-resolutions-priorities-statuses-and-issue-types-938847111.html names 'the priority field' as an issue constant (read 2026-09-26)"

### `tsk-labels`: Tag tasks with coloured labels.

- Area `tasks`. Planninq is rated `partial`, built.state `built`, owner `ConductionNL/planninq`.
- Built evidence: "Labels are app-wide objects tasks reference by UUID array (task.labels), rendered and filterable on the board: src/views/ProjectBoard.vue:69-95 (filter chips), :439-441 (labelsForTask), src/components/TaskCard.vue:44-58 (label chips). But no UI anywhere lets a user add/remove a label ON a given task: the library's generic per-object tag tab is deliberately hidden, src/utils/taskHelpers.js:34 TASK_SIDEBAR_HIDDEN_TABS = ['tags', 'tasks'] applied at :59, and Planninq built no replacement tagging ... (shortened; full text in the matrix row)"
- Defect: "src/utils/taskHelpers.js:34 hides the generic CnTagsTab with a comment claiming Planninq has its own label concept, but no per-task label-attach UI exists to replace it"
- Note: "a task can display and be filtered by labels, but there is no way for a user to actually tag a task with one."
- Demand: none recorded on the row.
- Competitors rated yes (3):
  - Nextcloud Deck 1.18 (no URL in the cell; source or corpus citation only): "corpus: nextcloud-deck/round4/M1-column.md 2.8 'labels per board with a colour ... Verified live: label Kap on card 6' ; source read at v1.19.0: src/components/card/TagSelector.vue:15-16 'Assign a tag to this card…', 'Select or create a tag…', mounted at src/components/card/CardSidebarTabDetails.vue:8-13, :187 addLabelToCard; lib/Controller/CardController.php:116 assignLabel, lib/Service/CardService.php:618"
  - Plane Community 1.4 (no URL in the cell; source or corpus citation only): "corpus: plane/round4/M1-column.md 2.8 'label.py:11 workspace-scoped with an optional project, a self-FK parent, color and sort_order' ; source read at v1.4.2: apps/web/core/components/issues/issue-modal/components/default-properties.tsx:147-157 label_ids dropdown; apps/web/core/components/issues/issue-layouts/properties/labels.tsx:155 coloured chip; apps/api/plane/db/models/label.py:11-21 Label with parent and color; apps/api/plane/db/models/issue.py:543-545 IssueLabel"
  - Kanboard 1.2 (no URL in the cell; source or corpus citation only): "corpus: kanboard/round4/journeys.md 4 'createTag spoed and setTaskTags' ; source: app/Template/board/task_footer.php:28 tag colour ; source read at v1.2.54: app/Helper/TaskHelper.php:90-95 'Tags' field on the task form; app/Model/TaskTagModel.php:100 save; app/Template/board/task_footer.php:28 tag colour on the card; app/Controller/ProjectTagController.php:39 save a project tag"

## Scope

### In scope

- A people picker limited to the project's members, on TaskDetail and in `TaskFormDialog.vue`.
- A new optional `sharedWith` array on the task schema for the extra assignees.
- Priority control on TaskDetail and in the card's action menu on the board.
- A label picker on TaskDetail writing `task.labels`.

### Out of scope

- Sending a notification on assignment: `collaboration-notifications` (lane B) does that and
  reads the same fields.
- Creating new labels: labels stay admin-managed (`2026-06-14-label-management-admin`).
- Filtering the board by assignee or priority: `boards-filters`.

## Impact

- `lib/Settings/planninq_register.json` (task: new `sharedWith`), `src/views/TaskDetail.vue`,
  `src/views/ProjectBoard.vue` (card action), `src/components/TaskCard.vue`,
  `src/dialogs/TaskFormDialog.vue`, `lib/Listener/TaskActivityListener.php` (audience includes
  `sharedWith`), `l10n/`.
- Depends on: `tasks-create-edit-delete` (the task dialog).

## Risks

### Risk 1: Turning `assignedTo` into an array breaks its readers
**Severity**: High
**Mitigation**: `assignedTo` stays a single uid (the responsible person, and the VNG InterneTaak
`toegewezenAanGebruikersnaam`). Extra people go in a new `sharedWith` array, so the reminder
rule, the Activity listener, the member task count and the portal exclusion list keep working.

### Risk 2: Assigning someone outside the project
**Severity**: Medium
**Mitigation**: the picker lists `project.members` only, and removing a member already counts
their assigned tasks first (`src/components/ProjectSettingsSidebar.vue`).
