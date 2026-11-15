---
kind: code
---

# Cumulative flow, lead and cycle time, and reports you build yourself

## Why

A team cannot see how work flows. The Reports page has three fixed cards (`src/manifest.json:161-165`), and the only charts are donuts of the current task status and priority (`:179-180`). Nothing shows how many tasks sat in each column day by day, so a growing queue before "Review" goes unnoticed until the board is full.

Nobody can tell how long work takes. `task.completedAt` and `task.resolvedAt` are declared on the schema (`lib/Settings/planninq_register.json:317-323`, `:348-354`) and nothing in `src/`, `lib/Service` or `lib/Controller` writes or reads them. Without a finish time there is no lead time, and without the moment work started there is no cycle time.

Nobody can build a report the fixed cards do not cover. There is no saved query, no saved filter and no report builder anywhere in `src/`.

Kanboard and Jira Data Center draw a cumulative flow diagram and report lead and cycle time. OpenProject, Jira Data Center and Zermelo let users save their own queries and build reports on them. FEATURES.md lists the cumulative flow diagram as V1 and cycle time as Enterprise.

Parity rows: `prt-cumulative-flow`, `prt-cycle-time`, `prt-custom-report` in planninq's `openspec/parity/capabilities.json`.
Decision: build. Two competitors ship the flow diagram and the cycle time report, and three ship custom reports.

## What changes

- Every project gets a cumulative flow diagram: tasks per column per day over a period you choose.
- Every project gets a lead and cycle time report: per finished task and as an average and 85th percentile, with the slowest tasks listed.
- The same two reports work for a whole portfolio.
- A user builds a report: which projects, which filters, grouped by what, counted or summed, as a table, a bar chart or a donut. The report is saved, and can be shared with everyone who can read the projects it covers.

## Evidence from the parity matrix

Matrix: `openspec/parity/capabilities.json` in ConductionNL/planninq (compared on 2026-09-26). Rows in this change: `prt-cumulative-flow`, `prt-cycle-time`, `prt-custom-report`.

### `prt-cumulative-flow`: See a cumulative flow diagram of the board.

