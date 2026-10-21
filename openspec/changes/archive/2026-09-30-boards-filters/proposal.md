---
kind: code
---

# Filter the board by person, label, priority and due date, and save the filter

## Why

The board filters by one label and nothing else. A row of label chips sits above the lanes
(`src/views/ProjectBoard.vue:69-95`) and narrows the tasks through `filterTasksByLabel`
(`src/utils/labelHelpers.js:174-180`), which can only keep tasks that carry the label. There is no
filter on assignee or priority, although both show on every card, and no way to say "everything
not assigned to me", which a Plane user asked for (https://github.com/makeplane/plane/issues/8510).
The active label lives in component state (`activeLabelId`, `:249`), so it is gone on reload and
cannot be shared with a colleague.

The main spec already expects a priority filter ("Task priority filter on board",
`openspec/specs/tasks.md`) and `docs/FEATURES.md` lists "Board filter (by assignee, label,
priority)" as MVP.

Parity rows: `brd-filter`, `brd-filter-exclude`, `brd-saved-filters` in planninq's
`openspec/parity/capabilities.json`.
Decision: build. `brd-filter` is rated partial (label only) with four competitors yes on the
missing half; `brd-filter-exclude` has a feature request plus two competitors yes;
`brd-saved-filters` has three competitors yes. All three are in the boards core area.

## What changes

- A filter bar on the board: assignee (including "Me" and "Unassigned"), label, priority and due
  (overdue, due this week, no date), each with "is" or "is not".
- The active filter is part of the page address, so a reload keeps it and a copied link opens the
  same view.
- A member saves a filter under a name, for themselves or for the whole project, and picks it
  again from a menu.

## Evidence from the parity matrix

Matrix: `openspec/parity/capabilities.json` in ConductionNL/planninq (compared on 2026-09-26). Rows in this change: `brd-filter`, `brd-filter-exclude`, `brd-saved-filters`.

### `brd-filter`: Filter the board by assignee, label or priority.

