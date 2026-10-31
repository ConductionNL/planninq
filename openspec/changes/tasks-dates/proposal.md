---
kind: code
---

# Set due and start dates on a task

## Why

The task schema stores a date-only `dueDate` and `startDate` (`lib/Settings/planninq_register.json`,
schema `task`), the board shows an overdue or due-soon badge from the due date
(`src/utils/taskHelpers.js:215-236`, `src/components/TaskCard.vue:16-21`), and the timeline
places bars from both dates (`src/utils/timelineHelpers.js:78-79`). But no screen lets anyone
set either date. TaskDetail lists the due date as read-only text (`src/views/TaskDetail.vue:268`)
and does not show the start date at all. The warning badge, the due reminder and the timeline
only ever work for data that arrived through an import.

A Deck user asked for a due date for a whole day without a time
(https://github.com/nextcloud/deck/issues/1157). Planninq's date-only field already has that
shape; it only needs a control.

Parity rows: `tsk-due-date`, `tsk-all-day-due`, `tsk-start-date` in planninq's
`openspec/parity/capabilities.json`.
Decision: build. `tsk-due-date` is rated partial (seeing overdue works, setting a date does not)
with five competitors yes; `tsk-all-day-due` has a feature request plus three competitors yes;
`tsk-start-date` has five competitors yes.

## What changes

- A project member sets, changes and clears a task's due date and start date on the task page
  and in the task dialog, with a date picker that has no time part.
- A start date after the due date is refused with an inline message.
- The due-date badge, the due reminder and the timeline pick up the new dates at once.

## Evidence from the parity matrix

Matrix: `openspec/parity/capabilities.json` in ConductionNL/planninq (compared on 2026-09-26). Rows in this change: `tsk-due-date`, `tsk-all-day-due`, `tsk-start-date`.

### `tsk-due-date`: Set a due date on a task and see when it is overdue.

- Area `tasks`. Planninq is rated `partial`, built.state `built`, owner `ConductionNL/planninq`.
- Built evidence: "Seeing overdue works: src/utils/taskHelpers.js:215-236 (dueDateStatus, date-only comparison, 'overdue'/'approaching') feeds src/components/TaskCard.vue:17-19,121-141 (due-date badge). Setting a due date has no UI anywhere: dueDate only appears as a read value (src/views/TaskDetail.vue:268) and as timeline positioning input (src/views/ProjectTimeline.vue:429-430, src/utils/timelineHelpers.js:79)."
- Note: "the seeing-overdue half of the sentence is well built; the setting half has no control anywhere."
- Demand: none recorded on the row.
- Competitors rated yes (5):
  - Nextcloud Deck 1.18 (no URL in the cell; source or corpus citation only): "corpus: nextcloud-deck/round4/journeys.md 4 'the date:overdue filter listed the card' ; code-census.md 'duedate, three colour thresholds, one overdue job' ; source read at v1.19.0: src/components/card/DueDateSelector.vue:13 'Set a due date', :187-206 quick picks; src/components/cards/badges/DueDate.vue:67 overdue state on the card badge; src/components/overview/Overview.vue:59 'Overdue' column; lib/Db/Card.php:106 DUEDATE_OVERDUE"
  - OpenProject 16 Community (no URL in the cell; source or corpus citation only): "corpus: openproject/round4/M1-column.md 3.6 'due_date on the work package'; 8.2 'due date filters and a late indicator on the work package table' ; source read at v17.8.0: app/contracts/work_packages/base_contract.rb:129 due_date attribute; app/models/work_package.rb:285-287 overdue? when due_date is past and not closed; frontend/src/app/shared/components/fields/display/field-types/date-display-field.module.ts:63 overdue highlight class"
  - Plane Community 1.4 (no URL in the cell; source or corpus citation only): "corpus: plane/round4/M1-column.md 3.6 'Issue.target_date settable, sortable and filterable' and 8.2 'overdue styling is computed client side from target_date' ; source read at v1.4.2: apps/web/core/components/issues/issue-modal/components/default-properties.tsx:184-196 target_date picker; apps/web/core/components/issues/issue-layouts/properties/all-properties.tsx:308 due date turns red via shouldHighlightIssueDueDate, packages/utils/src/work-item/base.ts:172; apps/api/plane/db/models/issue.py:148 target_date. Overdue is computed client side only"
  - Kanboard 1.2 (no URL in the cell; source or corpus citation only): "corpus: kanboard/round4/M1-column.md 8.2 'yes' ; _round4/compare/promoted-rows-batch7.md 8.14 'the list and the board colour the date (task-date-overdue)' ; source read at v1.2.54: app/Helper/TaskHelper.php:235 'Due Date' field; app/Model/TaskCreationModel.php:63 date_due; app/Template/board/task_footer.php:62-66 task-date-overdue on the card; app/Template/task_list/task_icons.php:36 same in the list"
  - Jira Software Data Center 11 (https://confluence.atlassian.com/jirasoftwareserver/scheduling-an-issue-939938943.html): "corpus: jira-data-center/round4/M1-column.md 3.6 'To schedule an issue, populate its Due date field'; 8.2 'overdue Find issues that were due before today' ; docs read 2026-09-26: https://confluence.atlassian.com/jirasoftwareserver/scheduling-an-issue-939938943.html 'You can schedule issue due dates in Jira Software'; 'To search for issues that are overdue at the time of the search, select the first radio button' (read 2026-09-26)"

### `tsk-all-day-due`: Set a due date for a whole day without picking a time.

- Area `tasks`. Planninq is rated `no`, built.state `none`, owner `ConductionNL/planninq`.
- Built evidence: "The data model already stores a whole-day due date [lib/Settings/planninq_register.json:268-272], and src/components/TaskCard.vue:16-21 shows the badge from it, but a user cannot set or change one anywhere [tsk-due-date]. A schema that fits the row is not a screen that delivers it."
- Note: "Demand row mined from nextcloud-deck (featureRequest) on 2026-09-26."
- Demand (featureRequest, via origin): https://github.com/nextcloud/deck/issues/1157
- Competitors rated yes (3):
  - OpenProject 16 Community (no URL in the cell; source or corpus citation only): "source read at v17.8.0: db/migrate/tables/work_packages.rb:40 due_date is a date column; frontend/src/app/shared/components/datepicker/wp-date-picker-modal/wp-date-picker.modal.ts:98 the picker sets a date, no time"
  - Plane Community 1.4 (no URL in the cell; source or corpus citation only): "source read at v1.4.2: apps/api/plane/db/models/issue.py:148 target_date is a DateField; apps/web/core/components/issues/issue-detail/sidebar.tsx:164-167 the due date picker sends a date only"
  - Jira Software Data Center 11 (https://confluence.atlassian.com/jirasoftwareserver/advanced-searching-fields-reference-939938743.html): "https://confluence.atlassian.com/jirasoftwareserver/advanced-searching-fields-reference-939938743.html 'Note that the due date relates to the date only (not to the time)' (read 2026-09-26)"

### `tsk-start-date`: Set a start date on a task.

- Area `tasks`. Planninq is rated `no`, built.state `built`, owner `ConductionNL/planninq`.
- Built evidence: "startDate is only read, for timeline bar positioning: src/views/ProjectTimeline.vue:426-427, src/utils/timelineHelpers.js:78-79. No form or input anywhere sets task.startDate."
- Demand: none recorded on the row.
- Competitors rated yes (5):
  - Nextcloud Deck 1.18 (no URL in the cell; source or corpus citation only): "corpus: nextcloud-deck/round4/menu-tree.md card Details 'a start date, a due date' ; code-census.md Card 'startdate' ; source read at v1.19.0: src/components/card/StartDateSelector.vue:6 'Assign a start date to this card…', :12 'Set a start date', mounted at src/components/card/CardSidebarTabDetails.vue:21-23, :176 updateCardStartDate; lib/Db/Card.php:91 startdate; lib/Controller/CardController.php:67 update takes startdate"
  - OpenProject 16 Community (no URL in the cell; source or corpus citation only): "corpus: openproject/round4/M1-column.md 3.6 'due_date on the work package, distinct from start_date and duration'; corpus: openproject/round4/journeys.md duration derivation ; source read at v17.8.0: app/contracts/work_packages/base_contract.rb:122-126 start_date attribute, validated against the soonest start; config/routes.rb:1007 work package pages whose date picker is served by app/controllers/work_packages/date_picker_controller.rb"
  - Plane Community 1.4 (no URL in the cell; source or corpus citation only): "corpus: plane/round4/code-census.md §2 'start_date and target_date DateFields (:147-148)' ; source read at v1.4.2: apps/web/core/components/issues/issue-modal/components/default-properties.tsx:165-177 start_date picker; apps/api/plane/db/models/issue.py:147 start_date DateField; apps/api/plane/app/views/issue/base.py:628 partial_update"
  - Kanboard 1.2 (no URL in the cell; source or corpus citation only): "source: app/Model/TaskCreationModel.php:64 date_started ; app/Action/TaskMoveColumnOnStartDate.php ; corpus: kanboard/round4/menu-tree.md 'the task's 26 detail fields' ; source read at v1.2.54: app/Helper/TaskHelper.php:229 'Start Date' field; app/Model/TaskCreationModel.php:64 date_started; app/Action/TaskMoveColumnOnStartDate.php:23 move once the start date is reached"
  - Jira Software Data Center 11 (https://confluence.atlassian.com/jirasoftwareserver/configure-your-advanced-roadmaps-plan-settings-1044784158.html): "corpus: jira-data-center/round4/M1-column.md 8.3 'plans schedule on target start and target end dates' (Configure your Advanced Roadmaps plan settings) ; docs read 2026-09-26: https://confluence.atlassian.com/jirasoftwareserver/configure-your-advanced-roadmaps-plan-settings-1044784158.html 'By default, target start and target end dates are used when scheduling issues in a plan' (read 2026-09-26)"

## Scope

### In scope

- Date controls on TaskDetail and in `TaskFormDialog.vue`.
- Local-date parsing of the stored `YYYY-MM-DD` value in `dueDateStatus`.

### Out of scope

- Due times. The field stays date-only; a time of day is not asked for by the rows.
- Rescheduling by dragging a timeline bar: `planning-timeline-editing`.
- Recurring tasks (row `tsk-recurring`, deferred).

## Impact

- `src/views/TaskDetail.vue`, `src/dialogs/TaskFormDialog.vue`, `src/utils/taskHelpers.js`,
  `l10n/`. No schema change.
- Depends on: `tasks-create-edit-delete` (the task dialog).

## Risks

### Risk 1: A date-only value shifts by a day in a time zone west of UTC
**Severity**: Medium
**Mitigation**: `dueDateStatus` parses `new Date('2026-09-30')`, which is UTC midnight, then reads
local parts. The change parses the three parts as a local date. A vitest case runs under
`TZ=America/New_York`.

### Risk 2: A reminder fires for a date a member only meant as a start
**Severity**: Low
**Mitigation**: the reminder rule (`taskDueSoon`) reads `dueDate` only; start dates never trigger it.
