---
kind: code
---

# Break a task into subtasks and a checklist

## Why

The task schema reserves `parent` for subtasks (`lib/Settings/planninq_register.json`, schema
`task`), but nothing in `src/` reads or writes it, and the library's generic linked-task tab is
hidden on purpose with no replacement (`src/utils/taskHelpers.js:34`). There is no checklist
concept anywhere. A team that splits "Prepare the council decision" into steps has to create
loose tasks and remember which belong together.

The same gap blocks two requests. A Jira user asked to copy a task with its subtasks
(https://jira.atlassian.com/browse/JSWSERVER-7721), and another asked for the subtasks'
estimates to add up on the parent (https://jira.atlassian.com/browse/JSWSERVER-9167). The
task page today compares a task's own logged time with its own estimate only
(`src/views/TaskDetail.vue:76-88`).

`docs/ARCHITECTURE.md` section 5 (resolved question 2) already settles the depth: one level,
task to subtask, with the project as the grouping above.

Parity rows: `tsk-subtasks`, `tsk-checklist`, `tsk-clone-tree`, `tsk-estimate-rollup` in
planninq's `openspec/parity/capabilities.json`.
Decision: build. `tsk-subtasks` has four competitors yes and `tsk-checklist` two;
`tsk-clone-tree` has a feature request plus one competitor yes; `tsk-estimate-rollup` has a
feature request plus two competitors yes.

## What changes

- A project member adds subtasks to a task from its page; each subtask is a normal task in the
  same project with `parent` set.
- A project member keeps a checklist of small steps inside a task and ticks them off.
- The parent shows how many subtasks are done and the sum of their estimates and logged time.
- A project member duplicates a task together with its subtasks and checklist.
- Deleting a parent asks whether to delete its subtasks or detach them.

## Evidence from the parity matrix

Matrix: `openspec/parity/capabilities.json` in ConductionNL/planninq (compared on 2026-09-26). Rows in this change: `tsk-subtasks`, `tsk-checklist`, `tsk-clone-tree`, `tsk-estimate-rollup`.

### `tsk-subtasks`: Break a task into subtasks.

- Area `tasks`. Planninq is rated `no`, built.state `built`, owner `ConductionNL/planninq`.
- Built evidence: "task.parent is declared for sub-task support (lib/Settings/planninq_register.json properties.parent) but is never read or written anywhere in src/ (no match for 'parent' as a task field in any .vue/.js). The library's generic linked-task tab that could serve this, CnTasksTab, is explicitly hidden: src/utils/taskHelpers.js:34 TASK_SIDEBAR_HIDDEN_TABS includes 'tasks', applied at src/views/TaskDetail.vue via taskCollaborationSidebarConfig:59. No Planninq replacement exists."
- Defect: "src/utils/taskHelpers.js:34 hides CnTasksTab (nextcloud-vue's generic linked-task/subtask tab) with no Planninq-built substitute, so task.parent (lib/Settings/planninq_register.json) is unreachable"
- Demand: none recorded on the row.
- Competitors rated yes (4):
  - OpenProject 16 Community (no URL in the cell; source or corpus citation only): "corpus: openproject/round4/M1-column.md 2.10 'hierarchy is a separate closure table, WorkPackageHierarchy' ; source read at v17.8.0: config/locales/js-en.yml:790 'Add child', :792 'Create new child'; app/contracts/work_packages/base_contract.rb:91 parent_id; app/models/work_package.rb:179 has_closure_tree; app/controllers/work_package_hierarchy_relations_controller.rb"
  - Plane Community 1.4 (no URL in the cell; source or corpus citation only): "corpus: plane/round4/code-census.md §2 'Issue.parent gives sub-issues; the endpoint is apps/api/plane/app/urls/issue.py:105' ; source read at v1.4.2: apps/web/core/components/issues/issue-detail-widgets/sub-issues/title-actions.tsx:114 add sub-work item button; apps/api/plane/app/urls/issue.py:105 sub-issues route, apps/api/plane/app/views/issue/sub_issue.py:204 post; apps/api/plane/db/models/issue.py:114 parent FK"
  - Kanboard 1.2 (no URL in the cell; source or corpus citation only): "corpus: kanboard/round4/menu-tree.md 'Add a sub-task' ; M1-column.md 3.3 'subtasks ... with a status of todo, in progress or done, an assignee and a time estimate' ; source read at v1.2.54: app/Template/task/sidebar.php:43 'Add a sub-task'; app/Template/subtask/create.php:19-20 assignee and time estimate; app/Controller/SubtaskController.php:65 save; app/Model/SubtaskModel.php:28-30 todo, in progress, done"
  - Jira Software Data Center 11 (https://confluence.atlassian.com/adminjiraserver/configuring-sub-tasks-938847874.html): "corpus: jira-data-center/round4/M1-column.md 2.10 'Sub-tasks are generally used to split up a parent issue into a number of tasks' (one level only) ; docs read 2026-09-26: https://confluence.atlassian.com/adminjiraserver/configuring-sub-tasks-938847874.html 'Sub-tasks are generally used to split up a parent issue into a number of tasks which can be assigned and tracked separately' (read 2026-09-26)"

### `tsk-checklist`: Keep a checklist inside a task.

- Area `tasks`. Planninq is rated `no`, built.state `none`, owner `ConductionNL/planninq`.
- Built evidence: "No checklist concept exists in the task schema or in src/. The library's generic CnTasksTab (a linked-item/checklist-style tab, nextcloud-vue/src/components/CnObjectSidebar/CnObjectSidebar.vue:124-142) is deliberately hidden by src/utils/taskHelpers.js:34, and Planninq built no checklist UI of its own."
- Demand: none recorded on the row.
- Competitors rated yes (2):
  - Nextcloud Deck 1.18 (no URL in the cell; source or corpus citation only): "corpus: nextcloud-deck/round4/M1-column.md 3.3 'a markdown task list in the description renders as checkboxes with a progress badge on the card ... Verified live: card 22' ; source read at v1.19.0: src/components/card/Description.vue:70 and :85 markdown-it-task-checkbox renders '- [ ]' items as clickable checkboxes; src/components/cards/CardBadges.vue:23-25 'Todo items' badge with checked/total, :75-78 counts; stored in the card description, lib/Db/Card.php:74"
  - Kanboard 1.2 (no URL in the cell; source or corpus citation only): "corpus: kanboard/round4/M1-column.md 3.3 'subtasks are a checklist on a task' ; source: app/Template/board/task_footer.php:98-99 completion percentage on the card ; source read at v1.2.54: app/Template/subtask/create.php:24 'Create another sub-task' for quick list entry; app/Controller/SubtaskStatusController.php:18 change toggles a subtask's status; app/Template/board/task_footer.php:98-99 completion percentage on the card"

### `tsk-clone-tree`: Copy a task together with its subtasks and the child tasks under it.

- Area `tasks`. Planninq is rated `no`, built.state `none`, owner `ConductionNL/planninq`.
- Built evidence: "Neither the board card menu [src/views/ProjectBoard.vue:152-165, move targets only] nor the task detail page offers copy. No hierarchy to copy [tsk-subtasks]."
- Note: "Demand row mined from jira (featureRequest) on 2026-09-26."
- Demand (featureRequest, via origin): https://jira.atlassian.com/browse/JSWSERVER-7721
- Competitors rated yes (1):
  - OpenProject 16 Community (no URL in the cell; source or corpus citation only): "source read at v17.8.0: config/locales/js-en.yml:155 'Duplicate in another project' and bulk copy; app/services/work_packages/bulk/copy_service.rb:87-97 copies every descendant under the copied parent, run by app/workers/work_packages/bulk_copy_job.rb:36"

### `tsk-estimate-rollup`: See the estimates of all subtasks added up on the parent task.

- Area `time`. Planninq is rated `no`, built.state `none`, owner `ConductionNL/planninq`.
- Built evidence: "No parent and child tasks on any screen, and no sum of child estimates. The progress line at src/views/TaskDetail.vue:76-88 compares the task's own logged time with its own estimate."
- Note: "Demand row mined from jira (featureRequest) on 2026-09-26."
- Demand (featureRequest, via origin): https://jira.atlassian.com/browse/JSWSERVER-9167
- Competitors rated yes (2):
  - OpenProject 16 Community (no URL in the cell; source or corpus citation only): "source read at v17.8.0: config/locales/en.yml:1790 'Total work' shown on parents; app/services/work_packages/update_ancestors_service.rb:146-147 derived_estimated_hours summed from children"
  - Kanboard 1.2 (no URL in the cell; source or corpus citation only): "source read at v1.2.54: app/Template/task/details.php:98-101 time estimated on the task; app/Model/SubtaskTimeTrackingModel.php:280-287 SUM of subtask time_estimated written to the task, called from app/Model/SubtaskModel.php:219"

## Scope

### In scope

- A Subtasks section and a Checklist section on TaskDetail.
- A new `checklist` array on the task schema.
- A "Duplicate" action on the task page and the card menu.
- The parent delete guard that `openspec/specs/tasks.md` lists as a V1 scenario.

### Out of scope

- Deeper nesting. `docs/ARCHITECTURE.md` section 5 question 2 limits it to one level.
- Epics (`task.epic`), which stay unused here.
- Copying comments, attachments or time entries on duplicate.

## Impact

- `lib/Settings/planninq_register.json` (task: `checklist`), `src/views/TaskDetail.vue`,
  `src/store/projects.js` (children query, duplicate), `src/components/TaskCard.vue` (parent
  chip, checklist count), `src/dialogs/TaskDeleteDialog.vue`, `l10n/`.
- Depends on: `tasks-create-edit-delete` (createTask, the delete dialog).

## Risks

### Risk 1: A subtask whose parent sits in another project
**Severity**: Medium
**Mitigation**: the add action always creates in the parent's project, and the parent picker
offers same-project tasks only. `tasks-move-between-projects` moves children with their parent.

### Risk 2: Double counting in the rollup
**Severity**: Low
**Mitigation**: the parent shows its own estimate and the subtasks' sum as two separate figures,
plus their total; a vitest spec fixes the arithmetic.
