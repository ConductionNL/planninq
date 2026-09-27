---
kind: code
---

# Plan work in sprints, with a sprint board, a sprint goal and a burndown

## Why

A project member cannot plan work in time boxes today. Nothing in planninq knows what a sprint is: `grep -rni sprint lib src` hits only PHP's `sprintf()`. The register declares seven schemas and none of them is a sprint (`lib/Settings/planninq_register.json:22-30`). The only board is the whole-project board, which groups every task of the project by status (`src/views/ProjectBoard.vue:283-305`, fed by `fetchTasks(projectId)` at `src/store/projects.js:775-783`), and the backlog page is a placeholder that says "Backlog view coming soon" (`src/views/ProjectBacklog.vue:24-30`).

The task schema already carries half of what a sprint needs. It has `storyPoints` (`lib/Settings/planninq_register.json:355-360`) and `completedAt` (`:317-323`), but nothing writes `completedAt`: the board moves a task by PATCHing only `status` (`src/store/projects.js:835-859`), and the only code that names `completedAt` reads it (`lib/Portal/PortalContributionProvider.php:179`). So a burndown has no finish time to plot today.

OpenProject, Plane and Jira all ship sprints with a start and end date, a board of the running sprint and a burndown chart; OpenProject and Jira also keep a sprint goal on the sprint header. OpenProject added the sprint goal in release 17.6 (the demand link on `bkl-sprint-goal` below). Plane ships sprints ("cycles") off by default per project, which is the shape this change takes.

Parity rows: `bkl-sprints`, `bkl-sprint-board`, `bkl-sprint-goal`, `bkl-burndown` in planninq's `openspec/parity/capabilities.json`.
Decision: build, because three competitors ship sprints, a sprint board and a burndown, and two ship a sprint goal, while planninq has no sprint concept at all.

This change extends the flat specs `openspec/specs/kanban-board.md` and `openspec/specs/projects.md` through a new capability, `sprints`.

## What changes

- A project owner can turn sprints on for one project. Projects without sprints keep today's continuous flow.
- A project member can create a sprint with a name, a start date, an end date and a goal, and plan backlog tasks into it.
- A project member can start a sprint and complete it. Completing it moves every unfinished task to the next planned sprint or back to the backlog.
- The board of a project with a running sprint shows only that sprint's tasks, with the sprint goal and the days left in its header.
- A project member can open a burndown chart of a sprint: remaining work per day against the ideal line.
- Moving a task to done records when it finished, so the burndown has a real finish time to plot.

## Evidence from the parity matrix

Matrix: `openspec/parity/capabilities.json` in ConductionNL/planninq (compared on 2026-09-26). Rows in this change: `bkl-sprints`, `bkl-sprint-board`, `bkl-sprint-goal`, `bkl-burndown`.

### `bkl-sprints`: Plan work in sprints with a start and end date.