- Area `portfolio`. Planninq is rated `no`, built.state `none`, owner `ConductionNL/planninq`.
- Built evidence: "no status-history aggregation anywhere; the only charts are the status and priority donuts of the current state in src/manifest.json:179-180"
- Demand: none recorded on the row.
- Competitors rated yes (2):
  - Kanboard 1.2 (no URL in the cell; source or corpus citation only): "corpus: kanboard/round4/menu-tree.md Analytics 'Cumulative flow diagram' ; M1-column.md 10.4 'from project_daily_column_stats' ; source read at v1.2.54: app/Template/analytic/sidebar.php:10 'Cumulative flow diagram'; app/Controller/AnalyticController.php:119 cfd; assets/js/components/chart-project-cumulative-flow.js renders it"
  - Jira Software Data Center 11 (https://confluence.atlassian.com/jirasoftwareserver/cumulative-flow-diagram-938845656.html): "corpus: jira-data-center/round4/M1-column.md 10.4 'the Cumulative Flow Diagram is an area chart that shows the various statuses of work items' ; docs read 2026-09-26: https://confluence.atlassian.com/jirasoftwareserver/cumulative-flow-diagram-938845656.html 'A Cumulative Flow Diagram (CFD) is an area chart that shows the various statuses of work items' (read 2026-09-26)"

### `prt-cycle-time`: See lead time and cycle time of finished tasks.

- Area `portfolio`. Planninq is rated `no`, built.state `none`, owner `ConductionNL/planninq`.
- Built evidence: "task.completedAt and task.resolvedAt exist in lib/Settings/planninq_register.json but nothing in src/, lib/Service or lib/Controller reads or writes them (grep returns no hits)"
- Note: "Not even the timestamps a lead-time report would need are set by the app."
- Demand: none recorded on the row.
- Competitors rated yes (2):
  - Kanboard 1.2 (no URL in the cell; source or corpus citation only): "corpus: kanboard/round4/M1-column.md 10.4 'lead and cycle time per task and averaged (TaskAnalyticModel::getLeadTime, getCycleTime)' ; source read at v1.2.54: app/Template/analytic/sidebar.php:19 'Lead and cycle time', app/Template/analytic/lead_cycle_time.php:3 averages; app/Controller/AnalyticController.php:21 leadAndCycleTime; app/Template/task/analytics.php:14-15 per task; app/Model/TaskAnalyticModel.php:22 getLeadTime, :34 getCycleTime"
  - Jira Software Data Center 11 (https://confluence.atlassian.com/jirasoftwareserver/control-chart-938845628.html): "corpus: jira-data-center/round4/M1-column.md 10.4 'The Control Chart shows the cycle time and lead time for your product, version, or sprint' ; docs read 2026-09-26: https://confluence.atlassian.com/jirasoftwareserver/control-chart-938845628.html 'The Control Chart shows the cycle time and lead time for your product, version, or sprint' (read 2026-09-26)"

### `prt-custom-report`: Build your own report or saved query over tasks.

- Area `portfolio`. Planninq is rated `no`, built.state `none`, owner `ConductionNL/planninq`.
- Built evidence: "the Reports page offers three fixed cards (src/manifest.json:161-165) whose dashboards are declared in the manifest; no report builder, saved query or saved filter anywhere in src/"
- Demand: none recorded on the row.
- Competitors rated yes (3):
  - OpenProject 16 Community (no URL in the cell; source or corpus citation only): "corpus: openproject/round4/M1-column.md 9.3 personal saved queries, 10.1 widgets, 10.4 cost report pivot engine ; source read at v17.8.0: config/locales/js-en.yml:591 'Save as' a work package view; app/contracts/queries/base_contract.rb:38 sums and :53 group_by saved on a query; modules/reporting/config/routes.rb:49-52 cost report save_as; modules/reporting/app/controllers/cost_reports_controller.rb:146 create"
  - Jira Software Data Center 11 (https://confluence.atlassian.com/jirasoftwareserver/gadgets-for-jira-applications-939939037.html): "corpus: jira-data-center/round4/M1-column.md 9.3 saved filters, 10.1 gadgets 'Issue Statistics', 'Pie Chart', 10.10 'Filter Results' ; docs read 2026-09-26: https://confluence.atlassian.com/jirasoftwareserver/gadgets-for-jira-applications-939939037.html 'Filter Results Shows the results of a selected issue filter'; 'Issue Statistics Shows issue statistics returned from a selected project or saved filter' (read 2026-09-26)"
  - Zermelo (Desktop, Portal and WebApp) (https://support.zermelo.nl/guides/roostermaker/diagrammen): "docs read 2026-09-27: https://support.zermelo.nl/guides/roostermaker/diagrammen 'Code met diagramdefinities (alleen voor experts)' next to a large set of predefined overviews, layout adjustable"

## Scope

### In scope

- A read-only flow endpoint that replays OpenRegister's audit trail of a project's tasks into daily counts and task timings.
- The two flow reports per project and per portfolio.
- A `report` schema for saved reports, a builder, and a report page.

### Out of scope

- Stamping `completedAt`. `boards-configurable-columns` stamps it on the server when a task becomes done and clears it when the task leaves done. This change only reads it.
- Sprint reports such as burndown. Planninq is kanban-only by a recorded decision (`docs/ARCHITECTURE.md:149`).
- Scheduled report e-mails.
- Throughput and workload charts (V1 rows of FEATURES.md without a matrix row here).

## Impact

- Controller and service: `FlowController` with `GET /apps/planninq/api/projects/{id}/flow` and `GET /apps/planninq/api/portfolios/{id}/flow`, backed by a `FlowHistoryService` that reads OpenRegister's audit trail server-side.
- Schema: new `report` schema.
- Views: a Flow tab in `ProjectTabs` (`/projects/:id/flow`), portfolio flow at `/portfolio/flow`, a report builder dialog `src/dialogs/ReportBuilderDialog.vue`, and a report page `/reports/custom/:id` with the saved reports listed on the Reports page.
- Extends the flat specs `openspec/specs/portfolio-dashboard-pmo.md` and `openspec/specs/kanban-board.md`.
- Depends on: `boards-configurable-columns` (stamps `completedAt` on the server, and makes columns objects the flow is counted in). `projects-grouping-hierarchy-fields` for portfolios. `projects-overview-logs-risks` for `ProjectTabs`.

## Risks

### Risk 1: replaying the audit trail is slow on a big project

**Severity**: Medium
**Mitigation**: The flow endpoint limits a window to 180 days, reads only `column`, `status` and `completedAt` changes, and caches each finished day per project in Nextcloud's distributed cache, since a past day never changes. Task 1.3 measures a project of 1,000 tasks over 180 days.

### Risk 2: history before this change is thin

**Severity**: Low
**Mitigation**: The flow is built from the changes OpenRegister's audit trail recorded for each task, so history reaches back as far as that trail does. Tasks finished before `completedAt` was stamped use the time of their last change to `done` from the audit trail, and the report says how many tasks it had to estimate that way.

### Risk 3: a shared report shows data a viewer should not see

**Severity**: High
**Mitigation**: A report stores a query, not results. Every viewer runs it with their own rights, so a shared report over five projects shows a viewer on two of them only those two, and says so.

### Risk 4: another schema changes the schema count

**Severity**: Low
**Mitigation**: Task 3.4 updates `testRegisterDeclaresExactlySevenSchemas` and `openspec/specs/project-delivery/spec.md:68-72`.