- Area `boards`. Planninq is rated `partial`, built.state `built`, owner `ConductionNL/planninq`.
- Built evidence: "src/views/ProjectBoard.vue:69-95 (label filter chips) -> :318-326 labelFilterChips() -> :356-358 setLabelFilter() -> :313-317 visibleTasks()/filterTasksByLabel()"
- Note: "only label filtering exists. Grep for assignee/priority filter UI on the board found nothing, task.assignedTo and task.priority are displayed on the card (TaskCard.vue) but not filterable."
- Demand: none recorded on the row.
- Competitors rated yes (4):
  - OpenProject 16 Community (no URL in the cell; source or corpus citation only): "source: opf/openproject HEAD 27a58131 (18.0.0-dev): frontend/src/app/features/boards/board/board-filter/board-filter.component.html '<op-filter-container />' with board-filters.service.ts building ApiV3 filters (assignee, type, priority, any work package filter corpus: openproject/round4/M1-column.md 9.2) (cited source paths checked present at the released v17.8.0 tag.) ; source read at v17.8.0: frontend/src/app/features/boards/board/board-filter/board-filter.component.html:1 '<op-filter-container />' the full work package filter set (assignee, type, priority, ...); frontend/src/app/features/boards/board/board-filter/board-filters.service.ts builds the ApiV3 filters saved on the board"
  - Plane Community 1.4 (no URL in the cell; source or corpus citation only): "corpus: plane/round4/usability.md 'Layouts, filtering, the command palette ... are all good' ; code-census.md §9 IssueView filters; source: packages/constants/src/issue/filter.ts 'issues' page filters include assignees, labels, priority ; source read at v1.4.2: apps/web/core/components/work-item-filters/filters-hoc/project-level.tsx:206 project filter bar with labels, members and priority; apps/api/plane/utils/filters/filterset.py:138-151 assignee_id and label_id filters and :199 priority; apps/api/plane/utils/filters/converters.py:17-20 labels and assignees mapping"
  - Kanboard 1.2 (no URL in the cell; source or corpus citation only): "corpus: kanboard/round4/usability.md 'one query language with twenty-five attributes runs the board' ; source: app/Filter/TaskPriorityFilter.php, assignee and tag filters in app/Filter/ ; source read at v1.2.54: app/Template/app/filters_helper.php:6-15 filter menu 'My tasks', 'Not assigned', 'Tasks due today'; app/ServiceProvider/FilterProvider.php:148 TaskAssigneeFilter, :156 TaskPriorityFilter, :223 TaskTagFilter in the board query lexer"
  - Jira Software Data Center 11 (https://confluence.atlassian.com/jirasoftwareserver/configuring-quick-filters-938845301.html): "docs: https://confluence.atlassian.com/jirasoftwareserver/configuring-quick-filters-938845301.html 'Quick Filters allow you (or anyone else using this board) to further filter the collection of issues appearing on a Scrum board or Kanban board'; default 'Only My Issues' and 'Recently Updated', each a JQL (assignee, labels, priority) ; docs read 2026-09-26: https://confluence.atlassian.com/jirasoftwareserver/configuring-quick-filters-938845301.html 'Quick Filters allow you (or anyone else using this board) to further filter the collection of issues appearing on a Scrum board or Kanban board' (read 2026-09-26)"

### `brd-filter-exclude`: Filter tasks by excluding a value, such as everything not assigned to me.

- Area `boards`. Planninq is rated `no`, built.state `none`, owner `ConductionNL/planninq`.
- Built evidence: "filterTasksByLabel [src/utils/labelHelpers.js:174-180] returns every task or those WITH the label. No exclusion operator and no other filter dimension on the board."
- Note: "Demand row mined from plane (featureRequest) on 2026-09-26."
- Demand (featureRequest, via origin): https://github.com/makeplane/plane/issues/8510
- Competitors rated yes (2):
  - OpenProject 16 Community (no URL in the cell; source or corpus citation only): "source read at v17.8.0: config/locales/en.yml:3505 'is not' operator in the filter bar; app/models/queries/operators/not_equals.rb:34 the '!' operator applies to assignee, status and other filters"
  - Jira Software Data Center 11 (https://confluence.atlassian.com/jirasoftwareserver/advanced-searching-operators-reference-939938745.html, https://confluence.atlassian.com/jirasoftwareserver/configuring-quick-filters-938845301.html): "https://confluence.atlassian.com/jirasoftwareserver/advanced-searching-operators-reference-939938745.html 'The != operator is used to search for issues where the value of a specified field doesn't match'; board quick filters are JQL (https://confluence.atlassian.com/jirasoftwareserver/configuring-quick-filters-938845301.html), e.g. assignee != currentUser() (read 2026-09-26)"

### `brd-saved-filters`: Save a board filter to reuse it or share it with the team.

- Area `boards`. Planninq is rated `no`, built.state `none`, owner `ConductionNL/planninq`.
- Built evidence: "src/views/ProjectBoard.vue's label filter is local component state (`activeLabelId`, data() default null, :356-358 setLabelFilter) with no persistence or sharing mechanism."
- Demand: none recorded on the row.
- Competitors rated yes (3):
  - Plane Community 1.4 (no URL in the cell; source or corpus citation only): "corpus: plane/round4/code-census.md §9 'a saved view can be shared (access = 1, the default) and it does carry its column set' ; menu-tree.md 'Views saved views' ; source read at v1.4.2: apps/web/core/components/views/form.tsx:48,98 view form with access default PUBLIC; apps/api/plane/app/urls/views.py:18 project views route, apps/api/plane/app/views/view/base.py:420 create; apps/api/plane/db/models/view.py:58-66 IssueView with access Private or Public"
  - Kanboard 1.2 (no URL in the cell; source or corpus citation only): "corpus: kanboard/round4/journeys.md 5 'a custom filter Deze week te doen was saved as shared' ; code-census.md 'custom_filters.is_shared' ; source read at v1.2.54: app/Template/custom_filter/create.php:14 'Share with all project members', :17 'Append filter'; app/Controller/CustomFilterController.php:57 save; app/ServiceProvider/AuthenticationProvider.php:95 open to project members"
  - Jira Software Data Center 11 (https://confluence.atlassian.com/jirasoftwareserver/configuring-quick-filters-938845301.html, https://confluence.atlassian.com/jirasoftwareserver/saving-your-search-as-a-filter-939938748.html): "corpus: jira-data-center/round4/M1-column.md 9.4 'Filters ... can be shared with other users, user groups, projects, and project roles'; docs: https://confluence.atlassian.com/jirasoftwareserver/configuring-quick-filters-938845301.html quick filters are saved per board for 'anyone else using this board' ; docs read 2026-09-26: https://confluence.atlassian.com/jirasoftwareserver/saving-your-search-as-a-filter-939938748.html 'Share and email search results with your colleagues'; 'Your new filter will be added to your favorite filters and shared, according to the sharing preference' (read 2026-09-26)"

## Scope

### In scope

- A filter model and pure matching helper, the filter bar, query-string sync.
- A `boardFilter` schema for saved filters, private or shared with the project.

### Out of scope

- Text search: `tasks-search-and-bulk` adds it to the same bar.
- Filters across projects: `boards-cross-project-board` reuses the model.
- Custom reports over saved filters: `portfolio-flow-reports`.

## Impact

- `src/views/ProjectBoard.vue`, a new `src/components/BoardFilterBar.vue`, `src/utils/` (filter
  model), `src/store/` (saved filters), `lib/Settings/planninq_register.json` (new `boardFilter`
  schema), `l10n/`.
- Depends on: `tasks-assignment-priority-labels` for assignee and priority values to filter on;
  works on today's status lanes and on `boards-configurable-columns`.

## Risks

### Risk 1: A shared filter shows tasks a viewer cannot see
**Severity**: Low
**Mitigation**: a saved filter stores criteria, not results; every viewer's board still reads only
the tasks Open Register lets them read.

### Risk 2: A filtered board hides work and the user forgets
**Severity**: Low
**Mitigation**: an active filter shows a count "Showing 12 of 40 tasks" and a "Clear filters"
button.