- Area `backlog`. Planninq is rated `no`, built.state `none`, owner `ConductionNL/planninq`.
- Built evidence: "No sprint concept exists anywhere: grepped lib/ and src/ for 'sprint' case-insensitively, only hit was the unrelated PHP function sprintf()."
- Demand: none recorded on the row.
- Competitors rated yes (3):
  - OpenProject 16 Community (no URL in the cell; source or corpus citation only): "corpus: openproject/round4/menu-tree.md 'Backlogs sprints and story points'; docs: opf/openproject HEAD 27a58131 docs/user-guide/backlogs-scrum/README.md:59 'Each sprint is displayed in a dedicated container showing ... start and end dates'; multiple active sprints are Enterprise gated (open-core.md multiple_active_sprints) (cited source paths checked present at the released v17.8.0 tag.) ; source read at v17.8.0: modules/backlogs/config/routes.rb:84-94 sprints with new, start, finish; modules/backlogs/app/models/sprint.rb:75-77 start_date and finish_date required when active, finish after start; modules/backlogs/app/contracts/backlogs/projects/backlog_settings_contract.rb:96 several active ... (shortened; full text in the matrix row)"
  - Plane Community 1.4 (no URL in the cell; source or corpus citation only): "corpus: plane/round4/menu-tree.md 'Cycles time boxes, off by default' ; code-census.md §3 'Cycle is a time box: start_date and end_date' ; source read at v1.4.2: apps/web/app/(all)/[workspaceSlug]/(projects)/projects/(detail)/[projectId]/cycles/(list)/page.tsx; apps/web/core/components/cycles/modal.tsx:43,52 createCycle; apps/api/plane/app/urls/cycle.py:23 route, apps/api/plane/app/views/cycle/base.py:271 create; apps/api/plane/db/models/cycle.py:60-64 Cycle start_date and end_date. Cycles are off by default per project"
  - Jira Software Data Center 11 (https://confluence.atlassian.com/jirasoftwareserver/estimate-in-story-points-938845204.html, https://confluence.atlassian.com/jirasoftwareserver/reporting-939938675.html, https://confluence.atlassian.com/adminjiraserver/managing-project-permissions-938847145.html): "docs: https://confluence.atlassian.com/jirasoftwareserver/estimate-in-story-points-938845204.html 'Velocity is then worked out based on how many points the team can complete in each sprint'; docs: https://confluence.atlassian.com/jirasoftwareserver/reporting-939938675.html 'Sprint Report' ; docs read 2026-09-26: https://confluence.atlassian.com/adminjiraserver/managing-project-permissions-938847145.html 'Create sprints Start sprints Complete sprints ... Edit sprint information (sprint name, goal, dates)' (read 2026-09-26)"

### `bkl-sprint-board`: See a board of the current sprint only.

- Area `backlog`. Planninq is rated `no`, built.state `none`, owner `ConductionNL/planninq`.
- Built evidence: "No sprint concept exists at all (see bkl-sprints), so there is no sprint-scoped board either. The only board is the whole-project Kanban board (src/views/ProjectBoard.vue)."
- Demand: none recorded on the row.
- Competitors rated yes (3):
  - OpenProject 16 Community (no URL in the cell; source or corpus citation only): "docs: opf/openproject HEAD 27a58131 docs/user-guide/backlogs-scrum/README.md:265 'Sprint board' among the sprint menu items; :234 'Clicking it will open the sprint board' (cited source paths checked present at the released v17.8.0 tag.) ; source read at v17.8.0: modules/backlogs/config/locales/en.yml:179 'Sprint board'; modules/backlogs/config/routes.rb:112 sprint taskboard; modules/backlogs/app/controllers/backlogs/taskboard_controller.rb:35-38 redirects to the sprint's board; modules/boards/app/services/boards/sprint_task_board_create_service.rb:65-76 one list per status of the sprint's work"
  - Plane Community 1.4 (no URL in the cell; source or corpus citation only): "corpus: plane/round4/code-census.md §3 CycleIssue membership ; source: apps/web/core/components/issues/issue-layouts/roots/cycle-layout-root.tsx renders the cycle's items in the same layouts, board included. The cross-project 'Active Cycles' page is an upsell (open-core.md shims table, active-cycles/page.tsx:13) ; source read at v1.4.2: apps/web/core/components/issues/issue-layouts/roots/cycle-layout-root.tsx:40-41 KANBAN renders CycleKanBanLayout for one cycle; apps/api/plane/app/urls/cycle.py:40 cycle-issues route, apps/api/plane/app/views/cycle/issue.py:40 CycleIssueViewSet"
  - Jira Software Data Center 11 (https://confluence.atlassian.com/jirasoftwareserver/customizing-cards-938845307.html, https://confluence.atlassian.com/jirasoftwareserver/reporting-939938675.html): "docs: https://confluence.atlassian.com/jirasoftwareserver/customizing-cards-938845307.html 'You can add up to three additional fields to cards in your Backlog and Active sprints' (the Active sprints board is the current sprint); docs: https://confluence.atlassian.com/jirasoftwareserver/reporting-939938675.html 'Burndown Chart: Tracks the total work remaining' per sprint ; docs read 2026-09-26: https://confluence.atlassian.com/jirasoftwareserver/customizing-cards-938845307.html 'display on cards in the Backlog and Active sprints of your Scrum board': Active sprints is the board of the running sprint (read 2026-09-26)"

### `bkl-sprint-goal`: Set a goal for each sprint and keep it visible while the sprint runs.

- Area `backlog`. Planninq is rated `no`, built.state `none`, owner `ConductionNL/planninq`.
- Built evidence: "The backlog page is an empty state [src/views/ProjectBacklog.vue:24-30], although its manifest note [src/manifest.json:146] describes an ordered task list. No sprint schema exists, so there is nothing to set a goal on."
- Note: "Demand row mined from openproject (changelog) on 2026-09-26."
- Demand (changelog, via origin): https://www.openproject.org/docs/release-notes/17-6-0/
- Competitors rated yes (2):
  - OpenProject 16 Community (no URL in the cell; source or corpus citation only): "source read at v17.8.0: modules/backlogs/app/models/sprint_goal.rb:31-35 a goal per sprint; modules/backlogs/app/components/backlogs/sprint_component.rb:100 goal_text on the sprint header; modules/backlogs/config/locales/en.yml:41 'Sprint goal'"
  - Jira Software Data Center 11 (https://confluence.atlassian.com/adminjiraserver/managing-project-permissions-938847145.html, https://confluence.atlassian.com/adminjiraserver/upgrade-matrix-966063322.html): "https://confluence.atlassian.com/adminjiraserver/managing-project-permissions-938847145.html sprint actions include 'Add sprint goals'; https://confluence.atlassian.com/adminjiraserver/upgrade-matrix-966063322.html 'Sprint goals: add goals to your sprints to let your team know what you want to achieve' (read 2026-09-26)"

### `bkl-burndown`: Follow a sprint on a burndown or burnup chart.

- Area `backlog`. Planninq is rated `no`, built.state `none`, owner `ConductionNL/planninq`.
- Built evidence: "No burndown/burnup chart exists anywhere; grepped lib/ and src/ for 'burndown' with no hits, and there is no sprint concept to chart against (see bkl-sprints)."
- Demand: none recorded on the row.
- Competitors rated yes (3):
  - OpenProject 16 Community (no URL in the cell; source or corpus citation only): "docs: opf/openproject HEAD 27a58131 docs/user-guide/backlogs-scrum/README.md:266 'Burndown chart'; source: opf/openproject HEAD 27a58131 (18.0.0-dev): modules/backlogs/app/models/burndown.rb (cited source paths checked present at the released v17.8.0 tag.) ; source read at v17.8.0: modules/backlogs/config/routes.rb:113 sprint burndown_chart; modules/backlogs/app/controllers/backlogs/burndown_chart_controller.rb:35 show; modules/backlogs/app/models/burndown.rb:31-32 Burndown built per sprint; modules/backlogs/config/locales/en.yml:144-147 burndown chart page strings"
  - Plane Community 1.4 (no URL in the cell; source or corpus citation only): "source: apps/web/core/components/cycles/analytics-sidebar/sidebar-chart.tsx:15 ProgressChart and apps/web/core/components/core/sidebar/progress-chart.tsx:23-45 'current' versus 'ideal' series, i18n 'Burndown chart' ; corpus: plane/round4/code-census.md §3 Cycle.progress_snapshot. Burndown comparison across cycles is the Active Cycles upsell ; source read at v1.4.2: apps/web/core/components/cycles/analytics-sidebar/sidebar-chart.tsx:15,26 cycle sidebar chart; apps/web/core/components/core/sidebar/progress-chart.tsx:20-44 'current' versus 'ideal' series; apps/api/plane/app/views/cycle/base.py:932 burndown_plot, apps/api/plane/utils/analytics_plot.py:123"
  - Jira Software Data Center 11 (https://confluence.atlassian.com/jirasoftwareserver/reporting-939938675.html): "docs: https://confluence.atlassian.com/jirasoftwareserver/reporting-939938675.html 'Burndown Chart', 'Burnup Chart', 'Release Burndown', 'Epic Burndown' ; docs read 2026-09-26: https://confluence.atlassian.com/jirasoftwareserver/reporting-939938675.html report list 'Burndown Chart Burnup Chart Control Chart Cumulative Flow Diagram Epic Burndown ... Release Burndown Sprint Report' (read 2026-09-26)"


## Scope

### In scope

- A `sprint` schema in `lib/Settings/planninq_register.json`, scoped to one project with the same project-membership authorization as `projectPhase`.
- A `sprint` reference on `task` and a `sprintsEnabled` flag on `project`.
- Sprint planning on the project's backlog page, the sprint filter on the project board, the sprint goal in the board header, and a burndown on the sprint.
- Writing `completedAt` whenever a task enters or leaves `done`.

### Out of scope

- Velocity reports across sprints, a sprint report and release burndowns. They read the same data and can follow later.
- More than one running sprint per project. OpenProject gates parallel sprints behind its Enterprise edition; planninq keeps one.
- A cross-project sprint. A sprint belongs to exactly one project.
- Changing the flow-based default. A project that never turns sprints on sees no change.

## Impact

- Schema: new `sprint`; new `task.sprint`; new `project.sprintsEnabled`. The register goes from seven schemas to eight.
- Store: `src/store/projects.js` gains sprint read and write actions.
- Backend: a new pre-save listener `lib/Listener/TaskCompletionListener.php` stamps and clears `completedAt`.
- Views: `src/views/ProjectBacklog.vue` (sprint planning), `src/views/ProjectBoard.vue` (sprint filter and header), a new `SprintBurndown` component, `src/components/ProjectSettingsSidebar.vue` (the sprints switch).
- Dialogs: `src/dialogs/SprintEditDialog.vue`, `src/dialogs/SprintCompleteDialog.vue`.
- Specs and tests: the "exactly seven schemas" scenario in `openspec/specs/project-delivery/spec.md:68-72` and `testRegisterDeclaresExactlySevenSchemas` (`tests/unit/Settings/PlanninqRegisterSchemaTest.php:370`) move to eight.
- Docs: `docs/ARCHITECTURE.md:149` records "No sprints, Kanban-only per user decision"; this change rewrites that row to "sprints are opt-in per project".
- Depends on: `backlog-list` (the real backlog page this change plans sprints from).

## Risks

### Risk 1: the build decision reverses a recorded product decision
**Severity**: High
**Mitigation**: `docs/ARCHITECTURE.md:149` says planninq has no sprints by user decision. This change keeps flow as the default and makes sprints an opt-in per project, so no existing project changes. The open question in design.md asks for explicit confirmation before implementation starts.

### Risk 2: older finished tasks have no finish time
**Severity**: Medium
**Mitigation**: `completedAt` is stamped by a pre-save listener on the server, so every path that saves a task through OpenRegister gets it, not only the board. Tasks finished before this change have no `completedAt`: the chart counts them as finished on the sprint's last day and says under the chart how many it placed that way, so the gap is visible instead of silently flattering the line.

### Risk 3: schema count assertions break
**Severity**: Low
**Mitigation**: the PHPUnit assertion and the project-delivery scenario are updated in the same PR as the schema, as task 1.4 says.
